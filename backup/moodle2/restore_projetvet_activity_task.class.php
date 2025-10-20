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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/projetvet/backup/moodle2/restore_projetvet_stepslib.php');

/**
 * Testore task that provides all the settings and steps to perform one complete restore of the activity
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_projetvet_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        $this->add_step(new restore_projetvet_activity_structure_step('projetvet_structure', 'projetvet.xml'));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('projetvet', ['intro'], 'projetvet');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging to the activity to be executed by the link decoder
     *
     * @return array
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('PROJETVETVIEWBYID', '/mod/projetvet/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('PROJETVETINDEX', '/mod/projetvet/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Define the restoring rules for logs belonging to the activity to be executed by the link decoder.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('projetvet', 'add', 'view.php?id={course_module}', '{projetvet}');
        $rules[] = new restore_log_rule('projetvet', 'update', 'view.php?id={course_module}', '{projetvet}');
        $rules[] = new restore_log_rule('projetvet', 'view', 'view.php?id={course_module}', '{projetvet}');

        return $rules;
    }

    /**
     * Define the restoring rules for course associated to the activity to be executed by the link decoder.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        $rules[] = new restore_log_rule('projetvet', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
