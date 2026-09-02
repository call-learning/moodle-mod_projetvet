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

namespace mod_projetvet;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/projetvet/tests/test_data_definition.php');

use advanced_testcase;
use context_module;
use core_renderer;
use core_user;
use mod_projetvet\output\student_info;
use renderer_base;
use test_data_definition;

/**
 * Tests the tutor information links in the student info renderable.
 *
 * @package     mod_projetvet
 * @copyright   2026 Laurent David <laurent@call-learning.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_projetvet\output\student_info
 */
final class output_student_info_test extends advanced_testcase {
    use test_data_definition;

    /**
     * Setup the test.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->prepare_scenario('set_1');
    }

    /**
     * Returns a renderer instance for the renderable.
     *
     * The export_for_template() method does not use the renderer, so a minimal
     * core renderer is enough here.
     *
     * @return renderer_base The renderer
     */
    private function get_renderer(): renderer_base {
        return new core_renderer(new \moodle_page(), \RENDERER_TARGET_GENERAL);
    }

    /**
     * Returns the tutor info row (Infos du tuteur) of the export data.
     *
     * @param array $data The export data
     * @return array The tutor info row
     */
    private function get_tutor_info_row(array $data): array {
        foreach ($data['infotable']['rows'] as $row) {
            if (($row['label'] ?? null) === get_string('tutorinfo', 'mod_projetvet')) {
                return $row;
            }
        }
        $this->fail('Tutor info row not found in the export data.');
    }

    /**
     * Create a group owned by the tutor with the student as a member.
     *
     * @param int $tutorid The tutor user id
     * @param int $studentid The student user id
     * @param int $projetvetid The projetvet instance id
     * @return void
     */
    private function assign_tutor(int $tutorid, int $studentid, int $projetvetid): void {
        $group = new \mod_projetvet\local\persistent\projetvet_group(0, (object)[
            'projetvetid' => $projetvetid,
            'name' => 'Test group',
            'ownerid' => $tutorid,
        ]);
        $group->create();

        $member = new \mod_projetvet\local\persistent\group_member(0, (object)[
            'groupid' => $group->get('id'),
            'userid' => $studentid,
            'membertype' => \mod_projetvet\local\persistent\group_member::TYPE_STUDENT,
        ]);
        $member->create();
    }

    /**
     * The tutor sees a link to define their practical info when it is empty.
     *
     * @return void
     */
    public function test_tutor_sees_set_link_when_info_empty(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $teacher = core_user::get_user_by_username('teacher1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);
        $cm = \get_coursemodule_from_instance('projetvet', $module->id);
        $context = context_module::instance($cm->id);

        // The teacher is the primary tutor of the student and has not set their practical info yet.
        $this->assign_tutor($teacher->id, $student->id, $module->id);

        $this->setUser($teacher);
        $renderable = new student_info($module, $cm, $context, $student->id, true);
        $data = $renderable->export_for_template($this->get_renderer());
        $row = $this->get_tutor_info_row($data);

        $this->assertTrue($row['haslink'], 'The tutor should see a link to define their practical info.');
        $this->assertSame(get_string('practicalinfo_notyet_link', 'mod_projetvet'), $row['linktext']);
        $this->assertStringContainsString('tutor_info.php', $row['linkurl']);

        // The link carries a return url back to this student view.
        $params = (new \moodle_url($row['linkurl']))->params();
        $this->assertArrayHasKey('returnurl', $params, 'The link should carry a return url.');
        $this->assertStringContainsString('/mod/projetvet/view.php', $params['returnurl']);
        $this->assertStringContainsString('studentid=' . $student->id, $params['returnurl']);
    }

    /**
     * The tutor sees an edit link when the practical info is already set.
     *
     * @return void
     */
    public function test_tutor_sees_edit_link_when_info_set(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $teacher = core_user::get_user_by_username('teacher1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);
        $cm = \get_coursemodule_from_instance('projetvet', $module->id);
        $context = context_module::instance($cm->id);

        $this->assign_tutor($teacher->id, $student->id, $module->id);
        set_user_preference('projetvet_tutor_info', 'My office is in room 12', $teacher->id);

        $this->setUser($teacher);
        $renderable = new student_info($module, $cm, $context, $student->id, true);
        $data = $renderable->export_for_template($this->get_renderer());
        $row = $this->get_tutor_info_row($data);

        $this->assertTrue($row['haslink'], 'The tutor should see an edit link.');
        $this->assertSame('fa-pencil', $row['linkicon']);
        $this->assertStringContainsString('tutor_info.php', $row['linkurl']);
    }

    /**
     * A student does not see the tutor link because they are not the tutor.
     *
     * @return void
     */
    public function test_student_does_not_see_tutor_link(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $teacher = core_user::get_user_by_username('teacher1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);
        $cm = \get_coursemodule_from_instance('projetvet', $module->id);
        $context = context_module::instance($cm->id);

        $this->assign_tutor($teacher->id, $student->id, $module->id);

        // The student views their own page; they are not the tutor of the student.
        $this->setUser($student);
        $renderable = new student_info($module, $cm, $context, $student->id, false);
        $data = $renderable->export_for_template($this->get_renderer());
        $row = $this->get_tutor_info_row($data);

        $this->assertArrayNotHasKey('haslink', $row, 'The student should not see the tutor link.');
    }
}
