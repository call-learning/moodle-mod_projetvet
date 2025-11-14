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
use lang_string;

/**
 * Activity data template entity
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_data extends persistent {
    /**
     * Current table
     */
    const TABLE = 'projetvet_form_data';
    /**
     * Internal storage for field.
     */
    const FIELD_TYPE_TO_INTERNAL = [
        'text' => 'charvalue',
        'number' => 'intvalue',
        'textarea' => 'textvalue',
        'select' => 'intvalue',
        'checkbox' => 'intvalue',
        'autocomplete' => 'textvalue',
        'tagselect' => 'textvalue',
        'date' => 'intvalue',
        'datetime' => 'intvalue',
        'tagconfirm' => 'textvalue',
    ];
    /**
     * @var form_field $field The field object
     */

    private $field = null;

    /**
     * Return the custom definition of the properties of this model.
     *
     * Each property MUST be listed here.
     *
     * @return array Where keys are the property names.
     */
    protected static function define_properties() {
        return [
            'fieldid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'fieldid'),
            ],
            'entryid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'entryid'),
            ],
            'intvalue' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_INT,
                'default' => 0,
                'message' => new lang_string('invaliddata', 'projetvet', 'intvalue'),
            ],
            'decvalue' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_FLOAT,
                'default' => 0.0,
                'message' => new lang_string('invaliddata', 'projetvet', 'decvalue'),
            ],
            'shortcharvalue' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_TEXT,
                'default' => '',
                'message' => new lang_string('invaliddata', 'projetvet', 'shortcharvalue'),
            ],
            'charvalue' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_TEXT,
                'default' => '',
                'message' => new lang_string('invaliddata', 'projetvet', 'charvalue'),
            ],
            'textvalue' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_TEXT,
                'default' => '',
                'message' => new lang_string('invaliddata', 'projetvet', 'textvalue'),
            ],
        ];
    }

    /**
     * Get the display value of the data.
     *
     * @return string
     */
    public function get_display_value() {
        $value = $this->retrieve_value();
        $field = $this->get_field();
        return $field->display_value($value);
    }

    /**
     * Retrieve the value from the right field.
     *
     * @return mixed|null
     */
    private function retrieve_value() {
        $fieldtype = $this->field_type_to_field();
        return $this->raw_get($fieldtype);
    }

    /**
     * Retrieve the value from the right field.
     *
     * @return mixed|null
     */
    private function field_type_to_field() {
        $field = $this->get_field();
        $type = $field->get('type');
        return self::FIELD_TYPE_TO_INTERNAL[$type] ?? 'charvalue';
    }

    /**
     * Retrieve the field object.
     *
     * @return form_field
     */
    private function get_field() {
        if (!isset($this->field)) {
            $this->field = form_field::get_record(['id' => $this->get('fieldid')]);
        }
        return $this->field;
    }

    /**
     * Set the value of the data.
     *
     * @param mixed $value
     * @return void
     */
    public function set_value($value) {
        $fieldtype = $this->field_type_to_field();
        $field = $this->get_field();
        $value = $field->convert_to_raw_value($value);
        $this->set($fieldtype, $value);
    }

    /**
     * Retrieve the value from the right field.
     *
     * @return mixed
     */
    public function get_value() {
        $fieldtype = $this->field_type_to_field();
        return $this->get($fieldtype);
    }
}
