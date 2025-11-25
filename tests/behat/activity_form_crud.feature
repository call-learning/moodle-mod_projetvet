@mod @mod_projetvet @javascript
Feature: Activity form CRUD operations in mod_projetvet

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
      | student2 | Student   | Two      | student2@example.com |
      | student3 | Student   | Three    | student3@example.com |
      | student4 | Student   | Four     | student4@example.com |
      | teacher2 | Teacher   | Two      | teacher2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student4 | C1     | student        |
    And the following "groups" exist:
      | name | description | course | idnumber |
      | Group 1 | G1 description | C1 | G1 |
      | Group 2 | G1 description | C1 | G2 |
    And the following "group members" exist:
      | user | group |
      | student1 | G1 |
      | student2 | G1 |
      | student2 | G2 |
      | student3 | G2 |
      | teacher1 | G1 |
      | student4 | G2 |
      | teacher2 | G2 |
    And the following "activities" exist:
      | activity  | name            | course | idnumber    |
      | projetvet | My Activities   | C1     | projetvet1  |

  Scenario: Student creates a new activity with all fields
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Activity" "button"
    And I wait until the page is ready
    Then I should see "General information"

    # Fill in general information category (entrystatus 0)
    When I set the following fields to these values:
      | Activity title                                                      | My First Activity       |
      | Summary description                                                 | This is a test activity |
      | Year of study in which the project will be completed                | Year 2                  |
      | Rank                                                                | A                       |
      | Expected workload (approximately)                                   | 40                      |
      | Number of credits suggested after discussion with tutor             | 3                       |

    # Select category using tagselect
    And I open tagselect for "Category"
    And I select tag "Stage en clinique vétérinaire canine" in tagselect popup
    And I click on "save-tags" buttonaction in the "tagselect-popup" "region"

    # Submit to tutor (changes status to 1)
    And I click on "Save and submit to tutor" "button"
    And I wait until the page is ready

    Then I should see "My First Activity"

  Scenario: Student creates, saves as draft, then edits and submits
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Activity" "button"

    # Create initial draft
    When I set the following fields to these values:
      | Activity title                                                      | Draft Activity          |
      | Summary description                                                 | Initial draft content   |
      | Expected workload (approximately)                                   | 20                      |
      | Number of credits suggested after discussion with tutor             | 2                       |

    # Select category using tagselect
    And I open tagselect for "Category"
    And I select tag "Stage en clinique vétérinaire canine" in tagselect popup
    And I click on "save-tags" buttonaction in the "tagselect-popup" "region"

    And I click on "Save as draft" "button"
    And I wait until the page is ready

    Then I should see "Draft Activity"

    # Edit the draft
    When I click on "Draft Activity" "link"
    And I set the following fields to these values:
      | Activity title                                                      | Updated Activity        |
      | Summary description                                                 | Updated content         |

    And I click on "Save and submit to tutor" "button"
    And I wait until the page is ready

    Then I should see "Updated Activity"
    And I should see "Teacher acceptance"

  Scenario: Teacher accepts student activity
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Activity" "button"
    And I set the following fields to these values:
      | Activity title                                                      | Activity for Approval   |
      | Summary description                                                 | Please review           |
      | Expected workload (approximately)                                   | 30                      |
      | Number of credits suggested after discussion with tutor             | 2                       |

    # Select category using tagselect
    And I open tagselect for "Category"
    And I select tag "Stage en clinique vétérinaire canine" in tagselect popup
    And I click on "save-tags" buttonaction in the "tagselect-popup" "region"

    And I click on "Save and submit to tutor" "button"
    And I wait until the page is ready
    And I log out

    # Teacher reviews and accepts
    When I am on the "My Activities" "projetvet activity" page logged in as "teacher1"
    And I view activities for student "Student One"
    And I click on "Activity for Approval" "link"
    And I wait "1" seconds

    # Add acceptance comments (entrystatus 1)
    And I set the following fields to these values:
      | Comments | Good work, approved |

    And I click on form button "Accept"
    And I wait until the page is ready

    # Verify we're back at the entry list and status is updated
    Then I should see "Activity for Approval"
    And I should see "Student additions"

  Scenario: Student completes activity report after acceptance
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Activity" "button"
    And I set the following fields to these values:
      | Activity title                                                      | Almost there         |
      | Summary description                                                 | To be completed         |
      | Expected workload (approximately)                                   | 25                      |
      | Number of credits suggested after discussion with tutor             | 2                       |

    # Select category using tagselect
    And I open tagselect for "Category"
    And I select tag "Stage en clinique vétérinaire canine" in tagselect popup
    And I click on "save-tags" buttonaction in the "tagselect-popup" "region"

    And I click on "Save and submit to tutor" "button"
    And I wait until the page is ready
    And I log out

    # Teacher reviews and accepts
    When I am on the "My Activities" "projetvet activity" page logged in as "teacher1"
    And I view activities for student "Student One"
    And I click on "Almost there" "link"
    And I wait "1" seconds

    # Add acceptance comments (entrystatus 1)
    And I set the following fields to these values:
      | Comments | Good work, approved |

    And I click on form button "Accept"
    And I wait until the page is ready

    # Verify we're back at the entry list and status is updated
    Then I should see "Almost there"
    And I should see "Student additions"

    # Student adds completion report (entrystatus 2)
    When I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "Almost there" "link"
    And I set the following fields to these values:
      | Start date of completion                                            | ##1 January 2025##      |
      | End date of completion                                              | ##31 March 2025##       |
      | Summary of main achievements and actions carried out during this project | I completed all tasks   |
      | Number of hours completed (modify if necessary)                     | 25                      |
      | Final number of ECTS                                                | 2                       |

    And I click on "Save and submit to tutor" "button"
    And I wait until the page is ready

    Then I should see "Almost there"
    And I should see "Teacher final acceptance"

  Scenario: Teacher provides final validation
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Activity" "button"
    And I set the following fields to these values:
      | Activity title                                                      | Final Validation Test   |
      | Summary description                                                 | Ready for validation    |
      | Expected workload (approximately)                                   | 30                      |
      | Number of credits suggested after discussion with tutor             | 3                       |

    # Select category using tagselect
    And I open tagselect for "Category"
    And I select tag "Stage en clinique vétérinaire canine" in tagselect popup
    And I click on "save-tags" buttonaction in the "tagselect-popup" "region"

    And I click on "Save and submit to tutor" "button"
    And I wait until the page is ready
    And I log out

    # Teacher reviews and accepts (entrystatus 1)
    When I am on the "My Activities" "projetvet activity" page logged in as "teacher1"
    And I view activities for student "Student One"
    And I click on "Final Validation Test" "link"
    And I wait "1" seconds

    And I set the following fields to these values:
      | Comments | Good work, approved |

    And I click on form button "Accept"
    And I wait until the page is ready
    And I log out

    # Student adds completion report (entrystatus 2)
    When I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "Final Validation Test" "link"
    And I set the following fields to these values:
      | Start date of completion                                            | ##1 February 2025##     |
      | End date of completion                                              | ##30 April 2025##       |
      | Summary of main achievements and actions carried out during this project | All objectives achieved |
      | Number of hours completed (modify if necessary)                     | 30                      |
      | Final number of ECTS                                                | 3                       |

    And I click on "Save and submit to tutor" "button"
    And I wait until the page is ready
    And I log out

    # Teacher provides final validation (entrystatus 3)
    When I am on the "My Activities" "projetvet activity" page logged in as "teacher1"
    And I view activities for student "Student One"
    And I click on "Final Validation Test" "link"
    And I wait "1" seconds

    And I set the following fields to these values:
      | Final comments    | Excellent work completed |
      | Final assessment  | Excellent                |

    And I click on form button "Validate definitively"
    And I wait until the page is ready

    Then I should see "Final Validation Test"
    And I should see "Validated"

  Scenario: Delete activity entry
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Activity" "button"
    And I set the following fields to these values:
      | Activity title                                                      | Activity to Delete      |
      | Summary description                                                 | Will be deleted         |
      | Expected workload (approximately)                                   | 10                      |
      | Number of credits suggested after discussion with tutor             | 1                       |

    # Select category using tagselect
    And I open tagselect for "Category"
    And I select tag "Stage en clinique vétérinaire canine" in tagselect popup
    And I click on "save-tags" buttonaction in the "tagselect-popup" "region"

    And I click on "Save as draft" "button"
    And I wait until the page is ready

    Then I should see "Activity to Delete"

    # First click on the row containing "Activity to Delete" to find the dropdown, then delete
    When I click on "Actions" "button" in the "Activity to Delete" "table_row"
    And I click on "Delete" "button"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I wait until the page is ready

    Then I should not see "Activity to Delete"
