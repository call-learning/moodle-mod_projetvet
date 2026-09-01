@mod @mod_projetvet @javascript
Feature: Practical info (tutor profile preference + per-student note)

  In order to know my tutor's practical information and to record a per-student note
  As a tutor and a teacher
  I need the practical info to be stored in my user preferences and shown on the student sheet

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity  | course | name        | intro                   | idnumber   |
      | projetvet | C1     | ProjetVet 1 | Test projetvet activity | projetvet1 |
    And the following "mod_projetvet > projetvet groups" exist:
      | name           | teacher  | rating  | projetvetidnumber | course |
      | Teacher1 Group | teacher1 | average | projetvet1        | C1     |
    And the following "mod_projetvet > projetvet group members" exist:
      | user     | group          |
      | student1 | Teacher1 Group |

  # --- 6.1 Preferences navigation node ---

  Scenario: A tutor sees the practical info setting in the user preferences navigation
    Given I log in as "teacher1"
    When I follow "Preferences" in the user menu
    Then I should see "Projetvet"
    And "Practical information for my tutored students" "link" should exist

  Scenario: A non-tutor does not see the practical info setting in the navigation
    Given I log in as "student1"
    When I follow "Preferences" in the user menu
    Then "Practical information for my tutored students" "link" should not exist

  # --- 6.2 Tutor info preferences page ---

  Scenario: A tutor can save and read back the practical info
    Given I log in as "teacher1"
    When I follow "Preferences" in the user menu
    And I follow "Practical information for my tutored students"
    Then I should see "Practical information for my tutored students"
    When I set the field "Practical information for my tutored students" to "Paris, 8am sharp"
    And I press "Save"
    Then I should see "Your tutor information has been saved."
    And the field "Practical information for my tutored students" should contain "Paris, 8am sharp"

  Scenario: A non-tutor is denied access to the practical info page
    Given I log in as "student1"
    When I am on the tutor practical info page
    Then I should see "Access is not allowed"

  # --- 6.3 Student sheet: tutor info row + button ---

  Scenario: A tutored student sees the tutor's practical info on the student sheet
    Given I log in as "teacher1"
    When I follow "Preferences" in the user menu
    And I follow "Practical information for my tutored students"
    And I set the field "Practical information for my tutored students" to "Paris, 8am sharp"
    And I press "Save"
    And I log in as "student1"
    Then I open the student info sheet for "student1" in "ProjetVet 1"
    Then I should see "Practical information for my tutored students"
    And I should see "Paris, 8am sharp"
    And a highlighted info row should exist

  Scenario: A teacher does not see the tutor's practical info on the student sheet
    Given I log in as "teacher1"
    When I open the student info sheet for "student1" in "ProjetVet 1"
    Then I should not see "Practical information for my tutored students"
