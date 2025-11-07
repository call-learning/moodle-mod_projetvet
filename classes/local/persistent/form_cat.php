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
 * Activity field category template entity
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_cat extends persistent {
    /**
     * Current table
     */
    const TABLE = 'projetvet_form_cat';

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
            'description' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_TEXT,
                'message' => new lang_string('invaliddata', 'projetvet', 'description'),
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
            'sortorder' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'sortorder'),
            ],
        ];
    }
}
