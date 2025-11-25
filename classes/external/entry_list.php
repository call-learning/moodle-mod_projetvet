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
use core_external\external_multiple_structure;
use mod_projetvet\local\api\entries;

/**
 * Class entry_list
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_list extends external_api {
    /**
     * Get entry list for a form set
     *
     * @param int $projetvetid The projetvet instance id
     * @param int $studentid The student id
     * @param string $formsetidnumber The form set idnumber
     * @param int $parententryid The parent entry id (for subset entries)
     * @return array
     */
    public static function execute(
        int $projetvetid,
        int $studentid,
        string $formsetidnumber,
        int $parententryid = 0
    ): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'projetvetid' => $projetvetid,
                'studentid' => $studentid,
                'formsetidnumber' => $formsetidnumber,
                'parententryid' => $parententryid,
            ]
        );

        // Get context from the projetvet instance.
        $cm = get_coursemodule_from_instance('projetvet', $params['projetvetid']);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Get the entry list.
        return entries::get_entry_list(
            $params['projetvetid'],
            $params['studentid'],
            $params['formsetidnumber'],
            $params['parententryid']
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
            'formsetidnumber' => new external_value(PARAM_ALPHANUMEXT, 'Form set idnumber', VALUE_REQUIRED),
            'parententryid' => new external_value(PARAM_INT, 'Parent entry ID for subset entries', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'activities' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Entry ID'),
                    'entrystatus' => new external_value(PARAM_INT, 'Entry status'),
                    'canedit' => new external_value(PARAM_BOOL, 'Can edit'),
                    'candelete' => new external_value(PARAM_BOOL, 'Can delete'),
                    'fields' => new external_multiple_structure(
                        new external_single_structure([
                            'idnumber' => new external_value(PARAM_ALPHANUMEXT, 'Field idnumber'),
                            'name' => new external_value(PARAM_TEXT, 'Field name'),
                            'value' => new external_value(PARAM_RAW, 'Field value'),
                            'displayvalue' => new external_value(PARAM_RAW, 'Display value'),
                        ])
                    ),
                ])
            ),
            'listfields' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Field ID'),
                    'idnumber' => new external_value(PARAM_ALPHANUMEXT, 'Field idnumber'),
                    'name' => new external_value(PARAM_TEXT, 'Field name'),
                    'type' => new external_value(PARAM_TEXT, 'Field type'),
                    'listorder' => new external_value(PARAM_INT, 'List order'),
                ])
            ),
        ]);
    }
}
