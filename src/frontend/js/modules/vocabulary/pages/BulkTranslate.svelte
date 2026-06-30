<!--
  Bulk Translate — Svelte 5 port of the Alpine `bulkTranslateApp` component
  (the retired `bulk_translate_form.php` view's `x-data="bulkTranslateApp()"` form).

  Drives the reader's "Lookup New Words" flow: a text's still-unknown words are
  listed, Google's Translate widget fills in candidate translations in-page, the
  user marks/edits which to keep, and the chosen terms are POSTed back to
  `/word/bulk-translate` (the kept save endpoint, `config.saveUrl`).

  Server-gated (Job-B-style): the unknown-word list comes from a server query,
  the save is a PHP form POST, and the in-page translations come from Google's
  Translate widget — none run offline, so the page only mounts this island when a
  server is connected (the gate lives in `app/bulk-translate.ts`, mirroring
  feeds.ts / starter-vocab.ts). The server-only bootstrap data (dictionaries,
  the page of unknown words, pagination) is fetched once by the entry and passed
  in as `config`.

  Behaviour is a faithful port of the Alpine component — same save endpoint, same
  request shape (a native form POST with `_csrf_token`), same Google-Translate
  conversion dance and the same mark/status helpers; only the rendering is Svelte.
  The term rows are rendered once from the static `config` prop and then mutated
  imperatively by the Google-Translate hookup (exactly as the Alpine component
  mutated the PHP-rendered DOM), so Svelte never reconciles them away. The dict
  (D1/D2/Tr) and del-translation controls — inserted as raw HTML and left unwired
  in the Alpine version — are wired here via a delegated click handler.

  @license Unlicense <http://unlicense.org/>
-->
<script lang="ts">
  import { onMount } from 'svelte';
  import { initIcons } from '@shared/icons/lucide_icons';
  import { t } from '@shared/i18n/translator';
  import { getCsrfToken } from '@shared/api/client';
  import { createTheDictUrl, openDictionaryPopup } from '@modules/vocabulary/services/dictionary';
  import { selectToggle } from '@shared/forms/bulk_actions';
  import { setDictionaryLinks } from '@modules/language/stores/language_config';
  import type { BulkTranslateConfig } from '@modules/vocabulary/api/bulk_translate_api';

  // Minimal typings for the Google Website Translator widget (loaded from
  // translate.google.com), accessed off `window` so the island needs no ambient
  // global declarations.
  interface GoogleTranslateElementConstructor {
    new (
      config: {
        pageLanguage: string;
        layout: unknown;
        includedLanguages: string;
        autoDisplay: boolean;
      },
      elementId: string
    ): unknown;
    InlineLayout: { SIMPLE: unknown };
  }
  interface GoogleTranslateApi {
    translate: { TranslateElement: GoogleTranslateElementConstructor };
  }
  interface BulkTranslateWindow extends Window {
    WBLINK?: string;
    google?: GoogleTranslateApi;
    googleTranslateElementInit?: () => void;
  }

  const { config }: { config: BulkTranslateConfig } = $props();

  // `config` is a static mount-time prop, but these are `$derived` to keep
  // svelte-check happy about reading a prop at the top level (mirrors StarterVocab).

  // Dictionary links, in the shape the legacy language-config store + the
  // clickDictionary helper expect (`translator`, not `translate`).
  const dictConfig = $derived({
    dict1: config.dictionaries.dict1,
    dict2: config.dictionaries.dict2,
    translator: config.dictionaries.translate
  });
  const sourceLanguage = $derived(config.sourceLanguage ?? 'en');
  const targetLanguage = $derived(config.targetLanguage ?? 'en');
  const csrfToken = getCsrfToken();
  // Pagination carried only when there is a next batch (mirrors the PHP view's
  // `if ($nextOffset !== null)` hidden fields); also drives the End/Next label.
  const hasOffset = $derived(config.nextOffset !== null);

  // The Save button label. These short status strings were hardcoded English in
  // the Alpine component (never `__e()` i18n keys), so the port keeps them
  // verbatim.
  let submitButtonText = $state('Save');

  let formEl: HTMLFormElement | undefined = $state();
  let pollTimer: ReturnType<typeof setInterval> | undefined;

  function termInputs(): NodeListOf<HTMLInputElement | HTMLSelectElement> {
    return document.querySelectorAll<HTMLInputElement | HTMLSelectElement>('[name^=term]');
  }

  function updateSubmitButton(): void {
    const checked = document.querySelectorAll('input[type="checkbox"]:checked');
    submitButtonText = checked.length ? 'Save' : hasOffset ? 'Next' : 'End';
  }

  function markAll(): void {
    submitButtonText = 'Save';
    selectToggle(true, 'form1');
    termInputs().forEach((el) => {
      el.disabled = false;
    });
  }

  function markNone(): void {
    submitButtonText = hasOffset ? 'Next' : 'End';
    selectToggle(false, 'form1');
    termInputs().forEach((el) => {
      el.disabled = true;
    });
  }

  function handleTermToggle(termId: number, checked: boolean): void {
    document
      .querySelectorAll<HTMLInputElement | HTMLSelectElement>(
        `[name="term[${termId}][text]"], [name="term[${termId}][lg]"], [name="term[${termId}][status]"]`
      )
      .forEach((input) => {
        input.disabled = !checked;
      });
    const transInput = document.querySelector<HTMLInputElement>(`#Trans_${termId} input`);
    if (transInput) {
      transInput.disabled = !checked;
    }
    updateSubmitButton();
  }

  // The "Marked Terms" action select: status changes (1/99/98), set-to-lowercase
  // (6) and delete-translation (7) for every checked row.
  function handleTermToggles(action: string): void {
    if (action === '6') {
      document.querySelectorAll<HTMLInputElement>('.markcheck:checked').forEach((checkbox) => {
        const id = checkbox.value;
        const termSpan = document.querySelector<HTMLElement>(`#Term_${id} .term`);
        if (termSpan) {
          const lower = (termSpan.textContent || '').toLowerCase();
          termSpan.textContent = lower;
          const textInput = document.querySelector<HTMLInputElement>(`#Text_${id}`);
          if (textInput) {
            textInput.value = lower;
          }
        }
      });
      return;
    }
    if (action === '7') {
      document.querySelectorAll<HTMLInputElement>('.markcheck:checked').forEach((checkbox) => {
        const transInput = document.querySelector<HTMLInputElement>(`#Trans_${checkbox.value} input`);
        if (transInput) {
          transInput.value = '*';
        }
      });
      return;
    }
    document.querySelectorAll<HTMLInputElement>('.markcheck:checked').forEach((checkbox) => {
      const statSelect = document.querySelector<HTMLSelectElement>(`#Stat_${checkbox.value}`);
      if (statSelect) {
        statSelect.value = action;
      }
    });
  }

  function onStatusActionChange(event: Event): void {
    const select = event.currentTarget as HTMLSelectElement;
    handleTermToggles(select.value);
    select.selectedIndex = 0;
  }

  // Open the D1/D2/Tr dictionary for a row, and re-point the "translation"
  // tracker at that row's input so a looked-up gloss lands in the right cell.
  function clickDictionary(element: HTMLElement): void {
    let dictLink: string;
    if (element.classList.contains('dict1')) {
      dictLink = dictConfig.dict1;
    } else if (element.classList.contains('dict2')) {
      dictLink = dictConfig.dict2;
    } else if (element.classList.contains('dict3')) {
      dictLink = dictConfig.translator;
    } else {
      return;
    }

    (window as unknown as BulkTranslateWindow).WBLINK = dictLink;
    if (dictLink.startsWith('*')) {
      dictLink = dictLink.substring(1);
    }

    const parent = element.parentElement;
    const termText = parent?.previousElementSibling?.textContent || '';
    openDictionaryPopup(createTheDictUrl(dictLink, termText));

    const currentTranslation = document.querySelector<HTMLElement>('[name="translation"]');
    if (currentTranslation) {
      currentTranslation.setAttribute('name', currentTranslation.getAttribute('data_name') ?? '');
    }
    const nextRow = parent?.parentElement?.nextElementSibling;
    const el = nextRow?.firstElementChild as HTMLElement | null;
    if (el) {
      el.setAttribute('data_name', el.getAttribute('name') ?? '');
      el.setAttribute('name', 'translation');
    }
  }

  function deleteTranslation(termId: string): void {
    const transInput = document.querySelector<HTMLInputElement>(`#Trans_${termId} input`);
    if (transInput) {
      transInput.value = '';
      transInput.focus();
    }
  }

  // Delegated handler for the controls the Google-Translate hookup inserts as raw
  // HTML (dict links + del-translation), which carry no inline bindings.
  function onFormClick(event: MouseEvent): void {
    const target = event.target as HTMLElement | null;
    if (!target) {
      return;
    }
    const dict = target.closest<HTMLElement>('.dict1, .dict2, .dict3');
    if (dict) {
      clickDictionary(dict);
      return;
    }
    const del = target.closest<HTMLElement>('.del_trans');
    if (del) {
      const trans = del.closest<HTMLElement>('.trans');
      if (trans) {
        deleteTranslation((trans.id || '').replace('Trans_', ''));
      }
    }
  }

  // Before the native POST, give the active translation input its real name back
  // (clickDictionary temporarily renames it to "translation" for popup tracking).
  function onFormSubmit(): void {
    const currentTranslation = document.querySelector<HTMLElement>('[name="translation"]');
    if (currentTranslation) {
      currentTranslation.setAttribute('name', currentTranslation.getAttribute('data_name') ?? '');
    }
  }

  function setupGoogleTranslateCallback(): void {
    const w = window as unknown as BulkTranslateWindow;
    w.googleTranslateElementInit = () => {
      const google = w.google;
      if (google?.translate) {
        new google.translate.TranslateElement(
          {
            pageLanguage: sourceLanguage,
            layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
            includedLanguages: targetLanguage,
            autoDisplay: false
          },
          'google_translate_element'
        );
      }
    };
  }

  // Poll until Google Translate has populated every `.trans` cell, then convert
  // the translated text into editable inputs, add the dict links, drop Google's
  // widget chrome and enable the form. Mirrors the Alpine `setupInteractions()`.
  function setupInteractions(): void {
    pollTimer = setInterval(() => {
      const transElements = document.querySelectorAll('.trans');
      const transFontElements = document.querySelectorAll('.trans>font');
      if (transFontElements.length !== transElements.length) {
        return;
      }

      transElements.forEach((trans) => {
        const txt = trans.textContent || '';
        const cnt = (trans.id || '').replace('Trans_', '');
        trans.classList.add('notranslate');
        trans.innerHTML =
          `<input type="text" name="term[${cnt}][trans]" value="${txt}" maxlength="100" class="respinput">` +
          '<div class="del_trans"></div>';
      });

      document.querySelectorAll<HTMLElement>('.term').forEach((term) => {
        const parent = term.parentElement;
        if (parent) {
          parent.style.position = 'relative';
        }
        const dictLinksHtml =
          '<div class="dict">' +
          (dictConfig.dict1 ? '<span class="dict1">D1</span>' : '') +
          (dictConfig.dict2 ? '<span class="dict2">D2</span>' : '') +
          (dictConfig.translator ? '<span class="dict3">Tr</span>' : '') +
          '</div>';
        term.insertAdjacentHTML('afterend', dictLinksHtml);
      });

      document.querySelectorAll('iframe, #google_translate_element').forEach((el) => el.remove());

      selectToggle(true, 'form1');
      termInputs().forEach((el) => {
        el.disabled = false;
      });

      if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = undefined;
      }
    }, 300);
  }

  onMount(() => {
    // Keep the legacy language-config store in sync (used by some dictionary
    // helpers), like the Alpine `init()` did.
    setDictionaryLinks(dictConfig);

    // Don't let Google Translate touch headings/title.
    document.querySelectorAll('h3, h4, title').forEach((el) => el.classList.add('notranslate'));

    setupGoogleTranslateCallback();

    // Inject Google's Website Translator script (the PHP view had it inline); the
    // `cb=` callback fires `googleTranslateElementInit` once it loads.
    const script = document.createElement('script');
    script.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
    document.body.appendChild(script);

    initIcons();
    setupInteractions();

    // Delegated click for the dict/del-translation controls inserted as raw HTML
    // (attached imperatively rather than as a form `onclick`, which a `<form>`
    // shouldn't carry per a11y rules).
    formEl?.addEventListener('click', onFormClick);

    return () => {
      if (pollTimer) {
        clearInterval(pollTimer);
      }
      formEl?.removeEventListener('click', onFormClick);
    };
  });
</script>

<div class="container">
  <h2 class="title is-4 mb-4 notranslate">{t('vocabulary.list.col_translation')}</h2>

  <form
    name="form1"
    action={config.saveUrl}
    method="post"
    bind:this={formEl}
    onsubmit={onFormSubmit}
  >
    <input type="hidden" name="_csrf_token" value={csrfToken} />

    <!-- Controls Panel -->
    <div class="box notranslate mb-4">
      <div id="google_translate_element" class="mb-3"></div>

      <div class="level">
        <div class="level-left">
          <div class="level-item">
            <div class="buttons are-small">
              <button type="button" class="button is-info is-outlined" onclick={markAll}>
                <span class="icon is-small"><i data-lucide="check-square"></i></span>
                <span>{t('vocabulary.multi.mark_all')}</span>
              </button>
              <button type="button" class="button is-outlined" onclick={markNone}>
                <span class="icon is-small"><i data-lucide="square"></i></span>
                <span>{t('vocabulary.multi.mark_none')}</span>
              </button>
            </div>
          </div>
        </div>

        <div class="level-right">
          <div class="level-item">
            <div class="field has-addons">
              <div class="control">
                <span class="button is-static is-small">{t('vocabulary.multi.marked_terms')}</span>
              </div>
              <div class="control">
                <div class="select is-small">
                  <select onchange={onStatusActionChange}>
                    <option value="0" selected>{t('vocabulary.bulk.choose_placeholder')}</option>
                    <!-- Learning level 1-5 is derived from FSRS, not hand-set (issue #238). -->
                    <optgroup label={t('vocabulary.bulk.change_status')}>
                      <option value="1">
                        {t('vocabulary.bulk.set_status_to_prefix')}
                        {t('common.status_learning')}
                      </option>
                      <option value="99">{t('vocabulary.bulk.set_status_wkn')}</option>
                      <option value="98">{t('vocabulary.bulk.set_status_ign')}</option>
                    </optgroup>
                    <option value="6">{t('vocabulary.bulk.set_to_lowercase')}</option>
                    <option value="7">{t('vocabulary.bulk.delete_translation')}</option>
                  </select>
                </div>
              </div>
              <div class="control">
                <button type="submit" class="button is-primary is-small">
                  <span class="icon is-small"><i data-lucide="save"></i></span>
                  <span>{submitButtonText}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Terms Table -->
    <div class="table-container">
      <table class="table is-fullwidth is-striped is-hoverable">
        <thead>
          <tr class="notranslate">
            <th class="has-text-centered" style="width: 60px;">{t('vocabulary.common.mark')}</th>
            <th style="min-width: 8em;">{t('vocabulary.list.col_term')}</th>
            <th>{t('vocabulary.list.col_translation')}</th>
            <th class="has-text-centered" style="width: 100px;">{t('vocabulary.list.col_status')}</th>
          </tr>
        </thead>
        <tbody>
          {#each config.terms as term, i (i)}
            {@const cnt = i + 1}
            <tr>
              <td class="has-text-centered notranslate">
                <label class="checkbox">
                  <input
                    name={`marked[${cnt}]`}
                    type="checkbox"
                    class="markcheck"
                    checked
                    value={cnt}
                    onchange={(e) => handleTermToggle(cnt, e.currentTarget.checked)}
                  />
                </label>
              </td>
              <td id={`Term_${cnt}`} class="notranslate">
                <span class="term tag is-medium is-light">{term.word}</span>
              </td>
              <td class="trans" id={`Trans_${cnt}`}>{term.word.toLowerCase()}</td>
              <td class="has-text-centered notranslate">
                <div class="select is-small">
                  <!-- Learning level 1-5 is derived from FSRS, not hand-set (issue #238). -->
                  <select id={`Stat_${cnt}`} name={`term[${cnt}][status]`}>
                    <option value="1" selected>{t('common.status_learning')}</option>
                    <option value="99">{t('common.status_well_known')}</option>
                    <option value="98">{t('common.status_ignored')}</option>
                  </select>
                </div>
                <input type="hidden" id={`Text_${cnt}`} name={`term[${cnt}][text]`} value={term.word} />
                <input type="hidden" name={`term[${cnt}][lg]`} value={term.languageId} />
              </td>
            </tr>
          {/each}
        </tbody>
      </table>
    </div>

    <!-- Hidden fields -->
    <input type="hidden" name="tid" value={config.tid} />
    {#if config.nextOffset !== null}
      <input type="hidden" name="offset" value={config.nextOffset} />
      <input type="hidden" name="sl" value={config.sourceLanguage ?? ''} />
      <input type="hidden" name="tl" value={config.targetLanguage ?? ''} />
    {/if}
  </form>
</div>
