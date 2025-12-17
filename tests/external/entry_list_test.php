<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_projetvet\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/projetvet/tests/test_data_definition.php');

use advanced_testcase;
use core_user;
use test_data_definition;

/**
 * Entry list external API test
 *
 * @package     mod_projetvet
 * @copyright   2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_projetvet\external\entry_list
 */
final class entry_list_test extends advanced_testcase {
    use test_data_definition;

    /**
     * Setup test data
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prepare_scenario('set_1');
    }

    /**
     * Test successful retrieval of entry list
     *
     * @return void
     */
    public function test_get_entry_list_success(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        $this->setUser($student);

        $result = entry_list::execute($module->id, $student->id, 'activities');

        // Verify return structure.
        $this->assertIsArray($result, 'Result should be an array');
        $this->assertArrayHasKey('activities', $result);
        $this->assertArrayHasKey('listfields', $result);

        // Verify activities structure.
        $this->assertIsArray($result['activities']);
        if (!empty($result['activities'])) {
            $activity = reset($result['activities']);
            $this->assertArrayHasKey('id', $activity);
            $this->assertArrayHasKey('entrystatus', $activity);
            $this->assertArrayHasKey('canedit', $activity);
            $this->assertArrayHasKey('candelete', $activity);
            $this->assertArrayHasKey('fields', $activity);
            $this->assertIsArray($activity['fields']);

            // Check field structure within activity.
            if (!empty($activity['fields'])) {
                $field = reset($activity['fields']);
                $this->assertArrayHasKey('idnumber', $field);
                $this->assertArrayHasKey('name', $field);
                $this->assertArrayHasKey('value', $field);
                $this->assertArrayHasKey('displayvalue', $field);
            }
        }

        // Verify listfields structure.
        $this->assertIsArray($result['listfields']);
        if (!empty($result['listfields'])) {
            $field = reset($result['listfields']);
            $this->assertIsObject($field);
            $this->assertObjectHasProperty('id', $field);
            $this->assertObjectHasProperty('idnumber', $field);
            $this->assertObjectHasProperty('name', $field);
            $this->assertObjectHasProperty('type', $field);
            $this->assertObjectHasProperty('listorder', $field);
        }
    }

    /**
     * Test entry list filters by student
     *
     * @return void
     */
    public function test_get_entry_list_filters_by_student(): void {
        global $DB;

        $student1 = core_user::get_user_by_username('student1');
        $student2 = core_user::get_user_by_username('student2');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        // Get entries for student1.
        $result1 = entry_list::execute($module->id, $student1->id, 'activities');

        // Get entries for student2.
        $result2 = entry_list::execute($module->id, $student2->id, 'activities');

        // Verify both have activities (based on test data).
        $this->assertNotEmpty($result1['activities'], 'Student1 should have entries');
        $this->assertNotEmpty($result2['activities'], 'Student2 should have entries');

        // Verify counts are different (student1 has 2 entries, student2 has 1).
        $this->assertCount(2, $result1['activities'], 'Student1 should have 2 entries');
        $this->assertCount(1, $result2['activities'], 'Student2 should have 1 entry');
    }

    /**
     * Test entry list validates context
     *
     * @return void
     */
    public function test_get_entry_list_validates_context(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        // Create a user with no access to the course.
        $nonaccessuser = $this->getDataGenerator()->create_user();
        $this->setUser($nonaccessuser);

        $this->expectException(\moodle_exception::class);
        entry_list::execute($module->id, $student->id, 'activities');
    }

    /**
     * Test entry list with default parent entry ID
     *
     * @return void
     */
    public function test_get_entry_list_default_parententryid(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        $this->setUser($student);

        // Call without parent entry ID (should default to 0).
        $result = entry_list::execute($module->id, $student->id, 'activities');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('activities', $result);
    }

    /**
     * Test entry list with specific parent entry ID
     *
     * @return void
     */
    public function test_get_entry_list_with_parententryid(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        $this->setUser($student);

        // Call with specific parent entry ID.
        $result = entry_list::execute($module->id, $student->id, 'activities', 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('activities', $result);
    }

    /**
     * Test entry list includes permission flags
     *
     * @return void
     */
    public function test_get_entry_list_includes_permissions(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        $this->setUser($student);

        $result = entry_list::execute($module->id, $student->id, 'activities');

        $this->assertNotEmpty($result['activities']);

        foreach ($result['activities'] as $activity) {
            $this->assertArrayHasKey('canedit', $activity);
            $this->assertArrayHasKey('candelete', $activity);
            $this->assertIsBool($activity['canedit']);
            $this->assertIsBool($activity['candelete']);
        }
    }

    /**
     * Test entry list field values are properly formatted
     *
     * @return void
     */
    public function test_get_entry_list_field_formatting(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        $this->setUser($student);

        $result = entry_list::execute($module->id, $student->id, 'activities');

        $this->assertNotEmpty($result['activities']);

        // Check field structure in activities.
        foreach ($result['activities'] as $activity) {
            $this->assertIsArray($activity['fields']);

            foreach ($activity['fields'] as $field) {
                $this->assertArrayHasKey('idnumber', $field);
                $this->assertArrayHasKey('name', $field);
                $this->assertArrayHasKey('value', $field);
                $this->assertArrayHasKey('displayvalue', $field);
            }
        }
    }

    /**
     * Test entry list returns empty for student with no entries
     *
     * @return void
     */
    public function test_get_entry_list_empty_for_no_entries(): void {
        global $DB;

        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        // Create a new student with no entries.
        $newstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($newstudent);

        $result = entry_list::execute($module->id, $newstudent->id, 'activities');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('activities', $result);
        $this->assertEmpty($result['activities'], 'Should return empty array for student with no entries');
        $this->assertArrayHasKey('listfields', $result);
    }

    /**
     * Test entry list with different formset
     *
     * @return void
     */
    public function test_get_entry_list_different_formset(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        $this->setUser($student);

        // Request with 'activities' formset.
        $result = entry_list::execute($module->id, $student->id, 'activities');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('activities', $result);

        // The actual entries returned depend on the formset.
        // For 'activities' we expect entries based on test data.
    }

    /**
     * Test teacher can view student entries
     *
     * @return void
     */
    public function test_teacher_can_view_student_entries(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $teacher = core_user::get_user_by_username('teacher1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        // Set as teacher.
        $this->setUser($teacher);

        // Teacher should be able to view student's entries.
        $result = entry_list::execute($module->id, $student->id, 'activities');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('activities', $result);
    }
}
