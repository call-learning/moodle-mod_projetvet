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
 * Thesis persistent class.
 *
 * @package   mod_projetvet
 * @copyright 2025 onwards Bas Brands
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class thesis extends persistent {

    /** Table name for the persistent. */
    const TABLE = 'projetvet_thesis';

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
            'thesis' => [
                'type' => PARAM_TEXT,
            ],
            'otherdata' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }

    /**
     * Get the other data as an associative array.
     *
     * @return array|null
     */
    public function get_other_data_array() {
        $otherdata = $this->get('otherdata');
        if (empty($otherdata)) {
            return [];
        }
        $decoded = json_decode($otherdata, true);
        return $decoded !== null ? $decoded : [];
    }

    /**
     * Set other data from an associative array.
     *
     * @param array $data
     * @return $this
     */
    public function set_other_data_array(array $data) {
        $this->set('otherdata', json_encode($data));
        return $this;
    }
}
