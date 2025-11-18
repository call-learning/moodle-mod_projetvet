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

namespace mod_projetvet;

use mod_projetvet\local\importer\fields_json_importer;
use mod_projetvet\local\persistent\form_field;

/**
 * Setup routines
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setup {
    /**
     * Create the default activity fields.
     *
     * @return void
     */
    public static function create_default_activities() {
        global $CFG;

        // Define all form sets to import.
        $formsets = [
            'activities' => [
                'name' => 'Activities',
                'description' => 'Activity form fields',
                'sortorder' => 0,
                'jsonfile' => 'default_activity_form.json',
            ],
            'facetoface' => [
                'name' => 'Face-to-face sessions',
                'description' => 'Face-to-face session forms',
                'sortorder' => 1,
                'jsonfile' => 'default_facetoface_form.json',
            ],
            'carnet_cas' => [
                'name' => 'Carnet de cas',
                'description' => 'Carnet de cas cliniques',
                'sortorder' => 2,
                'jsonfile' => 'default_carnet_cas_form.json',
            ],
        ];

        // Import each form set.
        foreach ($formsets as $idnumber => $config) {
            $jsonfile = $CFG->dirroot . '/mod/projetvet/data/' . $config['jsonfile'];

            if (!file_exists($jsonfile)) {
                debugging("JSON file not found: {$config['jsonfile']}", DEBUG_DEVELOPER);
                continue;
            }

            $importer = new fields_json_importer(
                $idnumber,
                $config['name'],
                $config['description'],
                $config['sortorder']
            );
            $importer->import($jsonfile);
        }

        // Import field lookup data for tagselect fields.
        fields_json_importer::import_field_lookup_data();
    }
}
