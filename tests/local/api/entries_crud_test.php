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

namespace mod_projetvet\local\api;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/projetvet/tests/test_data_definition.php');

use advanced_testcase;
use core_user;
use mod_projetvet\local\api\entries;
use mod_projetvet\local\persistent\form_field;
use test_data_definition;

/**
 * Entries API CRUD operations test
 *
 * @package     mod_projetvet
 * @copyright   2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_projetvet\local\api\entries
 */
final class entries_crud_test extends advanced_testcase {
    use test_data_definition;

    /**
     * Setup the test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser(); // Needed for report builder to work.
        $this->prepare_scenario('set_1');
    }

    /**
     * Test get_entry
     *
     * @return void
     * @covers \mod_projetvet\local\api\entries::get_entry
     */
    public function test_get_entry(): void {
        global $DB;
        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);
        $entrylist = entries::get_entry_list($module->id, $student->id);

        $this->assertNotEmpty($entrylist['activities'], 'Entry list should not be empty');

        // Get the first entry.
        $firstentrydata = reset($entrylist['activities']);
        $entryid = $firstentrydata['id'];

        $entry = entries::get_entry($entryid);

        // Verify basic entry properties.
        $this->assertEquals($entryid, $entry->id);
        $this->assertEquals($student->id, $entry->studentid);
        $this->assertObjectHasProperty('projetvetid', $entry);

        // Verify categories structure.
        $this->assertIsArray($entry->categories);
        $this->assertNotEmpty($entry->categories);

        // Check that each category has expected properties.
        foreach ($entry->categories as $category) {
            $this->assertObjectHasProperty('name', $category);
            $this->assertObjectHasProperty('fields', $category);
            $this->assertIsArray($category->fields);

            // Check that each field has expected properties.
            foreach ($category->fields as $field) {
                $this->assertObjectHasProperty('name', $field);
                $this->assertObjectHasProperty('idnumber', $field);
                $this->assertObjectHasProperty('type', $field);
                $this->assertObjectHasProperty('value', $field);
            }
        }

        // Verify the entry has fields (even if pre-populated data is not present due to test setup timing).
        // The test_create_entry test will verify that field data can be properly saved and retrieved.
        $hasfields = false;
        foreach ($entry->categories as $category) {
            if (!empty($category->fields)) {
                $hasfields = true;
                break;
            }
        }
        $this->assertTrue($hasfields, 'Entry should have fields');
    }

    /**
     * Test create_entry
     *
     * @return void
     * @covers \mod_projetvet\local\api\entries::create_entry
     */
    public function test_create_entry(): void {
        global $DB;

        $student = core_user::get_user_by_username('student2');

        // Get module instance.
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        // Get field IDs.
        $titlefield = form_field::get_record(['idnumber' => 'activity_title']);
        $datefield = form_field::get_record(['idnumber' => 'activity_date']);
        $descfield = form_field::get_record(['idnumber' => 'description']);
        $hoursfield = form_field::get_record(['idnumber' => 'hours_spent']);

        $newdata = [
            $titlefield->get('id') => 'New Test Activity',
            $datefield->get('id') => strtotime('2025-03-15'),
            $descfield->get('id') => 'This is a new test activity',
            $hoursfield->get('id') => 10,
        ];

        // Create the entry as the student (who has submit capability).
        $this->setUser($student);
        $entryid = entries::create_entry($module->id, $student->id, $newdata);

        $this->assertGreaterThan(0, $entryid, 'Entry ID should be greater than 0');

        // Verify the entry was created correctly.
        $entry = entries::get_entry($entryid);

        $this->assertEquals($student->id, $entry->studentid);
        $this->assertEquals('New Test Activity', $this->get_field_value($entry, 'activity_title'));
        $this->assertEquals(strtotime('2025-03-15'), $this->get_field_value($entry, 'activity_date'));
        $this->assertEquals('This is a new test activity', $this->get_field_value($entry, 'description'));
        $this->assertEquals(10, $this->get_field_value($entry, 'hours_spent'));
    }

    /**
     * Test update_entry
     *
     * @return void
     * @covers \mod_projetvet\local\api\entries::update_entry
     */
    public function test_update_entry(): void {
        global $DB;
        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);
        $entrylist = entries::get_entry_list($module->id, $student->id);

        $firstentrydata = reset($entrylist['activities']);
        $entryid = $firstentrydata['id'];

        // Get field IDs.
        $titlefield = form_field::get_record(['idnumber' => 'activity_title']);
        $hoursfield = form_field::get_record(['idnumber' => 'hours_spent']);

        $updatedata = [
            $titlefield->get('id') => 'Updated Activity Title',
            $hoursfield->get('id') => 15,
        ];

        // Update the entry as the student (who has submit capability).
        $this->setUser($student);
        entries::update_entry($entryid, $updatedata);

        // Verify the entry was updated.
        $entry = entries::get_entry($entryid);

        $this->assertEquals('Updated Activity Title', $this->get_field_value($entry, 'activity_title'));
        $this->assertEquals(15, $this->get_field_value($entry, 'hours_spent'));

        // Verify unchanged fields remain the same.
        $this->assertEquals(strtotime('2025-01-15'), $this->get_field_value($entry, 'activity_date'));
    }

    /**
     * Test delete_entry
     *
     * @return void
     * @covers \mod_projetvet\local\api\entries::delete_entry
     */
    public function test_delete_entry(): void {
        global $DB;
        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);
        $entrylist = entries::get_entry_list($module->id, $student->id);

        $firstentrydata = reset($entrylist['activities']);
        $entryid = $firstentrydata['id'];

        // Count entries before deletion.
        $countbefore = count($entrylist['activities']);

        // Delete the entry as the student.
        $this->setUser($student);
        $result = entries::delete_entry($entryid);

        $this->assertTrue($result, 'Entry deletion should return true');

        // Verify entry is deleted.
        $this->expectException(\moodle_exception::class);
        entries::get_entry($entryid);
    }

    /**
     * Test get_entries
     *
     * @return void
     * @covers \mod_projetvet\local\api\entries::get_entries
     */
    public function test_get_entries(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        $entriesobj = entries::get_entries($module->id, $student->id, 'activities');

        // Verify structure.
        $this->assertObjectHasProperty('activities', $entriesobj);
        $this->assertObjectHasProperty('structure', $entriesobj);
        $this->assertIsArray($entriesobj->activities);
        $this->assertNotEmpty($entriesobj->activities);

        // Student1 should have 2 entries.
        $this->assertCount(2, $entriesobj->activities);

        // Verify each entry has proper structure.
        foreach ($entriesobj->activities as $entry) {
            $this->assertEquals($student->id, $entry->studentid);
            $this->assertEquals($module->id, $entry->projetvetid);
            $this->assertIsArray($entry->categories);
        }
    }

    /**
     * Test get_entry_list
     *
     * @return void
     * @covers \mod_projetvet\local\api\entries::get_entry_list
     */
    public function test_get_entry_list(): void {
        global $DB;
        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        $entrylist = entries::get_entry_list($module->id, $student->id);

        $this->assertIsArray($entrylist);
        $this->assertArrayHasKey('activities', $entrylist);
        $this->assertArrayHasKey('listfields', $entrylist);
        $this->assertNotEmpty($entrylist['activities']);

        // Verify list structure.
        foreach ($entrylist['activities'] as $listitem) {
            $this->assertArrayHasKey('id', $listitem);
            $this->assertArrayHasKey('fields', $listitem);
            $this->assertArrayHasKey('entrystatus', $listitem);
            $this->assertArrayHasKey('statustext', $listitem);
        }

        // Verify we have 2 activities for student1.
        $this->assertCount(2, $entrylist['activities']);

        // Verify each activity has fields.
        foreach ($entrylist['activities'] as $activity) {
            $this->assertIsArray($activity['fields']);
            $this->assertNotEmpty($activity['fields']);
        }
    }

    /**
     * Test get_form_structure
     *
     * @return void
     * @covers \mod_projetvet\local\api\entries::get_form_structure
     */
    public function test_get_form_structure(): void {
        $structure = entries::get_form_structure('activities');

        $this->assertIsArray($structure);
        $this->assertNotEmpty($structure);

        // Verify structure contains expected categories.
        $categorynames = array_map(function ($cat) {
            return $cat->name;
        }, $structure);

        $this->assertContains('General Information', $categorynames);
        $this->assertContains('Activity Details', $categorynames);

        // Verify each category has fields.
        foreach ($structure as $category) {
            $this->assertObjectHasProperty('name', $category);
            $this->assertObjectHasProperty('fields', $category);
            $this->assertIsArray($category->fields);

            foreach ($category->fields as $field) {
                $this->assertObjectHasProperty('id', $field);
                $this->assertObjectHasProperty('idnumber', $field);
                $this->assertObjectHasProperty('name', $field);
                $this->assertObjectHasProperty('type', $field);
            }
        }
    }

    /**
     * Helper method to get field value from entry
     *
     * @param object $entry
     * @param string $fieldidnumber
     * @return mixed
     */
    private function get_field_value(object $entry, string $fieldidnumber) {
        foreach ($entry->categories as $category) {
            foreach ($category->fields as $field) {
                if ($field->idnumber === $fieldidnumber) {
                    return $field->value;
                }
            }
        }
        return null;
    }
}
