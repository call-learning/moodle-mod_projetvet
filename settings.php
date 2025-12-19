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

/**
 * Plugin administration pages are defined here.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('mod_projetvet_settings', new lang_string('pluginname', 'mod_projetvet'));

    if ($ADMIN->fulltree) {
        // Tutor role setting.
        $settings->add(
            new admin_setting_heading(
                'mod_projetvet/tutor_heading',
                get_string('tutor_heading', 'mod_projetvet'),
                get_string('tutor_heading_desc', 'mod_projetvet'),
            )
        );

        // Get all roles for selection.
        if (!during_initial_install()) {
            global $DB;
            $allroles = $DB->get_records('role', null, 'sortorder', 'id, name, shortname');
            $roles = [];
            foreach ($allroles as $role) {
                $roles[$role->shortname] = $role->name ? $role->name : $role->shortname;
            }

            $settings->add(
                new admin_setting_configselect(
                    'mod_projetvet/tutor_role',
                    get_string('tutor_role', 'mod_projetvet'),
                    get_string('tutor_role_desc', 'mod_projetvet'),
                    'teacher',
                    $roles
                )
            );
        }

        // Hours to ECTS conversion.
        $settings->add(
            new admin_setting_heading(
                'mod_projetvet/conversion_heading',
                get_string('conversion_heading', 'mod_projetvet'),
                get_string('conversion_heading_desc', 'mod_projetvet'),
            )
        );

        $settings->add(
            new admin_setting_configtext(
                'mod_projetvet/hours_per_ects',
                get_string('hours_per_ects', 'mod_projetvet'),
                get_string('hours_per_ects_desc', 'mod_projetvet'),
                '30',
                PARAM_INT
            )
        );

        // ECTS attribution guide PDF.
        $settings->add(
            new admin_setting_configstoredfile(
                'mod_projetvet/ects_guide_pdf',
                get_string('ects_guide_pdf', 'mod_projetvet'),
                get_string('ects_guide_pdf_desc', 'mod_projetvet'),
                'ectsguide',
                0,
                ['maxfiles' => 1, 'accepted_types' => ['.pdf']]
            )
        );

        // Target credits settings.
        $settings->add(
            new admin_setting_heading(
                'mod_projetvet/targets_heading',
                get_string('targets_heading', 'mod_projetvet'),
                get_string('targets_heading_desc', 'mod_projetvet'),
            )
        );

        // Target total ECTS.
        $settings->add(
            new admin_setting_configtext(
                'mod_projetvet/target_ects',
                get_string('target_ects', 'mod_projetvet'),
                get_string('target_ects_desc', 'mod_projetvet'),
                20,
                PARAM_INT,
            )
        );

        // Target credits by type.
        $settings->add(
            new admin_setting_configtext(
                'mod_projetvet/target_rank_a_percentage',
                get_string('target_rank_a_percentage', 'mod_projetvet'),
                get_string('target_rank_a_percentage_desc', 'mod_projetvet'),
                75,
                PARAM_INT,
            )
        );

        // Target rank B percentage.
        $settings->add(
            new admin_setting_configtext(
                'mod_projetvet/target_rank_b_percentage',
                get_string('target_rank_b_percentage', 'mod_projetvet'),
                get_string('target_rank_b_percentage_desc', 'mod_projetvet'),
                25,
                PARAM_INT,
            )
        );

        // Target tutor interviews.
        $settings->add(
            new admin_setting_configtext(
                'mod_projetvet/target_interviews',
                get_string('target_interviews', 'mod_projetvet'),
                get_string('target_interviews_desc', 'mod_projetvet'),
                20,
                PARAM_INT,
            )
        );

        // Permissions settings.
        $settings->add(
            new admin_setting_heading(
                'mod_projetvet/permissions_heading',
                get_string('permissions_heading', 'mod_projetvet'),
                get_string('permissions_heading_desc', 'mod_projetvet'),
            )
        );

        // Allow editing previous status.
        $settings->add(
            new admin_setting_configcheckbox(
                'mod_projetvet/allow_edit_previous_status',
                get_string('allow_edit_previous_status', 'mod_projetvet'),
                get_string('allow_edit_previous_status_desc', 'mod_projetvet'),
                0
            )
        );
    }
}
