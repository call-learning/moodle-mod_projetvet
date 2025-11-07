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
 * Activity entry entity
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_entry extends persistent {
    /**
     * Current table
     */
    const TABLE = 'projetvet_form_entry';

    /**
     * Entry status constants
     */
    const STATUS_DRAFT = 0;
    const STATUS_SUBMITTED = 1;
    const STATUS_VALIDATED = 2;
    const STATUS_COMPLETED = 3;

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
            ],
            'formsetid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            'studentid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            'entrystatus' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'default' => 0,
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

        $context = $this->get_context();
        $entrystatus = $this->get('entrystatus');
        $isstudent = $this->get('studentid') == $USER->id;

        // STATUS_DRAFT (0): Only the student can edit their own entry.
        if ($entrystatus == self::STATUS_DRAFT) {
            return $isstudent && has_capability('mod/projetvet:submit', $context, $USER->id);
        }

        // STATUS_SUBMITTED (1): Only teachers with edit capability can edit.
        if ($entrystatus == self::STATUS_SUBMITTED) {
            return has_capability('mod/projetvet:edit', $context, $USER->id);
        }

        // STATUS_VALIDATED (2): Student can edit again.
        if ($entrystatus == self::STATUS_VALIDATED) {
            return $isstudent && has_capability('mod/projetvet:submit', $context, $USER->id);
        }

        // STATUS_COMPLETED (3): Only users with viewallstudents capability (managers) can edit.
        if ($entrystatus == self::STATUS_COMPLETED) {
            return has_capability('mod/projetvet:viewallstudents', $context, $USER->id);
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

    /**
     * Hook to execute after delete.
     *
     * @param bool $result
     * @return void
     */
    protected function after_delete($result) {
        if ($result) {
            // Delete all associated form_data records using the persistent class.
            $datarecords = form_data::get_records(['entryid' => $this->get('id')]);
            foreach ($datarecords as $datarecord) {
                $datarecord->delete();
            }
        }
    }
}
