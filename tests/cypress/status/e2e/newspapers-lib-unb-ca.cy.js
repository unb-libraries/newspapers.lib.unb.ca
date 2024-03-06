const host = 'https://newspapers.lib.unb.ca'
describe('New Brunswick Historical Newspapers Project', {baseUrl: host, groups: ['sites']}, () => {

  context('Front page', {baseUrl: host}, () => {
    beforeEach(() => {
      cy.visit('/')
      cy.title()
        .should('contains', 'New Brunswick Historical Newspapers')
    })

    specify('Title search for "daily" finds 25+ results', () => {
      cy.get('#edit-input-title')
        .type('daily')
      cy.get('#edit-submit-title')
        .click()
      cy.url()
        .should('match', /\/search\?query=/)
      cy.get('h1')
        .should('contain', 'Newspaper Title Search')
      cy.get('div.search-results h2')
        .should('contain', 'Displaying 1 - 25 of')
      cy.get('div.search-results table tbody tr')
        .should('have.lengthOf', 25)
    });

    specify('Full text search for "cassidy" should find 10+ results', () => {
      cy.get('.nav-item a#tab-fulltext')
        .click()
      cy.get('#edit-input-fulltext')
        .type('cassidy')
      cy.get('#edit-submit-fulltext')
        .click()
      cy.url()
        .should('match', /\/page-search\?fulltext=/)
      cy.get('.view-header h2')
        .should('contain', 'Displaying 1 -')
      cy.get('.search-results .views-row')
        .should('have.lengthOf.at.least', 10)
    });

  });

})
