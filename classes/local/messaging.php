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

use mod_projetvet\task\send_message;

/**
 * Messaging queue facade for the send_message adhoc task.
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class messaging {
    /**
     * Message provider name registered in db/messages.php.
     * @var string
     */
    public const string PROVIDER = 'contact';

    /**
     * Maximum number of recipients for a single broadcast request.
     * @var int
     */
    public const int MAX_RECIPIENTS = 100;

    /**
     * Queue a message for delivery using a full descriptor.
     *
     * This is the generic entry point. The descriptor is handed verbatim to the
     * adhoc task and may carry any of the fields documented in
     * {@see send_message::execute()}: provider, cmid, userfromid, recipientids,
     * subjectkey/bodykey (or subject/body), subjectdata, bodydata, smallkey,
     * smalldata, contexturl, contexturlname, notification and courseid.
     *
     * The message is not sent inline: an adhoc task is scheduled, so that the
     * calling request stays fast and delivery is decoupled from it. The task
     * delivers the message through message_send, in the language of each
     * recipient, so that all standard Moodle messaging rules apply (recipient
     * preferences, enabled output plugins, provider availability).
     *
     * @param array $descriptor The message descriptor.
     * @return int The scheduled task id.
     */
    public static function schedule_message(array $descriptor): int {
        $task = new send_message();
        $task->set_custom_data((object) $descriptor);

        return \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Queue a contact message for delivery to the given recipients.
     *
     * Convenience wrapper around {@see self::schedule_message()} for the common
     * key-based contact message: the contact provider is used and the content
     * is a pair of language-string keys.
     *
     * @param int $cmid The course module id
     * @param int $userfromid The sender user id
     * @param array $recipientids Recipient user ids
     * @param string $subjectkey Language string key for the subject
     * @param string $bodykey Language string key for the body
     * @return int The scheduled task id
     */
    public static function schedule_send(
        int $cmid,
        int $userfromid,
        array $recipientids,
        string $subjectkey,
        string $bodykey
    ): int {
        return self::schedule_message([
            'cmid' => $cmid,
            'userfromid' => $userfromid,
            'recipientids' => array_values($recipientids),
            'subjectkey' => $subjectkey,
            'bodykey' => $bodykey,
        ]);
    }

    /**
     * Queue one localized message for one recipient with named parameters.
     *
     * Convenience wrapper around {@see self::schedule_message()} for a single
     * recipient whose subject and body are language-string keys.
     *
     * @param int $cmid The course module id.
     * @param int $userfromid The sender user id.
     * @param int $recipientid Recipient user id.
     * @param string $subjectkey Language string key for the subject.
     * @param string $bodykey Language string key for the body.
     * @param array $data Named language string parameters.
     * @param ?array $bodydata Named language string parameters for the body.
     * @return int The scheduled task id
     */
    public static function schedule_personal_send(
        int $cmid,
        int $userfromid,
        int $recipientid,
        string $subjectkey,
        string $bodykey,
        array $data = [],
        ?array $bodydata = null
    ): int {
        return self::schedule_message([
            'cmid' => $cmid,
            'userfromid' => $userfromid,
            'recipientids' => [$recipientid],
            'subjectkey' => $subjectkey,
            'bodykey' => $bodykey,
            'subjectdata' => $data,
            'bodydata' => $bodydata ?? $data,
        ]);
    }

    /**
     * Queue a custom broadcast message for delivery to the given recipients.
     *
     * Convenience wrapper around {@see self::schedule_message()} carrying a
     * tutor-composed subject and body rather than language-string keys. The
     * task delivers the message through message_send, in the language of each
     * recipient.
     *
     * @param int $cmid The course module id
     * @param int $userfromid The sender user id
     * @param array $recipientids Recipient user ids
     * @param string $subject The custom subject
     * @param string $body The custom body (HTML)
     * @return int The scheduled task id
     */
    public static function schedule_broadcast_send(
        int $cmid,
        int $userfromid,
        array $recipientids,
        string $subject,
        string $body
    ): int {
        return self::schedule_message([
            'cmid' => $cmid,
            'userfromid' => $userfromid,
            'recipientids' => array_values($recipientids),
            'subject' => $subject,
            'body' => $body,
        ]);
    }
}
