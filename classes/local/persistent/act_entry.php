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
 * Activity entry entity
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class act_entry extends persistent {
    /**
     * Current table
     */
    const TABLE = 'projetvet_act_entry';

    /**
     * Return the custom definition of the properties of this model.
     *
     * Each property MUST be listed here.
     *
     * @return array Where keys are the property names.
     */
    protected static function define_properties() {
        return [
            'projetvetid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'projetvetid'),
            ],
            'studentid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'studentid'),
            ],
        ];
    }

    /**
     * Check if the current user can edit this entry
     *
     * @return bool
     */
    public function can_edit(): bool {
        global $USER;
        // Students can edit their own entries
        if ($this->get('studentid') == $USER->id) {
            return true;
        }
        // Teachers can edit any entry
        $context = \context_module::instance($this->get_context()->instanceid);
        if (has_capability('mod/projetvet:edit', $context)) {
            return true;
        }
        return false;
    }

    /**
     * Check if the current user can delete this entry
     *
     * @return bool
     */
    public function can_delete(): bool {
        global $USER;
        // Students can delete their own entries
        if ($this->get('studentid') == $USER->id) {
            return true;
        }
        // Teachers can delete any entry
        $context = \context_module::instance($this->get_context()->instanceid);
        if (has_capability('mod/projetvet:edit', $context)) {
            return true;
        }
        return false;
    }

    /**
     * Get the context for this entry
     *
     * @return \context_module
     */
    protected function get_context() {
        global $DB;
        $cm = get_coursemodule_from_instance('projetvet', $this->get('projetvetid'));
        return \context_module::instance($cm->id);
    }
}
