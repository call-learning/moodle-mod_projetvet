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
use moodle_url;

/**
 * Adhoc task that delivers a ProjetVet contact message to its recipients.
 *
 * The task is queued by the mod_projetvet_send_message webservice and delivers
 * the message through the standard Moodle messaging subsystem (message_send),
 * so that recipient preferences, enabled output plugins (popup, email, ...)
 * and provider availability all apply.
 *
 * @package   mod_projetvet
 * @copyright 2026 Laurent David <laurent@call-learning.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends adhoc_task {
    /**
     * Deliver the queued contact message.
     *
     * The message is always delivered in the language of each recipient.
     * Recipients that no longer exist are silently skipped.
     *
     * The customdata is expected to hold:
     *   - cmid (int): the course module id
     *   - userfromid (int): the sender user id
     *   - recipientids (int[]): the recipient user ids
     *   - subjectkey (string): the language string key for the subject
     *   - bodykey (string): the language string key for the body
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $customdata = $this->get_custom_data();

        $cmid = (int) ($customdata->cmid ?? 0);
        $userfromid = (int) ($customdata->userfromid ?? 0);
        $subjectkey = (string) ($customdata->subjectkey ?? '');
        $bodykey = (string) ($customdata->bodykey ?? '');
        $recipientids = array_map(
            'intval',
            (array) ($customdata->recipientids ?? [])
        );

        if (!$cmid || !$userfromid || !$subjectkey || !$bodykey || empty($recipientids)) {
            // The request was invalid before it was queued; nothing to do.
            return;
        }

        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $sender = core_user::get_user($userfromid, '*', IGNORE_MISSING);
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

        $linkurl = new moodle_url('/mod/projetvet/view.php', [
            'id' => $cmid,
        ]);
        $contexturlname = get_string('openprojetvet', 'mod_projetvet');

        foreach ($recipients as $recipient) {
            // Deliver the message in the recipient's language.
            $previouslang = $GLOBALS['USER']->lang ?? null;
            if (!empty($recipient->lang)) {
                $GLOBALS['USER']->lang = $recipient->lang;
            }

            $subject = get_string($subjectkey, 'mod_projetvet');
            $body = get_string($bodykey, 'mod_projetvet', ['link' => $linkurl->out(false)]);

            $message = new \core\message\message();
            $message->component = 'mod_projetvet';
            $message->name = 'contact';
            $message->userfrom = $sender;
            $message->userto = $recipient;
            $message->subject = $subject;
            $message->fullmessage = html_to_text($body);
            $message->fullmessageformat = FORMAT_HTML;
            $message->fullmessagehtml = $body;
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = $linkurl->out(false);
            $message->contexturlname = $contexturlname;
            $message->courseid = $cm->course;

            message_send($message);

            // Restore the previous language.
            if ($previouslang !== null) {
                $GLOBALS['USER']->lang = $previouslang;
            }
        }
    }
}
