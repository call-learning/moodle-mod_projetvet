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

namespace mod_projetvet\local;

use mod_projetvet\local\api\groups;

/**
 * Builds the content of the "assign a teacher" selection popup.
 *
 * The popup displays the students being assigned and a teachers selection report
 * (with a preselected radio for the teacher already assigned to those students).
 * This class is used by the {@see \mod_projetvet\external\get_assign_teacher_modal}
 * web service so the content can be fetched on demand for a plain modal, without
 * embedding a reportbuilder report (and its own filter form) inside a form.
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_selection {
    /**
     * Get the user ID of the teacher already assigned to all the selected students, if any.
     *
     * The value is used to preselect the teacher radio in the selection report.
     *
     * @param int $cmid Course module ID
     * @param int $projetvetid ProjetVet ID
     * @param int[] $studentids List of student user IDs
     * @return int Teacher user ID or 0 when the selected students have no common assigned teacher
     */
    public static function get_preselected_teacher_id(int $cmid, int $projetvetid, array $studentids): int {
        if (empty($studentids)) {
            return 0;
        }

        $teacherids = array_map('intval', groups::get_all_teachers($cmid));
        if (empty($teacherids)) {
            return 0;
        }

        $tutorids = [];
        foreach ($studentids as $studentid) {
            $tutor = groups::get_student_primary_tutor((int) $studentid, $projetvetid);
            $tutorid = $tutor === null ? 0 : (int) $tutor->id;
            if (!$tutorid || !in_array($tutorid, $teacherids, true)) {
                // A teacher can only be preselected when every selected student has the same
                // eligible primary tutor.
                return 0;
            }
            $tutorids[] = $tutorid;
        }

        if (count(array_unique($tutorids)) === 1) {
            return (int) reset($tutorids);
        }

        return 0;
    }

    /**
     * Get the HTML for displaying selected students.
     *
     * @param int $cmid Course module ID
     * @param int $projetvetid ProjetVet ID
     * @param int[] $studentids List of student user IDs
     * @return string HTML output of the student list
     */
    public static function get_selected_students_html(int $cmid, int $projetvetid, array $studentids): string {
        global $OUTPUT;

        if (empty($studentids)) {
            return '';
        }

        // Get all students from the course (not filtered by assignment status).
        $allstudents = groups::get_all_students($cmid);

        // Filter to only selected students.
        $selectedstudents = [];
        foreach ($allstudents as $student) {
            if (in_array($student['uniqueid'], $studentids)) {
                $selectedstudents[] = $student;
            }
        }

        if (empty($selectedstudents)) {
            return '';
        }

        // Prepare context for template.
        $context = [
            'studentcount' => count($selectedstudents),
            'students' => $selectedstudents,
        ];

        return $OUTPUT->render_from_template('mod_projetvet/form/selected_students', $context);
    }

    /**
     * Get the HTML for the teachers selection report.
     *
     * @param int $cmid Course module ID
     * @param int $projetvetid ProjetVet ID
     * @param int $selectedteacherid User ID of the teacher to preselect in the report, or 0
     * @return string HTML output of the report
     */
    public static function get_teachers_report_html(int $cmid, int $projetvetid, int $selectedteacherid = 0): string {
        global $PAGE;

        try {
            $parameters = [
                'cmid' => $cmid,
                'projetvetid' => $projetvetid,
            ];
            if ($selectedteacherid > 0) {
                $parameters['selectedteacherid'] = $selectedteacherid;
            }

            // Create the report instance.
            $report = \core_reportbuilder\system_report_factory::create(
                \mod_projetvet\reportbuilder\local\systemreports\assignments_teachers_selection::class,
                $PAGE->context,
                '',
                '',
                0,
                $parameters
            );

            // Get the report output.
            $output = $report->output();

            // Handle null or unexpected output.
            if ($output === null) {
                return '';
            }

            return $output ?? '';
        } catch (\Exception $e) {
            debugging('Failed to load teachers report: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * Build the full HTML for the "assign a teacher" popup body.
     *
     * The returned array has two keys:
     * - html (string): The popup body HTML (selected students + teachers selection report).
     * - preselectedteacherid (int): The teacher user ID preselected in the report, or 0.
     *
     * @param int $cmid Course module ID
     * @param int $projetvetid ProjetVet ID
     * @param int[] $studentids List of student user IDs
     * @return array{html: string, preselectedteacherid: int}
     */
    public static function get_modal_html(int $cmid, int $projetvetid, array $studentids): array {
        $preselectedteacherid = self::get_preselected_teacher_id($cmid, $projetvetid, $studentids);

        $html = '';
        $studentshtml = self::get_selected_students_html($cmid, $projetvetid, $studentids);
        if (!empty($studentshtml)) {
            $html .= $studentshtml;
        }

        $reporthtml = self::get_teachers_report_html($cmid, $projetvetid, $preselectedteacherid);
        if (!empty($reporthtml)) {
            $html .= '<div class="assignments-teachers-report">' . $reporthtml . '</div>';
        }

        return [
            'html' => $html,
            'preselectedteacherid' => $preselectedteacherid,
        ];
    }
}
