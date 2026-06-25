@filter @filter_embedpoll
Feature: Embed poll filter renders polls in a label
  In order to gather opinions inline
  As a teacher
  I need the embed poll filter to render and run polls embedded in a label

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
    And the "embedpoll" filter is "on"

  Scenario: Multiple polls in one label all render with hidden results for students
    Given the following "activities" exist:
      | activity | name | course | intro                                                                                | introformat |
      | label    | L1   | C1     | <p>Fruit {poll:"Apple","Banana"} and colour {poll:"Red","Green","Blue"}</p>          | 1           |
    When I am on the "C1" "Course" page logged in as "student1"
    Then "//div[contains(@class,'filter-embedpoll')][.//button[contains(.,'Apple')]]" "xpath_element" should exist
    And "//div[contains(@class,'filter-embedpoll')][.//button[contains(.,'Green')]]" "xpath_element" should exist
    And "Apple" "button" should exist
    And "span.progress-bar" "css_element" should not exist
    And I should see "Select an option to vote and see the results."

  @javascript
  Scenario: Two identical polls in a label track votes independently
    Given the following "activities" exist:
      | activity | name | course | intro                                                                       | introformat |
      | label    | L1   | C1     | <p>First {poll:"Yes","No"} and second {poll:"Yes","No"}</p>                 | 1           |
    When I am on the "C1" "Course" page logged in as "student1"
    And I click on "(//div[contains(@class,'filter-embedpoll')])[1]//button[@data-choice='0']" "xpath_element"
    Then "(//div[contains(@class,'filter-embedpoll')])[1]//span[contains(@class,'progress-bar')]" "xpath_element" should exist
    And "(//div[contains(@class,'filter-embedpoll')])[2]//span[contains(@class,'progress-bar')]" "xpath_element" should not exist

  @javascript
  Scenario: A student can vote and then change their vote
    Given the following "activities" exist:
      | activity | name | course | intro                                       | introformat |
      | label    | L1   | C1     | <p>Pick one {poll:"Red","Green","Blue"}</p> | 1           |
    When I am on the "C1" "Course" page logged in as "student1"
    And I click on "Green" "button"
    Then "[data-choice='1'][aria-pressed='true']" "css_element" should exist
    And "span.progress-bar" "css_element" should exist
    And I click on "Blue" "button"
    Then "[data-choice='2'][aria-pressed='true']" "css_element" should exist
    And "[data-choice='1'][aria-pressed='true']" "css_element" should not exist
