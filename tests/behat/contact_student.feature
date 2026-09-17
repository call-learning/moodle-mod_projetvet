@mod @mod_projetvet @javascript
Feature: Contact a student through the messaging system in mod_projetvet

  In the activities form (entrystatus 1, i.e. after the student submitted the
  project for eligibility validation), the tutor can contact the student. The
  contact is delivered through the standard Moodle messaging subsystem via the
  mod_projetvet_send_message webservice, never through a client-side mailto:
  link, so no student email address is exposed to the browser.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity  | name            | course | idnumber   |
      | projetvet | My Activities   | C1     | projetvet1 |
    And the following "mod_projetvet > projetvet groups" exist:
      | name    | teacher  | rating  | projetvetidnumber | course |
      | Group 1 | teacher1 | average | projetvet1        | C1     |
    And the following "mod_projetvet > projetvet group members" exist:
      | user     | group   |
      | student1 | Group 1 |

  Scenario: Tutor sees the contact confirmation for a submitted activity
    # The student creates and submits an activity, which advances the entry
    # to entrystatus 1 (awaiting eligibility validation).
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Activity" "button"
    And I set the following fields to these values:
      | Activity title                      | Activity for Contact   |
      | Summary description                 | Please contact me      |
      | Expected workload (approximately)   | 30                     |
    And I open tagselect for "Category"
    And I select tag "Stage en clinique vétérinaire canine" in tagselect popup
    And I save tags in tagselect popup
    And I open tagselect for "Competencies (2 required minimum)"
    And I select tag "COMM3 - Communiquer en contexte international ou interculturel" in tagselect popup
    And I select tag "D5- Pratiquer un examen post-mortem" in tagselect popup
    And I save tags in tagselect popup
    And I submit the projetvet form
    And I log out

    # The tutor opens the student's entry and clicks the contact action.
    When I am on the "My Activities" "projetvet activity" page logged in as "teacher1"
    And I view activities for student "Student One"
    And I click on row with text "Activity for Contact"
    And I click on form button "Contact student to discuss"

    # The confirmation dialog is displayed.
    Then I should see "Send a contact message to the student"
    And I should see "A message will be sent to the student through the standard Moodle messaging system."
