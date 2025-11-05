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

use mod_projetvet\local\persistent\act_cat;
use mod_projetvet\local\persistent\act_field;

/**
 * Fields importer
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fields_importer {
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
     * Import fields from CSV file
     *
     * @param string $filepath Path to CSV file
     * @return void
     */
    public function import(string $filepath): void {
        if (!file_exists($filepath)) {
            throw new \moodle_exception('filenotfound', 'error', '', $filepath);
        }

        $csvdata = array_map(function ($line) {
            return str_getcsv($line, ';');
        }, file($filepath));

        $headers = array_shift($csvdata);

        $categories = [];
        $sortorder = 1;

        foreach ($csvdata as $row) {
            $data = array_combine($headers, $row);

            // Create or get category.
            $categoryname = $data['category'];
            if (!isset($categories[$categoryname])) {
                $category = act_cat::get_record(['name' => $categoryname]);
                if (!$category) {
                    $category = new act_cat(0, (object)[
                        'idnumber' => strtolower(str_replace(' ', '_', $categoryname)),
                        'name' => $categoryname,
                        'description' => '',
                        'sortorder' => count($categories) + 1,
                    ]);
                    $category->create();
                }
                $categories[$categoryname] = $category;
            }

            // Create or update field.
            $field = act_field::get_record(['idnumber' => $data['idnumber']]);
            $fielddata = (object)[
                'idnumber' => $data['idnumber'],
                'name' => $data['name'],
                'type' => $data['type'],
                'description' => $data['description'] ?? '',
                'sortorder' => intval($data['sortorder']),
                'categoryid' => $categories[$categoryname]->get('id'),
                'configdata' => $data['configdata'] ?? '{}',
                'capability' => $data['capability'] ?? null,
                'entrystatus' => isset($data['entrystatus']) ? intval($data['entrystatus']) : 0,
            ];

            if ($field) {
                $field->from_record($fielddata);
                $field->update();
            } else {
                $field = new act_field(0, $fielddata);
                $field->create();
            }
        }
    }
}
