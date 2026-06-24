/// <reference types="cypress" />

/**
 * The F-Droid milestone, proven end-to-end against the REAL bundled app
 * (`dist-app/`, served statically with no PHP) and with every `/api/v1` request
 * destroyed at the network layer. If any step depended on a server it would
 * fail; passing proves the read/learn path is fully on-device.
 *
 * Covered (the bundled, reachable surface): serverless boot + seed → library
 * from IndexedDB → reader with word-status highlighting → save a word offline →
 * review surface boots. NOT covered here (not bundled — see the report): the
 * create-language and import-text UI, which `bundledPageFor()` routes to a
 * remote server and which therefore dead-link in pure-offline mode.
 */

const API = '**/api/v1/**';

describe('offline milestone — bundled app, no server', () => {
  let apiAttempts = 0;
  let attemptedUrls: string[] = [];

  beforeEach(() => {
    apiAttempts = 0;
    attemptedUrls = [];
    // Poison every server call: prove the flow needs none of them.
    cy.intercept(API, (req) => {
      apiAttempts += 1;
      attemptedUrls.push(`${req.method} ${new URL(req.url).pathname}`);
      req.destroy();
    }).as('api');
  });

  it('boots serverless, seeds, reads with highlighting, saves a word, reviews', () => {
    // 1. First launch, no server configured -> on-device library (seeds starter
    //    content on the way).
    cy.clearLocalStorage();
    cy.visit('/index.html');
    cy.location('pathname', { timeout: 20000 }).should('include', 'library.html');

    // 2. The library lists seeded texts entirely from IndexedDB.
    cy.get('a[href*="/read"]', { timeout: 20000 }).its('length').should('be.greaterThan', 0);

    // 3. Open a text -> the reader parses + renders tokens with status
    //    highlighting (unknown words carry .status0; CSS is inlined in the page).
    cy.get('a[href*="/read"]').first().click();
    cy.location('pathname').should('include', 'read.html');
    cy.get('.word', { timeout: 20000 }).its('length').should('be.greaterThan', 0);
    cy.get('.word.status0').its('length').should('be.greaterThan', 0);
    cy.get('.word').its('length').then((n) => cy.log(`reader rendered ${n} word tokens`));
    cy.screenshot('02-reader-highlighted', { capture: 'viewport' });

    // 4. Save a word with no server: click an unknown word, mark it Known via the
    //    popover; the highlight flips to well-known across its occurrences.
    cy.get('.word.status0').first().click();
    cy.get('.word-popover', { timeout: 10000 }).should('be.visible');
    cy.get('.word-popover').find('button.is-success').filter(':visible').first().click();
    cy.get('.word.status99', { timeout: 10000 }).its('length').should('be.greaterThan', 0);
    cy.get('.word.status99').its('length').then((n) => cy.log(`${n} occurrences now well-known`));

    // 5. The review surface boots and loads its config locally (no server).
    cy.visit('/review.html');
    cy.get('[x-data], #review-app, section', { timeout: 20000 }).should('exist');
    cy.get('#review-config', { timeout: 20000 }).should('exist');
    cy.screenshot('03-review-surface', { capture: 'viewport' });

    // 6. The whole flow ran without reaching for a server at all: every bundled
    //    surface (incl. navbar streak + reader audio/book-context chrome) is
    //    served on-device. Asserted to 0 so any new server dependency regresses
    //    here instead of silently degrading the offline experience.
    cy.then(() => {
      cy.log(`/api/v1 calls attempted during the flow: ${apiAttempts}`);
      const summary = [`attempts: ${apiAttempts}`, ...attemptedUrls].join('\n');
      cy.writeFile('cypress/app-e2e/.last-run-api-attempts.txt', summary);
      expect(apiAttempts, 'no /api/v1 calls — the bundled app is fully on-device').to.equal(0);
    });
  });

  it('creates a language and pastes a text with no server', () => {
    cy.clearLocalStorage();
    // Unique name so the spec is re-runnable against a persisted IndexedDB.
    const langName = `E2E ${Date.now()}`;

    // Add a language from a preset (server form does a native POST; this one
    // drives the API client into IndexedDB). English so the Latin sample below
    // parses into words; rename it uniquely so the spec re-runs cleanly.
    cy.visit('/language.html');
    cy.get('#nl-preset', { timeout: 20000 }).should('exist').select('English');
    cy.get('#nl-name').clear().type(langName);
    cy.get('#nl-submit').click();
    cy.location('pathname', { timeout: 20000 }).should('include', 'library.html');

    // Paste a text in that language.
    cy.visit('/text.html');
    cy.get('#nt-lang', { timeout: 20000 }).find('option').should('have.length.greaterThan', 0);
    cy.get('#nt-lang').select(langName);
    cy.get('#nt-title').type('My pasted text');
    cy.get('#nt-text').type('The quick brown fox jumps over the lazy dog.');
    cy.get('#nt-submit').click();

    // Lands in the reader with the pasted (not seeded) text parsed + highlighted.
    cy.location('pathname', { timeout: 20000 }).should('include', 'read.html');
    cy.get('.word.status0', { timeout: 20000 }).its('length').should('be.greaterThan', 0);
    cy.contains('.word', 'quick').should('exist');
    cy.screenshot('04-pasted-text', { capture: 'viewport' });
  });
});
