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
 * CLI script to create a test course with ProjetVet data for backup/restore testing.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/lib/testing/generator/lib.php');

// Get CLI options.
[$options, $unrecognized] = cli_get_params([
    'help' => false,
], [
    'h' => 'help',
]);

if ($options['help']) {
    $help = "Create a test course with ProjetVet data for backup/restore testing.

Options:
-h, --help            Print out this help

Example:
\$ php create_test_course.php
";
    echo $help;
    exit(0);
}

echo "Creating test course with ProjetVet data for backup/restore testing...\n\n";

// Initialize the data generator.
$generator = new testing_data_generator();

// Create a new course.
$coursedata = [
    'fullname' => 'ProjetVet Backup Test Course ' . date('Y-m-d H:i:s'),
    'shortname' => 'PVET_TEST_' . time(),
    'category' => 1,
    'summary' => 'Test course for ProjetVet backup/restore validation',
];

$course = $generator->create_course($coursedata);
echo "✓ Created course: {$course->fullname} (ID: {$course->id})\n";

// Create ProjetVet activity.
$projetvet = $generator->create_module('projetvet', [
    'course' => $course->id,
    'name' => 'ProjetVet Test Activity',
    'promo' => '2025',
    'currentyear' => 'M1',
    'intro' => 'Test ProjetVet activity with sample data for backup/restore testing',
    'introformat' => FORMAT_HTML,
]);

echo "✓ Created ProjetVet activity: {$projetvet->name} (ID: {$projetvet->id})\n";

// Create test users.
$student1 = $generator->create_user([
    'username' => 'pvet_student1_' . time(),
    'firstname' => 'Student',
    'lastname' => 'One',
    'email' => 'pvet_student1_' . time() . '@example.com',
]);

$student2 = $generator->create_user([
    'username' => 'pvet_student2_' . time(),
    'firstname' => 'Student',
    'lastname' => 'Two',
    'email' => 'pvet_student2_' . time() . '@example.com',
]);

// Enroll students.
$generator->enrol_user($student1->id, $course->id, 'student');
$generator->enrol_user($student2->id, $course->id, 'student');

echo "✓ Created and enrolled students: {$student1->username}, {$student2->username}\n";

// Get the ProjetVet generator.
$projetvetgenerator = $generator->get_plugin_generator('mod_projetvet');

// Create some test data if form sets exist.
$formsets = $DB->get_records('projetvet_form_set', [], '', '*', 0, 1);
if (!empty($formsets)) {
    $formset = reset($formsets);
    echo "✓ Found form set: {$formset->name} (ID: {$formset->id})\n";

    // Create form entry for student1.
    $entry = $projetvetgenerator->create_form_entry([
        'projetvetid' => $projetvet->id,
        'formsetid' => $formset->id,
        'studentid' => $student1->id,
        'entrystatus' => 1, // Completed.
    ]);

    if ($entry) {
        echo "✓ Created form entry for {$student1->username}\n";

        // Get a field from this formset and create data.
        $fields = $projetvetgenerator->get_form_fields($formset->id);
        if (!empty($fields)) {
            $field = reset($fields);
            $formdata = $projetvetgenerator->create_form_data([
                'fieldid' => $field->id,
                'entryid' => $entry->id,
                'textvalue' => 'Test data for backup/restore validation',
            ]);
            if ($formdata) {
                echo "✓ Created form data for field: {$field->name}\n";
            }
        }
    }
} else {
    echo "! No form sets found - skipping form entry creation\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "SUCCESS: Test course created with sample data!\n";
echo str_repeat("=", 60) . "\n\n";

echo "Course Information:\n";
echo "  - Course ID: {$course->id}\n";
echo "  - Course shortname: {$course->shortname}\n";
echo "  - ProjetVet activity ID: {$projetvet->id}\n\n";

echo "Next Steps:\n";
echo "1. Backup this course:\n";
echo "   php admin/cli/backup.php --courseid={$course->id} --destination=/tmp/\n\n";

echo "2. Create a new course and restore:\n";
echo "   php admin/cli/restore_backup.php --file=/tmp/[backup-filename].mbz --categoryid=1\n\n";

echo "3. Or run the automated tests:\n";
echo "   php admin/tool/phpunit/cli/util.php --run mod/projetvet/tests/backup_restore_test.php\n\n";

echo "Data Created:\n";
if (!empty($formsets)) {
    echo "  - Form entries with data\n";
}
echo "  - Thesis record\n";
echo "  - Mobility record\n";
echo "  - Student enrollments\n\n";
