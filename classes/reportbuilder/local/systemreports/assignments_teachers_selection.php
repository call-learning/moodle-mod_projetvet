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

namespace mod_projetvet\reportbuilder\local\systemreports;

use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use lang_string;
use context_module;
use mod_projetvet\reportbuilder\local\entities\teacher;

/**
 * Assignments teachers selection system report for projetvet
 *
 * Standalone variant of the assignments teachers report used in the "assign a teacher"
 * popup. It builds on the very same teacher capacity entity (see
 * {@see \mod_projetvet\reportbuilder\local\entities\teacher}) but presents the teachers as
 * selectable radio buttons, has no row actions and is not clickable via JavaScript.
 *
 * Because it is a dedicated report class it gets its own report ID and therefore its
 * own persisted filter state, fully independent from the main page report.
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignments_teachers_selection extends system_report {
    /**
     * Initialise report
     */
    protected function initialise(): void {
        global $DB;

        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);

        // Get course module.
        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);

        // Main user entity.
        $entityuser = new user();
        $entityuseralias = $entityuser->get_table_alias('user');

        $this->set_main_table('user', $entityuseralias);
        $this->add_entity($entityuser);

        // Teacher capacity entity. Assign the report's user table alias so the entity's
        // sub-queries correlate against the report's main user table.
        $entityteacher = new teacher($projetvetid);
        $entityteacher->set_table_alias('user', $entityuseralias);
        $this->add_entity($entityteacher);

        // Base fields needed.
        $this->add_base_fields("{$entityuseralias}.id, {$entityuseralias}.firstname, {$entityuseralias}.lastname");

        // Get all teachers with approve capability.
        $teacherids = \mod_projetvet\local\api\groups::get_all_teachers($cmid);

        if (!empty($teacherids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($teacherids, SQL_PARAMS_NAMED, database::generate_param_name());
            $this->add_base_condition_sql("{$entityuseralias}.id $insql", $inparams);
        } else {
            $this->add_base_condition_sql("1 = 0");
        }

        $this->add_columns();
        $this->add_filters();

        $this->set_downloadable(false);
        $this->set_default_per_page(30);
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        if (!$cmid) {
            return false;
        }

        $cm = get_coursemodule_from_id('projetvet', $cmid);
        if (!$cm) {
            return false;
        }

        $context = context_module::instance($cm->id);
        return has_capability('mod/projetvet:admin', $context);
    }

    /**
     * Add columns to the report
     */
    protected function add_columns(): void {
        $entityuser = $this->get_entity('user');
        $entityuseralias = $entityuser->get_table_alias('user');
        $entityteacher = $this->get_entity('teacher');
        $selectedteacherid = $this->get_parameter('selectedteacherid', 0, PARAM_INT);

        // Radio column used by the form to select a teacher.
        $selectcolumn = (new column(
            'select',
            new lang_string('select', 'core'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_select')
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(false)
            ->add_attributes(['class' => 'w-30'])
            ->add_callback(static function ($value, $row) use ($selectedteacherid): string {
                global $OUTPUT;
                return $OUTPUT->render_from_template('mod_projetvet/reportbuilder/teacher_radio', [
                    'teacherid' => $row->userid_select,
                    'checked' => ($selectedteacherid > 0 && (int)$row->userid_select === $selectedteacherid),
                ]);
            });

        $this->add_column($selectcolumn);

        // Fullname with picture. Sort by the actual lastname field, rather than by the
        // configured display order of the user's full name.
        $fullnamecolumn = $entityuser->get_column('fullnamewithpicturelink');
        $fullnamecolumn->add_field("{$entityuseralias}.lastname")
            ->add_field("{$entityuseralias}.firstname")
            ->set_is_sortable(true, ["{$entityuseralias}.lastname", "{$entityuseralias}.firstname"]);
        $this->add_column($fullnamecolumn);

        // Rating, target capacity, current student count and gap columns from the teacher entity.
        $this->add_column($entityteacher->get_column('rating'));
        $this->add_column($entityteacher->get_column('target'));
        $this->add_column($entityteacher->get_column('current'));
        $this->add_column($entityteacher->get_column('gap'));

        // Default sorting by the actual lastname field.
        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
    }

    /**
     * Add filters to the report
     */
    protected function add_filters(): void {
        $entityuser = $this->get_entity('user');

        // Fullname filter.
        $this->add_filter($entityuser->get_filter('fullname'));
    }
}
