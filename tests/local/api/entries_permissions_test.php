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

use advanced_testcase;
use context_module;

/**
 * Test entries API permissions with different entry statuses and user capabilities.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_projetvet\local\api\entries
 */
final class entries_permissions_test extends advanced_testcase {
    /** @var \stdClass Course object */
    private $course;

    /** @var \stdClass ProjetVet module object */
    private $projetvet;

    /** @var \context_module Module context */
    private $context;

    /** @var \stdClass Student user with submit capability */
    private $student;

    /** @var \stdClass Teacher user with approve capability */
    private $teacher;

    /** @var \stdClass Admin user with unlock capability */
    private $admin;

    /** @var \stdClass Form set for activities */
    private $formset;

    /** @var array Form categories indexed by entrystatus */
    private $categories;

    /** @var array Form entries at different statuses */
    private $entries;

    /**
     * Setup test data.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();

        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();

        // Create course and module.
        $this->course = $generator->create_course();
        $this->projetvet = $generator->create_module('projetvet', ['course' => $this->course->id]);
        $this->context = context_module::instance($this->projetvet->cmid);

        // Create users with different capabilities.
        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->admin = $generator->create_user();

        // Assign specific capabilities for testing.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $adminrole = $DB->get_record('role', ['shortname' => 'manager']);

        // Students have submit capability.
        assign_capability('mod/projetvet:submit', CAP_ALLOW, $studentrole->id, $this->context->id);

        // Teachers have approve capability (and by extension can edit submit).
        assign_capability('mod/projetvet:approve', CAP_ALLOW, $teacherrole->id, $this->context->id);

        // Assign admin unlock capability.
        role_assign($adminrole->id, $this->admin->id, \context_system::instance());
        assign_capability('mod/projetvet:unlock', CAP_ALLOW, $adminrole->id, $this->context->id);

        // Get the activities formset.
        $this->formset = $DB->get_record('projetvet_form_set', ['idnumber' => 'activities']);
        if (!$this->formset) {
            $this->markTestSkipped('Activities formset not found. Run import_forms.php first.');
        }

        // Get categories organized by entrystatus.
        $cats = $DB->get_records('projetvet_form_cat', ['formsetid' => $this->formset->id], 'entrystatus');
        $this->categories = [];
        foreach ($cats as $cat) {
            $this->categories[$cat->entrystatus] = $cat;
        }

        // Verify we have the expected categories.
        $this->assertArrayHasKey(0, $this->categories, 'Submit category at status 0 should exist');
        $this->assertArrayHasKey(1, $this->categories, 'Approve category at status 1 should exist');
        $this->assertArrayHasKey(2, $this->categories, 'Submit category at status 2 should exist');
        $this->assertArrayHasKey(3, $this->categories, 'Approve category at status 3 should exist');
        $this->assertArrayHasKey(4, $this->categories, 'Unlock category at status 4 should exist');

        // Verify capabilities on categories.
        $this->assertEquals('submit', $this->categories[0]->capability);
        $this->assertEquals('approve', $this->categories[1]->capability);
        $this->assertEquals('submit', $this->categories[2]->capability);
        $this->assertEquals('approve', $this->categories[3]->capability);
        $this->assertEquals('unlock', $this->categories[4]->capability);

        // Create test entries at different statuses.
        $this->create_test_entries();
    }

    /**
     * Create test entries at each status level.
     */
    private function create_test_entries(): void {
        $projetvetgenerator = $this->getDataGenerator()->get_plugin_generator('mod_projetvet');

        $this->entries = [];

        // Entry at status 0 (draft - submit category).
        $this->entries[0] = $projetvetgenerator->create_form_entry([
            'projetvetid' => $this->projetvet->id,
            'formsetid' => $this->formset->id,
            'studentid' => $this->student->id,
            'entrystatus' => 0,
            'usermodified' => $this->student->id,
        ]);

        // Entry at status 1 (submitted - approve category).
        $this->entries[1] = $projetvetgenerator->create_form_entry([
            'projetvetid' => $this->projetvet->id,
            'formsetid' => $this->formset->id,
            'studentid' => $this->student->id,
            'entrystatus' => 1,
            'usermodified' => $this->student->id,
        ]);

        // Entry at status 2 (approved - report validation submit).
        $this->entries[2] = $projetvetgenerator->create_form_entry([
            'projetvetid' => $this->projetvet->id,
            'formsetid' => $this->formset->id,
            'studentid' => $this->student->id,
            'entrystatus' => 2,
            'usermodified' => $this->teacher->id,
        ]);

        // Entry at status 3 (validated - final approve).
        $this->entries[3] = $projetvetgenerator->create_form_entry([
            'projetvetid' => $this->projetvet->id,
            'formsetid' => $this->formset->id,
            'studentid' => $this->student->id,
            'entrystatus' => 3,
            'usermodified' => $this->student->id,
        ]);

        // Entry at status 4 (locked - unlock category).
        $this->entries[4] = $projetvetgenerator->create_form_entry([
            'projetvetid' => $this->projetvet->id,
            'formsetid' => $this->formset->id,
            'studentid' => $this->student->id,
            'entrystatus' => 4,
            'usermodified' => $this->teacher->id,
        ]);
    }

    /**
     * Test that structure is hydrated with correct canview permissions.
     *
     * @dataProvider canview_provider
     * @param int $entrystatus The entry status
     * @param int $userid The user ID
     * @param array $expectedcanview The expected canview values
     */
    public function test_canview_permissions(int $entrystatus, int $userid, array $expectedcanview): void {
        $this->setUser($userid);

        $structure = entries::get_form_structure('activities', $entrystatus, $this->context);

        foreach ($structure as $category) {
            $expected = $expectedcanview[$category->entrystatus] ?? false;
            $this->assertEquals(
                $expected,
                $category->canview,
                "Category {$category->id} (status {$category->entrystatus}) canview should be {$expected}"
            );
        }
    }

    /**
     * Data provider for canview tests.
     *
     * @return array
     */
    public static function canview_provider(): array {
        return [
            'Status 0 - all users see only status 0' => [
                0,
                0, // Will be replaced with actual user ID.
                [0 => true, 1 => false, 2 => false, 3 => false, 4 => false],
            ],
            'Status 1 - all users see status 0-1' => [
                1,
                0,
                [0 => true, 1 => true, 2 => false, 3 => false, 4 => false],
            ],
            'Status 2 - all users see status 0-2' => [
                2,
                0,
                [0 => true, 1 => true, 2 => true, 3 => false, 4 => false],
            ],
            'Status 3 - all users see status 0-3' => [
                3,
                0,
                [0 => true, 1 => true, 2 => true, 3 => true, 4 => false],
            ],
            'Status 4 - all users see all statuses' => [
                4,
                0,
                [0 => true, 1 => true, 2 => true, 3 => true, 4 => true],
            ],
        ];
    }

    /**
     * Test that structure is hydrated with correct canedit permissions for student.
     */
    public function test_canedit_permissions_student(): void {
        $this->setUser($this->student);

        // At status 0, student can edit submit category (status 0).
        $structure = entries::get_form_structure('activities', 0, $this->context);
        $this->assert_canedit_by_status($structure, [0 => true, 1 => false, 2 => false, 3 => false, 4 => false]);

        // At status 1, student cannot edit (approve category).
        $structure = entries::get_form_structure('activities', 1, $this->context);
        $this->assert_canedit_by_status($structure, [0 => false, 1 => false, 2 => false, 3 => false, 4 => false]);

        // At status 2, student can edit submit category (status 2).
        $structure = entries::get_form_structure('activities', 2, $this->context);
        $this->assert_canedit_by_status($structure, [0 => false, 1 => false, 2 => true, 3 => false, 4 => false]);

        // At status 3, student cannot edit (approve category).
        $structure = entries::get_form_structure('activities', 3, $this->context);
        $this->assert_canedit_by_status($structure, [0 => false, 1 => false, 2 => false, 3 => false, 4 => false]);

        // At status 4 (locked), student cannot edit anything.
        $structure = entries::get_form_structure('activities', 4, $this->context);
        $this->assert_canedit_by_status($structure, [0 => false, 1 => false, 2 => false, 3 => false, 4 => false]);
    }

    /**
     * Test that structure is hydrated with correct canedit permissions for teacher.
     */
    public function test_canedit_permissions_teacher(): void {
        $this->setUser($this->teacher);

        // At status 0, teacher can edit submit category (has approve, but it's a submit category).
        // Teachers with approve can edit BOTH approve AND submit categories.
        $structure = entries::get_form_structure('activities', 0, $this->context);
        $this->assert_canedit_by_status($structure, [0 => false, 1 => false, 2 => false, 3 => false, 4 => false]);

        // At status 1, teacher can edit approve and submit categories.
        $structure = entries::get_form_structure('activities', 1, $this->context);
        $this->assert_canedit_by_status($structure, [0 => false, 1 => true, 2 => false, 3 => false, 4 => false]);

        // At status 2, teacher can edit submit category.
        $structure = entries::get_form_structure('activities', 2, $this->context);
        $this->assert_canedit_by_status($structure, [0 => false, 1 => false, 2 => false, 3 => false, 4 => false]);

        // At status 3, teacher can edit approve and submit categories.
        $structure = entries::get_form_structure('activities', 3, $this->context);
        $this->assert_canedit_by_status($structure, [0 => false, 1 => false, 2 => false, 3 => true, 4 => false]);
        // At status 4 (locked), teacher cannot edit anything (only unlock can).
        $structure = entries::get_form_structure('activities', 4, $this->context);
        $this->assert_canedit_by_status($structure, [0 => false, 1 => false, 2 => false, 3 => false, 4 => false]);
    }

    /**
     * Test that structure is hydrated with correct canedit permissions for admin with unlock.
     */
    public function test_canedit_permissions_admin_unlock(): void {
        $this->setUser($this->admin);

        // At any status, admin with unlock can ONLY edit unlock category.
        $structure = entries::get_form_structure('activities', 0, $this->context);
        $this->assert_canedit_by_status($structure, [0 => false, 1 => false, 2 => false, 3 => false, 4 => true]);

        $structure = entries::get_form_structure('activities', 1, $this->context);
        $this->assert_canedit_by_status($structure, [0 => false, 1 => false, 2 => false, 3 => false, 4 => true]);

        $structure = entries::get_form_structure('activities', 4, $this->context);
        $this->assert_canedit_by_status($structure, [0 => false, 1 => false, 2 => false, 3 => false, 4 => true]);
    }

    /**
     * Test get_form_structure without permissions hydration.
     */
    public function test_get_form_structure_without_permissions(): void {
        $structure = entries::get_form_structure('activities');

        // Should not have canview/canedit properties when not hydrated.
        foreach ($structure as $category) {
            $this->assertObjectNotHasProperty('canview', $category);
            $this->assertObjectNotHasProperty('canedit', $category);
        }
    }

    /**
     * Test get_form_structure with permissions hydration.
     */
    public function test_get_form_structure_with_permissions(): void {
        $this->setUser($this->student);

        $structure = entries::get_form_structure('activities', 2, $this->context);

        // Should have canview/canedit properties when hydrated.
        foreach ($structure as $category) {
            $this->assertObjectHasProperty('canview', $category);
            $this->assertObjectHasProperty('canedit', $category);
        }
    }

    /**
     * Test that update_entry respects permissions.
     */
    public function test_update_entry_respects_permissions(): void {
        global $DB;

        // Get a field from the submit category (status 0).
        $fields = $DB->get_records_sql("
            SELECT ff.*
            FROM {projetvet_form_field} ff
            WHERE ff.categoryid = ?
            LIMIT 1
        ", [$this->categories[0]->id]);

        if (empty($fields)) {
            $this->markTestSkipped('No fields found in submit category');
        }

        $field = reset($fields);

        // Student can edit entry at status 0.
        $this->setUser($this->student);
        $this->expectNotToPerformAssertions();

        entries::update_entry($this->entries[0]->id, [$field->id => 'Test value'], null);
    }

    /**
     * Test that update_entry blocks unauthorized edits.
     */
    public function test_update_entry_blocks_unauthorized(): void {
        global $DB;

        // Get a field from the approve category (status 1).
        $fields = $DB->get_records_sql("
            SELECT ff.*
            FROM {projetvet_form_field} ff
            WHERE ff.categoryid = ?
            LIMIT 1
        ", [$this->categories[1]->id]);

        if (empty($fields)) {
            $this->markTestSkipped('No fields found in approve category');
        }

        $field = reset($fields);

        // Student cannot edit approve category when entry is at status 0.
        $this->setUser($this->student);

        $this->expectException(\moodle_exception::class);
        entries::update_entry($this->entries[0]->id, [$field->id => 'Unauthorized value'], null);
    }

    /**
     * Assert canedit values match expected for each category status.
     *
     * @param array $structure Form structure
     * @param array $expected Expected canedit values indexed by entrystatus
     */
    private function assert_canedit_by_status(array $structure, array $expected): void {
        foreach ($structure as $category) {
            $expectedvalue = $expected[$category->entrystatus] ?? false;
            $this->assertEquals(
                $expectedvalue,
                $category->canedit,
                "Category {$category->name} (ID: {$category->id}, entrystatus: {$category->entrystatus}, " .
                "capability: {$category->capability}) canedit should be " .
                ($expectedvalue ? 'true' : 'false') . " but is " . ($category->canedit ? 'true' : 'false')
            );
        }
    }
}
