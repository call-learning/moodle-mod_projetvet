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

namespace mod_projetvet;

/**
 * Tests for the ECTS agreement and suggestion logic.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(utils::class)]
final class ects_test extends \advanced_testcase {
    /** @var int */
    /**
     * Test that a tutor-validated ECTS override is preserved.
     */
    public function test_tutor_override_is_preserved(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $projetvetgenerator = $generator->get_plugin_generator('mod_projetvet');

        $course = $generator->create_course();
        $student = $generator->create_user(['username' => 'student_ects_override']);
        $generator->enrol_user($student->id, $course->id, 'student');

        $projetvet = $generator->create_module('projetvet', ['course' => $course->id]);

        $activitiesformsetid = $this->ensure_formset_exists('activities');
        $fields = $this->ensure_ects_fields_exist($activitiesformsetid);

        $entry = $projetvetgenerator->create_form_entry([
            'projetvetid' => $projetvet->id,
            'formsetid' => $activitiesformsetid,
            'studentid' => $student->id,
            'entrystatus' => 2,
        ]);

        $this->add_field_value($projetvetgenerator, $fields['hours'], $entry->id, 228);
        $this->add_field_value($projetvetgenerator, $fields['rang'], $entry->id, 1);
        $this->add_field_value($projetvetgenerator, $fields['credits'], $entry->id, 6);

        set_config('hours_per_ects', 30, 'mod_projetvet');
        set_config('max_ects', 10, 'mod_projetvet');
        set_config('min_hours', 20, 'mod_projetvet');

        $this->assertEquals(6, utils::get_agreed_ects($entry->id));

        $result = utils::get_suggested_ects(
            $projetvet->id,
            $student->id,
            $entry->id,
            228,
            'suggestedects_hours_completed',
            1,
            0
        );

        $this->assertEquals(8, $result['suggestedects']);
        $this->assertStringContainsString('6 ECTS', $result['message']);
        $this->assertStringContainsString('8 ECTS', $result['message']);
    }

    /**
     * Test that accepting the automatic suggestion produces the same agreed value.
     */
    public function test_accepting_suggestion_preserves_value(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $projetvetgenerator = $generator->get_plugin_generator('mod_projetvet');

        $course = $generator->create_course();
        $student = $generator->create_user(['username' => 'student_ects_suggestion']);
        $generator->enrol_user($student->id, $course->id, 'student');

        $projetvet = $generator->create_module('projetvet', ['course' => $course->id]);

        $activitiesformsetid = $this->ensure_formset_exists('activities');
        $fields = $this->ensure_ects_fields_exist($activitiesformsetid);

        $entry = $projetvetgenerator->create_form_entry([
            'projetvetid' => $projetvet->id,
            'formsetid' => $activitiesformsetid,
            'studentid' => $student->id,
            'entrystatus' => 2,
        ]);

        $this->add_field_value($projetvetgenerator, $fields['hours'], $entry->id, 228);
        $this->add_field_value($projetvetgenerator, $fields['rang'], $entry->id, 1);
        $this->add_field_value($projetvetgenerator, $fields['credits'], $entry->id, 8);

        set_config('hours_per_ects', 30, 'mod_projetvet');
        set_config('max_ects', 10, 'mod_projetvet');
        set_config('min_hours', 20, 'mod_projetvet');

        $this->assertEquals(8, utils::get_agreed_ects($entry->id));

        $result = utils::get_suggested_ects(
            $projetvet->id,
            $student->id,
            $entry->id,
            228,
            'suggestedects_hours_completed',
            1,
            0
        );

        $this->assertEquals(8, $result['suggestedects']);
        $this->assertStringContainsString('8 ECTS', $result['message']);
    }

    /**
     * Test that legacy entries without a persisted credits value fall back to the workload estimate.
     */
    public function test_legacy_fallback_uses_workload_estimate(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $projetvetgenerator = $generator->get_plugin_generator('mod_projetvet');

        $course = $generator->create_course();
        $student = $generator->create_user(['username' => 'student_ects_legacy']);
        $generator->enrol_user($student->id, $course->id, 'student');

        $projetvet = $generator->create_module('projetvet', ['course' => $course->id]);

        $activitiesformsetid = $this->ensure_formset_exists('activities');
        $fields = $this->ensure_ects_fields_exist($activitiesformsetid);

        $entry = $projetvetgenerator->create_form_entry([
            'projetvetid' => $projetvet->id,
            'formsetid' => $activitiesformsetid,
            'studentid' => $student->id,
            'entrystatus' => 2,
        ]);

        $this->add_field_value($projetvetgenerator, $fields['hours'], $entry->id, 228);
        $this->add_field_value($projetvetgenerator, $fields['rang'], $entry->id, 1);

        set_config('hours_per_ects', 30, 'mod_projetvet');
        set_config('max_ects', 10, 'mod_projetvet');
        set_config('min_hours', 20, 'mod_projetvet');

        $this->assertEquals(8, utils::get_agreed_ects($entry->id));
    }

    /**
     * Ensure the activities formset exists and return its ID.
     *
     * @return int
     */
    private function ensure_formset_exists(): int {
        global $DB;

        $existing = $DB->get_record('projetvet_form_set', ['idnumber' => 'activities']);
        if ($existing) {
            return (int)$existing->id;
        }

        return (int)$DB->insert_record('projetvet_form_set', (object)[
            'idnumber' => 'activities',
            'name' => 'Activities',
            'description' => '',
            'sortorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 2,
        ]);
    }

    /**
     * Ensure the ECTS-related fields exist and return their IDs.
     *
     * @param int $activitiesformsetid
     * @return array
     */
    private function ensure_ects_fields_exist(int $activitiesformsetid): array {
        global $DB;

        $categoryidnumber = 'activity_details';
        $DB->delete_records('projetvet_form_cat', ['idnumber' => $categoryidnumber]);
        $categoryid = (int)$DB->insert_record('projetvet_form_cat', (object)[
            'formsetid' => $activitiesformsetid,
            'idnumber' => $categoryidnumber,
            'name' => 'PHPUnit ECTS category',
            'description' => '',
            'capability' => 'submit',
            'entrystatus' => 0,
            'statusmsg' => 'draft',
            'sortorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 2,
        ]);

        $fieldids = [];
        foreach (['hours', 'rang', 'credits'] as $idnumber) {
            $DB->delete_records('projetvet_form_field', ['idnumber' => $idnumber]);
            $fieldids[$idnumber] = (int)$DB->insert_record('projetvet_form_field', (object)[
                'categoryid' => $categoryid,
                'idnumber' => $idnumber,
                'name' => $idnumber,
                'type' => 'number',
                'description' => '',
                'sortorder' => 0,
                'configdata' => null,
                'capability' => 'submit',
                'entrystatus' => 0,
                'listorder' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
                'usermodified' => 2,
            ]);
        }

        \cache::make('mod_projetvet', 'activitystructures')->purge();

        return $fieldids;
    }

    /**
     * Add a form data value for one field.
     *
     * @param object $projetvetgenerator
     * @param int $fieldid
     * @param int $entryid
     * @param int $value
     * @return void
     */
    private function add_field_value(object $projetvetgenerator, int $fieldid, int $entryid, int $value): void {
        $projetvetgenerator->create_form_data([
            'fieldid' => $fieldid,
            'entryid' => $entryid,
            'intvalue' => $value,
        ]);
    }
}
