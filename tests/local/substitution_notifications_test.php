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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/projetvet/tests/substitution_test_setup.php');

use mod_projetvet\task\send_message;

/**
 * Tests for the notifications during a substitution.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(notifications::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(assignment_notifications::class)]
final class substitution_notifications_test extends \advanced_testcase {
    use \substitution_test_setup;

    /**
     * Set up the substitution scenario.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_substitution_scenario();

        // PHPUnit installs the plugin without necessarily seeding message
        // providers, so make the entry provider available to message_send().
        global $DB;
        $providerexists = $DB->record_exists('message_providers', [
            'component' => 'mod_projetvet',
            'name' => notifications::PROVIDER,
        ]);
        if (!$providerexists) {
            $DB->insert_record('message_providers', (object) [
                'component' => 'mod_projetvet',
                'name' => notifications::PROVIDER,
                'capability' => 'mod/projetvet:view',
            ]);
        }
    }

    /**
     * The follow-up notifications go to the active tutor, and to the principal tutor when kept informed.
     */
    public function test_tutor_recipients(): void {
        $projetvetid = $this->projetvet->id;
        $this->substitute([$this->students[1]], $this->substitutes[1]);
        $this->substitute([$this->students[2]], $this->substitutes[2], ['notifyprimary' => true]);
        $this->substitute([$this->students[3]], $this->substitutes[3], ['startdate' => time() + WEEKSECS]);

        $this->assertEquals(
            [$this->substitutes[1]->id],
            array_keys(notifications::get_tutor_recipients($this->students[1]->id, $projetvetid))
        );
        $this->assertEqualsCanonicalizing(
            [$this->substitutes[2]->id, $this->primary->id],
            array_keys(notifications::get_tutor_recipients($this->students[2]->id, $projetvetid))
        );
        // A planned substitution is not in effect yet.
        $this->assertEquals(
            [$this->primary->id],
            array_keys(notifications::get_tutor_recipients($this->students[3]->id, $projetvetid))
        );
        $this->assertEquals(
            [$this->primary->id],
            array_keys(notifications::get_tutor_recipients($this->students[4]->id, $projetvetid))
        );
        $this->assertEmpty(notifications::get_tutor_recipients($this->unassigned->id, $projetvetid));
    }

    /**
     * A project submitted during a substitution notifies the substitute, not the principal tutor.
     */
    /**
     * Pop the next queued entry notification task.
     *
     * @return send_message
     */
    private function pop_entry_task(): send_message {
        $tasks = \core\task\manager::get_adhoc_tasks('\\' . send_message::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertInstanceOf(send_message::class, $task);
        return $task;
    }

    /**
     * Get the entry notification records delivered to a user.
     *
     * @param int $userid The recipient user id.
     * @return \stdClass[]
     */
    private function get_entry_notifications(int $userid): array {
        global $DB;
        return $DB->get_records('notifications', [
            'useridto' => $userid,
            'component' => 'mod_projetvet',
            'eventtype' => notifications::PROVIDER,
        ]);
    }

    /**
     * The tutor notification is queued for the active tutor(s) and delivered
     * through the entry provider.
     */
    public function test_send_tutor_notification(): void {
        $this->preventResetByRollback();
        $this->substitute([$this->students[1]], $this->substitutes[1]);
        $this->substitute([$this->students[2]], $this->substitutes[2], ['notifyprimary' => true]);

        // Case 1: only the active substitute is notified.
        $this->assertTrue(notifications::send_tutor_notification(
            0,
            $this->students[1]->id,
            $this->projetvet->cmid,
            'studentsubmittedactivity'
        ));
        $task = $this->pop_entry_task();
        $data = $task->get_custom_data();
        $this->assertSame(notifications::PROVIDER, $data->provider);
        $this->assertEquals($this->students[1]->id, $data->userfromid);
        $this->assertEquals([$this->substitutes[1]->id], array_map('intval', $data->recipientids));
        $this->assertSame('studentsubmittedactivity_subject', $data->subjectkey);
        $this->assertSame('studentsubmittedactivity', $data->bodykey);
        $this->assertSame('notification_smallmessage', $data->smallkey);
        $this->assertStringContainsString('studentid=' . $this->students[1]->id, $data->contexturl);
        $task->execute();

        // The delivered notification keeps the student context.
        $notifications = $this->get_entry_notifications($this->substitutes[1]->id);
        $this->assertCount(1, $notifications);
        $notification = reset($notifications);
        $this->assertEquals($this->students[1]->id, $notification->useridfrom);
        $this->assertStringContainsString('studentid=' . $this->students[1]->id, $notification->contexturl);
        $this->assertStringContainsString('has submitted a project for validation', $notification->smallmessage);
        \core\task\manager::delete_adhoc_task($task->get_id());

        // Case 2: the substitute and, when configured, the principal tutor.
        notifications::send_tutor_notification(0, $this->students[2]->id, $this->projetvet->cmid, 'studentsubmittedactivity');
        $task = $this->pop_entry_task();
        $this->assertEqualsCanonicalizing(
            [$this->substitutes[2]->id, $this->primary->id],
            array_map('intval', $task->get_custom_data()->recipientids)
        );
        \core\task\manager::delete_adhoc_task($task->get_id());

        // Case 3: without a substitution the principal tutor is notified.
        notifications::send_tutor_notification(0, $this->students[4]->id, $this->projetvet->cmid, 'studentsubmittedactivity');
        $task = $this->pop_entry_task();
        $this->assertEquals([$this->primary->id], array_map('intval', $task->get_custom_data()->recipientids));
        \core\task\manager::delete_adhoc_task($task->get_id());

        // A student without tutor gets no notification.
        $this->assertFalse(
            notifications::send_tutor_notification(0, $this->unassigned->id, $this->projetvet->cmid, 'studentsubmittedactivity')
        );
    }

    /**
     * Notifications to the student are queued on behalf of the active tutor
     * and delivered through the entry provider.
     */
    public function test_send_student_notification(): void {
        $this->preventResetByRollback();
        $this->substitute([$this->students[1]], $this->substitutes[1]);

        // While the substitution is in effect the substitute is the sender.
        notifications::send_student_notification(0, $this->students[1]->id, $this->projetvet->cmid, 'acceptedelegibility');
        $task = $this->pop_entry_task();
        $data = $task->get_custom_data();
        $this->assertSame(notifications::PROVIDER, $data->provider);
        $this->assertEquals($this->substitutes[1]->id, $data->userfromid);
        $this->assertEquals([$this->students[1]->id], array_map('intval', $data->recipientids));
        $this->assertSame('acceptedelegibility_subject', $data->subjectkey);
        $this->assertSame('notification_smallmessage_student', $data->smallkey);
        $task->execute();
        \core\task\manager::delete_adhoc_task($task->get_id());

        // Without a substitution the principal tutor is the sender.
        notifications::send_student_notification(0, $this->students[2]->id, $this->projetvet->cmid, 'acceptedelegibility');
        $task = $this->pop_entry_task();
        $this->assertEquals($this->primary->id, $task->get_custom_data()->userfromid);
        $task->execute();
        \core\task\manager::delete_adhoc_task($task->get_id());

        // Both notifications are delivered through the entry provider.
        foreach ([$this->substitutes[1]->id, $this->primary->id] as $senderid) {
            $studentid = $senderid === $this->substitutes[1]->id ? $this->students[1]->id : $this->students[2]->id;
            $notifications = $this->get_entry_notifications($studentid);
            $this->assertCount(1, $notifications);
            $this->assertEquals($senderid, reset($notifications)->useridfrom);
        }
    }

    /**
     * Data provider for test_queue_substitution_notifications.
     *
     * @return array
     */
    public static function scenario_provider(): array {
        return [
            'Temporary start' => [
                assignment_notifications::SCENARIO_TEMPORARY_START,
                ['student', 'primary', 'substitute'],
                'assignment_temporary_start_temporary_subject',
            ],
            'Temporary end' => [
                assignment_notifications::SCENARIO_TEMPORARY_END,
                ['student', 'primary', 'substitute'],
                'assignment_temporary_end_temporary_subject',
            ],
            'Definitive change' => [
                assignment_notifications::SCENARIO_DEFINITIVE,
                ['student', 'primary', 'substitute'],
                'assignment_definitive_new_subject',
            ],
        ];
    }

    /**
     * The manager can notify the student and the tutors of a substitution change.
     *
     * @param string $scenario The notification scenario
     * @param string[] $recipients The expected recipients
     * @param string $substitutesubject The subject key of the substitute's message
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('scenario_provider')]
    public function test_queue_substitution_notifications(string $scenario, array $recipients, string $substitutesubject): void {
        $created = $this->substitute([$this->students[1]], $this->substitutes[1]);
        $this->setUser($this->manager);

        $count = assignment_notifications::queue_substitution_notifications(
            $this->projetvet->cmid,
            $created[$this->students[1]->id],
            $scenario
        );
        $this->assertEquals(count($recipients), $count);

        $users = [
            'student' => $this->students[1]->id,
            'primary' => $this->primary->id,
            'substitute' => $this->substitutes[1]->id,
        ];
        $subjects = [];
        foreach (\core\task\manager::get_adhoc_tasks('\\' . send_message::class) as $task) {
            $data = $task->get_custom_data();
            $this->assertEquals($this->manager->id, $data->userfromid);
            $subjects[(int) reset($data->recipientids)] = $data->subjectkey;
        }
        $this->assertEqualsCanonicalizing(
            array_map(fn($role) => $users[$role], $recipients),
            array_keys($subjects)
        );
        $this->assertEquals($substitutesubject, $subjects[$this->substitutes[1]->id]);
    }
}
