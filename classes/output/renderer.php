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

namespace mod_projetvet\output;

/**
 * Renderer for mod_projetvet
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the student list for teachers/managers
     *
     * @param \stdClass $moduleinstance The projetvet instance
     * @param \stdClass $cm The course module
     * @param \context_module $context The context
     * @return string HTML to output
     */
    public function render_student_list($moduleinstance, $cm, $context) {
        global $USER;

        $studentlist = new student_list($moduleinstance, $cm, $context, $USER->id);
        return $this->render_from_template('mod_projetvet/student_list', $studentlist->export_for_template($this));
    }

    /**
     * Render the info section above the activity list
     *
     * @param \stdClass $moduleinstance The projetvet instance
     * @param \stdClass $cm The course module
     * @param \context_module $context The context
     * @param int $studentid The student ID
     * @return string HTML to output
     */
    public function render_student_info($moduleinstance, $cm, $context, $studentid) {
        $studentinfo = new student_info($moduleinstance, $cm, $studentid);
        return $this->render_from_template('mod_projetvet/student_info', $studentinfo->export_for_template($this));
    }

    /**
     * Render the entry list for a specific student (student view or teacher/manager viewing student)
     *
     * @param \stdClass $moduleinstance The projetvet instance
     * @param \stdClass $cm The course module
     * @param \context_module $context The context
     * @param int $studentid The student ID
     * @param string $formsetidnumber The form set idnumber (default: 'activities')
     * @return string HTML to output
     */
    public function render_entry_list($moduleinstance, $cm, $context, $studentid, $formsetidnumber = 'activities') {
        global $USER;

        // Determine if viewer has elevated permissions.
        $canviewall = has_capability('mod/projetvet:viewallactivities', $context);
        $isteacher = $canviewall && $studentid != $USER->id;

        $entrylist = new entry_list($moduleinstance, $cm, $studentid, $isteacher, $formsetidnumber);
        return $this->render_from_template('mod_projetvet/entry_list', $entrylist->export_for_template($this));
    }
}
