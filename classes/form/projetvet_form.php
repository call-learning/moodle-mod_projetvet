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

        // Use the entrystatus from form data (set by button clicks).
        // If no specific status is set, use the current status from the entry.
        $currententrystatus = $data->entrystatus ?? 0;

        // Check if this is a button submission by looking for button_action in form data.
        if (isset($data->button_entrystatus)) {
            // Button was clicked, use the button's entrystatus.
            $entrystatus = $data->button_entrystatus;
        } else {
            // Regular form submission, progress to next status.
            $nextstatus = $currententrystatus + 1;
            $entrystatus = $nextstatus;
        }

        // Extract field values from form data - only for categories user can edit.
        $fields = [];
        $formsetidnumber = $data->formsetidnumber ?? 'activities';
        $structure = entries::get_form_structure($formsetidnumber);
        $context = $this->get_context_for_dynamic_submission();

        foreach ($structure as $category) {
            // Check if user can edit this category.
            $caneditcategory = entries::can_edit_category($category, $currententrystatus, $context);

            if ($caneditcategory) {
                // Only collect fields from categories the user can edit.
                foreach ($category->fields as $field) {
                    $fieldname = 'field_' . $field->id;
                    if (isset($data->$fieldname)) {
                        $fields[$field->id] = $data->$fieldname;
                    }
                }
            }
        }

        // Save files for filemanager fields - only for editable categories.
        foreach ($structure as $category) {
            // Check if user can edit this category.
            $caneditcategory = entries::can_edit_category($category, $currententrystatus, $context);

            if ($caneditcategory) {
                foreach ($category->fields as $field) {
                    if ($field->type === 'filemanager') {
                        $fieldname = 'field_' . $field->id;
                        if (isset($data->$fieldname)) {
                            // The itemid in the entry becomes the permanent storage location.
                            // We use the field->id combined with entryid to create a unique itemid.
                            $itemid = $entryid * 1000 + $field->id;

                            file_save_draft_area_files(
                                $data->$fieldname, // Draft itemid.
                                $context->id,
                                'mod_projetvet',
                                'entry_files',
                                $itemid,
                                [
                                    'subdirs' => 0,
                                    'maxbytes' => 0,
                                    'maxfiles' => 50,
                                ]
                            );

                            // Update the field value to store the permanent itemid.
                            $fields[$field->id] = $itemid;
                        }
                    }
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
            'tagconfirm',
            "$CFG->dirroot/mod/projetvet/classes/form/tagconfirm_element.php",
            'mod_projetvet\form\tagconfirm_element'
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

        // Get student email for contact button.
        $studentemail = '';
        if ($studentid) {
            $student = \core_user::get_user($studentid);
            if ($student) {
                $studentemail = $student->email;
            }
        }

        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->addElement('hidden', 'projetvetid', $projetvetid);
        $mform->addElement('hidden', 'studentid', $studentid);
        $mform->addElement('hidden', 'studentemail', $studentemail);
        $mform->addElement('hidden', 'entryid', $entryid);
        $mform->addElement('hidden', 'formsetidnumber', $formsetidnumber);
        $mform->setType('formsetidnumber', PARAM_ALPHANUMEXT);
        $mform->setType('studentemail', PARAM_EMAIL);
        $mform->addElement('hidden', 'entrystatus');
        $mform->setType('entrystatus', PARAM_INT);
        $mform->addElement('hidden', 'button_entrystatus');
        $mform->setType('button_entrystatus', PARAM_INT);

        // Get the context for capability checking.
        $context = context_module::instance($cmid);

        // Get current entry status if editing.
        $currententrystatus = 0;
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

            // Use the API function to determine if user can edit this category.
            $caneditcategory = entries::can_edit_category($category, $currententrystatus, $context);

            // Add category header.
            $mform->addElement('header', 'category_' . $category->id, $category->name);

            // Expand header if category entrystatus matches current entry status.
            if ($category->entrystatus == $currententrystatus || $currententrystatus == count($structure)) {
                $mform->setExpanded('category_' . $category->id);
            } else {
                $mform->setExpanded('category_' . $category->id, false);
            }

            // Array to collect button elements for grouping.
            $buttonelements = [];

            foreach ($category->fields as $field) {
                $fieldname = 'field_' . $field->id;

                // Use category-level edit permission for all fields in the category.
                $canediffield = $caneditcategory;

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
                        $textareaattributes = [
                            'rows' => $rows,
                            'placeholder' => $field->description,
                        ];
                        $mform->addElement('textarea', $fieldname, $field->name, $textareaattributes);
                        $mform->setType($fieldname, PARAM_TEXT);
                        break;

                    case 'number':
                        $attributes = [];
                        if (isset($configdata['max'])) {
                            $attributes['max'] = $configdata['max'];
                        }
                        $mform->addElement('float', $fieldname, $field->name, $attributes);
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

                    case 'tagconfirm':
                        // Get the source field idnumber from configdata.
                        $sourcefielidnumber = $configdata['tagselect'] ?? '';
                        $sourcetags = [];
                        $lookupfieldid = 0;

                        // Find the source field and get its selected values.
                        if ($sourcefielidnumber && $entryid) {
                            $entry = entries::get_entry($entryid);
                            foreach ($entry->categories as $entrycategory) {
                                foreach ($entrycategory->fields as $entryfield) {
                                    if ($entryfield->idnumber === $sourcefielidnumber) {
                                        // Decode the JSON value to get selected tags.
                                        $decoded = json_decode($entryfield->value, true);
                                        $sourcetags = is_array($decoded) ? $decoded : [];
                                        // Get the field ID for lookup data.
                                        $lookupfieldid = $entryfield->id;
                                        break 2;
                                    }
                                }
                            }
                        }

                        $mform->addElement('tagconfirm', $fieldname, $field->name, [
                            'sourcefielidnumber' => $sourcefielidnumber,
                            'sourcetags' => $sourcetags,
                            'lookupfieldid' => $lookupfieldid,
                        ]);
                        break;

                    case 'checkbox':
                        $mform->addElement('advcheckbox', $fieldname, $field->name, '', null, [0, 1]);
                        break;

                    case 'filemanager':
                        // Get configuration for file manager options.
                        $maxfiles = $configdata['maxfiles'] ?? 50;
                        $maxbytes = $configdata['maxbytes'] ?? 0; // 0 means use course/site limit.
                        $acceptedtypes = $configdata['accepted_types'] ?? '*';
                        $subdirs = $configdata['subdirs'] ?? 0;

                        $filemanageroptions = [
                            'subdirs' => $subdirs,
                            'maxbytes' => $maxbytes,
                            'areamaxbytes' => 10485760, // 10MB.
                            'maxfiles' => $maxfiles,
                            'accepted_types' => $acceptedtypes,
                        ];

                        $mform->addElement('filemanager', $fieldname, $field->name, null, $filemanageroptions);
                        break;

                    case 'button':
                        // Get button configuration.
                        $buttontext = $configdata['text'] ?? $field->name;
                        $buttonicon = $configdata['icon'] ?? '';
                        $buttonstatus = $configdata['entrystatus'] ?? 0;
                        $buttonstyle = $configdata['style'] ?? 'secondary';
                        $buttonaction = $configdata['action'] ?? '';

                        // Create button label with icon if specified.
                        $buttonlabel = $buttontext;
                        if (!empty($buttonicon)) {
                            $buttonlabel = '<i class="fa ' . $buttonicon . '"></i> ' . $buttontext;
                        }

                        // Create button attributes.
                        $buttonattributes = [
                            'class' => 'projetvet-form-button',
                            'data-entrystatus' => $buttonstatus,
                            'data-fieldid' => $field->id,
                        ];

                        if (!empty($buttonicon)) {
                            $buttonattributes['data-icon'] = $buttonicon;
                        }

                        if (!empty($buttonaction)) {
                            $buttonattributes['data-action-type'] = $buttonaction;
                        }

                        // Determine button styling based on style configuration.
                        $styleclass = 'btn-' . $buttonstyle;
                        $customclass = 'btn ' . $styleclass;

                        // Collect button element for grouping (don't add immediately).
                        $buttonelements[] = $mform->createElement('button', $fieldname, $buttonlabel, $buttonattributes, [
                            'customclassoverride' => $customclass . ' projetvet-form-button',
                        ]);
                        break;
                }
                $isrequired = !empty($configdata['required']) && $configdata['required'] == true;
                if ($isrequired && $canediffield && $field->type !== 'button') {
                    $mform->addRule($fieldname, null, 'required', null, 'client');
                }

                if (!empty($field->description) && $field->type !== 'button') {
                    $mform->addHelpButton($fieldname, $field->idnumber, 'mod_projetvet');
                }

                // If user cannot edit this field, freeze it but preserve the value using setConstant.
                if (!$canediffield) {
                    $mform->freeze($fieldname);
                    // For frozen fields, we need to ensure the value is preserved on submission.
                    // We'll set it as constant in set_data_for_dynamic_submission instead.
                }
            }
            // Add all button elements as a group if any were found.
            if (!empty($buttonelements) && ($category->entrystatus == $currententrystatus) && $caneditcategory) {
                $mform->addGroup($buttonelements, 'buttongroup', '', [' '], false);
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
            'entrystatus' => 0, // Default to draft for new entries.
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

                    // Skip button fields - they don't store values and shouldn't have their labels overwritten.
                    if ($fieldobj->type === 'button') {
                        continue;
                    }

                    // For autocomplete, tagselect, and tagconfirm fields, decode JSON to array.
                    if (in_array($fieldobj->type, ['autocomplete', 'tagselect', 'tagconfirm'])) {
                        $decoded = json_decode($field->value, true);
                        $fieldvalue = is_array($decoded) ? $decoded : [];
                    } else if ($fieldobj->type === 'filemanager') {
                        // For filemanager, prepare the draft area with existing files.
                        // Get a new draft itemid for this specific field in this specific entry.
                        $draftitemid = file_get_submitted_draft_itemid($fieldname . '_' . $data['entryid']);

                        // If there's no submitted draft (i.e., we're loading the form),
                        // and there are existing files, copy them to a new draft area.
                        if (!empty($field->value)) {
                            $context = $this->get_context_for_dynamic_submission();
                            file_prepare_draft_area(
                                $draftitemid,
                                $context->id,
                                'mod_projetvet',
                                'entry_files',
                                $field->value, // The permanent itemid from the database.
                                [
                                    'subdirs' => 0,
                                    'maxbytes' => 0,
                                    'maxfiles' => 50,
                                ]
                            );
                        }
                        $fieldvalue = $draftitemid;
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
