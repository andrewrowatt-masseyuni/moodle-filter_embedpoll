@filter @filter_embedpoll
Feature: Embed poll filter renders polls in a book
  In order to gather opinions inside book chapters
  As a teacher
  I need the embed poll filter to render and run polls embedded in book chapters

  Background:
    Given the following "courses" exist:
      | shortname | fullname |
      | C1        | Course 1 |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name      | course | section |
      | book     | Test book | C1     | 1       |
    And the "embedpoll" filter is "on"

  @javascript
  Scenario: Multiple distinct polls in one chapter are independent
    Given the following "mod_book > chapter" exist:
      | book      | title     | content                                                                              |
      | Test book | Chapter 1 | <p>Fruit {poll:"Apple","Banana"} and colour {poll:"Red","Green","Blue"}</p>          |
    When I am on the "Test book" "book activity" page logged in as "student1"
    Then "Apple" "button" should exist
    And "Red" "button" should exist
    And "span.progress-bar" "css_element" should not exist
    When I click on "Apple" "button"
    Then "//div[contains(@class,'filter-embedpoll')][.//button[contains(.,'Apple')]]//span[contains(@class,'progress-bar')]" "xpath_element" should exist
    And "//div[contains(@class,'filter-embedpoll')][.//button[contains(.,'Green')]]//span[contains(@class,'progress-bar')]" "xpath_element" should not exist

  @javascript
  Scenario: Identical polls in different chapters track votes independently
    Given the following "mod_book > chapter" exist:
      | book      | title     | content                  |
      | Test book | Chapter 1 | <p>Agree? {poll:"Yes","No"}</p> |
      | Test book | Chapter 2 | <p>Agree? {poll:"Yes","No"}</p> |
    When I am on the "Test book" "book activity" page logged in as "student1"
    And I click on "Yes" "button"
    Then "span.progress-bar" "css_element" should exist
    And I click on "Chapter 2" "link" in the "Table of contents" "block"
    Then "Yes" "button" should exist
    And "span.progress-bar" "css_element" should not exist

  Scenario: Teachers always see results without voting
    Given the following "mod_book > chapter" exist:
      | book      | title     | content                              |
      | Test book | Chapter 1 | <p>Colour {poll:"Red","Green"}</p>   |
    When I am on the "Test book" "book activity" page logged in as "teacher1"
    Then "span.progress-bar" "css_element" should exist
    And "[data-action='poll-vote'][disabled]" "css_element" should exist
    And I should see "No votes yet"
