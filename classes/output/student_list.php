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

use mod_projetvet\local\api\entries;
use mod_projetvet\local\persistent\form_entry;
use renderer_base;
use renderable;
use templatable;
use moodle_url;

/**
 * Student list renderable class.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_list implements renderable, templatable {
    /**
     * @var object $moduleinstance The module instance.
     */
    protected $moduleinstance;

    /**
     * @var object $cm The course module.
     */
    protected $cm;

    /**
     * @var object $context The context.
     */
    protected $context;

    /**
     * @var int $userid The user ID viewing the list.
     */
    protected $userid;

    /**
     * @var bool $ismanager Whether the user is a manager (can view all students).
     */
    protected $ismanager;

    /**
     * Constructor.
     *
     * @param object $moduleinstance The module instance
     * @param object $cm The course module
     * @param object $context The context
     * @param int $userid The user ID viewing the list
     */
    public function __construct($moduleinstance, $cm, $context, $userid) {
        $this->moduleinstance = $moduleinstance;
        $this->cm = $cm;
        $this->context = $context;
        $this->userid = $userid;
        $this->ismanager = has_capability('mod/projetvet:viewallstudents', $context, $userid);
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        // Get students based on role - managers see all, teachers see their groups.
        if ($this->ismanager) {
            $students = $this->get_all_students();
        } else {
            $students = $this->get_students_in_teacher_groups();
        }

        $data = [
            'hasstudents' => !empty($students),
            'students' => [],
        ];

        foreach ($students as $student) {
            // Only show students with submitted entries.
            $hassubmitted = $this->student_has_submitted_entries($student->id);
            if (!$hassubmitted) {
                continue;
            }

            try {
                $activitylist = entries::get_entry_list($this->moduleinstance->id, $student->id);
                $count = count($activitylist);
            } catch (\Exception $e) {
                $count = 0;
            }

            $viewurl = new moodle_url('/mod/projetvet/view.php', [
                'id' => $this->cm->id,
                'studentid' => $student->id,
            ]);

            $data['students'][] = [
                'fullname' => fullname($student),
                'activitiescount' => $count,
                'viewurl' => $viewurl->out(false),
            ];
        }

        // Update hasstudents after filtering.
        $data['hasstudents'] = !empty($data['students']);

        return $data;
    }

    /**
     * Get all students enrolled in the course with submit capability.
     *
     * @return array Array of student user objects
     */
    protected function get_all_students() {
        $enrolled = get_enrolled_users($this->context, 'mod/projetvet:submit', 0, 'u.*', 'u.lastname ASC, u.firstname ASC');
        return array_values($enrolled);
    }

    /**
     * Get students in the same groups as the teacher.
     *
     * @return array Array of student user objects
     */
    protected function get_students_in_teacher_groups() {
        $teachergroups = groups_get_user_groups($this->cm->course, $this->userid);

        if (empty($teachergroups) || empty($teachergroups[0])) {
            return [];
        }

        $students = [];
        $seenstudents = [];

        foreach ($teachergroups[0] as $groupid) {
            $groupstudents = groups_get_members($groupid, 'u.*', 'u.lastname ASC, u.firstname ASC');
            foreach ($groupstudents as $student) {
                if (
                    has_capability('mod/projetvet:submit', $this->context, $student->id) &&
                    !isset($seenstudents[$student->id])
                ) {
                    $students[] = $student;
                    $seenstudents[$student->id] = true;
                }
            }
        }

        return $students;
    }

    /**
     * Check if a student has any submitted entries.
     *
     * @param int $studentid The student ID
     * @return bool True if student has submitted entries
     */
    protected function student_has_submitted_entries(int $studentid): bool {
        global $DB;

        // Check for entries with status submitted or higher.
        $sql = "SELECT COUNT(*)
                  FROM {projetvet_form_entry}
                 WHERE projetvetid = :projetvetid
                   AND studentid = :studentid
                   AND entrystatus >= :submitted";

        $count = $DB->count_records_sql($sql, [
            'projetvetid' => $this->moduleinstance->id,
            'studentid' => $studentid,
            'submitted' => form_entry::STATUS_SUBMITTED,
        ]);

        return $count > 0;
    }
}
