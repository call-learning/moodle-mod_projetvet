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

use moodle_url;

/**
 * Notifications helper class
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifications {
    /**
     * Message provider name registered in db/messages.php.
     * @var string
     */
    public const string PROVIDER = 'entry_action_required';

    /**
     * Get the tutors to notify about the follow-up of a student.
     *
     * While a substitution is in effect the substitute is notified instead of the principal tutor;
     * the principal tutor also receives the notifications when the manager chose to keep them
     * informed for that substitution. Otherwise the principal tutor is notified.
     *
     * @param int $studentid The student ID
     * @param int $projetvetid The projetvet instance ID
     * @return \stdClass[] The tutors to notify, indexed by user id
     */
    public static function get_tutor_recipients(int $studentid, int $projetvetid): array {
        $primary = \mod_projetvet\local\api\groups::get_student_primary_tutor($studentid, $projetvetid);
        $substitution = \mod_projetvet\local\api\substitutions::get_substitution_in_effect($studentid, $projetvetid);

        $recipients = [];
        if ($substitution) {
            $substitute = \core_user::get_user($substitution->get('substitutetutorid'));
            if ($substitute) {
                $recipients[$substitute->id] = $substitute;
            }
            if ($primary && $substitution->get('notifyprimary')) {
                $recipients[$primary->id] = $primary;
            }
        } else if ($primary) {
            $recipients[$primary->id] = $primary;
        }
        return $recipients;
    }

    /**
     * Queue a notification for the tutor(s) when a student submits an entry.
     *
     * The recipients follow the substitution rule, see {@see self::get_tutor_recipients()}.
     * The message is delivered asynchronously by the adhoc task, in the language
     * of each recipient.
     *
     * @param int $entryid The entry ID
     * @param int $studentid The student ID
     * @param int $cmid The course module ID
     * @param string $messagekey The language string key for the message
     * @return bool True if the notification has been queued successfully
     */
    public static function send_tutor_notification(int $entryid, int $studentid, int $cmid, string $messagekey): bool {
        global $DB;

        // Get the student user object.
        $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

        // Get course module to build the link.
        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);
        $projetvetid = $cm->instance;

        // Get the tutors to notify for this student.
        $tutors = self::get_tutor_recipients($studentid, $projetvetid);
        if (empty($tutors)) {
            // No tutor found - cannot queue the notification.
            return false;
        }

        // Build the link to the entry, kept as message parameter and context URL.
        $linkurl = new moodle_url('/mod/projetvet/view.php', [
            'id' => $cmid,
            'studentid' => $studentid,
        ]);

        $messagedata = [
            'studentname' => fullname($student),
            'link' => $linkurl->out(false),
        ];

        messaging::schedule_message([
            'provider' => self::PROVIDER,
            'cmid' => $cmid,
            'userfromid' => $studentid,
            'recipientids' => array_map('intval', array_keys($tutors)),
            'subjectkey' => $messagekey . '_subject',
            'bodykey' => $messagekey,
            'bodydata' => $messagedata,
            'smallkey' => 'notification_smallmessage',
            'smalldata' => $messagedata,
            'contexturl' => $linkurl->out(false),
            'courseid' => (int) $cm->course,
        ]);

        return true;
    }

    /**
     * Queue a notification for the student when a tutor takes action.
     *
     * The notification is sent on behalf of the active tutor (the substitute
     * while a substitution is in effect), or from noreply when the student
     * has no tutor. The message is delivered asynchronously by the adhoc task,
     * in the language of the recipient.
     *
     * @param int $entryid The entry ID
     * @param int $studentid The student ID
     * @param int $cmid The course module ID
     * @param string $messagekey The language string key for the message
     * @return bool True if the notification has been queued successfully
     */
    public static function send_student_notification(int $entryid, int $studentid, int $cmid, string $messagekey): bool {
        global $DB;

        // Get the student user object.
        $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

        // Get course module to build the link.
        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);
        $projetvetid = $cm->instance;

        // Send on behalf of the active tutor (the substitute while a substitution is in effect).
        $tutor = \mod_projetvet\local\api\substitutions::get_active_tutor($studentid, $projetvetid);

        // A sender id of 0 tells the task to use the noreply sender.
        $userfromid = $tutor ? (int) $tutor->id : 0;

        // Build the link to the entry, kept as message parameter and context URL.
        $linkurl = new moodle_url('/mod/projetvet/view.php', [
            'id' => $cmid,
        ]);

        messaging::schedule_message([
            'provider' => self::PROVIDER,
            'cmid' => $cmid,
            'userfromid' => $userfromid,
            'recipientids' => [$studentid],
            'subjectkey' => $messagekey . '_subject',
            'bodykey' => $messagekey,
            'bodydata' => ['link' => $linkurl->out(false)],
            'smallkey' => 'notification_smallmessage_student',
            'contexturl' => $linkurl->out(false),
            'courseid' => (int) $cm->course,
        ]);

        return true;
    }
}
