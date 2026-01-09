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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * Class suggested_ects
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suggested_ects extends external_api {
    /**
     * Get suggested ECTS for given hours and rang
     *
     * @param int $projetvetid The projetvet instance id
     * @param int $studentid The student id
     * @param int $entryid The entry id to get rang from
     * @param float $hours The number of hours
     * @param string $stringidentifier The language string identifier for the message
     * @param int $rangvalue The rang value (0 = fetch from entry, 1 = A, 2 = B)
     * @param float|null $finalects The final ECTS value (optional)
     * @return array
     */
    public static function execute(
        int $projetvetid,
        int $studentid,
        int $entryid,
        float $hours,
        string $stringidentifier = '',
        int $rangvalue = 0,
        ?float $finalects = 0
    ): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'projetvetid' => $projetvetid,
                'studentid' => $studentid,
                'entryid' => $entryid,
                'hours' => $hours,
                'stringidentifier' => $stringidentifier,
                'rangvalue' => $rangvalue,
                'finalects' => $finalects,
            ]
        );

        // Get context from the projetvet instance.
        $cm = get_coursemodule_from_instance('projetvet', $params['projetvetid']);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Call the utils function to calculate suggested ECTS.
        return \mod_projetvet\utils::get_suggested_ects(
            $params['projetvetid'],
            $params['studentid'],
            $params['entryid'],
            $params['hours'],
            $params['stringidentifier'],
            $params['rangvalue'],
            $params['finalects']
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'projetvetid' => new external_value(PARAM_INT, 'Projetvet instance ID', VALUE_REQUIRED),
            'studentid' => new external_value(PARAM_INT, 'Student ID', VALUE_REQUIRED),
            'entryid' => new external_value(PARAM_INT, 'Entry ID to get rang from', VALUE_DEFAULT, 0),
            'hours' => new external_value(PARAM_FLOAT, 'Number of hours', VALUE_REQUIRED),
            'stringidentifier' => new external_value(PARAM_TEXT, 'Language string identifier', VALUE_DEFAULT, ''),
            'rangvalue' => new external_value(PARAM_INT, 'Rang value (0=fetch from entry, 1=A, 2=B)', VALUE_DEFAULT, 0),
            'finalects' => new external_value(PARAM_FLOAT, 'Final ECTS value', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'suggestedects' => new external_value(PARAM_INT, 'Suggested ECTS credits'),
            'message' => new external_value(PARAM_RAW, 'Formatted message with before/after values'),
            'warning' => new external_value(PARAM_TEXT, 'Warning message if any'),
            'error' => new external_value(PARAM_TEXT, 'Error message if any'),
        ]);
    }
}
