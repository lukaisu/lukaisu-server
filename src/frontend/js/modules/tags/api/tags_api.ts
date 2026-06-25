/**
 * Tags API — type-safe wrapper for tag management operations.
 *
 * The management arms (`GET /tags/manage`, `PUT`/`DELETE /tags/{term,text}/{id}`)
 * power the bundled `tags.html` page. They are served both on-device (the
 * local-first router, from IndexedDB) and server-backed (the matching `/api/v1`
 * endpoints, added with the PHP-view cut-over so the page also works against a
 * connected server). The read-only autocomplete/filter lists stay at `GET /tags`,
 * `/tags/term`, `/tags/text`.
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
  /** List every term + text tag with usage counts. */
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
