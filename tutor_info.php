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

/**
 * Edit the tutor's practical info, stored as a user preference.
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();

global $DB, $USER, $SITE, $PAGE, $OUTPUT;

// Only tutors (primary owners of at least one projetvet group) can edit their practical info.
if (!$DB->record_exists('projetvet_groups', ['ownerid' => $USER->id])) {
    throw new moodle_exception('accessdenied');
}

$PAGE->set_url('/mod/projetvet/tutor_info.php');
$PAGE->set_title(get_string('practicalinfo_title', 'mod_projetvet'));
$PAGE->set_heading($SITE->fullname);
$PAGE->set_context(context_user::instance($USER->id));

$saved = optional_param('saved', 0, PARAM_BOOL);

/**
 * Form to edit the tutor's practical info.
 *
 * @package mod_projetvet
 */
class projetvet_tutor_info_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        global $USER;
        $mform = $this->_form;

        $current = get_user_preferences('projetvet_tutor_info', '', $USER->id);

        $mform->addElement(
            'textarea',
            'tutorinfo',
            get_string('practicalinfo_settings', 'mod_projetvet'),
            ['rows' => 8, 'cols' => 60]
        );
        $mform->setType('tutorinfo', PARAM_RAW);
        $mform->setDefault('tutorinfo', $current);

        $mform->addElement('submit', 'submitbutton', get_string('save'));
    }
}

$mform = new projetvet_tutor_info_form();

if ($mform->is_submitted() && $mform->is_validated()) {
    $data = $mform->get_data();
    set_user_preference('projetvet_tutor_info', $data->tutorinfo, $USER->id);
    redirect(new moodle_url('/mod/projetvet/tutor_info.php', ['saved' => 1]));
}

echo $OUTPUT->header();
if ($saved) {
    echo $OUTPUT->box(get_string('practicalinfo_savesuccess', 'mod_projetvet'), 'notifysuccess');
    echo html_writer::empty_tag('br');
}
echo $OUTPUT->heading(get_string('practicalinfo_title', 'mod_projetvet'));
echo html_writer::div(get_string('practicalinfo_hint', 'mod_projetvet'));
$mform->display();
echo $OUTPUT->footer();
