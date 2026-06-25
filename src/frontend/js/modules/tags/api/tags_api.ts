/**
 * Tags API — type-safe wrapper for tag management operations.
 *
 * The PHP server exposes tags over `/api/v1` as **GET only** (`/tags`,
 * `/tags/term`, `/tags/text` — the autocomplete + filter lists); renaming and
 * deleting tags are native web-route form POSTs, not JSON. The management arms
 * below (`/tags/manage`, `PUT`/`DELETE /tags/{term,text}/{id}`) are therefore
 * served only by the bundled client's local-first router, from IndexedDB. They
 * power the bundled `tags.html` page offline; there is no remote counterpart
 * (PHP being frozen), the same local-first-only shape as the per-text edit arms.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { apiGet, apiPut, apiDelete, type ApiResponse } from '@shared/api/client';

/** One tag in the management list (term or text), with its usage count. */
export interface TagManageItem {
  id: number;
  name: string;
  /** How many terms (or texts) currently carry this tag. */
  count: number;
}

/** Response for GET /tags/manage. */
export interface TagsManageResponse {
  term: TagManageItem[];
  text: TagManageItem[];
}

/** Response for a tag rename/delete. */
export interface TagMutationResponse {
  success?: boolean;
  error?: string;
}

/**
 * Tags API methods (management surface — see the module note on availability).
 */
export const TagsApi = {
  /** List every term + text tag with usage counts (local-first only). */
  async listForManagement(): Promise<ApiResponse<TagsManageResponse>> {
    return apiGet<TagsManageResponse>('/tags/manage');
  },

  /** Rename a term tag. */
  async renameTerm(id: number, name: string): Promise<ApiResponse<TagMutationResponse>> {
    return apiPut<TagMutationResponse>(`/tags/term/${id}`, { name });
  },

  /** Delete a term tag and unassign it from every term. */
  async deleteTerm(id: number): Promise<ApiResponse<TagMutationResponse>> {
    return apiDelete<TagMutationResponse>(`/tags/term/${id}`);
  },

  /** Rename a text tag. */
  async renameText(id: number, name: string): Promise<ApiResponse<TagMutationResponse>> {
    return apiPut<TagMutationResponse>(`/tags/text/${id}`, { name });
  },

  /** Delete a text tag and unassign it from every text. */
  async deleteText(id: number): Promise<ApiResponse<TagMutationResponse>> {
    return apiDelete<TagMutationResponse>(`/tags/text/${id}`);
  }
};
