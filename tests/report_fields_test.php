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

use mod_projetvet\form\projetvet_form;
use mod_projetvet\local\api\entries;
use mod_projetvet\local\persistent\form_field;

/**
 * Tests for the mandatory report fields and tagconfirm minimum.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_projetvet\form\projetvet_form::class)]
final class report_fields_test extends \advanced_testcase {
    /** @var array|null */
    private ?array $fixtures = null;
    /**
     * Set up test fixtures with a student, tutor, entry at report stage,
     * and the required report fields configured.
     *
     * @return array An array with keys: course, student, tutor, projetvet, cm, entry, formsetid
     */
    protected function setup_report_fixtures(): array {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $projetvetgenerator = $generator->get_plugin_generator('mod_projetvet');

        $course = $generator->create_course();
        $student = $generator->create_user(['username' => 'student_report']);
        $tutor = $generator->create_user(['username' => 'tutor_report']);
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($tutor->id, $course->id, 'editingteacher');

        $projetvet = $generator->create_module('projetvet', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('projetvet', $projetvet->id, $course->id);

        // Create the report category with required fields.
        $reportformsetid = $this->ensure_report_fields_exist();

        // Create an entry at the report stage (entrystatus 2).
        $entry = $projetvetgenerator->create_form_entry([
            'projetvetid' => $projetvet->id,
            'formsetid' => $reportformsetid,
            'studentid' => $student->id,
            'entrystatus' => 2,
        ]);

        return [
            'course' => $course,
            'student' => $student,
            'tutor' => $tutor,
            'projetvet' => $projetvet,
            'cm' => $cm,
            'entry' => $entry,
            'formsetid' => $reportformsetid,
        ];
    }

    /**
     * Ensure the report fields exist in the database with required flags.
     *
     * @return int The formset ID.
     */
    protected function ensure_report_fields_exist(): int {
        global $DB;

        // Clean up any existing fields.
        $idnumbers = [
            'start_date',
            'end_date',
            'actions_summary',
            'progress_on',
            'must_still_progress_on',
            'practiced_competencies',
        ];
        foreach ($idnumbers as $idnumber) {
            $DB->delete_records('projetvet_form_field', ['idnumber' => $idnumber]);
        }
        $DB->delete_records('projetvet_form_cat', ['idnumber' => 'report_validation']);

        $formset = $DB->get_record('projetvet_form_set', ['idnumber' => 'activities']);
        if (!$formset) {
            $formset = (object) [
                'idnumber' => 'activities',
                'name' => 'Activités',
                'description' => '',
                'categoryid' => 0,
            ];
            $formset->id = $DB->insert_record('projetvet_form_set', $formset);
        }

        // Create the report category.
        $reportcat = (object) [
            'formsetid' => $formset->id,
            'idnumber' => 'report_validation',
            'name' => 'Bilan du projet',
            'description' => '',
            'sortorder' => 3,
            'entrystatus' => 2,
            'capability' => 'submit',
            'statusmsg' => 'report',
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 1,
        ];
        $reportcat->id = $DB->insert_record('projetvet_form_cat', $reportcat);

        $fields = [
            [
                'idnumber' => 'start_date',
                'name' => 'Date de début',
                'type' => 'date',
                'configdata' => json_encode(['required' => true]),
            ],
            [
                'idnumber' => 'end_date',
                'name' => 'Date de fin',
                'type' => 'date',
                'configdata' => json_encode(['required' => true]),
            ],
            [
                'idnumber' => 'actions_summary',
                'name' => 'Résumé des actions',
                'type' => 'textarea',
                'configdata' => json_encode(['rows' => 6, 'required' => true]),
            ],
            [
                'idnumber' => 'progress_on',
                'name' => 'Progressé sur',
                'type' => 'textarea',
                'configdata' => json_encode(['rows' => 4, 'required' => true]),
            ],
            [
                'idnumber' => 'must_still_progress_on',
                'name' => 'Doit encore progresser',
                'type' => 'textarea',
                'configdata' => json_encode(['rows' => 4, 'required' => true]),
            ],
            [
                'idnumber' => 'practiced_competencies',
                'name' => 'Compétences',
                'type' => 'tagconfirm',
                'configdata' => json_encode(['tagselect' => 'competency', 'mintags' => 2]),
            ],
        ];

        foreach ($fields as $field) {
            $field['categoryid'] = $reportcat->id;
            $field['description'] = '';
            $field['sortorder'] = 0;
            $field['listorder'] = 0;
            $field['timecreated'] = time();
            $field['timemodified'] = time();
            $field['usermodified'] = 1;
            $DB->insert_record('projetvet_form_field', $field);
        }

        return $formset->id;
    }

    /**
     * Test that the upgrade step merges required flags into configdata.
     */
    public function test_upgrade_merges_required_flags(): void {
        global $DB;

        $this->resetAfterTest(true);

        // Create a formset, category and a field with existing configdata.
        $DB->delete_records('projetvet_form_field', ['idnumber' => 'start_date']);
        $DB->delete_records('projetvet_form_cat', ['idnumber' => 'report_validation']);

        $formset = $DB->get_record('projetvet_form_set', ['idnumber' => 'activities']);
        if (!$formset) {
            $formset = (object) ['idnumber' => 'activities', 'name' => 'Activités', 'description' => ''];
            $formset->id = $DB->insert_record('projetvet_form_set', $formset);
        }

        $cat = (object) [
            'formsetid' => $formset->id,
            'idnumber' => 'report_validation',
            'name' => 'Bilan',
            'description' => '',
            'sortorder' => 3,
            'entrystatus' => 2,
            'capability' => 'submit',
            'statusmsg' => 'report',
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 1,
        ];
        $cat->id = $DB->insert_record('projetvet_form_cat', $cat);

        $DB->insert_record('projetvet_form_field', (object) [
            'idnumber' => 'start_date',
            'name' => 'Date de début',
            'type' => 'date',
            'configdata' => json_encode(['rows' => 3]),
            'categoryid' => $cat->id,
            'description' => '',
            'sortorder' => 0,
            'listorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 1,
        ]);

        // Run the merge (as the upgrade savepoint does).
        projetvet_merge_report_field_configdata();

        $record = $DB->get_record('projetvet_form_field', ['idnumber' => 'start_date']);
        $configdata = json_decode($record->configdata, true);
        $this->assertTrue($configdata['required']);
        $this->assertSame(3, $configdata['rows']);
    }

    /**
     * Test that the upgrade is idempotent.
     */
    public function test_upgrade_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest(true);

        // Create a formset, category and a field with existing configdata.
        $DB->delete_records('projetvet_form_field', ['idnumber' => 'start_date']);
        $DB->delete_records('projetvet_form_cat', ['idnumber' => 'report_validation']);

        $formset = $DB->get_record('projetvet_form_set', ['idnumber' => 'activities']);
        if (!$formset) {
            $formset = (object) ['idnumber' => 'activities', 'name' => 'Activités', 'description' => ''];
            $formset->id = $DB->insert_record('projetvet_form_set', $formset);
        }

        $cat = (object) [
            'formsetid' => $formset->id,
            'idnumber' => 'report_validation',
            'name' => 'Bilan',
            'description' => '',
            'sortorder' => 3,
            'entrystatus' => 2,
            'capability' => 'submit',
            'statusmsg' => 'report',
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 1,
        ];
        $cat->id = $DB->insert_record('projetvet_form_cat', $cat);

        $DB->insert_record('projetvet_form_field', (object) [
            'idnumber' => 'start_date',
            'name' => 'Date de début',
            'type' => 'date',
            'configdata' => json_encode(['rows' => 3]),
            'categoryid' => $cat->id,
            'description' => '',
            'sortorder' => 0,
            'listorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 1,
        ]);

        // Run the merge twice (idempotency check).
        projetvet_merge_report_field_configdata();
        projetvet_merge_report_field_configdata();

        $record = $DB->get_record('projetvet_form_field', ['idnumber' => 'start_date']);
        $configdata = json_decode($record->configdata, true);
        $this->assertTrue($configdata['required']);
        $this->assertSame(3, $configdata['rows']);
        // Verify no duplication.
        $count = $DB->count_records('projetvet_form_field', ['idnumber' => 'start_date']);
        $this->assertSame(1, $count);
    }

    /**
     * Build a form with the given field maps populated, for validation testing.
     *
     * @param array $requiredfinalfields The required final fields.
     * @param array $minimumtagconfirmfields The tagconfirm minimum fields.
     * @return projetvet_form
     */
    private function build_testable_form(
        array $requiredfinalfields,
        array $minimumtagconfirmfields
    ): projetvet_form {
        global $PAGE;

        if ($this->fixtures === null) {
            $this->fixtures = $this->setup_report_fixtures();
        }

        $cmid = $this->fixtures['cm']->id;
        $context = \context_module::instance($cmid);
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/mod/projetvet/view.php', ['id' => $cmid]));

        // Pass the form parameters via the ajaxformdata argument, which
        // optional_param() checks before $_POST/$_GET. This is the supported
        // way to supply form data in tests without polluting superglobals.
        $ajaxformdata = [
            'cmid' => $cmid,
            'projetvetid' => $this->fixtures['projetvet']->id,
            'studentid' => $this->fixtures['student']->id,
            'entryid' => $this->fixtures['entry']->id,
        ];

        $form = new projetvet_form(
            new \moodle_url('/mod/projetvet/view.php', ['id' => $cmid]),
            null,
            'post',
            '',
            null,
            true,
            $ajaxformdata
        );

        // Populate the (protected) field maps via reflection so validation()
        // can be exercised in isolation without depending on form rendering.
        $this->set_field_map($form, 'requiredfinalfields', $requiredfinalfields);
        $this->set_field_map($form, 'minimumtagconfirmfields', $minimumtagconfirmfields);

        return $form;
    }

    /**
     * Set a protected field map on the form via reflection.
     *
     * @param projetvet_form $form The form instance.
     * @param string $propertyname The property name.
     * @param array $value The value to set.
     * @return void
     */
    private function set_field_map(projetvet_form $form, string $propertyname, array $value): void {
        $property = new \ReflectionProperty($form, $propertyname);
        $property->setValue($form, $value);
    }

    /**
     * Test that a final submission with empty required report fields is rejected.
     */
    public function test_final_submission_requires_report_fields(): void {
        $form = $this->build_testable_form(['field_actions_summary' => 'actions_summary'], []);

        // Final submission (button_entrystatus advances beyond current status).
        $errors = $form->validation([
            'entrystatus' => 2,
            'button_entrystatus' => 3,
            'field_actions_summary' => '',
        ], []);

        $this->assertArrayHasKey('field_actions_summary', $errors);
    }

    /**
     * Test that a draft save does not reject empty required report fields.
     */
    public function test_draft_save_skips_required_report_fields(): void {
        $form = $this->build_testable_form(['field_actions_summary' => 'actions_summary'], []);

        // Draft save (button_entrystatus equals current status).
        $errors = $form->validation([
            'entrystatus' => 2,
            'button_entrystatus' => 2,
            'field_actions_summary' => '',
        ], []);

        $this->assertArrayNotHasKey('field_actions_summary', $errors);
    }

    /**
     * Test that a final submission with fewer than the minimum confirmed
     * competencies is rejected.
     *
     * @dataProvider tagconfirm_count_provider
     */
    public function test_final_submission_tagconfirm_minimum(int $count, bool $expecterror): void {
        $form = $this->build_testable_form([], ['field_practiced_competencies' => 2]);

        $confirmed = $count > 0 ? array_map('strval', range(1, $count)) : [];
        $errors = $form->validation([
            'entrystatus' => 2,
            'button_entrystatus' => 3,
            'field_practiced_competencies' => $confirmed,
        ], []);

        if ($expecterror) {
            $this->assertArrayHasKey('field_practiced_competencies', $errors);
        } else {
            $this->assertArrayNotHasKey('field_practiced_competencies', $errors);
        }
    }

    /**
     * Data provider for tagconfirm minimum tests.
     *
     * @return array
     */
    public static function tagconfirm_count_provider(): array {
        return [
            'zero confirmed' => [0, true],
            'one confirmed' => [1, true],
            'two confirmed' => [2, false],
            'three confirmed' => [3, false],
        ];
    }

    /**
     * Test that a draft save does not enforce the tagconfirm minimum.
     */
    public function test_draft_save_skips_tagconfirm_minimum(): void {
        $form = $this->build_testable_form([], ['field_practiced_competencies' => 2]);

        $errors = $form->validation([
            'entrystatus' => 2,
            'button_entrystatus' => 2,
            'field_practiced_competencies' => ['1'],
        ], []);

        $this->assertArrayNotHasKey('field_practiced_competencies', $errors);
    }
}
