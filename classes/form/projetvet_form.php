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

defined('MOODLE_INTERNAL') || die();

global $CFG;

use context;
use context_module;
use core_form\dynamic_form;
use mod_projetvet\local\api\entries;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\persistent\form_field;
use mod_projetvet\local\persistent\field_data;
use moodle_exception;
use moodle_url;

require_once($CFG->libdir . '/formslib.php');

/**
 * Class activity_entry_form
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class projetvet_form extends dynamic_form {
    /**
     * Process the form submission
     *
     * @return array
     * @throws moodle_exception
     */
    public function process_dynamic_submission(): array {
        global $USER;
        $data = $this->get_data();

        // Progress to next status on submission.
        // Current status is stored in hidden field, we increment it on form submission.
        $currententrystatus = $data->entrystatus ?? form_entry::STATUS_DRAFT;

        // Progress to next status (0->1, 1->2, 2->3, 3 stays at 3).
        $nextstatus = min($currententrystatus + 1, form_entry::STATUS_COMPLETED);
        $entrystatus = $nextstatus;

        // Extract field values from form data.
        $fields = [];
        $formsetidnumber = $data->formsetidnumber ?? 'activities';
        $structure = entries::get_form_structure($formsetidnumber);
        foreach ($structure as $category) {
            foreach ($category->fields as $field) {
                $fieldname = 'field_' . $field->id;
                if (isset($data->$fieldname)) {
                    $fields[$field->id] = $data->$fieldname;
                }
            }
        }

        if (!empty($data->entryid)) {
            // Update existing entry.
            entries::update_entry($data->entryid, $fields, $entrystatus);
            $entryid = $data->entryid;
        } else {
            // Create new entry.
            $studentid = $data->studentid ?? $USER->id;
            $entryid = entries::create_entry($data->projetvetid, $studentid, $fields, $entrystatus, $formsetidnumber);
        }

        return [
            'result' => true,
            'entryid' => $entryid,
        ];
    }

    /**
     * Get context
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $context = context_module::instance($cmid);
        return $context;
    }

    /**
     * Check access for dynamic submission
     *
     * @return void
     * @throws moodle_exception
     */
    protected function check_access_for_dynamic_submission(): void {
        global $USER;
        $context = $this->get_context_for_dynamic_submission();
        $entryid = $this->optional_param('entryid', 0, PARAM_INT);

        if ($entryid) {
            // Check if user can edit this entry.
            $entry = entries::get_entry($entryid);
            if ($entry->studentid != $USER->id && !has_capability('mod/projetvet:edit', $context)) {
                throw new moodle_exception('invalidaccess');
            }
        } else {
            // Check if user can create entries.
            if (!has_capability('mod/projetvet:view', $context)) {
                throw new moodle_exception('invalidaccess');
            }
        }
    }

    /**
     * Get page URL
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        return new moodle_url('/mod/projetvet/view.php', ['id' => $cmid]);
    }

    /**
     * Form definition
     *
     * @return void
     */
    protected function definition() {
        global $CFG;
        $mform = $this->_form;

        // Set vertical display mode.
        $this->set_display_vertical();

        // Register custom form elements.
        \MoodleQuickForm::registerElementType(
            'tagselect',
            "$CFG->dirroot/mod/projetvet/classes/form/tagselect_element.php",
            'mod_projetvet\form\tagselect_element'
        );
        \MoodleQuickForm::registerElementType(
            'switch',
            "$CFG->dirroot/mod/projetvet/classes/form/switch_element.php",
            'mod_projetvet\form\switch_element'
        );

        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $projetvetid = $this->optional_param('projetvetid', null, PARAM_INT);
        $studentid = $this->optional_param('studentid', null, PARAM_INT);
        $entryid = $this->optional_param('entryid', 0, PARAM_INT);
        $formsetidnumber = $this->optional_param('formsetidnumber', 'activities', PARAM_ALPHANUMEXT);

        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->addElement('hidden', 'projetvetid', $projetvetid);
        $mform->addElement('hidden', 'studentid', $studentid);
        $mform->addElement('hidden', 'entryid', $entryid);
        $mform->addElement('hidden', 'formsetidnumber', $formsetidnumber);
        $mform->setType('formsetidnumber', PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', 'entrystatus');
        $mform->setType('entrystatus', PARAM_INT);

        // Get the context for capability checking.
        $context = context_module::instance($cmid);

        // Get current entry status if editing.
        $currententrystatus = form_entry::STATUS_DRAFT;
        if ($entryid) {
            $entry = entries::get_entry($entryid);
            $currententrystatus = $entry->entrystatus;
        }

        // Get the activity structure and add fields.
        $structure = entries::get_form_structure($formsetidnumber);

        foreach ($structure as $category) {
            if ($category->entrystatus > $currententrystatus) {
                // Skip this category as its entrystatus is higher than current entry status.
                continue;
            }

            // Add category header.
            $mform->addElement('header', 'category_' . $category->id, $category->name);

            // Expand header if category entrystatus matches current entry status.
            if ($category->entrystatus == $currententrystatus) {
                $mform->setExpanded('category_' . $category->id);
            } else {
                $mform->setExpanded('category_' . $category->id, false);
            }

            foreach ($category->fields as $field) {
                $fieldname = 'field_' . $field->id;

                // Check if user can edit this field based on capability and entry status.
                $canediffield = true;
                if (!empty($field->capability)) {
                    $canediffield = has_capability($field->capability, $context);
                }
                // Also check if entry status allows editing this field.
                if ($canediffield && $field->entrystatus != $currententrystatus) {
                    $canediffield = false;
                }

                // Decode configdata - it may be a string or already decoded.
                if (is_string($field->configdata)) {
                    // Remove slashes that may have been added during storage.
                    $configdata = json_decode(stripslashes($field->configdata), true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $configdata = [];
                    }
                } else {
                    $configdata = (array) $field->configdata;
                }

                switch ($field->type) {
                    case 'text':
                        $mform->addElement('text', $fieldname, $field->name);
                        $mform->setType($fieldname, PARAM_TEXT);
                        break;

                    case 'date':
                        $mform->addElement('date_selector', $fieldname, $field->name);
                        $mform->setDefault($fieldname, time());
                        break;

                    case 'textarea':
                        $rows = $configdata['rows'] ?? 4;
                        $mform->addElement('textarea', $fieldname, $field->name, ['rows' => $rows]);
                        $mform->setType($fieldname, PARAM_TEXT);
                        break;

                    case 'number':
                        $attributes = [];
                        if (isset($configdata['max'])) {
                            $attributes['max'] = $configdata['max'];
                        }
                        $mform->addElement('text', $fieldname, $field->name, $attributes);
                        $mform->setType($fieldname, PARAM_INT);
                        break;

                    case 'select':
                        $options = $configdata['options'] ?? [];
                        $selectoptions = ['' => get_string('choose')] + $options;
                        $mform->addElement('select', $fieldname, $field->name, $selectoptions);
                        break;

                    case 'autocomplete':
                        $options = $configdata['options'] ?? [];
                        $mform->addElement('autocomplete', $fieldname, $field->name, $options, [
                            'multiple' => true,
                            'noselectionstring' => get_string('choose'),
                        ]);
                        break;

                    case 'tagselect':
                        // Load grouped options from field_data table.
                        $groupedoptions = field_data::get_grouped_options($field->id);

                        $mform->addElement('tagselect', $fieldname, $field->name, [], [
                            'groupedoptions' => $groupedoptions,
                            'rowname' => $field->name,
                            'maxtags' => 0,
                        ]);
                        break;

                    case 'checkbox':
                        $mform->addElement('advcheckbox', $fieldname, $field->name, '', null, [0, 1]);
                        break;
                }
                if (!empty($configdata['required']) && $configdata['required'] == true && $canediffield) {
                    $mform->addRule($fieldname, null, 'required', null, 'client');
                }

                if (!empty($field->description)) {
                    $mform->addHelpButton($fieldname, $field->idnumber, 'mod_projetvet');
                }

                // If user cannot edit this field, freeze it but preserve the value using setConstant.
                if (!$canediffield) {
                    $mform->freeze($fieldname);
                    // For frozen fields, we need to ensure the value is preserved on submission.
                    // We'll set it as constant in set_data_for_dynamic_submission instead.
                }
            }
        }
    }

    /**
     * Set data for dynamic submission
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        global $USER;

        $formsetidnumber = $this->optional_param('formsetidnumber', 'activities', PARAM_ALPHANUMEXT);

        $data = [
            'cmid' => $this->optional_param('cmid', 0, PARAM_INT),
            'projetvetid' => $this->optional_param('projetvetid', 0, PARAM_INT),
            'studentid' => $this->optional_param('studentid', $USER->id, PARAM_INT),
            'entryid' => $this->optional_param('entryid', 0, PARAM_INT),
            'formsetidnumber' => $formsetidnumber,
            'entrystatus' => form_entry::STATUS_DRAFT, // Default to draft for new entries.
        ];

        // If editing an existing entry, load its data.
        if (!empty($data['entryid'])) {
            $entry = entries::get_entry($data['entryid']);
            // Set the entry status switch from the entry.
            $data['entrystatus'] = $entry->entrystatus;
            $structure = entries::get_form_structure($formsetidnumber);

            foreach ($entry->categories as $category) {
                foreach ($category->fields as $field) {
                    $fieldname = 'field_' . $field->id;
                    $fieldobj = $this->get_field_by_id($structure, $field->id);

                    if (!$fieldobj) {
                        continue;
                    }

                    // For autocomplete and tagselect fields, decode JSON to array.
                    if (in_array($fieldobj->type, ['autocomplete', 'tagselect'])) {
                        $decoded = json_decode($field->value, true);
                        $fieldvalue = is_array($decoded) ? $decoded : [];
                    } else {
                        if ($fieldobj->type == 'date' && $field->value == '') {
                            $fieldvalue = time();
                        } else {
                            $fieldvalue = $field->value;
                        }
                    }

                    $data[$fieldname] = $fieldvalue;
                }
            }
        }

        parent::set_data((object) $data);
    }

    /**
     * Get field object by id from structure
     *
     * @param array $structure
     * @param int $fieldid
     * @return object|null
     */
    private function get_field_by_id($structure, $fieldid) {
        foreach ($structure as $category) {
            foreach ($category->fields as $field) {
                if ($field->id == $fieldid) {
                    return $field;
                }
            }
        }
        return null;
    }
}
