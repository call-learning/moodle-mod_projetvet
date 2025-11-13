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

namespace mod_projetvet\local\persistent;

use core\persistent;
use DateTime;
use lang_string;

/**
 * Activity field template entity
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_field extends persistent {
    /**
     * @var string TABLE
     */
    const TABLE = 'projetvet_form_field';

    /**
     * @var array FIELD_TYPES
     */
    const FIELD_TYPES = [
        'text',
        'number',
        'textarea',
        'select',
        'checkbox',
        'autocomplete',
        'tagselect',
        'date',
        'button',
        'tagconfirm',
        'filemanager',
    ];

    /**
     * Return the custom definition of the properties of this model.
     *
     * Each property MUST be listed here.
     *
     * @return array Where keys are the property names.
     */
    protected static function define_properties() {
        return [
            'idnumber' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_ALPHANUMEXT,
                'message' => new lang_string('invaliddata', 'projetvet', 'idnumber'),
            ],
            'name' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_TEXT,
                'message' => new lang_string('invaliddata', 'projetvet', 'shortname'),
            ],
            'type' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_TEXT,
                'message' => new lang_string('invaliddata', 'projetvet', 'type'),
            ],
            'description' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_TEXT,
                'message' => new lang_string('invaliddata', 'projetvet', 'description'),
            ],
            'sortorder' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'sortorder'),
            ],
            'categoryid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'categoryid'),
            ],
            'configdata' => [
                'null' => NULL_ALLOWED,
                'default' => null,
                'type' => PARAM_TEXT,
                'message' => new lang_string('invaliddata', 'projetvet', 'configdata'),
            ],
            'capability' => [
                'null' => NULL_ALLOWED,
                'default' => null,
                'type' => PARAM_TEXT,
                'message' => new lang_string('invaliddata', 'projetvet', 'capability'),
            ],
            'entrystatus' => [
                'null' => NULL_NOT_ALLOWED,
                'default' => 0,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'entrystatus'),
            ],
            'listorder' => [
                'null' => NULL_NOT_ALLOWED,
                'default' => 0,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'listorder'),
            ],
        ];
    }

    /**
     * Validate type
     *
     * @param string $type
     * @return bool
     */
    protected function validate_type($type) {
        if (!in_array($type, self::FIELD_TYPES)) {
            return false;
        }
        return true;
    }

    /**
     * Display a given raw value as string.
     *
     * @param mixed $value
     * @return string
     */
    public function display_value($value) {
        $type = $this->raw_get('type');
        if ($type === null) {
            return '';
        }
        switch ($this->get('type')) {
            case 'text':
            case 'textarea':
            case 'number':
                return $value;
            case 'date':
                if (empty($value)) {
                    return '';
                }
                return userdate($value, get_string('strftimedatefullshort', 'core_langconfig'));
            case 'checkbox':
                return $value ? get_string('yes') : get_string('no');
            case 'select':
                $configdata = json_decode(stripslashes($this->get('configdata')), true);
                if (!empty($configdata['options'])) {
                    foreach ($configdata['options'] as $key => $option) {
                        if ($key == $value) {
                            return $option;
                        }
                    }
                }
                return '';
            case 'autocomplete':
                $configdata = json_decode(stripslashes($this->get('configdata')), true);
                $selectedvalues = json_decode($value, true);
                if (!is_array($selectedvalues)) {
                    return '';
                }
                $displayvalues = [];
                if (!empty($configdata['options'])) {
                    foreach ($selectedvalues as $selectedkey) {
                        if (isset($configdata['options'][$selectedkey])) {
                            $displayvalues[] = $configdata['options'][$selectedkey];
                        }
                    }
                }
                return implode(', ', $displayvalues);
            case 'tagselect':
                $selectedvalues = json_decode($value, true);
                if (!is_array($selectedvalues)) {
                    return '';
                }
                // Get lookup map from field_data table.
                $lookupmap = field_data::get_lookup_map($this->get('id'), 'item');
                $displayvalues = [];
                foreach ($selectedvalues as $uniqueid) {
                    if (isset($lookupmap[$uniqueid])) {
                        $displayvalues[] = $lookupmap[$uniqueid];
                    }
                }
                return implode(', ', $displayvalues);
            case 'tagconfirm':
                // Same as tagselect - display confirmed tags.
                $selectedvalues = json_decode($value, true);
                if (!is_array($selectedvalues)) {
                    return '';
                }
                // Need to get the lookup field ID from configdata.
                $configdata = json_decode(stripslashes($this->get('configdata')), true);
                $sourcefielidnumber = $configdata['tagselect'] ?? '';
                if (!$sourcefielidnumber) {
                    return '';
                }
                // Find the source field to get its lookup data.
                // This is a simplified version - in production you'd cache this.
                $sourcefield = self::get_record(['idnumber' => $sourcefielidnumber]);
                if (!$sourcefield) {
                    return '';
                }
                $lookupmap = field_data::get_lookup_map($sourcefield->get('id'), 'item');
                $displayvalues = [];
                foreach ($selectedvalues as $uniqueid) {
                    if (isset($lookupmap[$uniqueid])) {
                        $displayvalues[] = $lookupmap[$uniqueid];
                    }
                }
                return implode(', ', $displayvalues);
            case 'filemanager':
                // For filemanager, value is the itemid. Display file count or links.
                if (empty($value)) {
                    return get_string('nofiles', 'mod_projetvet');
                }
                $fs = get_file_storage();
                $files = $fs->get_area_files(
                    \context_system::instance()->id,
                    'mod_projetvet',
                    'entry_files',
                    $value,
                    'filename',
                    false
                );
                if (empty($files)) {
                    return get_string('nofiles', 'mod_projetvet');
                }
                $filenames = array_map(function ($file) {
                    return $file->get_filename();
                }, $files);
                return implode(', ', $filenames);
        }
        return '';
    }

    /**
     * Convert a raw value to a value that can be stored in the database.
     * @param mixed $value
     * @return mixed
     */
    public function convert_to_raw_value(mixed $value) {
        switch ($this->get('type')) {
            case 'text':
            case 'textarea':
                return $value;
            case 'number':
            case 'checkbox':
            case 'date':
                return intval($value);
            case 'select':
                if (is_numeric($value)) {
                    return intval($value);
                }
                $configdata = json_decode(stripslashes($this->get('configdata')), true);
                if (!empty($configdata['options'])) {
                    foreach ($configdata['options'] as $key => $option) {
                        if ($option == $value) {
                            return $key;
                        }
                    }
                }
                return 0;
            case 'autocomplete':
            case 'tagselect':
            case 'tagconfirm':
                if (is_array($value)) {
                    return json_encode(array_values($value));
                }
                return json_encode([]);
            case 'filemanager':
                // For filemanager, value is the draft itemid that will be used to save files.
                // We store the itemid in the database.
                return intval($value);
        }
    }
}
