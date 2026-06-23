/**
 * "Add a text" page entry for the bundled client.
 *
 * The server-rendered text form (`Modules/Text/Views/edit_form.php`) does a
 * native multipart POST to `/texts/new` and bundles server-only importers
 * (EPUB / web page / YouTube / Gutenberg…), so it cannot work offline. This is a
 * purpose-built offline-first replacement for the core case — paste a text — that
 * creates it through the API client (`TextsApi.create` -> `POST /api/v1/texts`),
 * which the local-first router parses on-device. Content discovery / file import
 * stay as enhanced-when-connected features reached via a server.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { bootAppPage, initDataMode } from './boot';
import { pageUrl } from './router';
import { LanguagesApi } from '@modules/language/api/languages_api';
import { TextsApi } from '@modules/text/api/texts_api';

interface LanguageOption {
  id: number;
  name: string;
}

function showError(el: HTMLElement | null, message: string): void {
  if (el) {
    el.textContent = message;
    el.style.display = '';
  }
}

function parseTags(raw: string): string[] {
  return raw
    .split(',')
    .map((t) => t.trim())
    .filter((t) => t !== '');
}

function setupForm(languages: LanguageOption[]): void {
  const langSelect = document.getElementById('nt-lang') as HTMLSelectElement | null;
  const titleInput = document.getElementById('nt-title') as HTMLInputElement | null;
  const textInput = document.getElementById('nt-text') as HTMLTextAreaElement | null;
  const tagsInput = document.getElementById('nt-tags') as HTMLInputElement | null;
  const form = document.getElementById('new-text-form') as HTMLFormElement | null;
  const errorEl = document.getElementById('nt-error');
  const submit = document.getElementById('nt-submit') as HTMLButtonElement | null;
  const noLang = document.getElementById('nt-no-lang');
  if (!langSelect || !titleInput || !textInput || !form) {
    return;
  }

  // No language yet -> a text has nothing to parse into. Guide to create one.
  if (languages.length === 0) {
    if (noLang) {
      noLang.style.display = '';
    }
    form.style.display = 'none';
    return;
  }

  for (const lang of languages) {
    const option = document.createElement('option');
    option.value = String(lang.id);
    option.textContent = lang.name;
    langSelect.appendChild(option);
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (errorEl) {
      errorEl.style.display = 'none';
    }
    const langId = Number(langSelect.value);
    const title = titleInput.value.trim();
    const text = textInput.value.trim();
    if (!langId || title === '' || text === '') {
      showError(errorEl, 'Please choose a language and enter a title and some text.');
      return;
    }
    submit?.classList.add('is-loading');
    const tags = parseTags(tagsInput?.value ?? '');
    void TextsApi.create({ langId, title, text, tags }).then((res) => {
      const id = res.data?.id;
      if (res.error || !id) {
        showError(errorEl, res.error || 'Could not create the text.');
        submit?.classList.remove('is-loading');
        return;
      }
      // Created and parsed on-device: open it in the reader.
      window.location.assign(pageUrl.read(id, langId));
    });
  });
}

async function start(): Promise<void> {
  // Become local-first (and seed on first run) before listing languages or
  // POSTing, so everything is served on-device.
  await initDataMode();
  const res = await LanguagesApi.list();
  const languages = (res.data?.languages ?? []).map((l) => ({ id: l.id, name: l.name }));
  setupForm(languages);
  await bootAppPage({ requireAuth: true });
}

void start();
