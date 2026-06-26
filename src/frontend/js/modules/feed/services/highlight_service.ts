/**
 * Highlight Service - Manages CSS class application for feed wizard highlighting.
 *
 * Encapsulates all DOM class manipulation for testability and separation of concerns.
 *
 * CSS classes used:
 * - .lukaisu_selected_text - Elements matching confirmed XPath selections
 * - .lukaisu_marked_text - Elements matching current/preview selection
 * - .lukaisu_filtered_text - Elements to be filtered out (step 3)
 * - .lukaisu_highlighted_text - Currently highlighted list item selection
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { xpathQuery, getDescendantsAndSelf } from '../utils/xpath_utils';

/**
 * Service for managing DOM element highlighting in the feed wizard.
 */
export class HighlightService {
  /** Header element containing wizard controls (excluded from highlighting) */
  private headerElement: HTMLElement | null = null;

  /** Set of header descendant elements for quick exclusion checks */
  private headerDescendants: Set<HTMLElement> = new Set();

  /** Track elements we've modified for cleanup */
  private modifiedElements: Set<HTMLElement> = new Set();

  /**
   * Initialize the highlight service with container references.
   *
   * @param headerSelector - CSS selector for the header element (default: '#lukaisu_header')
   */
  init(headerSelector: string = '#lukaisu_header'): void {
    this.headerElement = document.querySelector(headerSelector);
    this.headerDescendants.clear();

    if (this.headerElement) {
      getDescendantsAndSelf(this.headerElement).forEach(el => {
        this.headerDescendants.add(el);
      });
    }
  }

  /**
   * Apply marking (preview) to elements matching an XPath expression.
   * Clears any existing marking first.
   *
   * @param xpath - XPath expression to match
   */
  markElements(xpath: string): void {
    this.clearMarking();

    if (!xpath) return;

    const elements = xpathQuery(xpath);
    for (const el of this.filterValidElements(elements)) {
      this.addClassToTree(el, 'lukaisu_marked_text');
    }
  }

  /**
   * Clear all marking classes from the document.
   */
  clearMarking(): void {
    document.querySelectorAll('.lukaisu_marked_text').forEach(el => {
      el.classList.remove('lukaisu_marked_text');
      this.modifiedElements.add(el as HTMLElement);
    });
    this.cleanEmptyClassAttrs();
  }

  /**
   * Apply selection to all elements matching an array of XPath expressions.
   *
   * @param xpaths - Array of XPath expressions
   */
  applySelections(xpaths: string[]): void {
    this.clearSelections();

    if (xpaths.length === 0) return;

    // Combine all xpaths and query once
    const combined = xpaths.filter(x => x.trim()).join(' | ');
    if (!combined) return;

    const elements = xpathQuery(combined);
    for (const el of this.filterValidElements(elements)) {
      this.addClassToTree(el, 'lukaisu_selected_text');
    }
  }

  /**
   * Clear all selection classes from the document.
   */
  clearSelections(): void {
    document.querySelectorAll('.lukaisu_selected_text').forEach(el => {
      el.classList.remove('lukaisu_selected_text');
      this.modifiedElements.add(el as HTMLElement);
    });
    this.cleanEmptyClassAttrs();
  }

  /**
   * Highlight elements matching an XPath (for list item focus).
   * Also applies selection class to descendants.
   *
   * @param xpath - XPath expression to highlight
   */
  highlightListItem(xpath: string): void {
    this.clearHighlighting();

    if (!xpath) return;

    const elements = xpathQuery(xpath);
    for (const el of this.filterValidElements(elements)) {
      el.classList.add('lukaisu_highlighted_text');
      this.modifiedElements.add(el);

      // Also mark descendants as selected
      this.addClassToTree(el, 'lukaisu_selected_text');
    }
  }

  /**
   * Clear all highlighting classes from the document.
   */
  clearHighlighting(): void {
    document.querySelectorAll('.lukaisu_highlighted_text').forEach(el => {
      el.classList.remove('lukaisu_highlighted_text');
      this.modifiedElements.add(el as HTMLElement);
    });
  }

  /**
   * Apply filter classes to elements matching XPath expressions.
   * Used in step 3 to dim elements that will be filtered out.
   *
   * @param xpaths - Array of XPath expressions to filter
   */
  applyFilters(xpaths: string[]): void {
    if (xpaths.length === 0) return;

    const combined = xpaths.filter(x => x.trim()).join(' | ');
    if (!combined) return;

    const elements = xpathQuery(combined);
    for (const el of this.filterValidElements(elements)) {
      el.classList.add('lukaisu_filtered_text');
      this.modifiedElements.add(el);
    }
  }

  /**
   * Apply article section filtering for step 3.
   * Marks all elements outside the article section as filtered.
   *
   * @param articleSelector - XPath expression for article section
   */
  applyArticleSectionFilter(articleSelector: string): void {
    this.clearFiltering();

    if (!articleSelector) return;

    // Get all elements in the article section
    const articleElements = xpathQuery(articleSelector);
    const articleSet = new Set<HTMLElement>();

    articleElements.forEach(el => {
      getDescendantsAndSelf(el).forEach(d => articleSet.add(d));
    });

    // Find all content after the header and filter non-article elements
    const lukaisuLast = document.getElementById('lukaisu_last');
    if (!lukaisuLast) return;

    let sibling = lukaisuLast.nextElementSibling;
    while (sibling) {
      if (sibling instanceof HTMLElement) {
        getDescendantsAndSelf(sibling).forEach(el => {
          if (!articleSet.has(el) && !this.headerDescendants.has(el)) {
            el.classList.add('lukaisu_filtered_text');
            this.modifiedElements.add(el);
          }
        });
      }
      sibling = sibling.nextElementSibling;
    }
  }

  /**
   * Clear all filter classes from the document.
   */
  clearFiltering(): void {
    document.querySelectorAll('.lukaisu_filtered_text').forEach(el => {
      el.classList.remove('lukaisu_filtered_text');
      this.modifiedElements.add(el as HTMLElement);
    });
    this.cleanEmptyClassAttrs();
  }

  /**
   * Clear all wizard-related classes from the document.
   */
  clearAll(): void {
    this.clearMarking();
    this.clearSelections();
    this.clearFiltering();
    this.clearHighlighting();
  }

  /**
   * Toggle image visibility in the feed content.
   *
   * @param hide - Whether to hide images
   */
  toggleImages(hide: boolean): void {
    document.querySelectorAll<HTMLImageElement>('img').forEach(img => {
      // Skip images in the header
      if (this.headerDescendants.has(img)) return;

      img.style.display = hide ? 'none' : '';
    });
  }

  /**
   * Get the feed content container (elements after lukaisu_last).
   *
   * @returns Array of content container elements
   */
  getContentElements(): HTMLElement[] {
    const elements: HTMLElement[] = [];
    const lukaisuLast = document.getElementById('lukaisu_last');

    if (lukaisuLast) {
      let sibling = lukaisuLast.nextElementSibling;
      while (sibling) {
        if (sibling instanceof HTMLElement) {
          elements.push(sibling);
        }
        sibling = sibling.nextElementSibling;
      }
    }

    return elements;
  }

  /**
   * Update the margin of lukaisu_last to account for header height.
   */
  updateLastMargin(): void {
    const lukaisuLast = document.getElementById('lukaisu_last');
    if (lukaisuLast && this.headerElement) {
      lukaisuLast.style.marginTop = this.headerElement.offsetHeight + 'px';
    }
  }

  // === Private Helper Methods ===

  /**
   * Filter out elements that are within the header.
   */
  private filterValidElements(elements: HTMLElement[]): HTMLElement[] {
    return elements.filter(el => !this.headerDescendants.has(el));
  }

  /**
   * Add a class to an element and all its descendants.
   */
  private addClassToTree(element: HTMLElement, className: string): void {
    element.classList.add(className);
    this.modifiedElements.add(element);

    element.querySelectorAll('*').forEach(child => {
      if (child instanceof HTMLElement) {
        // Don't overwrite selected_text with marked_text
        if (className === 'lukaisu_marked_text' && child.classList.contains('lukaisu_selected_text')) {
          return;
        }
        child.classList.add(className);
        this.modifiedElements.add(child);
      }
    });
  }

  /**
   * Remove empty class attributes from modified elements.
   */
  private cleanEmptyClassAttrs(): void {
    this.modifiedElements.forEach(el => {
      if (el.getAttribute('class') === '') {
        el.removeAttribute('class');
      }
    });

    // Also clean any others we might have missed
    document.querySelectorAll('[class=""]').forEach(el => {
      el.removeAttribute('class');
    });
  }
}

/**
 * Singleton instance of the highlight service.
 */
let highlightServiceInstance: HighlightService | null = null;

/**
 * Get the highlight service singleton.
 *
 * @returns The highlight service instance
 */
export function getHighlightService(): HighlightService {
  if (!highlightServiceInstance) {
    highlightServiceInstance = new HighlightService();
  }
  return highlightServiceInstance;
}

/**
 * Initialize the highlight service with default settings.
 * Call this when the wizard page loads.
 */
export function initHighlightService(): HighlightService {
  const service = getHighlightService();
  service.init();
  return service;
}
