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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_projetvet\local;

use mod_projetvet\local\assignment_notifications;

/**
 * Assignment notification planner tests.
 *
 * @package   mod_projetvet
 * @copyright 2026 Laurent David <laurent@call-learning.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(assignment_notifications::class)]
final class assignment_notifications_test extends \advanced_testcase {
    /** @var \stdClass Test student. */
    protected \stdClass $student;

    /** @var \stdClass Test tutor. */
    protected \stdClass $tutor;

    /** @var int Course module id. */
    protected int $cmid;

    /** @var int ProjetVet instance id. */
    protected int $projetvetid;

    /**
     * Set up an activity with an eligible student and tutor.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->student = $generator->create_user([
            'username' => 'notificationstudent',
            'firstname' => 'Student',
            'lastname' => 'Example',
        ]);
        $this->tutor = $generator->create_user([
            'username' => 'notificationtutor',
            'firstname' => 'Tutor',
            'lastname' => 'Example',
        ]);
        $generator->enrol_user($this->student->id, $course->id, 'student');
        $generator->enrol_user($this->tutor->id, $course->id, 'editingteacher');
        $module = $generator->create_module('projetvet', ['course' => $course->id]);
        $this->cmid = $module->cmid;
        $this->projetvetid = $module->id;
    }

    /**
     * Test the four supported state transitions.
     *
     * @return void
     */
    public function test_detect_scenario(): void {
        $this->assertSame(
            assignment_notifications::SCENARIO_FIRST,
            assignment_notifications::detect_scenario(0, 10)
        );
        $this->assertSame(
            assignment_notifications::SCENARIO_DEFINITIVE,
            assignment_notifications::detect_scenario(10, 20)
        );
        $this->assertSame(
            assignment_notifications::SCENARIO_TEMPORARY_START,
            assignment_notifications::detect_scenario(10, 10, 0, 30)
        );
        $this->assertSame(
            assignment_notifications::SCENARIO_TEMPORARY_END,
            assignment_notifications::detect_scenario(10, 10, 30, 0)
        );
    }

    /**
     * Test invalid transitions are rejected instead of guessing a scenario.
     *
     * @return void
     */
    public function test_invalid_transition_is_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        assignment_notifications::detect_scenario(0, 0);
    }

    /**
     * Test the plan validates context and contains recipient-specific data.
     *
     * @return void
     */
    public function test_build_first_assignment_plan(): void {
        $plan = assignment_notifications::build_plan(
            $this->cmid,
            $this->projetvetid,
            $this->student->id,
            0,
            $this->tutor->id,
            0,
            0,
            1740000000
        );

        $this->assertSame(assignment_notifications::SCENARIO_FIRST, $plan['scenario']);
        $this->assertCount(2, $plan['messages']);
        $this->assertSame((int) $this->student->id, (int) $plan['messages'][0]['userid']);
        $this->assertSame((int) $this->tutor->id, (int) $plan['messages'][1]['userid']);
        $this->assertSame('Student', $plan['messages'][0]['data']['studentfirstname']);
        $this->assertStringContainsString('/mod/projetvet/view.php', $plan['messages'][0]['data']['link']);
    }

    /**
     * Test definitive and temporary plans use their correct tutor roles.
     *
     * @return void
     */
    public function test_build_definitive_and_temporary_plans(): void {
        $generator = $this->getDataGenerator();
        $former = $this->getDataGenerator()->create_user([
            'username' => 'formerassignmenttutor',
            'firstname' => 'Former',
            'lastname' => 'Tutor',
        ]);
        $temporary = $generator->create_user([
            'username' => 'temporaryassignmenttutor',
            'firstname' => 'Temporary',
            'lastname' => 'Tutor',
        ]);
        $courseid = \context_module::instance($this->cmid)->get_course_context()->instanceid;
        $generator->enrol_user($former->id, $courseid, 'editingteacher');
        $generator->enrol_user($temporary->id, $courseid, 'editingteacher');

        $definitive = assignment_notifications::build_plan(
            $this->cmid,
            $this->projetvetid,
            $this->student->id,
            $former->id,
            $this->tutor->id
        );
        $this->assertSame(assignment_notifications::SCENARIO_DEFINITIVE, $definitive['scenario']);
        $this->assertCount(3, $definitive['messages']);

        $temporarystart = assignment_notifications::build_plan(
            $this->cmid,
            $this->projetvetid,
            $this->student->id,
            $this->tutor->id,
            $this->tutor->id,
            0,
            $temporary->id,
            1740000000
        );
        $this->assertSame(assignment_notifications::SCENARIO_TEMPORARY_START, $temporarystart['scenario']);
        $this->assertSame('Temporary', $temporarystart['messages'][2]['data']['newtutorfirstname']);

        $temporaryend = assignment_notifications::build_plan(
            $this->cmid,
            $this->projetvetid,
            $this->student->id,
            $this->tutor->id,
            $this->tutor->id,
            $temporary->id,
            0,
            1740000000
        );
        $this->assertSame(assignment_notifications::SCENARIO_TEMPORARY_END, $temporaryend['scenario']);
        $this->assertSame('Temporary', $temporaryend['messages'][2]['data']['newtutorfirstname']);
    }

    /**
     * Test templates do not expose a substitution reason parameter.
     *
     * @return void
     */
    public function test_templates_do_not_contain_substitution_reason(): void {
        $keys = [
            'assignment_temporary_start_student_body',
            'assignment_temporary_start_primary_body',
            'assignment_temporary_start_temporary_body',
            'assignment_temporary_end_student_body',
            'assignment_temporary_end_primary_body',
            'assignment_temporary_end_temporary_body',
        ];

        foreach ($keys as $key) {
            $this->assertStringNotContainsString('reason', get_string($key, 'mod_projetvet'));
            $this->assertStringNotContainsString('motif', get_string($key, 'mod_projetvet'));
        }
    }
}
