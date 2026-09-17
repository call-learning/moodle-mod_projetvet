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
 * Contact messaging helper
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class messaging {
    /**
     * Message provider name registered in db/messages.php.
     */
    const PROVIDER = 'contact';

    /**
     * Queue a contact message for delivery to the given recipients.
     *
     * The message is not sent inline: an adhoc task is scheduled, so that the
     * webservice call stays fast and delivery is decoupled from the request.
     * The task delivers the message through message_send, in the language of
     * each recipient, so that all standard Moodle messaging rules apply
     * (recipient preferences, enabled output plugins, provider availability).
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
        $task = new send_message();
        $task->set_custom_data((object) [
            'cmid' => $cmid,
            'userfromid' => $userfromid,
            'recipientids' => array_values($recipientids),
            'subjectkey' => $subjectkey,
            'bodykey' => $bodykey,
        ]);

        return \core\task\manager::queue_adhoc_task($task);
    }
}
