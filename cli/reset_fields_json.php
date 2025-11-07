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
 * Reset activity fields from JSON script
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_projetvet\local\importer\fields_json_importer;
use mod_projetvet\local\persistent\form_field;

// Delete existing activity fields and categories.
echo "Deleting existing activity data...\n";
$DB->delete_records('projetvet_form_data');
$DB->delete_records('projetvet_form_entry');
$DB->delete_records('projetvet_form_field');
$DB->delete_records('projetvet_form_cat');

// Import from JSON file.
echo "Importing fields from JSON...\n";
$jsonfile = $CFG->dirroot . '/mod/projetvet/data/default_activity_form.json';

if (!file_exists($jsonfile)) {
    echo "Error: JSON file not found at $jsonfile\n";
    exit(1);
}

$importer = new fields_json_importer(form_field::class);
$importer->import($jsonfile);

// Clear the activity structure cache.
$cache = cache::make('mod_projetvet', 'activitystructures');
$cache->purge();

echo "Activity fields recreated successfully from JSON\n";

// Display summary.
$categorycount = $DB->count_records('projetvet_form_cat');
$fieldcount = $DB->count_records('projetvet_form_field');
echo "Created $categorycount categories and $fieldcount fields\n";
