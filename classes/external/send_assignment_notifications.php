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

use context_module;
use core_external\external_api;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_function_parameters;
use mod_projetvet\local\api\groups;
use mod_projetvet\local\assignment_notifications;
use mod_projetvet\local\messaging;

/**
 * Queue manager-confirmed tutor assignment notifications.
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_assignment_notifications extends external_api {
    /**
     * Queue notifications for the supplied, validated assignment changes.
     *
     * @param int $cmid Course module id.
     * @param int $projetvetid ProjetVet instance id.
     * @param string $scenario Expected scenario.
     * @param array $changes Assignment state transitions.
     * @return array
     */
    public static function execute(int $cmid, int $projetvetid, string $scenario, array $changes): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'projetvetid' => $projetvetid,
            'scenario' => $scenario,
            'changes' => $changes,
        ]);

        $cm = get_coursemodule_from_id('projetvet', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/projetvet:admin', $context);

        if ((int) $cm->instance !== $params['projetvetid']) {
            throw new \invalid_parameter_exception('The ProjetVet instance does not match the course module.');
        }
        if (empty($params['changes'])) {
            throw new \invalid_parameter_exception('At least one assignment change is required.');
        }

        $queued = 0;
        foreach ($params['changes'] as $change) {
            $plan = assignment_notifications::build_plan(
                $params['cmid'],
                $params['projetvetid'],
                (int) $change['studentid'],
                (int) $change['beforeprimaryid'],
                (int) $change['afterprimaryid'],
                (int) $change['beforetemporaryid'],
                (int) $change['aftertemporaryid'],
                (int) $change['date']
            );
            if (
                !assignment_notifications::is_valid_signature(
                    $change['signature'],
                    $params['cmid'],
                    $params['projetvetid'],
                    (int) $change['studentid'],
                    (int) $change['beforeprimaryid'],
                    (int) $change['afterprimaryid'],
                    (int) $change['beforetemporaryid'],
                    (int) $change['aftertemporaryid'],
                    (int) $change['date']
                )
            ) {
                throw new \invalid_parameter_exception('The assignment notification signature is invalid.');
            }
            if ($plan['scenario'] !== $params['scenario']) {
                throw new \invalid_parameter_exception('The selected notification scenario does not match the assignment change.');
            }

            // The post-change principal tutor must still be the current assignment.
            // This prevents a stale or forged notification request from targeting an
            // unrelated tutor after the assignment operation has completed.
            $currentprimary = groups::get_student_primary_tutor(
                (int) $change['studentid'],
                $params['projetvetid']
            );
            if (!$currentprimary || (int) $currentprimary->id !== (int) $change['afterprimaryid']) {
                throw new \invalid_parameter_exception('The assignment context is no longer current.');
            }

            foreach ($plan['messages'] as $message) {
                messaging::schedule_personal_send(
                    $params['cmid'],
                    (int) $USER->id,
                    (int) $message['userid'],
                    $message['subjectkey'],
                    $message['bodykey'],
                    $message['data'],
                    $message['bodydata']
                );
                $queued++;
            }
        }

        return [
            'result' => true,
            'message' => get_string('assignmentnotificationsqueued', 'mod_projetvet', $queued),
            'count' => $queued,
        ];
    }

    /**
     * Describe the external function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        $change = new external_single_structure([
            'studentid' => new external_value(PARAM_INT, 'Student user id', VALUE_REQUIRED),
            'beforeprimaryid' => new external_value(PARAM_INT, 'Previous principal tutor id', VALUE_DEFAULT, 0),
            'afterprimaryid' => new external_value(PARAM_INT, 'New principal tutor id', VALUE_DEFAULT, 0),
            'beforetemporaryid' => new external_value(PARAM_INT, 'Previous temporary tutor id', VALUE_DEFAULT, 0),
            'aftertemporaryid' => new external_value(PARAM_INT, 'New temporary tutor id', VALUE_DEFAULT, 0),
            'date' => new external_value(PARAM_INT, 'Effective date', VALUE_DEFAULT, 0),
            'signature' => new external_value(PARAM_ALPHANUMEXT, 'Signed assignment transition', VALUE_REQUIRED),
        ]);

        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id', VALUE_REQUIRED),
            'projetvetid' => new external_value(PARAM_INT, 'ProjetVet instance id', VALUE_REQUIRED),
            'scenario' => new external_value(PARAM_ALPHANUMEXT, 'Notification scenario', VALUE_REQUIRED),
            'changes' => new external_multiple_structure($change, 'Assignment changes', VALUE_REQUIRED),
        ]);
    }

    /**
     * Describe the external function return values.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, 'Success', VALUE_REQUIRED),
            'message' => new external_value(PARAM_TEXT, 'Result message', VALUE_REQUIRED),
            'count' => new external_value(PARAM_INT, 'Number of queued messages', VALUE_REQUIRED),
        ]);
    }
}
