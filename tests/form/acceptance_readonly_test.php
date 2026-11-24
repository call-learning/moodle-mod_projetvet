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

namespace mod_projetvet\form;

use advanced_testcase;
use mod_projetvet\local\api\entries;
use mod_projetvet\local\persistent\form_cat;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\persistent\form_field;
use mod_projetvet\local\persistent\form_data;
use mod_projetvet\local\persistent\form_set;
use moodle_exception;

/**
 * Regression tests for the "selected competencies disappear on acceptance" bug.
 *
 * Drives the real projetvet_form through the modal (webservice) submission flow
 * used by the tutor "Valider l'eligibilite" step. The form is first rendered from
 * the initial modal arguments and the submission payload is then serialised
 * exactly as the browser would serialise it: frozen (disabled) controls are
 * excluded, so a view-mode submission only carries the hidden fields.
 *
 * The readonly flag is part of the page arguments, not a stored field, so it
 * must round-trip through the form payload (hidden input): in the modal (AJAX)
 * flow the form is re-instantiated in the webservice from the submitted data
 * only, where frozen controls are never submitted. Without that round-trip the
 * view-mode flag is lost on the validation pass, validation rules re-appear on
 * fields that can never submit a value and block the status-only submission.
 *
 * @package     mod_projetvet
 * @category    test
 * @copyright   2026 projetvet
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(projetvet_form::class)]
final class acceptance_readonly_test extends advanced_testcase {
    /**
     * The (non-default) form set idnumber used by this test.
     */
    protected const FORMSET = 'repro_accept';

    /**
     * Lookup uniqueids used for the stored tagselect value.
     */
    protected const UIDS = [101, 102];

    /**
     * The stored value of the required title field.
     */
    protected const TITLE = 'Test title';

    /**
     * The course created for the test.
     *
     * @var \stdClass
     */
    protected $course;

    /**
     * The projetvet course module.
     *
     * @var \stdClass
     */
    protected $cm;

    /**
     * The projetvet instance id.
     *
     * @var int
     */
    protected $projetvetid;

    /**
     * The student user.
     *
     * @var \core_user
     */
    protected $student;

    /**
     * The tutor user.
     *
     * @var \core_user
     */
    protected $tutor;

    /**
     * The id of the tagselect field under test.
     *
     * @var int
     */
    protected $competencyfieldid;

    /**
     * The id of the required title field.
     *
     * @var int
     */
    protected $titlefieldid;

    /**
     * Set up a minimal two-category form set: a tagselect in a past-status submit
     * category and an empty approve category at the current status.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $modgenerator = $generator->get_plugin_generator('mod_projetvet');
        $instance = $modgenerator->create_instance(['course' => $this->course->id]);
        $this->projetvetid = $instance->id;
        $this->cm = get_coursemodule_from_instance('projetvet', $this->projetvetid);
        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->tutor = $generator->create_and_enrol($this->course, 'editingteacher');

        $formset = new form_set();
        $formset->set('idnumber', self::FORMSET);
        $formset->set('name', 'Repro accept');
        $formset->set('description', 'Test');
        $formset->set('sortorder', 1);
        $formset->create();

        // Idnumbers are unique across all form sets; reuse names whose language
        // strings (category_details, category_general_info, field_mobilitytitle,
        // field_description) already exist.
        $cat1 = new form_cat();
        $cat1->set('formsetid', $formset->get('id'));
        $cat1->set('idnumber', 'details');
        $cat1->set('name', 'Details');
        $cat1->set('description', 'Student details');
        $cat1->set('capability', 'submit');
        $cat1->set('entrystatus', 0);
        $cat1->set('statusmsg', 'draft');
        $cat1->set('sortorder', 1);
        $cat1->create();

        $cat2 = new form_cat();
        $cat2->set('formsetid', $formset->get('id'));
        $cat2->set('idnumber', 'general_info');
        $cat2->set('name', 'Accept');
        $cat2->set('description', 'Tutor acceptance');
        $cat2->set('capability', 'approve');
        $cat2->set('entrystatus', 1);
        $cat2->set('statusmsg', 'accepted');
        $cat2->set('sortorder', 2);
        $cat2->create();

        $titlefield = new form_field();
        $titlefield->set('idnumber', 'mobilitytitle');
        $titlefield->set('name', 'Title');
        $titlefield->set('type', 'text');
        $titlefield->set('description', '');
        $titlefield->set('categoryid', $cat1->get('id'));
        $titlefield->set('capability', 'submit');
        $titlefield->set('configdata', json_encode(['required' => true]));
        $titlefield->set('sortorder', 1);
        $titlefield->set('entrystatus', 0);
        $titlefield->set('listorder', 1);
        $titlefield->create();
        $this->titlefieldid = $titlefield->get('id');

        $field = new form_field();
        $field->set('idnumber', 'description');
        $field->set('name', 'Description');
        $field->set('type', 'tagselect');
        $field->set('description', '');
        $field->set('categoryid', $cat1->get('id'));
        $field->set('capability', 'submit');
        $field->set('configdata', json_encode(['maxtags' => 5, 'mintags' => 2]));
        $field->set('sortorder', 2);
        $field->set('entrystatus', 0);
        $field->set('listorder', 1);
        $field->create();
        $this->competencyfieldid = $field->get('id');

        // Seed lookup options: a heading plus the item uniqueids.
        global $DB;
        $DB->insert_record('projetvet_field_data', [
            'fieldid' => $this->competencyfieldid,
            'uniqueid' => 100,
            'itemtype' => 'heading',
            'parent' => 0,
            'name' => 'Group A',
            'sortorder' => 1,
        ]);
        foreach (self::UIDS as $i => $uid) {
            $DB->insert_record('projetvet_field_data', [
                'fieldid' => $this->competencyfieldid,
                'uniqueid' => $uid,
                'itemtype' => 'item',
                'parent' => 100,
                'name' => 'Competency ' . $uid,
                'sortorder' => 2 + $i,
            ]);
        }
    }

    /**
     * Create an entry at entrystatus 1 with the title and the two lookup tags.
     *
     * @return int The entry id.
     */
    private function create_entry(): int {
        return entries::create_entry(
            $this->projetvetid,
            $this->student->id,
            [
                $this->titlefieldid => self::TITLE,
                $this->competencyfieldid => self::UIDS,
            ],
            1,
            self::FORMSET
        );
    }

    /**
     * Read the stored raw tagselect value for an entry.
     *
     * @param int $entryid The entry id.
     * @return mixed The raw stored value, or null when no record exists.
     */
    private function stored_competency(int $entryid) {
        $record = form_data::get_record(['entryid' => $entryid, 'fieldid' => $this->competencyfieldid]);
        return $record ? $record->get_value() : null;
    }

    /**
     * Read the stored raw title value for an entry.
     *
     * @param int $entryid The entry id.
     * @return mixed The raw stored value, or null when no record exists.
     */
    private function stored_title(int $entryid) {
        $record = form_data::get_record(['entryid' => $entryid, 'fieldid' => $this->titlefieldid]);
        return $record ? $record->get_value() : null;
    }

    /**
     * Build the arguments of the initial modal render (the ModalForm config args).
     *
     * @param int $entryid The entry id.
     * @param int $readonly The readonly flag (1 = view mode, 0 = edit mode).
     * @return array The modal arguments.
     */
    private function modal_args(int $entryid, int $readonly): array {
        return [
            'cmid' => $this->cm->id,
            'projetvetid' => $this->projetvetid,
            'studentid' => $this->student->id,
            'entryid' => $entryid,
            'parententryid' => 0,
            'formsetidnumber' => self::FORMSET,
            'readonly' => $readonly,
        ];
    }

    /**
     * Render the modal form as the webservice would for the initial call.
     *
     * @param int $entryid The entry id.
     * @param int $readonly The readonly flag (1 = view mode, 0 = edit mode).
     * @return array The form instance and its PEAR form.
     */
    private function render_modal_form(int $entryid, int $readonly): array {
        global $USER;

        $this->setUser($this->tutor);
        $USER->ignoresesskey = true;

        $form = new projetvet_form(null, null, 'post', '', [], true, $this->modal_args($entryid, $readonly), true);
        $form->set_data_for_dynamic_submission();

        $rp = new \ReflectionProperty($form, '_form');
        $rp->setAccessible(true);
        return [$form, $rp->getValue($form)];
    }

    /**
     * Build the formdata the browser serialises when the modal form is submitted.
     *
     * Frozen (disabled) controls are excluded from the serialised data, which is
     * what makes a view-mode payload a hidden-fields-only submission. The readonly
     * flag must therefore be carried by the form itself (hidden input), not by
     * the initial modal arguments. As in a real browser, every value is a string
     * (a multiple select serialises to an array of strings).
     *
     * @param \HTML_QuickForm $quickform The rendered PEAR form.
     * @param array $extra Additional submitted values (e.g. the pressed button).
     * @return array The submitted formdata.
     */
    private function browser_payload($quickform, array $extra = []): array {
        $payload = [];
        foreach ($quickform->exportValues() as $name => $value) {
            if ($quickform->elementExists($name)) {
                $element = $quickform->getElement($name);
                if (is_object($element) && $element->isFrozen()) {
                    continue;
                }
            }
            if (is_array($value)) {
                $payload[$name] = array_map('strval', $value);
            } else {
                $payload[$name] = (string) $value;
            }
        }
        return array_merge($payload, $extra);
    }

    /**
     * Run a modal submission webservice call with a browser-serialised payload.
     *
     * @param int $entryid The entry id.
     * @param array $payload The submitted formdata.
     * @return array Result keys: processed, validated, stored, storedtitle, quickform.
     */
    private function submit_modal_form(int $entryid, array $payload): array {
        global $USER;

        $this->setUser($this->tutor);
        $USER->ignoresesskey = true;

        $form = new projetvet_form(null, null, 'post', '', [], true, $payload, true);
        $form->set_data_for_dynamic_submission();

        $rp = new \ReflectionProperty($form, '_form');
        $rp->setAccessible(true);

        $result = [
            'processed' => false,
            'validated' => $form->is_validated(),
            'stored' => $this->stored_competency($entryid),
            'storedtitle' => $this->stored_title($entryid),
            'quickform' => $rp->getValue($form),
        ];

        if (!$form->is_cancelled() && $form->is_submitted() && $form->is_validated()) {
            $form->process_dynamic_submission();
            $result['processed'] = true;
            $result['stored'] = $this->stored_competency($entryid);
            $result['storedtitle'] = $this->stored_title($entryid);
        }

        return $result;
    }

    /**
     * Read the selected option values of the tagselect element from its template context.
     *
     * @param \HTML_QuickForm $quickform The rendered PEAR form.
     * @return array The selected values.
     */
    private function selected_competencies($quickform): array {
        global $OUTPUT;

        $context = $quickform->getElement('field_description')->export_for_template($OUTPUT);
        $selected = array_filter($context['options'] ?? [], static fn($o) => !empty($o['selected']));
        return array_values(array_map(static fn($o) => $o['value'], $selected));
    }

    /**
     * The stored values survive a status-only submission in View mode.
     *
     * In View mode the required title and the tagselect are frozen, so the
     * browser submits neither of them; the submission must still validate and
     * advance the status without wiping the stored values.
     */
    public function test_view_mode_preserves_values(): void {
        $entryid = $this->create_entry();

        // The modal opens in View mode: both fields are frozen and the tagselect is prefilled.
        [$form, $quickform] = $this->render_modal_form($entryid, 1);
        $this->assertTrue($quickform->getElement('field_mobilitytitle')->isFrozen(), 'Title should be frozen in View mode');
        $this->assertTrue($quickform->getElement('field_description')->isFrozen(), 'Competency should be frozen in View mode');
        $this->assertEquals(
            self::UIDS,
            $this->selected_competencies($quickform),
            'Prefill should show stored options in View mode'
        );

        // The accept button submits the serialised form. Frozen controls are not
        // serialised, so the payload only carries the hidden fields; the readonly
        // flag must be among them for the server to keep the fields frozen on the
        // validation pass instead of validating them against an empty submission.
        $payload = $this->browser_payload($quickform, ['button_entrystatus' => 2]);
        $this->assertArrayNotHasKey('field_mobilitytitle', $payload, 'Frozen title must not be serialised');
        $this->assertArrayNotHasKey('field_description', $payload, 'Frozen tagselect must not be serialised');

        $submit = $this->submit_modal_form($entryid, $payload);

        // On the validation pass the form is re-instantiated from the submitted
        // payload only; the frozen tagselect must keep its prefilled value
        // instead of losing it on the submitted re-render.
        $this->assertEquals(
            self::UIDS,
            array_map('intval', (array) $submit['quickform']->getElement('field_description')->getValue()),
            'Frozen tagselect must keep its prefilled value on the validation pass'
        );

        $this->assertTrue($submit['processed'], 'View-mode submission should process');
        $stored = array_map('intval', (array) json_decode((string) $submit['stored'], true));
        $this->assertEquals(self::UIDS, $stored, 'Competency must survive the tutor validation in View mode');
        $this->assertEquals(self::TITLE, $submit['storedtitle'], 'Title must survive the tutor validation in View mode');
        $this->assertSame(
            2,
            (int) form_entry::get_record(['id' => $entryid])->get('entrystatus'),
            'Status must advance on validation'
        );
    }

    /**
     * The stored values survive a submission in Edit mode.
     */
    public function test_edit_mode_preserves_values(): void {
        $entryid = $this->create_entry();

        // The modal opens in Edit mode: the fields are not frozen and are prefilled from the stored values.
        [$form, $quickform] = $this->render_modal_form($entryid, 0);
        $this->assertFalse($quickform->getElement('field_mobilitytitle')->isFrozen(), 'Title should not be frozen in Edit mode');
        $this->assertFalse($quickform->getElement('field_description')->isFrozen(), 'Competency should not be frozen in Edit mode');
        $this->assertEquals(
            self::UIDS,
            $this->selected_competencies($quickform),
            'Prefill should show stored options in Edit mode'
        );

        // In Edit mode the enabled controls are serialised with their values.
        $payload = $this->browser_payload($quickform, ['button_entrystatus' => 2]);
        $this->assertArrayHasKey('field_mobilitytitle', $payload, 'Enabled title must be serialised');
        $this->assertArrayHasKey('field_description', $payload, 'Enabled tagselect must be serialised');

        $submit = $this->submit_modal_form($entryid, $payload);

        $this->assertTrue($submit['processed'], 'Edit-mode submission should process');
        $stored = array_map('intval', (array) json_decode((string) $submit['stored'], true));
        $this->assertEquals(self::UIDS, $stored, 'Competency must survive the tutor validation in Edit mode');
        $this->assertEquals(self::TITLE, $submit['storedtitle'], 'Title must survive the tutor validation in Edit mode');
    }

    /**
     * A hand-crafted read-only payload cannot skip required-field validation.
     *
     * View-mode (status-only) submissions skip validation of the frozen fields,
     * so they must be rejected when the current user is expected to submit field
     * values at the entry's current status.
     */
    public function test_crafted_readonly_payload_is_rejected(): void {
        // A draft entry of the student, at the status of the submit category.
        $entryid = entries::create_entry($this->projetvetid, $this->student->id, [], 0, self::FORMSET);

        $this->setUser($this->student);
        global $USER;
        $USER->ignoresesskey = true;

        $formname = preg_replace('/[^a-z0-9_]/i', '_', projetvet_form::class);
        $payload = [
            'cmid' => $this->cm->id,
            'projetvetid' => $this->projetvetid,
            'studentid' => $this->student->id,
            'entryid' => $entryid,
            'parententryid' => 0,
            'formsetidnumber' => self::FORMSET,
            'readonly' => 1, // Hand-crafted: claim view mode to skip the field validation.
            'button_entrystatus' => 1,
            '_qf__' . $formname => 1,
        ];

        $form = new projetvet_form(null, null, 'post', '', [], true, $payload, true);
        $form->set_data_for_dynamic_submission();

        // The frozen fields carry no validation constraint, so the form validates...
        $this->assertTrue($form->is_validated(), 'Frozen fields must not carry validation constraints');

        // ...but the submission must be rejected: the student is expected to
        // submit field values at this status and may not bypass their validation.
        try {
            $form->process_dynamic_submission();
            $this->fail('A crafted read-only submission must be rejected');
        } catch (moodle_exception $exception) {
            $this->assertSame('invalidaccess', $exception->errorcode);
        }
        $this->assertSame(
            0,
            (int) form_entry::get_record(['id' => $entryid])->get('entrystatus'),
            'Status must not advance on a rejected submission'
        );
    }
}
