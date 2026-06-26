/**
 * Gutenberg Suggestions - Auto-suggested texts from Project Gutenberg.
 *
 * Alpine.js component that fetches and displays popular books
 * for the user's current language, ranked by estimated difficulty.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import Alpine from 'alpinejs';
import { initIcons } from '@shared/icons/lucide_icons';
import { t } from '@shared/i18n/translator';
import { apiGet, apiPost } from '@shared/api/client';
import { isLocalFirst } from '@shared/offline/local/router';

interface SuggestedBook {
  id: number;
  title: string;
  authors: string[];
  languages: string[];
  subjects: string[];
  downloadCount: number;
  textUrl: string;
  difficultyTier?: 'easy' | 'medium' | 'hard';
}

interface PreviewData {
  total_unique_words: number;
  known_words: number;
  unknown_words: number;
  coverage_percent: number;
  difficulty_label: string;
  sample_unknown_words: string[];
}

/** Catalog page envelope (`{ results, count, next }`), shared with the server. */
interface CatalogResponse {
  results: SuggestedBook[];
  count: number;
  next: boolean;
}

interface SuggestionsData {
  books: SuggestedBook[];
  hasMore: boolean;
  page: number;
  loading: boolean;
  error: string;
  languageId: number;
  basePath: string;
  importing: number | null;
  previewBookId: number | null;
  previewLoading: boolean;
  previewData: PreviewData | null;
  previewError: string;

  init(): void;
  fetchSuggestions(page: number): Promise<void>;
  loadMore(): Promise<void>;
  previewBook(book: SuggestedBook): Promise<void>;
  importBook(book: SuggestedBook): Promise<void>;
  formatAuthors(authors: string[]): string;
  tierLabel(tier: string): string;
  tierClass(tier: string): string;
  importingClass(book: SuggestedBook): string;
  isImporting(): boolean;
  isPreviewing(book: SuggestedBook): boolean;
  coverageClass(label: string): string;
  coverageLabel(data: PreviewData): string;
}

/**
 * Alpine.js data component for Gutenberg suggestions.
 */
export function gutenbergSuggestionsData(): SuggestionsData {
  return {
    books: [],
    hasMore: false,
    page: 1,
    loading: false,
    error: '',
    languageId: 0,
    basePath: '',
    importing: null,
    previewBookId: null,
    previewLoading: false,
    previewData: null,
    previewError: '',

    init() {
      const configEl = document.getElementById('home-warnings-config');
      if (configEl) {
        const config = JSON.parse(configEl.textContent || '{}');
        this.languageId = config.currentLanguageId || 0;
        this.basePath = config.basePath || '';
      }

      if (this.languageId > 0) {
        this.fetchSuggestions(1);
      }

      // Re-fetch when language changes
      document.addEventListener('lukaisu:languageChanged', ((event: CustomEvent) => {
        const langId = parseInt(event.detail.languageId, 10);
        if (langId > 0 && langId !== this.languageId) {
          this.languageId = langId;
          this.books = [];
          this.page = 1;
          this.fetchSuggestions(1);
        }
      }) as EventListener);
    },

    async fetchSuggestions(page: number) {
      if (this.loading || this.languageId <= 0) return;

      this.loading = true;
      this.error = '';

      try {
        const { data, error } = await apiGet<CatalogResponse>(
          '/texts/gutenberg-suggestions',
          { language_id: this.languageId, page }
        );

        if (error || !data) {
          this.error = error || 'Could not load suggestions.';
          return;
        }

        const results = data.results || [];
        this.books = page === 1 ? results : this.books.concat(results);
        this.hasMore = data.next || false;
        this.page = page;
        requestAnimationFrame(() => initIcons());
      } catch {
        this.error = 'Could not reach the server.';
      } finally {
        this.loading = false;
      }
    },

    async loadMore() {
      if (this.loading || !this.hasMore) return;
      await this.fetchSuggestions(this.page + 1);
    },

    async previewBook(book: SuggestedBook) {
      // Toggle off if already previewing this book
      if (this.previewBookId === book.id) {
        this.previewBookId = null;
        this.previewData = null;
        this.previewError = '';
        return;
      }

      this.previewBookId = book.id;
      this.previewLoading = true;
      this.previewData = null;
      this.previewError = '';

      try {
        // Coverage preview stays server-enhanced (it needs to fetch + sample
        // the full text). Routed through apiGet so it targets the configured
        // server when connected and fails gracefully when none is.
        const { data, error } = await apiGet<PreviewData>('/texts/library-preview', {
          url: book.textUrl,
          language_id: this.languageId,
        });

        if (this.previewBookId !== book.id) return;

        if (error || !data) {
          this.previewError = error || 'Could not analyze this text.';
          return;
        }

        this.previewData = data;
      } catch {
        if (this.previewBookId === book.id) {
          this.previewError = 'Could not reach the server.';
        }
      } finally {
        this.previewLoading = false;
      }
    },

    async importBook(book: SuggestedBook) {
      if (this.importing !== null) return;
      this.importing = book.id;

      // Local-first: import the plain-text book on-device (fetch CORS-free,
      // strip Gutenberg boilerplate, parse) and open the reader — no server.
      if (isLocalFirst()) {
        const { data, error } = await apiPost<{ id?: number }>('/texts/import-gutenberg', {
          url: book.textUrl,
          title: book.title,
          language_id: this.languageId,
        });
        if (data?.id) {
          window.location.href = `${this.basePath}/text/${data.id}/read`;
          return;
        }
        this.error = error || 'Could not import this book.';
        this.importing = null;
        return;
      }

      // Server mode: hand off to the server's URL-import flow.
      const params = new URLSearchParams({
        import_url: book.textUrl,
        import_title: book.title,
      });
      window.location.href = `${this.basePath}/texts/new?${params}`;
    },

    formatAuthors(authors: string[]): string {
      if (authors.length === 0) return 'Unknown author';
      return authors.join(', ');
    },

    tierLabel(tier: string): string {
      return tier === 'easy' ? 'Easy' : tier === 'hard' ? 'Hard' : 'Medium';
    },

    tierClass(tier: string): string {
      if (tier === 'easy') return 'is-success is-light';
      if (tier === 'hard') return 'is-danger is-light';
      return 'is-warning is-light';
    },

    importingClass(book: SuggestedBook): string {
      return this.importing === book.id ? 'is-loading' : '';
    },

    isImporting(): boolean {
      return this.importing !== null;
    },

    isPreviewing(book: SuggestedBook): boolean {
      return this.previewBookId === book.id;
    },

    coverageClass(label: string): string {
      if (label === 'easy') return 'is-success';
      if (label === 'hard') return 'is-danger';
      return 'is-warning';
    },

    coverageLabel(data: PreviewData): string {
      return t('home.you_know_x_of_unique_words', { percent: data.coverage_percent });
    },
  };
}

/**
 * Initialize the Gutenberg suggestions Alpine.js component.
 */
export function initGutenbergSuggestions(): void {
  Alpine.data('gutenbergSuggestions', gutenbergSuggestionsData);
}

initGutenbergSuggestions();
