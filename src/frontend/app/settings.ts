/**
 * "Preferences" page entry for the bundled client.
 *
 * Replaces the server-rendered preferences form (`Modules/User/Views/
 * preferences.php`), which POSTs to `/profile/preferences` (a native PHP form,
 * not the API) and so cannot work offline. This is a purpose-built API-client
 * form (like `language-edit.ts`/`word.ts`) reached from the navbar's
 * "Preferences" link (`/profile/preferences`).
 *
 * Scope vs. the PHP form: it carries only the preferences the *bundled* client
 * actually honours. The vast majority of `preferences.php` (per-page pagination,
 * tooltip mode, review timings, sentence counts, translation delimiters, …) is
 * consumed by the PHP renderer at request time and has **no consumer in the
 * bundle** — porting those would ship dead controls — and the offline reader
 * config is hard-coded (`local/text-assembly.ts:buildReadingConfig`), so reader
 * behaviours don't read the settings store either. What's left, and genuinely
 * works here, is:
 *
 *   - **Default language** (`currentlanguage`) — fully on-device: the local-first
 *     router persists it (`setCurrentLanguageId`) and the library / new-text
 *     pages read it back. This is the one preference that takes effect offline.
 *   - **Interface language** (the UI locale) — works when a server is connected
 *     (it serves the chosen catalog from `GET /api/v1/i18n/{locale}`). Offline
 *     only English is bundled (`local/i18n.ts`), so the picker is disabled with a
 *     note — the same graceful degradation as language-edit's server-only fields.
 *
 * Everything is persisted through the API client, so the page works in both
 * modes: offline the calls are intercepted by the local-first router (zero
 * network); server-backed they hit `POST /api/v1/settings`.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { bootAppPage, initDataMode } from './boot';
import { LanguagesApi } from '@modules/language/api/languages_api';
import { SettingsApi } from '@modules/admin/api/settings_api';
import { loadI18nFromApi, getStoredLocale } from '@shared/i18n/translator';

/** The UI locales the catalog ships (`locale/<code>/`), with native names. */
const LOCALES: ReadonlyArray<{ code: string; name: string }> = [
  { code: 'en', name: 'English' },
  { code: 'de', name: 'Deutsch' },
  { code: 'es', name: 'Español' },
  { code: 'fr', name: 'Français' },
  { code: 'it', name: 'Italiano' },
  { code: 'ja', name: '日本語' },
  { code: 'pt', name: 'Português' },
  { code: 'ru', name: 'Русский' },
  { code: 'zh', name: '中文' },
];

function el<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

function showError(target: HTMLElement | null, message: string): void {
  if (target) {
    target.textContent = message;
    target.style.display = '';
  }
}

async function start(): Promise<void> {
  // Local-first (seed on first run) before any API call, so this works offline.
  const localFirst = await initDataMode();

  const loading = el<HTMLElement>('st-loading');
  const form = el<HTMLFormElement>('settings-form');
  const defaultLang = el<HTMLSelectElement>('st-default-lang');
  const locale = el<HTMLSelectElement>('st-locale');
  const localeNote = el<HTMLElement>('st-locale-note');
  const errorEl = el<HTMLElement>('st-error');
  const successEl = el<HTMLElement>('st-success');
  const submit = el<HTMLButtonElement>('st-submit');

  // 1. Default language — populate from the languages list and pre-select the
  //    current one (the same resolution library.ts uses).
  const res = await LanguagesApi.list();
  const languages = res.data?.languages ?? [];
  const currentLangId = res.data?.currentLanguageId ?? 0;
  if (defaultLang) {
    for (const lang of languages) {
      const option = document.createElement('option');
      option.value = String(lang.id);
      option.textContent = lang.name;
      option.selected = lang.id === currentLangId;
      defaultLang.appendChild(option);
    }
    if (languages.length === 0) {
      const option = document.createElement('option');
      option.value = '';
      option.textContent = 'No languages yet';
      defaultLang.appendChild(option);
      defaultLang.disabled = true;
    }
  }

  // 2. Interface language — pre-select the stored locale. Offline only English
  //    is bundled, so disable the picker and explain (server-backed gets the
  //    full set from GET /i18n/{locale}).
  const storedLocale = getStoredLocale() ?? 'en';
  if (locale) {
    for (const loc of LOCALES) {
      const option = document.createElement('option');
      option.value = loc.code;
      option.textContent = loc.name;
      option.selected = loc.code === storedLocale;
      locale.appendChild(option);
    }
    if (localFirst) {
      locale.disabled = true;
      if (localeNote) localeNote.style.display = '';
    }
  }

  if (loading) loading.style.display = 'none';
  if (form) form.style.display = '';

  form?.addEventListener('submit', (event) => {
    event.preventDefault();
    if (errorEl) errorEl.style.display = 'none';
    if (successEl) successEl.style.display = 'none';
    submit?.classList.add('is-loading');

    const newLangId = Number(defaultLang?.value) || 0;
    const newLocale = locale?.value ?? storedLocale;
    const localeChanged = !localFirst && newLocale !== storedLocale;

    // Finish the save: surface an error, apply a server-backed locale change (by
    // fetching its catalog and reloading — the page re-renders in the chosen
    // locale, its own confirmation), or just confirm inline. Offline the locale
    // picker is disabled, so the reload branch never runs.
    const finish = (errMsg?: string): void => {
      if (errMsg) {
        showError(errorEl, errMsg || 'Could not save your preferences.');
        submit?.classList.remove('is-loading');
        return;
      }
      if (localeChanged) {
        void loadI18nFromApi(newLocale).then(() => window.location.reload());
        return;
      }
      submit?.classList.remove('is-loading');
      if (successEl) successEl.style.display = '';
    };

    // Persist the default language through the API client (offline -> local
    // router's setCurrentLanguageId; server-backed -> POST /settings).
    if (newLangId > 0) {
      void SettingsApi.save('currentlanguage', String(newLangId)).then((r) => finish(r.error));
    } else {
      finish();
    }
  });

  await bootAppPage({ requireAuth: true });
}

void start();
