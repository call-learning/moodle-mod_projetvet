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

namespace mod_projetvet\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Send message task test.
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
        $this->data['student1'] = $generator->create_user(['username' => 'student1', 'email' => 'student1@example.com']);

        $generator->enrol_user($this->data['teacher']->id, $this->data['course']->id, 'editingteacher');
        $generator->enrol_user($this->data['student1']->id, $this->data['course']->id, 'student');

        $module = $generator->create_module('projetvet', ['course' => $this->data['course']->id]);
        $this->data['projetvetid'] = $module->id;
        $this->data['cmid'] = $module->cmid;

        $this->seed_contact_provider();
    }

    /**
     * Grant a user a role with the approve capability at the module context.
     *
     * The contact provider requires the recipient to hold mod/projetvet:approve
     * to receive the message. Grant it to the recipient so the messaging
     * pipeline allows delivery in the test environment.
     *
     * @param int $userid The user id to grant.
     * @return void
     */
    protected function grant_approve_capability(int $userid): void {
        global $DB;

        $context = \context_module::instance($this->data['cmid']);
        $roleid = $this->getDataGenerator()->create_role();

        // Assign the role to the user at the module context.
        $DB->insert_record('role_assignments', (object) [
            'roleid' => $roleid,
            'userid' => $userid,
            'contextid' => $context->id,
        ]);

        // Grant the approve capability on that role at the module context.
        $DB->insert_record('role_capabilities', (object) [
            'roleid' => $roleid,
            'contextid' => $context->id,
            'capability' => 'mod/projetvet:approve',
            'permission' => CAP_ALLOW,
        ]);
    }

    /**
     * Build a task with the given custom data.
     *
     * @param int $userfromid The sender user id.
     * @param array $recipientids The recipient user ids.
     * @param string $subjectkey The subject string key.
     * @param string $bodykey The body string key.
     * @return send_message
     */
    protected function get_task(
        int $userfromid,
        array $recipientids,
        string $subjectkey = 'contactactivity',
        string $bodykey = 'contactactivity'
    ): send_message {
        $task = new send_message();
        $task->set_custom_data((object) [
            'cmid' => $this->data['cmid'],
            'userfromid' => $userfromid,
            'recipientids' => $recipientids,
            'subjectkey' => $subjectkey,
            'bodykey' => $bodykey,
        ]);
        return $task;
    }

    /**
     * Seed the contact provider into the message_providers table.
     *
     * The providers listed in db/messages.php are normally seeded by the
     * plugin upgrade process. The PHPUnit test site is installed before the
     * upgrade runs, so the provider must be inserted manually here.
     *
     * @return void
     */
    protected function seed_contact_provider(): void {
        global $DB;
        $existing = $DB->get_record('message_providers', [
            'component' => 'mod_projetvet',
            'name' => 'contact',
        ]);
        if (!$existing) {
            $DB->insert_record('message_providers', (object) [
                'component' => 'mod_projetvet',
                'name' => 'contact',
                'capability' => 'mod/projetvet:approve',
            ]);
        }
    }

    /**
     * Get notifications delivered to a user by the contact provider.
     *
     * @param int $userid The recipient user id.
     * @return array
     */
    protected function get_contact_notifications(int $userid): array {
        global $DB;
        return $DB->get_records('notifications', [
            'useridto' => $userid,
            'component' => 'mod_projetvet',
            'eventtype' => 'contact',
        ]);
    }

    /**
     * Test the task delivers a message through the messaging subsystem.
     */
    public function test_execute_sends_message(): void {
        $this->setAdminUser();
        $this->grant_approve_capability($this->data['student1']->id);
        $task = $this->get_task($this->data['teacher']->id, [$this->data['student1']->id]);
        $task->execute();

        $notifications = $this->get_contact_notifications($this->data['student1']->id);
        $this->assertCount(1, $notifications);
        $notification = reset($notifications);
        $this->assertSame($this->data['teacher']->id, $notification->useridfrom);
        $this->assertSame($this->data['student1']->id, $notification->useridto);
        $this->assertStringContainsString('ProjetVet', $notification->subject);
    }

    /**
     * Test the task delivers to multiple recipients.
     */
    public function test_execute_sends_multiple_recipients(): void {
        $this->setAdminUser();
        $student2 = $this->getDataGenerator()->create_user(['username' => 'student2', 'email' => 'student2@example.com']);
        $this->getDataGenerator()->enrol_user($student2->id, $this->data['course']->id, 'student');
        $this->grant_approve_capability($this->data['student1']->id);
        $this->grant_approve_capability($student2->id);

        $task = $this->get_task($this->data['teacher']->id, [$this->data['student1']->id, $student2->id]);
        $task->execute();

        $this->assertCount(1, $this->get_contact_notifications($this->data['student1']->id));
        $this->assertCount(1, $this->get_contact_notifications($student2->id));
    }

    /**
     * Test recipients that no longer exist are skipped.
     */
    public function test_execute_skips_deleted_recipient(): void {
        $this->setAdminUser();
        $this->grant_approve_capability($this->data['student1']->id);
        $deleted = $this->getDataGenerator()->create_user(['username' => 'deleteduser', 'email' => 'deleted@example.com']);
        $this->getDataGenerator()->enrol_user($deleted->id, $this->data['course']->id, 'student');

        // Delete the user.
        global $DB;
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);

        $task = $this->get_task($this->data['teacher']->id, [$deleted->id, $this->data['student1']->id]);
        $task->execute();

        // Only the valid recipient should receive a message.
        $this->assertCount(1, $this->get_contact_notifications($this->data['student1']->id));
        $this->assertCount(0, $this->get_contact_notifications($deleted->id));
    }

    /**
     * Test an invalid custom data (missing cmid) is a no-op.
     */
    public function test_execute_invalid_customdata(): void {
        $this->setAdminUser();
        $task = new send_message();
        $task->set_custom_data((object) [
            'cmid' => 0,
            'userfromid' => $this->data['teacher']->id,
            'recipientids' => [$this->data['student1']->id],
            'subjectkey' => 'contactactivity',
            'bodykey' => 'contactactivity',
        ]);
        $task->execute();

        // No message should be sent.
        $this->assertCount(0, $this->get_contact_notifications($this->data['student1']->id));
    }
}
