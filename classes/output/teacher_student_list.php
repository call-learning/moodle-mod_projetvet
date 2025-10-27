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

use mod_projetvet\local\api\activities;
use renderer_base;
use renderable;
use templatable;
use moodle_url;

/**
 * Teacher student list renderable class.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_student_list implements renderable, templatable {

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
     * @var int $teacherid The teacher ID.
     */
    protected $teacherid;

    /**
     * Constructor.
     *
     * @param object $moduleinstance The module instance
     * @param object $cm The course module
     * @param object $context The context
     * @param int $teacherid The teacher ID
     */
    public function __construct($moduleinstance, $cm, $context, $teacherid) {
        $this->moduleinstance = $moduleinstance;
        $this->cm = $cm;
        $this->context = $context;
        $this->teacherid = $teacherid;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $students = $this->get_students_in_teacher_groups();

        $data = [
            'hasstudents' => !empty($students),
            'students' => [],
        ];

        foreach ($students as $student) {
            try {
                $activitylist = activities::get_activity_list($this->moduleinstance->id, $student->id);
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

        return $data;
    }

    /**
     * Get students in the same groups as the teacher.
     *
     * @return array Array of student user objects
     */
    protected function get_students_in_teacher_groups() {
        $teachergroups = groups_get_user_groups($this->cm->course, $this->teacherid);

        if (empty($teachergroups) || empty($teachergroups[0])) {
            return [];
        }

        $students = [];
        $seenstudents = [];

        foreach ($teachergroups[0] as $groupid) {
            $groupstudents = groups_get_members($groupid, 'u.*', 'u.lastname ASC, u.firstname ASC');
            foreach ($groupstudents as $student) {
                if (has_capability('mod/projetvet:submit', $this->context, $student->id) &&
                    !isset($seenstudents[$student->id])) {
                    $students[] = $student;
                    $seenstudents[$student->id] = true;
                }
            }
        }

        return $students;
    }
}
