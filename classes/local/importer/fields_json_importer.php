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

namespace mod_projetvet\local\importer;

use mod_projetvet\local\persistent\form_cat;
use mod_projetvet\local\persistent\form_field;
use mod_projetvet\local\persistent\form_set;

/**
 * Fields JSON importer
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fields_json_importer {
    /**
     * @var string $persistentclass The persistent class to import to
     */
    protected $persistentclass;

    /**
     * Constructor
     *
     * @param string $persistentclass
     */
    public function __construct(string $persistentclass) {
        $this->persistentclass = $persistentclass;
    }

    /**
     * Import fields from JSON file
     *
     * @param string $filepath Path to JSON file
     * @return void
     */
    public function import(string $filepath): void {
        if (!file_exists($filepath)) {
            throw new \moodle_exception('filenotfound', 'error', '', $filepath);
        }

        $jsondata = file_get_contents($filepath);
        $data = json_decode($jsondata, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('invalidjson', 'error', '', json_last_error_msg());
        }

        if (!isset($data['categories']) || !is_array($data['categories'])) {
            throw new \moodle_exception('invalidjsonstructure', 'error', '', 'Missing categories array');
        }

        // Get or create the 'activities' form set.
        $formset = form_set::get_record(['idnumber' => 'activities']);
        if (!$formset) {
            $formset = new form_set(0, (object)[
                'idnumber' => 'activities',
                'name' => 'Activities',
                'description' => 'Activity form fields',
                'sortorder' => 0,
            ]);
            $formset->create();
        }

        foreach ($data['categories'] as $categorydata) {
            // Create or get category.
            $category = form_cat::get_record(['idnumber' => $categorydata['idnumber']]);
            if (!$category) {
                $category = new form_cat(0, (object)[
                    'formsetid' => $formset->get('id'),
                    'idnumber' => $categorydata['idnumber'],
                    'name' => $categorydata['name'],
                    'description' => $categorydata['description'] ?? '',
                    'sortorder' => $categorydata['sortorder'] ?? 0,
                    'capability' => $categorydata['capability'] ?? null,
                    'entrystatus' => $categorydata['entrystatus'] ?? 0,
                ]);
                $category->create();
            } else {
                // Update existing category.
                $category->set('formsetid', $formset->get('id'));
                $category->set('name', $categorydata['name']);
                $category->set('description', $categorydata['description'] ?? '');
                $category->set('sortorder', $categorydata['sortorder'] ?? 0);
                $category->update();
            }

            // Process fields for this category.
            if (isset($categorydata['fields']) && is_array($categorydata['fields'])) {
                foreach ($categorydata['fields'] as $fielddata) {
                    $this->import_field($fielddata, $category->get('id'));
                }
            }
        }
    }

    /**
     * Import a single field
     *
     * @param array $fielddata Field data from JSON
     * @param int $categoryid Category ID
     * @return void
     */
    protected function import_field(array $fielddata, int $categoryid): void {
        // Create or update field.
        $field = form_field::get_record(['idnumber' => $fielddata['idnumber']]);

        $fieldrecord = (object)[
            'idnumber' => $fielddata['idnumber'],
            'name' => $fielddata['name'],
            'type' => $fielddata['type'],
            'description' => $fielddata['description'] ?? '',
            'sortorder' => $fielddata['sortorder'] ?? 0,
            'categoryid' => $categoryid,
            'configdata' => json_encode($fielddata['configdata'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'capability' => $fielddata['capability'] ?? null,
            'entrystatus' => $fielddata['entrystatus'] ?? 0,
            'listorder' => $fielddata['listorder'] ?? 0,
        ];

        if ($field) {
            $field->from_record($fieldrecord);
            $field->update();
        } else {
            $field = new form_field(0, $fieldrecord);
            $field->create();
        }
    }
}
