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

use moodleform;

/**
 * Form to compose and send a custom broadcast message to assigned students.
 *
 * The list of selectable students is restricted (on the page) to the students
 * assigned to the current tutor. Recipient validation is performed again on
 * the page at submission time, so the form itself only declares the fields.
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class broadcast_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        global $CFG;
        $mform = $this->_form;
        $customdata = $this->_customdata;

        // Session key (CSRF protection) — the page requires it before any send.
        $mform->addElement('hidden', 'sesskey', sesskey());
        $mform->setType('sesskey', PARAM_RAW);

        // The activity id. Carried as a hidden field so it survives the form
        // submission (the page re-loads it to reload the same activity).
        if (!empty($customdata['id'])) {
            $mform->addElement('hidden', 'id', $customdata['id']);
            $mform->setType('id', PARAM_INT);
        }

        // Where to go back to once the message has been queued.
        if (!empty($customdata['returnurl'])) {
            $mform->addElement('hidden', 'returnurl', $customdata['returnurl']);
            $mform->setType('returnurl', PARAM_LOCALURL);
        }

        // Student selection (checkbox group). The choices are set by the caller
        // (broadcast.php) from the tutor's assigned students. Each student gets
        // an advcheckbox in a shared group so a "select all" controller can be
        // added. Recipients are submitted as students[id] => 0|1.
        $mform->addElement('header', 'studentsheader', get_string('broadcast_select_recipients', 'mod_projetvet'));
        $needsstudents = false;
        foreach ($customdata['students'] as $student) {
            $mform->addElement('advcheckbox', 'students[' . $student->id . ']', fullname($student), null, ['group' => 1]);
            $mform->setType('students[' . $student->id . ']', PARAM_INT);
            $mform->setDefault('students[' . $student->id . ']', 1);
            $needsstudents = true;
        }
        if ($needsstudents) {
            // Add the "select all/none" controller for the checkbox group.
            $this->add_checkbox_controller(1, get_string('broadcast_select_all', 'mod_projetvet'), null, 1);
        }

        $mform->addElement('text', 'subject', get_string('broadcast_subject_label', 'mod_projetvet'), ['maxlength' => 255]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('broadcast_subject_empty', 'mod_projetvet'), 'required', null, 'server');

        $mform->addElement('textarea', 'body', get_string('broadcast_body_label', 'mod_projetvet'), ['rows' => 6, 'cols' => 60]);
        $mform->setType('body', PARAM_RAW);
        $mform->addRule('body', get_string('broadcast_body_empty', 'mod_projetvet'), 'required', null, 'server');

        $this->add_action_buttons(
            false,
            get_string('broadcast_send', 'mod_projetvet'),
            get_string('cancel', 'moodle')
        );
    }
}
