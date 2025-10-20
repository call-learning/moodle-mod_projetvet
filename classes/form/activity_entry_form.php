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

use context;
use context_module;
use core_form\dynamic_form;
use mod_projetvet\local\api\activities;
use mod_projetvet\local\persistent\act_field;
use moodle_exception;
use moodle_url;

/**
 * Class activity_entry_form
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_entry_form extends dynamic_form {
    /**
     * Process the form submission
     *
     * @return array
     * @throws moodle_exception
     */
    public function process_dynamic_submission(): array {
        global $USER;
        $data = $this->get_data();

        // Extract field values from form data.
        $fields = [];
        $structure = activities::get_activity_structure();
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
            activities::update_activity($data->entryid, $fields);
            $entryid = $data->entryid;
        } else {
            // Create new entry.
            $studentid = $data->studentid ?? $USER->id;
            $entryid = activities::create_activity($data->projetvetid, $studentid, $fields);
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
            $entry = activities::get_entry($entryid);
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
        $mform = $this->_form;
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $projetvetid = $this->optional_param('projetvetid', null, PARAM_INT);
        $studentid = $this->optional_param('studentid', null, PARAM_INT);
        $entryid = $this->optional_param('entryid', 0, PARAM_INT);

        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->addElement('hidden', 'projetvetid', $projetvetid);
        $mform->addElement('hidden', 'studentid', $studentid);
        $mform->addElement('hidden', 'entryid', $entryid);

        // Get the activity structure and add fields.
        $structure = activities::get_activity_structure();

        foreach ($structure as $category) {
            // Add category header.
            $mform->addElement('header', 'category_' . $category->id, $category->name);

            foreach ($category->fields as $field) {
                $fieldname = 'field_' . $field->id;
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

                    case 'checkbox':
                        $mform->addElement('advcheckbox', $fieldname, $field->name, '', null, [0, 1]);
                        break;
                }

                if (!empty($field->description)) {
                    $mform->addHelpButton($fieldname, $field->idnumber, 'mod_projetvet');
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

        $data = [
            'cmid' => $this->optional_param('cmid', 0, PARAM_INT),
            'projetvetid' => $this->optional_param('projetvetid', 0, PARAM_INT),
            'studentid' => $this->optional_param('studentid', $USER->id, PARAM_INT),
            'entryid' => $this->optional_param('entryid', 0, PARAM_INT),
        ];

        // If editing an existing entry, load its data.
        if (!empty($data['entryid'])) {
            $entry = activities::get_entry($data['entryid']);
            foreach ($entry->categories as $category) {
                foreach ($category->fields as $field) {
                    $fieldname = 'field_' . $field->id;
                    $data[$fieldname] = $field->value;
                }
            }
        }

        parent::set_data((object) $data);
    }
}
