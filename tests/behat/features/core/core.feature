# Basic functionality testing.
# Ref : phase2/behat-suite
# http://behat-drupal-extension.readthedocs.io/en/3.1/drupalapi.htm

@api
Feature: Core
  In order to know the website is running
  As a website user
  I need to be able to view the site title and login

  @api
    Scenario: Create users
      Given users:
      | name     | mail            | status |
      | Joe User | joe@example.com | 1      |
      And I am logged in as a user with the "administrator" role
      When I visit "admin/people"
      Then I should see the link "Joe User"
