@filter @filter_embedpoll
Feature: Basic tests for Embed poll

  @javascript
  Scenario: Plugin filter_embedpoll appears in the list of installed additional plugins
    Given I log in as "admin"
    When I navigate to "Plugins > Plugins overview" in site administration
    And I follow "Additional plugins"
    Then I should see "Embed poll"
    And I should see "filter_embedpoll"
