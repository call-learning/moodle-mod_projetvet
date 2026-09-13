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

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_projetvet\local\api\groups;

/**
 * Assign a teacher (primary tutor) to a set of students.
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_teacher extends external_api {
    /**
     * Assign a teacher to the given students.
     *
     * @param int $cmid Course module ID.
     * @param int $projetvetid Projetvet instance ID.
     * @param int[] $studentids The student user IDs to assign.
     * @param int $teacherid The teacher (primary tutor) user ID.
     * @return array
     */
    public static function execute(int $cmid, int $projetvetid, array $studentids, int $teacherid): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'cmid' => $cmid,
                'projetvetid' => $projetvetid,
                'studentids' => $studentids,
                'teacherid' => $teacherid,
            ]
        );

        $cm = get_coursemodule_from_id('projetvet', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/projetvet:admin', $context);

        if ((int) $cm->instance !== $params['projetvetid']) {
            throw new \invalid_parameter_exception('The projetvet instance does not match the course module.');
        }

        if (empty($params['studentids'])) {
            throw new \moodle_exception('nouseridprovided', 'mod_projetvet');
        }
        if (empty($params['teacherid'])) {
            throw new \moodle_exception('assignselectteacher', 'mod_projetvet');
        }

        $teacherids = array_map('intval', groups::get_all_teachers($params['cmid']));
        if (!in_array($params['teacherid'], $teacherids, true)) {
            throw new \invalid_parameter_exception('The selected user is not an eligible teacher.');
        }

        $studentids = array_map('intval', $params['studentids']);
        $studentids = array_values(array_unique($studentids));
        $studentidsincourse = array_map(
            'intval',
            array_column(groups::get_all_students($params['cmid']), 'uniqueid')
        );
        if (array_diff($studentids, $studentidsincourse)) {
            throw new \invalid_parameter_exception('One or more selected users are not eligible students.');
        }

        $assignedcount = groups::assign_students_to_teacher(
            $params['teacherid'],
            $studentids,
            $params['projetvetid']
        );

        return [
            'result' => true,
            'message' => get_string('membersadded', 'mod_projetvet', $assignedcount),
        ];
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID', VALUE_REQUIRED),
            'projetvetid' => new external_value(PARAM_INT, 'Projetvet instance ID', VALUE_REQUIRED),
            'studentids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Student user ID'),
                'Student user IDs to assign'
            ),
            'teacherid' => new external_value(PARAM_INT, 'Teacher (primary tutor) user ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, 'Success', VALUE_REQUIRED),
            'message' => new external_value(PARAM_TEXT, 'Result message', VALUE_REQUIRED),
        ]);
    }
}
