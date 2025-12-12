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

use core_user;
use context_system;
use moodle_page;
use moodle_url;
use mod_projetvet\local\api\entries;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\persistent\form_set;
use mod_projetvet\output\entry_action_required_message;
use mod_projetvet\utils;

/**
 * Notification helpers for Projetvet.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifications {

    /**
     * Queue an adhoc task to notify the next actor.
     *
     * @param int $entryid Entry id
     * @param int $cmid Course module id
     * @param int $oldstatus Previous entry status
     * @param int $newstatus New entry status
     * @return void
     */
    public static function queue_entry_action_required(int $entryid, int $cmid, int $oldstatus, int $newstatus): void {
        $task = new \mod_projetvet\task\entry_action_required();
        $task->set_custom_data((object) [
            'entryid' => $entryid,
            'cmid' => $cmid,
            'oldstatus' => $oldstatus,
            'newstatus' => $newstatus,
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Send notification about an entry requiring action.
     *
     * Called from the adhoc task.
     *
     * @param int $entryid Entry id
     * @param int $cmid Course module id
     * @param int|null $oldstatus Previous status (optional)
     * @param int|null $newstatus New status (optional)
     * @return void
     */
    public static function send_entry_action_required(int $entryid, int $cmid, ?int $oldstatus = null, ?int $newstatus = null): void {
        $entryrecord = form_entry::get_record(['id' => $entryid]);
        if (!$entryrecord) {
            return;
        }

        $formset = form_set::get_record(['id' => $entryrecord->get('formsetid')]);
        $formsetidnumber = $formset ? $formset->get('idnumber') : 'activities';

        $currentstatus = (int)$entryrecord->get('entrystatus');
        $oldstatus = $oldstatus ?? $currentstatus;
        $newstatus = $newstatus ?? $currentstatus;

        $actorbefore = self::get_required_actor_for_status($formsetidnumber, $oldstatus);
        $actorafter = self::get_required_actor_for_status($formsetidnumber, $newstatus);

        // Only notify when the required actor changes, and when there is a next actor.
        if ($actorafter === 'none' || $actorafter === $actorbefore) {
            return;
        }

        $entry = entries::get_entry($entryid);
        $studentid = (int)$entry->studentid;

        $recipients = self::get_recipients($actorafter, $studentid, $cmid);
        if (empty($recipients)) {
            return;
        }

        $entrytitle = self::get_entry_title($entry);
        $statustext = entries::get_status_message($newstatus, $formsetidnumber);

        $urlparams = ['id' => $cmid];
        if ($actorafter === 'tutor') {
            $urlparams['studentid'] = $studentid;
        }
        $url = (new moodle_url('/mod/projetvet/view.php', $urlparams))->out(false);

        foreach ($recipients as $recipient) {
            self::send_to_user($recipient, $actorafter, $entrytitle, $statustext, $url, $studentid);
        }
    }

    /**
     * Get the required actor for a given status.
     *
     * @param string $formsetidnumber Formset idnumber
     * @param int $status Entry status
     * @return string 'student'|'tutor'|'none'
     */
    private static function get_required_actor_for_status(string $formsetidnumber, int $status): string {
        $structure = entries::get_form_structure($formsetidnumber);
        if (empty($structure)) {
            return 'none';
        }

        $maxstatus = 0;
        foreach ($structure as $category) {
            $maxstatus = max($maxstatus, (int)$category->entrystatus);
        }

        // The highest status is considered terminal; no action required.
        if ($status >= $maxstatus) {
            return 'none';
        }

        $caps = [];
        foreach ($structure as $category) {
            if ((int)$category->entrystatus === $status) {
                $caps[] = (string)($category->capability ?? '');
            }
        }

        // Prefer tutor-side actions first.
        if (in_array('approve', $caps, true) || in_array('unlock', $caps, true)) {
            return 'tutor';
        }
        if (in_array('submit', $caps, true)) {
            return 'student';
        }

        return 'none';
    }

    /**
     * Resolve recipients for the actor.
     *
     * @param string $actor 'student'|'tutor'
     * @param int $studentid Student id
     * @param int $cmid Course module id
     * @return array Array of user objects
     */
    private static function get_recipients(string $actor, int $studentid, int $cmid): array {
        if ($actor === 'student') {
            $student = core_user::get_user($studentid);
            return $student ? [$student] : [];
        }

        if ($actor === 'tutor') {
            $tutor = utils::get_student_tutor($studentid, $cmid);
            if ($tutor) {
                return [$tutor];
            }

            $roleshortname = get_config('mod_projetvet', 'tutor_role') ?: 'teacher';
            return array_values(utils::get_users_with_role($roleshortname, $cmid));
        }

        return [];
    }

    /**
     * Get a display title for an entry.
     *
     * @param object $entry Entry DTO
     * @return string
     */
    private static function get_entry_title(object $entry): string {
        if (!empty($entry->categories)) {
            foreach ($entry->categories as $category) {
                if (empty($category->fields)) {
                    continue;
                }
                foreach ($category->fields as $field) {
                    if (empty($field->idnumber) || !isset($field->value)) {
                        continue;
                    }
                    $idnumber = (string)$field->idnumber;
                    if (preg_match('/title$/', $idnumber) && trim((string)$field->value) !== '') {
                        return (string)$field->value;
                    }
                    if ($idnumber === 'activity_title' && trim((string)$field->value) !== '') {
                        return (string)$field->value;
                    }
                }
            }
        }

        return get_string('entryid', 'mod_projetvet') . ': ' . (int)$entry->id;
    }

    /**
     * Send notification to a single user based on their preference.
     *
     * @param \stdClass $recipient Recipient user record
     * @param string $actor Actor after transition
     * @param string $entrytitle Entry title
     * @param string $statustext Status text
     * @param string $url URL to open
     * @param int $studentid Student id (for display)
     * @return void
     */
    private static function send_to_user(\stdClass $recipient, string $actor, string $entrytitle, string $statustext, string $url, int $studentid): void {
        $student = core_user::get_user($studentid);
        $studentname = $student ? fullname($student) : '';

        $subject = get_string('notification_subject_actionrequired', 'mod_projetvet', $entrytitle);
        $istutor = ($actor === 'tutor');

        $renderable = new entry_action_required_message(
            $istutor,
            $studentname,
            $entrytitle,
            $statustext,
            $url
        );

        $renderer = self::get_renderer();
        if ($renderer) {
            $html = $renderer->render_entry_action_required_message($renderable);
        } else {
            global $OUTPUT;
            $html = $OUTPUT->render_from_template(
                'mod_projetvet/notification_entry_action_required',
                $renderable->export_for_template($OUTPUT)
            );
        }
        $text = html_to_text($html);

        // Build smallmessage for notification summary.
        $actionline = $istutor
            ? get_string('notification_action_tutor', 'mod_projetvet')
            : get_string('notification_action_student', 'mod_projetvet');

        // Send via Moodle's message system - user preferences control delivery method.
        $message = new \core\message\message();
        $message->component = 'mod_projetvet';
        $message->name = 'entry_action_required';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $recipient;
        $message->subject = $subject;
        $message->fullmessage = $text;
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $html;
        $message->smallmessage = $actionline;
        $message->notification = 1;
        $message->contexturl = $url;
        $message->contexturlname = get_string('openprojetvet', 'mod_projetvet');

        message_send($message);
    }

    /**
     * Get the plugin renderer in non-UI contexts (like adhoc tasks).
     *
     * @return \mod_projetvet\output\renderer|null
     */
    private static function get_renderer(): ?\mod_projetvet\output\renderer {
        global $PAGE;

        if (!($PAGE instanceof moodle_page)) {
            return null;
        }

        // Ensure we have at least a context + URL, so get_renderer() won't throw.
        if (!$PAGE->has_set_url()) {
            $PAGE->set_url(new moodle_url('/'));
        }
        if (!$PAGE->context) {
            $PAGE->set_context(context_system::instance());
        }

        try {
            /** @var \mod_projetvet\output\renderer $renderer */
            $renderer = $PAGE->get_renderer('mod_projetvet');
            return $renderer;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
