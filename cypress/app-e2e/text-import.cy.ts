/// <reference types="cypress" />

/**
 * "Add a text" importers (text.html) — server-enhanced (Job B, surface 2),
 * proven against the REAL bundled app.
 *
 * Two halves of the Job-B contract:
 *  - **Offline (local-first):** the web-page / YouTube sources are gated (their
 *    buttons hidden, no fall-through), while **file/subtitle import works on the
 *    device** — a `.srt` is read and stripped to plain text with zero `/api/v1`
 *    calls.
 *  - **Connected:** the web-page and YouTube sources light up and fill the
 *    Title/Text fields from the (stubbed) server, after which the normal
 *    create path would land the text on-device.
 */

const API = '**/api/v1/**';

describe('add-text importers — bundled app, no server (file offline + gated)', () => {
  let apiAttempts = 0;

  beforeEach(() => {
    apiAttempts = 0;
    cy.intercept(API, (req) => {
      apiAttempts += 1;
      req.destroy();
    }).as('api');
  });

  it('hides the server importers and imports a subtitle file on-device', () => {
    // Boot offline -> library (seeds a starter language so the create form shows).
    cy.clearLocalStorage();
    cy.visit('/index.html');
    cy.location('pathname', { timeout: 20000 }).should('include', 'library.html');

    cy.visit('/text.html');
    cy.get('#new-text-form', { timeout: 20000 }).should('be.visible');

    // Offline: only the File source is offered; web-page / YouTube are gated.
    cy.get('#nt-import [data-import="file"]').should('be.visible');
    cy.get('#nt-import [data-import="url"]').should('not.be.visible');
    cy.get('#nt-import [data-import="youtube"]').should('not.be.visible');

    // Open the File panel and import a .srt — parsed on-device (FileReader).
    cy.get('#nt-import [data-import="file"]').click();
    const srt = [
      '1',
      '00:00:01,000 --> 00:00:04,000',
      'Hello world',
      '',
      '2',
      '00:00:05,000 --> 00:00:08,000',
      'This is a subtitle',
      '',
    ].join('\n');
    cy.get('#nt-file').selectFile(
      { contents: Cypress.Buffer.from(srt), fileName: 'sample.srt', mimeType: 'application/x-subrip' },
      { force: true }
    );

    // Timestamps/cue numbers stripped; title taken from the filename.
    cy.get('#nt-text', { timeout: 20000 }).should('contain.value', 'Hello world');
    cy.get('#nt-text').should('contain.value', 'This is a subtitle');
    cy.get('#nt-text').should('not.contain.value', '-->');
    cy.get('#nt-text').should('not.contain.value', '00:00:01');
    cy.get('#nt-title').should('have.value', 'sample');

    // On-device the whole flow fired no /api/v1 calls.
    cy.then(() => {
      expect(apiAttempts, 'file import is on-device — no /api/v1 fall-through').to.equal(0);
    });
  });
});

describe('add-text importers — bundled app, server connected (web page + YouTube)', () => {
  beforeEach(() => {
    cy.intercept('**/api/v1/**', { statusCode: 200, body: {} });
    cy.intercept('GET', '**/api/v1/i18n**', { statusCode: 200, body: {} });
    cy.intercept('GET', '**/api/v1/navbar', {
      statusCode: 200,
      body: {
        basePath: '', logoUrl: '', languages: [], currentLanguageId: 0,
        isMultiUser: false, showAdminItems: false,
        theme: { mode: 'light', counterpart: '', current: '', auto: true },
      },
    });
    cy.intercept('GET', '**/api/v1/languages', {
      statusCode: 200,
      body: { languages: [{ id: 1, name: 'Test Language' }], currentLanguageId: 1 },
    });
    cy.intercept('POST', '**/api/v1/texts/extract-url', {
      statusCode: 200,
      body: { title: 'Imported Article', text: 'The imported article body.', sourceUri: 'https://example.com/a' },
    }).as('extractUrl');
    cy.intercept('GET', '**/api/v1/youtube/video**', {
      statusCode: 200,
      body: {
        data: {
          success: true,
          data: { title: 'A YouTube Video', description: 'The video description text.', source_url: 'https://youtu.be/abc123' },
        },
      },
    }).as('ytVideo');
  });

  function visitConnected(): void {
    cy.visit('/text.html', {
      onBeforeLoad(win) {
        win.localStorage.clear();
        win.localStorage.setItem('lukaisu.apiServer', 'http://localhost:8099');
        win.localStorage.setItem('lukaisu.authOptional', '1');
      },
    });
    cy.get('#new-text-form', { timeout: 20000 }).should('be.visible');
  }

  it('imports a web page into the form via the connected server', () => {
    visitConnected();
    cy.get('#nt-import [data-import="url"]').should('be.visible').click();
    cy.get('#nt-url').type('https://example.com/article');
    cy.get('#nt-url-fetch').click();
    cy.wait('@extractUrl');
    cy.get('#nt-title').should('have.value', 'Imported Article');
    cy.get('#nt-text').should('contain.value', 'The imported article body.');
  });

  it('imports a YouTube video into the form via the connected server', () => {
    visitConnected();
    cy.get('#nt-import [data-import="youtube"]').should('be.visible').click();
    cy.get('#nt-yt').type('https://www.youtube.com/watch?v=abc123');
    cy.get('#nt-yt-fetch').click();
    cy.wait('@ytVideo');
    cy.get('#nt-title').should('have.value', 'A YouTube Video');
    cy.get('#nt-text').should('contain.value', 'The video description text.');
  });
});
