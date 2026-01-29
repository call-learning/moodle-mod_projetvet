@mod @mod_projetvet
Feature: Admin interface for managing groups
  In order to manage student groups and teacher assignments
  As an editing teacher
  I need to be able to access the admin interface

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname | email                 |
      | teacher1  | Teacher   | One      | teacher1@example.com  |
      | teacher2  | Teacher   | Two      | teacher2@example.com  |
      | teacher3  | Teacher   | Three    | teacher3@example.com  |
      | student1  | Student   | One      | student1@example.com  |
      | student2  | Student   | Two      | student2@example.com  |
      | student3  | Student   | Three    | student3@example.com  |
      | student4  | Student   | Four     | student4@example.com  |
      | student5  | Student   | Five     | student5@example.com  |
      | student6  | Student   | Six      | student6@example.com  |
      | student7  | Student   | Seven    | student7@example.com  |
      | student8  | Student   | Eight    | student8@example.com  |
      | student9  | Student   | Nine     | student9@example.com  |
      | student10 | Student   | Ten      | student10@example.com |
      | student11 | Student   | Eleven   | student11@example.com |
      | student12 | Student   | Twelve   | student12@example.com |
      | student13 | Student   | Thirteen | student13@example.com |
      | student14 | Student   | Fourteen | student14@example.com |
      | student15 | Student   | Fifteen  | student15@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | teacher1  | C1     | editingteacher |
      | teacher2  | C1     | editingteacher |
      | teacher3  | C1     | editingteacher |
      | student1  | C1     | student        |
      | student2  | C1     | student        |
      | student3  | C1     | student        |
      | student4  | C1     | student        |
      | student5  | C1     | student        |
      | student6  | C1     | student        |
      | student7  | C1     | student        |
      | student8  | C1     | student        |
      | student9  | C1     | student        |
      | student10 | C1     | student        |
      | student11 | C1     | student        |
      | student12 | C1     | student        |
      | student13 | C1     | student        |
      | student14 | C1     | student        |
      | student15 | C1     | student        |
    And the following "activities" exist:
      | activity  | course | name        | intro                   | idnumber   |
      | projetvet | C1     | ProjetVet 1 | Test projetvet activity | projetvet1 |
    And the following "mod_projetvet > projetvet groups" exist:
      | name           | teacher  | rating  | projetvetidnumber | course |
      | Teacher1 Group | teacher1 | average | projetvet1        | C1     |
      | Teacher2 Group | teacher2 | expert  | projetvet1        | C1     |
    And the following "mod_projetvet > projetvet group members" exist:
      | user      | group          |
      | student1  | Teacher1 Group |
      | student2  | Teacher1 Group |
      | student3  | Teacher1 Group |
      | student4  | Teacher1 Group |
      | student5  | Teacher1 Group |
      | student6  | Teacher2 Group |
      | student7  | Teacher2 Group |
      | student8  | Teacher2 Group |
      | student9  | Teacher2 Group |
      | student10 | Teacher2 Group |

  Scenario: Navigate to admin page
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as admin
    When I am on the "ProjetVet 1" "mod_projetvet > Admin" page
    Then I should see "Show only students without teachers"
