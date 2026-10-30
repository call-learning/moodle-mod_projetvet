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

namespace mod_projetvet\external;

use mod_projetvet\local\assignment_notifications;
use mod_projetvet\local\api\groups;
use mod_projetvet\task\send_message;

/**
 * Assignment notification external function tests.
 *
 * @package   mod_projetvet
 * @copyright 2026 Laurent David <laurent@call-learning.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(send_assignment_notifications::class)]
final class send_assignment_notifications_test extends \advanced_testcase {
    /** @var array Test data. */
    protected array $data = [];

    /**
     * Set up a module with a manager, student and tutor.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->data['course'] = $generator->create_course();
        $this->data['student'] = $generator->create_user(['username' => 'assignmentstudent']);
        $this->data['tutor'] = $generator->create_user(['username' => 'assignmenttutor']);
        $this->data['manager'] = $generator->create_user(['username' => 'assignmentmanager']);
        $generator->enrol_user($this->data['student']->id, $this->data['course']->id, 'student');
        $generator->enrol_user($this->data['tutor']->id, $this->data['course']->id, 'editingteacher');
        $generator->enrol_user($this->data['manager']->id, $this->data['course']->id, 'manager');
        $module = $generator->create_module('projetvet', ['course' => $this->data['course']->id]);
        $this->data['cmid'] = $module->cmid;
        $this->data['projetvetid'] = $module->id;
    }

    /**
     * Test a manager can queue the two first-assignment messages.
     *
     * @return void
     */
    public function test_manager_can_queue_first_assignment_notifications(): void {
        $this->setUser($this->data['manager']);
        groups::assign_students_to_teacher(
            $this->data['tutor']->id,
            [$this->data['student']->id],
            $this->data['projetvetid']
        );
        $date = 1740000000;

        $result = send_assignment_notifications::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            assignment_notifications::SCENARIO_FIRST,
            [[
                'studentid' => $this->data['student']->id,
                'beforeprimaryid' => 0,
                'afterprimaryid' => $this->data['tutor']->id,
                'beforetemporaryid' => 0,
                'aftertemporaryid' => 0,
                'date' => $date,
                'signature' => assignment_notifications::sign_change(
                    $this->data['cmid'],
                    $this->data['projetvetid'],
                    $this->data['student']->id,
                    0,
                    $this->data['tutor']->id,
                    0,
                    0,
                    $date
                ),
            ]]
        );

        $this->assertTrue($result['result']);
        $this->assertSame(2, $result['count']);
        $tasks = \core\task\manager::get_adhoc_tasks('\\' . send_message::class);
        $this->assertCount(2, $tasks);
    }

    /**
     * Test a forged scenario cannot be used to send a different message type.
     *
     * @return void
     */
    public function test_scenario_must_match_assignment_state(): void {
        $this->setUser($this->data['manager']);

        $date = 1740000000;
        $this->expectException(\invalid_parameter_exception::class);
        send_assignment_notifications::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            assignment_notifications::SCENARIO_DEFINITIVE,
            [[
                'studentid' => $this->data['student']->id,
                'beforeprimaryid' => 0,
                'afterprimaryid' => $this->data['tutor']->id,
                'beforetemporaryid' => 0,
                'aftertemporaryid' => 0,
                'date' => $date,
                'signature' => assignment_notifications::sign_change(
                    $this->data['cmid'],
                    $this->data['projetvetid'],
                    $this->data['student']->id,
                    0,
                    $this->data['tutor']->id,
                    0,
                    0,
                    $date
                ),
            ]]
        );
    }
}
