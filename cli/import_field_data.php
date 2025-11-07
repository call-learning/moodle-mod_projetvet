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
 * Import field lookup data from JSON files
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_projetvet\local\persistent\form_field;
use mod_projetvet\local\persistent\field_data;

/**
 * Import field data from JSON file
 *
 * @param int $fieldid
 * @param string $jsonfile
 * @return int Number of records imported
 */
function import_field_data($fieldid, $jsonfile) {
    if (!file_exists($jsonfile)) {
        echo "File not found: $jsonfile\n";
        return 0;
    }

    $json = file_get_contents($jsonfile);
    $data = json_decode($json, true);

    if (empty($data)) {
        echo "No data found in: $jsonfile\n";
        return 0;
    }

    // Delete existing data for this field.
    field_data::delete_field_data($fieldid);

    $count = 0;
    foreach ($data as $item) {
        $record = new field_data(0, (object)[
            'fieldid' => $fieldid,
            'uniqueid' => $item['uniqueid'],
            'itemtype' => $item['type'],
            'parent' => $item['parent'],
            'name' => $item['name'],
            'sortorder' => $item['sortorder'],
        ]);
        $record->create();
        $count++;
    }

    return $count;
}

// Get all tagselect fields.
$fields = form_field::get_records(['type' => 'tagselect']);

if (empty($fields)) {
    echo "No tagselect fields found\n";
    exit(0);
}

$totalcount = 0;

foreach ($fields as $field) {
    echo "Processing field: {$field->get('name')} (ID: {$field->get('id')}, idnumber: {$field->get('idnumber')})\n";

    // Determine which JSON file to use based on idnumber.
    $jsonfile = null;
    if ($field->get('idnumber') === 'competency') {
        $jsonfile = $CFG->dirroot . '/mod/projetvet/data/complist.json';
    } else if ($field->get('idnumber') === 'category') {
        $jsonfile = $CFG->dirroot . '/mod/projetvet/data/categories.json';
    }

    if ($jsonfile) {
        $count = import_field_data($field->get('id'), $jsonfile);
        echo "  Imported $count records from " . basename($jsonfile) . "\n";
        $totalcount += $count;
    } else {
        echo "  No JSON file mapped for this field\n";
    }
}

echo "\nTotal records imported: $totalcount\n";
echo "Field data import completed successfully\n";
