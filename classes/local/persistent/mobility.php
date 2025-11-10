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

/**
 * Mobility persistent class.
 *
 * @package   mod_projetvet
 * @copyright 2025 onwards Bas Brands
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobility extends persistent {
    /** Table name for the persistent. */
    const TABLE = 'projetvet_mobility';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'projetvetid' => [
                'type' => PARAM_INT,
            ],
            'userid' => [
                'type' => PARAM_INT,
            ],
            'title' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => '',
            ],
            'erasmus' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'fmp' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }
}
