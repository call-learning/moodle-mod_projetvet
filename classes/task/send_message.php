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

use core\task\adhoc_task;
use core_user;
use mod_projetvet\local\messaging;
use moodle_url;

/**
 * Adhoc task that delivers a queued ProjetVet notification to its recipients.
 *
 * The task is queued by the messaging facade ({@see mod_projetvet\local\messaging})
 * and delivers the message through the standard Moodle messaging subsystem
 * (message_send), so that recipient preferences, enabled output plugins
 * (popup, email, ...) and provider availability all apply.
 *
 * @package   mod_projetvet
 * @copyright 2026 Laurent David <laurent@call-learning.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends adhoc_task {
    /**
     * Deliver the queued notification.
     *
     * The message is always delivered in the language of each recipient.
     * Recipients that no longer exist are silently skipped.
     *
     * The customdata is expected to hold:
     *   - cmid (int): the course module id
     *   - userfromid (int): the sender user id, 0 for the noreply sender
     *   - recipientids (int[]): the recipient user ids
     *   - provider (string): the message provider name, defaults to {@see messaging::PROVIDER}
     *   - subjectkey (string): the language string key for the subject (key-based path)
     *   - bodykey (string): the language string key for the body (key-based path)
     *   - subjectdata (array): named parameters for the subject string
     *   - bodydata (array): named parameters for the body string
     *   - smallkey (string): the language string key for the small message (key-based path)
     *   - smalldata (array): named parameters for the small message string
     *   - subject (string): the custom subject (broadcast path)
     *   - body (string): the custom body HTML (broadcast path)
     *   - contexturl (string): an explicit context URL, defaults to the module URL
     *   - contexturlname (string): the context URL label, defaults to the localized open link
     *   - notification (bool): whether the message is a notification, defaults to true
     *   - courseid (int): the course id stored on the message, defaults to the module course
     *
     * Either the key-based pair (subjectkey/bodykey) or the custom pair
     * (subject/body) must be present.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $customdata = $this->get_custom_data();

        $cmid = (int) ($customdata->cmid ?? 0);
        $userfromid = (int) ($customdata->userfromid ?? -1);
        $provider = (string) ($customdata->provider ?? messaging::PROVIDER);
        $subjectkey = (string) ($customdata->subjectkey ?? '');
        $bodykey = (string) ($customdata->bodykey ?? '');
        $subjectdata = (array) ($customdata->subjectdata ?? []);
        $bodydata = (array) ($customdata->bodydata ?? []);
        $smallkey = (string) ($customdata->smallkey ?? '');
        $smalldata = (array) ($customdata->smalldata ?? []);
        $customsubject = (string) ($customdata->subject ?? '');
        $custombody = (string) ($customdata->body ?? '');
        $contexturl = (string) ($customdata->contexturl ?? '');
        $contexturlname = (string) ($customdata->contexturlname ?? '');
        $notification = (bool) ($customdata->notification ?? true);
        $courseid = (int) ($customdata->courseid ?? 0);
        $recipientids = array_map(
            'intval',
            (array) ($customdata->recipientids ?? [])
        );

        // Validate: either key-based or custom, but not both or neither.
        $iskeybased = ($subjectkey !== '' && $bodykey !== '');
        $isbroadcast = ($customsubject !== '' && $custombody !== '');
        if (!$cmid || $userfromid < 0 || empty($provider) || empty($recipientids) || !$iskeybased && !$isbroadcast) {
            // The request was invalid before it was queued; nothing to do.
            return;
        }

        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        // A sender id of 0 means the noreply sender.
        if ($userfromid === 0) {
            $sender = core_user::get_noreply_user();
        } else {
            $sender = core_user::get_user($userfromid, '*', IGNORE_MISSING);
        }
        if (!$sender) {
            return;
        }

        // Fetch recipients in one query so we only send to existing users.
        [$inids, $idparams] = $DB->get_in_or_equal($recipientids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_sql(
            "SELECT *
               FROM {user}
              WHERE id $inids
                AND deleted = 0",
            $idparams
        );
        $recipients = [];
        foreach ($records as $record) {
            $recipients[$record->id] = $record;
        }
        if (empty($recipients)) {
            return;
        }

        // Default to the activity page; callers may pass a recipient-specific link.
        $moduleurl = new moodle_url('/mod/projetvet/view.php', [
            'id' => $cmid,
        ]);
        if ($contexturl === '' && !empty($bodydata['link'])) {
            $contexturl = (string) $bodydata['link'];
        }
        $contexturl = $contexturl !== '' ? $contexturl : $moduleurl->out(false);

        foreach ($recipients as $recipient) {
            // Deliver the message in the language of the recipient.
            $previouslang = force_current_language($recipient->lang ?? '');

            // The default link label must be localized with the message.
            $recipientcontexturlname = $contexturlname !== '' ? $contexturlname
                : get_string('openprojetvet', 'mod_projetvet');

            if ($iskeybased) {
                $subject = get_string($subjectkey, 'mod_projetvet', $subjectdata);
                // Do not overwrite a link that the caller explicitly provided.
                $bodydata['link'] = $bodydata['link'] ?? $moduleurl->out(false);
                $body = get_string($bodykey, 'mod_projetvet', $bodydata);
                if ($smallkey !== '') {
                    $smallmessage = get_string($smallkey, 'mod_projetvet', $smalldata !== [] ? $smalldata : $subjectdata);
                } else {
                    $smallmessage = $subject;
                }
            } else {
                $subject = $customsubject;
                $body = $custombody . '<br><a href="' . $contexturl . '">' . $recipientcontexturlname . '</a>';
                $smallmessage = $subject;
            }

            $message = new \core\message\message();
            $message->component = 'mod_projetvet';
            $message->name = $provider;
            $message->userfrom = $sender;
            $message->userto = $recipient;
            $message->subject = $subject;
            $message->fullmessage = html_to_text($body);
            $message->fullmessageformat = FORMAT_HTML;
            $message->fullmessagehtml = $body;
            $message->smallmessage = $smallmessage;
            $message->notification = $notification ? 1 : 0;
            $message->contexturl = $contexturl;
            $message->contexturlname = $recipientcontexturlname;
            $message->courseid = $courseid > 0 ? $courseid : $cm->course;

            message_send($message);

            // Restore the previous language.
            force_current_language($previouslang);
        }
    }
}
