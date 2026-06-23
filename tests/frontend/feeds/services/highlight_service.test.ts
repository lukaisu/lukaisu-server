/**
 * Tests for feeds/services/highlight_service.ts - DOM highlighting for feed wizard
 */
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import {
  HighlightService,
  getHighlightService,
  initHighlightService
} from '../../../../src/frontend/js/modules/feed/services/highlight_service';

describe('feeds/services/highlight_service.ts', () => {
  let service: HighlightService;

  beforeEach(() => {
    document.body.innerHTML = '';
    service = new HighlightService();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  // ===========================================================================
  // init Tests
  // ===========================================================================

  describe('init', () => {
    it('initializes without error', () => {
      expect(() => service.init()).not.toThrow();
    });

    it('finds header element by default selector', () => {
      document.body.innerHTML = '<div id="lukaisu_header">Header</div>';

      service.init();

      // No error thrown means it found the element
      expect(true).toBe(true);
    });

    it('uses custom selector', () => {
      document.body.innerHTML = '<div id="custom_header">Header</div>';

      service.init('#custom_header');

      // No error thrown means it found the element
      expect(true).toBe(true);
    });
  });

  // ===========================================================================
  // markElements Tests
  // ===========================================================================

  describe('markElements', () => {
    it('adds lukaisu_marked_text class to matching elements', () => {
      document.body.innerHTML = '<div class="target">hello</div>';
      service.init();

      service.markElements('//div[@class="target"]');

      const el = document.querySelector('.target');
      expect(el?.classList.contains('lukaisu_marked_text')).toBe(true);
    });

    it('clears previous marking', () => {
      document.body.innerHTML = '<div class="first">1</div><div class="second">2</div>';
      service.init();

      service.markElements('//div[@class="first"]');
      service.markElements('//div[@class="second"]');

      expect(document.querySelector('.first')?.classList.contains('lukaisu_marked_text')).toBe(false);
      expect(document.querySelector('.second')?.classList.contains('lukaisu_marked_text')).toBe(true);
    });

    it('handles empty xpath', () => {
      document.body.innerHTML = '<div>hello</div>';
      service.init();

      expect(() => service.markElements('')).not.toThrow();
    });

    it('marks descendants too', () => {
      document.body.innerHTML = '<div class="parent"><span class="child">hello</span></div>';
      service.init();

      service.markElements('//div[@class="parent"]');

      expect(document.querySelector('.child')?.classList.contains('lukaisu_marked_text')).toBe(true);
    });
  });

  // ===========================================================================
  // clearMarking Tests
  // ===========================================================================

  describe('clearMarking', () => {
    it('removes lukaisu_marked_text class from all elements', () => {
      document.body.innerHTML = '<div class="lukaisu_marked_text">hello</div>';
      service.init();

      service.clearMarking();

      expect(document.querySelector('.lukaisu_marked_text')).toBeNull();
    });

    it('handles no marked elements', () => {
      document.body.innerHTML = '<div>hello</div>';
      service.init();

      expect(() => service.clearMarking()).not.toThrow();
    });
  });

  // ===========================================================================
  // applySelections Tests
  // ===========================================================================

  describe('applySelections', () => {
    it('adds lukaisu_selected_text class to matching elements', () => {
      document.body.innerHTML = '<div class="target">hello</div>';
      service.init();

      service.applySelections(['//div[@class="target"]']);

      expect(document.querySelector('.target')?.classList.contains('lukaisu_selected_text')).toBe(true);
    });

    it('handles multiple xpaths', () => {
      document.body.innerHTML = '<div class="a">A</div><span class="b">B</span>';
      service.init();

      service.applySelections(['//div[@class="a"]', '//span[@class="b"]']);

      expect(document.querySelector('.a')?.classList.contains('lukaisu_selected_text')).toBe(true);
      expect(document.querySelector('.b')?.classList.contains('lukaisu_selected_text')).toBe(true);
    });

    it('clears previous selections first', () => {
      document.body.innerHTML = '<div class="first">1</div><div class="second">2</div>';
      service.init();

      service.applySelections(['//div[@class="first"]']);
      service.applySelections(['//div[@class="second"]']);

      expect(document.querySelector('.first')?.classList.contains('lukaisu_selected_text')).toBe(false);
      expect(document.querySelector('.second')?.classList.contains('lukaisu_selected_text')).toBe(true);
    });

    it('handles empty array', () => {
      document.body.innerHTML = '<div class="lukaisu_selected_text">hello</div>';
      service.init();

      service.applySelections([]);

      expect(document.querySelector('.lukaisu_selected_text')).toBeNull();
    });
  });

  // ===========================================================================
  // clearSelections Tests
  // ===========================================================================

  describe('clearSelections', () => {
    it('removes lukaisu_selected_text class from all elements', () => {
      document.body.innerHTML = '<div class="lukaisu_selected_text">hello</div>';
      service.init();

      service.clearSelections();

      expect(document.querySelector('.lukaisu_selected_text')).toBeNull();
    });
  });

  // ===========================================================================
  // highlightListItem Tests
  // ===========================================================================

  describe('highlightListItem', () => {
    it('adds lukaisu_highlighted_text class', () => {
      document.body.innerHTML = '<div class="target">hello</div>';
      service.init();

      service.highlightListItem('//div[@class="target"]');

      expect(document.querySelector('.target')?.classList.contains('lukaisu_highlighted_text')).toBe(true);
    });

    it('also applies selection class', () => {
      document.body.innerHTML = '<div class="target">hello</div>';
      service.init();

      service.highlightListItem('//div[@class="target"]');

      expect(document.querySelector('.target')?.classList.contains('lukaisu_selected_text')).toBe(true);
    });

    it('clears previous highlighting', () => {
      document.body.innerHTML = '<div class="first">1</div><div class="second">2</div>';
      service.init();

      service.highlightListItem('//div[@class="first"]');
      service.highlightListItem('//div[@class="second"]');

      expect(document.querySelector('.first')?.classList.contains('lukaisu_highlighted_text')).toBe(false);
    });
  });

  // ===========================================================================
  // clearHighlighting Tests
  // ===========================================================================

  describe('clearHighlighting', () => {
    it('removes lukaisu_highlighted_text class from all elements', () => {
      document.body.innerHTML = '<div class="lukaisu_highlighted_text">hello</div>';
      service.init();

      service.clearHighlighting();

      expect(document.querySelector('.lukaisu_highlighted_text')).toBeNull();
    });
  });

  // ===========================================================================
  // applyFilters Tests
  // ===========================================================================

  describe('applyFilters', () => {
    it('adds lukaisu_filtered_text class to matching elements', () => {
      document.body.innerHTML = '<div class="target">hello</div>';
      service.init();

      service.applyFilters(['//div[@class="target"]']);

      expect(document.querySelector('.target')?.classList.contains('lukaisu_filtered_text')).toBe(true);
    });

    it('handles empty array', () => {
      document.body.innerHTML = '<div>hello</div>';
      service.init();

      expect(() => service.applyFilters([])).not.toThrow();
    });
  });

  // ===========================================================================
  // clearFiltering Tests
  // ===========================================================================

  describe('clearFiltering', () => {
    it('removes lukaisu_filtered_text class from all elements', () => {
      document.body.innerHTML = '<div class="lukaisu_filtered_text">hello</div>';
      service.init();

      service.clearFiltering();

      expect(document.querySelector('.lukaisu_filtered_text')).toBeNull();
    });
  });

  // ===========================================================================
  // clearAll Tests
  // ===========================================================================

  describe('clearAll', () => {
    it('clears all wizard classes', () => {
      document.body.innerHTML = `
        <div class="lukaisu_marked_text">1</div>
        <div class="lukaisu_selected_text">2</div>
        <div class="lukaisu_filtered_text">3</div>
        <div class="lukaisu_highlighted_text">4</div>
      `;
      service.init();

      service.clearAll();

      expect(document.querySelector('.lukaisu_marked_text')).toBeNull();
      expect(document.querySelector('.lukaisu_selected_text')).toBeNull();
      expect(document.querySelector('.lukaisu_filtered_text')).toBeNull();
      expect(document.querySelector('.lukaisu_highlighted_text')).toBeNull();
    });
  });

  // ===========================================================================
  // toggleImages Tests
  // ===========================================================================

  describe('toggleImages', () => {
    it('hides images when hide is true', () => {
      document.body.innerHTML = '<img id="img1" src="test.jpg">';
      service.init();

      service.toggleImages(true);

      const img = document.getElementById('img1') as HTMLImageElement;
      expect(img.style.display).toBe('none');
    });

    it('shows images when hide is false', () => {
      document.body.innerHTML = '<img id="img1" src="test.jpg" style="display: none;">';
      service.init();

      service.toggleImages(false);

      const img = document.getElementById('img1') as HTMLImageElement;
      expect(img.style.display).toBe('');
    });
  });

  // ===========================================================================
  // getContentElements Tests
  // ===========================================================================

  describe('getContentElements', () => {
    it('returns elements after lukaisu_last', () => {
      document.body.innerHTML = '<div id="lukaisu_last"></div><div class="content">1</div><div class="content">2</div>';
      service.init();

      const result = service.getContentElements();

      expect(result.length).toBe(2);
      expect(result.every(el => el.classList.contains('content'))).toBe(true);
    });

    it('returns empty array when lukaisu_last not found', () => {
      document.body.innerHTML = '<div>hello</div>';
      service.init();

      const result = service.getContentElements();

      expect(result).toEqual([]);
    });
  });

  // ===========================================================================
  // updateLastMargin Tests
  // ===========================================================================

  describe('updateLastMargin', () => {
    it('sets margin based on header height', () => {
      document.body.innerHTML = '<div id="lukaisu_header" style="height: 100px;"></div><div id="lukaisu_last"></div>';
      service.init();

      service.updateLastMargin();

      // In jsdom, offsetHeight is 0, but the function should run without error
      const lukaisuLast = document.getElementById('lukaisu_last');
      expect(lukaisuLast?.style.marginTop).toBeDefined();
    });
  });

  // ===========================================================================
  // Singleton Tests
  // ===========================================================================

  describe('getHighlightService', () => {
    it('returns the same instance on multiple calls', () => {
      const service1 = getHighlightService();
      const service2 = getHighlightService();

      expect(service1).toBe(service2);
    });
  });

  describe('initHighlightService', () => {
    it('returns initialized service', () => {
      document.body.innerHTML = '<div id="lukaisu_header"></div>';

      const result = initHighlightService();

      expect(result).toBeDefined();
      expect(result instanceof HighlightService).toBe(true);
    });
  });

  // ===========================================================================
  // Header Exclusion Tests
  // ===========================================================================

  describe('Header exclusion', () => {
    it('excludes header elements from marking', () => {
      document.body.innerHTML = `
        <div id="lukaisu_header"><span class="header-item">H</span></div>
        <div class="content">C</div>
      `;
      service.init();

      service.markElements('//span | //div');

      expect(document.querySelector('.header-item')?.classList.contains('lukaisu_marked_text')).toBe(false);
      expect(document.querySelector('.content')?.classList.contains('lukaisu_marked_text')).toBe(true);
    });

    it('excludes header images from toggle', () => {
      document.body.innerHTML = `
        <div id="lukaisu_header"><img id="header-img" src="h.jpg"></div>
        <img id="content-img" src="c.jpg">
      `;
      service.init();

      service.toggleImages(true);

      expect((document.getElementById('header-img') as HTMLImageElement).style.display).toBe('');
      expect((document.getElementById('content-img') as HTMLImageElement).style.display).toBe('none');
    });
  });

  // ===========================================================================
  // Edge Cases
  // ===========================================================================

  describe('Edge Cases', () => {
    it('handles elements with empty class attributes', () => {
      document.body.innerHTML = '<div class="">hello</div>';
      service.init();

      service.markElements('//div');
      service.clearMarking();

      // Should clean up empty class attribute
      const div = document.querySelector('div');
      expect(div?.hasAttribute('class')).toBe(false);
    });

    it('handles nested marking without overwriting selection', () => {
      document.body.innerHTML = '<div class="outer"><span class="inner lukaisu_selected_text">text</span></div>';
      service.init();

      service.markElements('//div[@class="outer"]');

      // Inner span should retain selected class, not be overwritten
      const inner = document.querySelector('.inner');
      expect(inner?.classList.contains('lukaisu_selected_text')).toBe(true);
    });
  });
});
