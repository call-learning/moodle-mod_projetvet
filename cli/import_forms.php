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
 * Import form structures and field data from JSON files
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_projetvet\local\persistent\form_set;
use mod_projetvet\local\persistent\form_cat;
use mod_projetvet\local\persistent\form_field;
use mod_projetvet\local\persistent\field_data;

// Get CLI options.
list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'reset' => false,
        'formset' => null,
    ],
    [
        'h' => 'help',
        'r' => 'reset',
        'f' => 'formset',
    ]
);

if ($options['help']) {
    echo "Import form structures and field data from JSON files.

Options:
-h, --help          Print this help
-r, --reset         Delete all existing form data before importing
-f, --formset=NAME  Import only specified form set (activities or facetoface)

Examples:
# Import all form sets
php import_forms.php

# Reset and reimport all form sets
php import_forms.php --reset

# Import only activities form set
php import_forms.php --formset=activities

# Import only facetoface form set
php import_forms.php --formset=facetoface
";
    exit(0);
}

// Define form sets to import.
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
];

// Filter by requested form set if specified.
if ($options['formset']) {
    if (!isset($formsets[$options['formset']])) {
        echo "Error: Unknown form set '{$options['formset']}'\n";
        echo "Available form sets: " . implode(', ', array_keys($formsets)) . "\n";
        exit(1);
    }
    $formsets = [$options['formset'] => $formsets[$options['formset']]];
}

// Reset all data if requested.
if ($options['reset']) {
    echo "Resetting all form data...\n";
    $DB->delete_records('projetvet_form_data');
    echo "  Deleted form_data records\n";
    $DB->delete_records('projetvet_form_entry');
    echo "  Deleted form_entry records\n";
    $DB->delete_records('projetvet_field_data');
    echo "  Deleted field_data records\n";
    $DB->delete_records('projetvet_form_field');
    echo "  Deleted form_field records\n";
    $DB->delete_records('projetvet_form_cat');
    echo "  Deleted form_cat records\n";

    // Only delete form sets if resetting everything.
    if (!$options['formset']) {
        $DB->delete_records('projetvet_form_set');
        echo "  Deleted form_set records\n";
    }

    // Clear caches.
    $cache = cache::make('mod_projetvet', 'activitystructures');
    $cache->purge();
    echo "  Cleared cache\n\n";
}

/**
 * Import a form set from JSON file
 *
 * @param string $idnumber Form set idnumber
 * @param array $config Form set configuration
 * @return array Statistics
 */
function import_formset($idnumber, $config) {
    global $CFG;

    $stats = ['categories' => 0, 'fields' => 0];

    // Get or create the form set.
    $formset = form_set::get_record(['idnumber' => $idnumber]);
    if (!$formset) {
        echo "Creating form set: {$config['name']}\n";
        $formset = new form_set(0, (object)[
            'idnumber' => $idnumber,
            'name' => $config['name'],
            'description' => $config['description'],
            'sortorder' => $config['sortorder'],
        ]);
        $formset->create();
    } else {
        echo "Using existing form set: {$config['name']} (ID: {$formset->get('id')})\n";
    }

    // Load JSON file.
    $jsonfile = $CFG->dirroot . '/mod/projetvet/data/' . $config['jsonfile'];
    if (!file_exists($jsonfile)) {
        echo "  ERROR: File not found: {$config['jsonfile']}\n";
        return $stats;
    }

    $jsondata = file_get_contents($jsonfile);
    $data = json_decode($jsondata, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "  ERROR: Invalid JSON: " . json_last_error_msg() . "\n";
        return $stats;
    }

    if (!isset($data['categories']) || !is_array($data['categories'])) {
        echo "  ERROR: Missing categories array in JSON\n";
        return $stats;
    }

    // Import categories and fields.
    foreach ($data['categories'] as $categorydata) {
        $category = form_cat::get_record(['idnumber' => $categorydata['idnumber']]);

        if (!$category) {
            echo "  Creating category: {$categorydata['name']}\n";
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
            $stats['categories']++;
        } else {
            echo "  Updating category: {$categorydata['name']}\n";
            $category->set('formsetid', $formset->get('id'));
            $category->set('name', $categorydata['name']);
            $category->set('description', $categorydata['description'] ?? '');
            $category->set('sortorder', $categorydata['sortorder'] ?? 0);
            $category->update();
        }

        // Import fields.
        if (isset($categorydata['fields']) && is_array($categorydata['fields'])) {
            foreach ($categorydata['fields'] as $fielddata) {
                $field = form_field::get_record(['idnumber' => $fielddata['idnumber']]);

                $fieldrecord = (object)[
                    'idnumber' => $fielddata['idnumber'],
                    'name' => $fielddata['name'],
                    'type' => $fielddata['type'],
                    'description' => $fielddata['description'] ?? '',
                    'sortorder' => $fielddata['sortorder'] ?? 0,
                    'categoryid' => $category->get('id'),
                    'configdata' => json_encode($fielddata['configdata'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'capability' => $fielddata['capability'] ?? null,
                    'entrystatus' => $fielddata['entrystatus'] ?? 0,
                    'listorder' => $fielddata['listorder'] ?? 0,
                ];

                if ($field) {
                    $field->from_record($fieldrecord);
                    $field->update();
                } else {
                    echo "    Creating field: {$fielddata['name']}\n";
                    $field = new form_field(0, $fieldrecord);
                    $field->create();
                    $stats['fields']++;
                }
            }
        }
    }

    return $stats;
}

/**
 * Import field lookup data from JSON files
 *
 * @return int Number of records imported
 */
function import_field_data() {
    global $CFG;

    $totalcount = 0;

    // Get all tagselect fields.
    $fields = form_field::get_records(['type' => 'tagselect']);

    if (empty($fields)) {
        echo "No tagselect fields found for data import\n";
        return 0;
    }

    echo "\nImporting field lookup data...\n";

    foreach ($fields as $field) {
        // Determine which JSON file to use based on idnumber.
        $jsonfile = null;
        if ($field->get('idnumber') === 'competency') {
            $jsonfile = $CFG->dirroot . '/mod/projetvet/data/complist.json';
        } else if ($field->get('idnumber') === 'category') {
            $jsonfile = $CFG->dirroot . '/mod/projetvet/data/categories.json';
        }

        if (!$jsonfile || !file_exists($jsonfile)) {
            continue;
        }

        echo "  Processing field: {$field->get('name')} (idnumber: {$field->get('idnumber')})\n";

        $json = file_get_contents($jsonfile);
        $data = json_decode($json, true);

        if (empty($data)) {
            echo "    No data found in: " . basename($jsonfile) . "\n";
            continue;
        }

        // Delete existing data for this field.
        field_data::delete_field_data($field->get('id'));

        $count = 0;
        foreach ($data as $item) {
            $record = new field_data(0, (object)[
                'fieldid' => $field->get('id'),
                'uniqueid' => $item['uniqueid'],
                'itemtype' => $item['type'],
                'parent' => $item['parent'],
                'name' => $item['name'],
                'sortorder' => $item['sortorder'],
            ]);
            $record->create();
            $count++;
        }

        echo "    Imported $count records from " . basename($jsonfile) . "\n";
        $totalcount += $count;
    }

    return $totalcount;
}

// Import form sets.
echo "=== Importing Form Sets ===\n\n";

$totalstats = ['categories' => 0, 'fields' => 0];

foreach ($formsets as $idnumber => $config) {
    $stats = import_formset($idnumber, $config);
    $totalstats['categories'] += $stats['categories'];
    $totalstats['fields'] += $stats['fields'];
    echo "\n";
}

// Import field lookup data.
$fielddata = import_field_data();

// Display summary.
echo "\n=== Import Summary ===\n";
echo "Form sets processed: " . count($formsets) . "\n";
echo "Categories created: {$totalstats['categories']}\n";
echo "Fields created: {$totalstats['fields']}\n";
echo "Field data records: $fielddata\n";
echo "\nImport completed successfully!\n";
