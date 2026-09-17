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

namespace mod_projetvet\external;

use mod_projetvet\external\send_message;
use mod_projetvet\task\send_message as task;

defined('MOODLE_INTERNAL') || die();

/**
 * Send message external function test.
 *
 * @package   mod_projetvet
 * @copyright 2026 Laurent David <laurent@call-learning.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(send_message::class)]
final class send_message_test extends \advanced_testcase {
    /** @var array Test data (course, users, module). */
    protected array $data = [];

    /**
     * Test setup.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        $this->data['course'] = $generator->create_course();
        $this->data['teacher'] = $generator->create_user(['username' => 'teacher1', 'firstname' => 'Alice', 'lastname' => 'Tutor']);
        $this->data['student1'] = $generator->create_user(['username' => 'student1']);
        $this->data['student2'] = $generator->create_user(['username' => 'student2']);
        $this->data['manager'] = $generator->create_user(['username' => 'manager1']);

        $generator->enrol_user($this->data['teacher']->id, $this->data['course']->id, 'editingteacher');
        $generator->enrol_user($this->data['student1']->id, $this->data['course']->id, 'student');
        $generator->enrol_user($this->data['student2']->id, $this->data['course']->id, 'student');
        $generator->enrol_user($this->data['manager']->id, $this->data['course']->id, 'manager');

        $module = $generator->create_module('projetvet', ['course' => $this->data['course']->id]);
        $this->data['projetvetid'] = $module->id;
        $this->data['cmid'] = $module->cmid;

        // Assign both students to the teacher.
        $this->setAdminUser();
        \mod_projetvet\external\assign_teacher::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$this->data['student1']->id, $this->data['student2']->id],
            $this->data['teacher']->id
        );
    }

    /**
     * Test sending a contact message to a single student schedules an adhoc task.
     */
    public function test_send_message_success(): void {
        $this->setUser($this->data['teacher']);

        $result = send_message::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$this->data['student1']->id],
            'contactactivity',
            'contactactivity'
        );

        $this->assertTrue($result->result);
        $this->assertSame(1, $result->count);

        // An adhoc task should have been scheduled.
        $tasks = \core\task\manager::get_adhoc_tasks('\\' . task::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertInstanceOf(task::class, $task);
        $customdata = $task->get_custom_data();
        $this->assertSame((int) $this->data['cmid'], (int) $customdata->cmid);
        $this->assertSame((int) $this->data['teacher']->id, (int) $customdata->userfromid);
        $this->assertSame([(int) $this->data['student1']->id], array_map('intval', (array) $customdata->recipientids));
        $this->assertSame('contactactivity', $customdata->subjectkey);
        $this->assertSame('contactactivity', $customdata->bodykey);
    }

    /**
     * Test sending to all students of the caller (empty studentids) works.
     */
    public function test_send_message_all_students(): void {
        $this->setUser($this->data['teacher']);

        $result = send_message::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [],
            'contactactivity',
            'contactactivity'
        );

        $this->assertTrue($result->result);
        $this->assertSame(2, $result->count);

        // An adhoc task should have been scheduled for both students.
        $tasks = \core\task\manager::get_adhoc_tasks('\\' . task::class);
        $this->assertCount(1, $tasks);
        $customdata = reset($tasks)->get_custom_data();
        $recipientids = array_map('intval', (array) $customdata->recipientids);
        $this->assertCount(2, $recipientids);
        $this->assertContains((int) $this->data['student1']->id, $recipientids);
        $this->assertContains((int) $this->data['student2']->id, $recipientids);
    }

    /**
     * Test a user without the approve capability is rejected.
     */
    public function test_send_message_requires_capability(): void {
        // Create a user enrolled as a plain student (no approve capability).
        $outsidestudent = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($outsidestudent->id, $this->data['course']->id, 'student');
        $this->setUser($outsidestudent);

        $this->expectException(\moodle_exception::class);
        send_message::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$this->data['student1']->id]
        );
    }

    /**
     * Test a student cannot contact another student.
     */
    public function test_send_message_student_rejected(): void {
        $this->setUser($this->data['student1']);

        $this->expectException(\moodle_exception::class);
        send_message::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$this->data['student2']->id]
        );
    }

    /**
     * Test a recipient not assigned to the caller is rejected.
     */
    public function test_send_message_invalid_recipient(): void {
        // A student enrolled in the course but not assigned to the teacher.
        $outsidestudent = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($outsidestudent->id, $this->data['course']->id, 'student');

        $this->setUser($this->data['teacher']);

        try {
            send_message::execute(
                $this->data['cmid'],
                $this->data['projetvetid'],
                [$outsidestudent->id]
            );
            $this->fail('Expected invalidrecipient exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidrecipient', $e->errorcode);
        }
    }

    /**
     * Test an unknown message key is rejected.
     */
    public function test_send_message_invalid_message_key(): void {
        $this->setUser($this->data['teacher']);

        try {
            send_message::execute(
                $this->data['cmid'],
                $this->data['projetvetid'],
                [$this->data['student1']->id],
                'contactactivity',
                'unknownkey'
            );
            $this->fail('Expected invalidcontactmessage exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidcontactmessage', $e->errorcode);
        }
    }

    /**
     * Test an empty recipient list with no students is rejected.
     */
    public function test_send_message_no_recipients(): void {
        $this->setAdminUser();

        try {
            send_message::execute(
                $this->data['cmid'],
                $this->data['projetvetid'],
                []
            );
            $this->fail('Expected nouseridprovided exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nouseridprovided', $e->errorcode);
        }
    }

    /**
     * Test the cmid and projetvetid must match.
     */
    public function test_send_message_requires_matching_instance(): void {
        $this->setUser($this->data['teacher']);
        $othermodule = $this->getDataGenerator()->create_module('projetvet', [
            'course' => $this->data['course']->id,
        ]);

        $this->expectException(\invalid_parameter_exception::class);
        send_message::execute(
            $this->data['cmid'],
            $othermodule->id,
            [$this->data['student1']->id]
        );
    }
}
