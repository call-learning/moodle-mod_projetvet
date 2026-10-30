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
use moodle_url;
use mod_projetvet\local\api\groups;
use mod_projetvet\local\teacher_selection;

/**
 * Returns the HTML body of the "assign a teacher" selection popup.
 *
 * The content (selected students + teachers selection report with a preselected
 * radio) is rendered here so that it can be shown in a plain modal. The report is
 * NOT embedded in a form, which would break its own filter form (nested forms).
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_assign_teacher_modal extends external_api {
    /**
     * Get the "assign a teacher" popup body.
     *
     * @param int $cmid Course module ID.
     * @param int $projetvetid Projetvet instance ID.
     * @param int[] $studentids The student user IDs being assigned.
     * @return array
     */
    public static function execute(int $cmid, int $projetvetid, array $studentids): array {
        global $PAGE, $OUTPUT;

        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'cmid' => $cmid,
                'projetvetid' => $projetvetid,
                'studentids' => $studentids,
            ]
        );

        $cm = get_coursemodule_from_id('projetvet', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/projetvet:admin', $context);

        if ((int) $cm->instance !== $params['projetvetid']) {
            throw new \invalid_parameter_exception('The projetvet instance does not match the course module.');
        }

        $studentids = array_map('intval', array_values(array_unique($params['studentids'])));
        $studentidsincourse = array_map(
            'intval',
            array_column(groups::get_all_students($params['cmid']), 'uniqueid')
        );
        if (array_diff($studentids, $studentidsincourse)) {
            throw new \invalid_parameter_exception('One or more selected users are not eligible students.');
        }

        // The report renderer needs the page to be set up on the module context.
        $PAGE->set_context($context);
        $PAGE->set_url(new moodle_url('/mod/projetvet/assignments.php', ['id' => $params['cmid']]));
        $OUTPUT->header();

        // Ensure the theme/output is initialised before collecting the fragment javascript.
        // $OUTPUT->header() does this for the HTML renderer, but the CLI renderer (used by
        // unit tests) does not, so initialise it explicitly. This is a no-op when the theme
        // has already been initialised.
        $PAGE->initialise_theme_and_output();

        // Render the popup body and collect the javascript it requires (report and filter init).
        $PAGE->start_collecting_javascript_requirements();
        $result = teacher_selection::get_modal_html(
            $params['cmid'],
            $params['projetvetid'],
            $studentids
        );
        $javascript = $PAGE->requires->get_end_code();

        return [
            'html' => $result['html'],
            'javascript' => $javascript,
            'preselectedteacherid' => $result['preselectedteacherid'],
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
                'Student user IDs being assigned'
            ),
        ]);
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Popup body HTML', VALUE_REQUIRED),
            'javascript' => new external_value(PARAM_RAW, 'JavaScript required by the popup body', VALUE_REQUIRED),
            'preselectedteacherid' => new external_value(PARAM_INT, 'Preselected teacher user ID, or 0', VALUE_REQUIRED),
        ]);
    }
}
