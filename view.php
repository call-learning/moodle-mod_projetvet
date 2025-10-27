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
 * View Projetvet instance
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

// Course module id.
$id = optional_param('id', 0, PARAM_INT);

// Activity instance id.
$p = optional_param('p', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('projetvet', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('projetvet', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('projetvet', ['id' => $p], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('projetvet', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

// Check if viewing a specific student (for teachers).
$studentid = optional_param('studentid', 0, PARAM_INT);

\mod_projetvet\event\course_module_viewed::create_from_record($moduleinstance, $cm, $course)->trigger();

$PAGE->set_url('/mod/projetvet/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Determine if user is a teacher (has viewallactivities capability).
$isteacher = has_capability('mod/projetvet:viewallactivities', $context);

// Only load JavaScript for students or when teacher is viewing a specific student.
if (!$isteacher || $studentid) {
    $PAGE->requires->js_call_amd('mod_projetvet/activity_entry_form', 'init');
    $PAGE->requires->js_call_amd('mod_projetvet/student_info_forms', 'init');
}

echo $OUTPUT->header();

// Display the module introduction.
echo $OUTPUT->box(format_module_intro('projetvet', $moduleinstance, $cm->id), 'generalbox', 'intro');

// Get the renderer.
$renderer = $PAGE->get_renderer('mod_projetvet');

// Display appropriate view based on user role.
if ($isteacher && !$studentid) {
    // Teacher view: show list of students.
    echo $renderer->render_teacher_student_list($moduleinstance, $cm, $context);
} else {
    // Student view or teacher viewing a specific student.
    $viewingstudentid = $studentid ? $studentid : $USER->id;

    // If teacher is viewing a student, verify they have access (same group).
    if ($isteacher && $studentid) {
        // Teachers can view any student in their groups.
        echo $renderer->render_activity_list($moduleinstance, $cm, $context, $viewingstudentid, true);
    } else {
        // Student viewing their own activities.
        echo $renderer->render_activity_list($moduleinstance, $cm, $context, $viewingstudentid, false);
    }
}

echo $OUTPUT->footer();
