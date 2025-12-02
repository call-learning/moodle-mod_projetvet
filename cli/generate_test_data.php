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
 * CLI script to generate test data for projetvet
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

/**
 * Simple data generator for CLI (not using testing framework)
 */
class projetvet_cli_generator {
    /**
     * Create a form entry
     *
     * @param array $data Entry data
     * @return stdClass The created entry
     */
    public function create_entry(array $data): stdClass {
        global $DB;

        $defaults = [
            'entrystatus' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 2,
        ];

        $data = array_merge($defaults, $data);
        $data['id'] = $DB->insert_record('projetvet_form_entry', $data);

        return (object)$data;
    }

    /**
     * Create a field data record
     *
     * @param array $data Field data
     * @return stdClass The created field data
     */
    public function create_form_data(array $data): stdClass {
        global $DB;

        $defaults = [
            'intvalue' => 0,
            'decvalue' => 0.0,
            'shortcharvalue' => '',
            'charvalue' => '',
            'textvalue' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 2,
        ];

        $data = array_merge($defaults, $data);
        $data['id'] = $DB->insert_record('projetvet_form_data', $data);

        return (object)$data;
    }

    /**
     * Get formset by idnumber
     *
     * @param string $idnumber
     * @return stdClass|null
     */
    public function get_formset_by_idnumber(string $idnumber): ?stdClass {
        global $DB;
        return $DB->get_record('projetvet_form_set', ['idnumber' => $idnumber]);
    }

    /**
     * Get form fields for a formset up to a specific entry status
     *
     * @param int $formsetid
     * @param int|null $entrystatus Only include fields from categories with entrystatus <= this value
     * @return array
     */
    public function get_form_fields(int $formsetid, ?int $entrystatus = null): array {
        global $DB;

        if ($entrystatus === null) {
            // Get all fields if no status specified.
            return $DB->get_records_sql("
                SELECT ff.*
                FROM {projetvet_form_field} ff
                JOIN {projetvet_form_cat} fc ON ff.categoryid = fc.id
                WHERE fc.formsetid = ?
                ORDER BY fc.sortorder, ff.sortorder
            ", [$formsetid]);
        }

        // Get fields only from categories up to the specified entry status.
        return $DB->get_records_sql("
            SELECT ff.*
            FROM {projetvet_form_field} ff
            JOIN {projetvet_form_cat} fc ON ff.categoryid = fc.id
            WHERE fc.formsetid = ? AND fc.entrystatus <= ?
            ORDER BY fc.sortorder, ff.sortorder
        ", [$formsetid, $entrystatus]);
    }

    /**
     * Get available entry statuses for a formset from its categories
     *
     * @param int $formsetid
     * @return array Array of entry status values
     */
    public function get_available_entry_statuses(int $formsetid): array {
        global $DB;

        $statuses = $DB->get_fieldset_sql("
            SELECT DISTINCT entrystatus
            FROM {projetvet_form_cat}
            WHERE formsetid = ?
            ORDER BY entrystatus
        ", [$formsetid]);

        if (empty($statuses)) {
            return [0];
        }

        // Add the final status (highest + 1).
        $maxstatus = max($statuses);
        $statuses[] = $maxstatus + 1;

        return $statuses;
    }

    /**
     * Get a random valid entry status for a formset
     *
     * @param int $formsetid
     * @return int Random entry status
     */
    public function get_random_entry_status(int $formsetid): int {
        $statuses = $this->get_available_entry_statuses($formsetid);
        return $statuses[array_rand($statuses)];
    }

    /**
     * Generate random activities entries for a student
     *
     * @param int $studentid User ID
     * @param int $projetvetid ProjetVet instance ID
     * @param int $count Number of entries to create
     * @return array Array of created entries
     */
    public function generate_activities(int $studentid, int $projetvetid, int $count = 5): array {
        $formset = $this->get_formset_by_idnumber('activities');
        if (!$formset) {
            cli_error("Activities formset not found. Please run import_forms.php first.");
        }

        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $entrystatus = $this->get_random_entry_status($formset->id);
            $entry = $this->create_entry([
                'studentid' => $studentid,
                'projetvetid' => $projetvetid,
                'formsetid' => $formset->id,
                'entrystatus' => $entrystatus,
            ]);

            // Get fields only up to the current entry status.
            $fields = $this->get_form_fields($formset->id, $entrystatus);
            $entrydata = new stdClass();

            foreach ($fields as $field) {
                $value = $this->generate_random_field_value($field, $entrydata);

                // Store value for cross-field dependencies.
                if ($field->idnumber) {
                    $entrydata->{$field->idnumber} = $value;
                }

                if ($value !== null) {
                    $this->create_form_data([
                        'entryid' => $entry->id,
                        'fieldid' => $field->id,
                        'charvalue' => is_string($value) && strlen($value) <= 255 ? $value : '',
                        'textvalue' => is_string($value) && strlen($value) > 255 ? $value : '',
                        'intvalue' => is_numeric($value) ? (int)$value : 0,
                    ]);
                }
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Generate random facetoface entries for a student
     *
     * @param int $studentid User ID
     * @param int $projetvetid ProjetVet instance ID
     * @param int $count Number of entries to create
     * @return array Array of created entries
     */
    public function generate_facetoface_sessions(int $studentid, int $projetvetid, int $count = 3): array {
        $formset = $this->get_formset_by_idnumber('facetoface');
        if (!$formset) {
            cli_error("Facetoface formset not found. Please run import_forms.php first.");
        }

        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $entrystatus = $this->get_random_entry_status($formset->id);
            $entry = $this->create_entry([
                'studentid' => $studentid,
                'projetvetid' => $projetvetid,
                'formsetid' => $formset->id,
                'entrystatus' => $entrystatus,
            ]);

            // Get fields only up to the current entry status.
            $fields = $this->get_form_fields($formset->id, $entrystatus);
            $entrydata = new stdClass();

            foreach ($fields as $field) {
                $value = $this->generate_random_field_value($field, $entrydata);

                // Store value for cross-field dependencies.
                if ($field->idnumber) {
                    $entrydata->{$field->idnumber} = $value;
                }

                if ($value !== null) {
                    $this->create_form_data([
                        'entryid' => $entry->id,
                        'fieldid' => $field->id,
                        'charvalue' => is_string($value) && strlen($value) <= 255 ? $value : '',
                        'textvalue' => is_string($value) && strlen($value) > 255 ? $value : '',
                        'intvalue' => is_numeric($value) ? (int)$value : 0,
                    ]);
                }
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Generate a random value for a field based on its type
     *
     * @param stdClass $field The field object
     * @param stdClass|null $entry The entry being created (for cross-field dependencies)
     * @return string|int|null The generated value
     */
    protected function generate_random_field_value(stdClass $field, ?stdClass $entry = null) {
        $configdata = json_decode($field->configdata ?? '{}', true);

        // Special handling for specific field idnumbers.
        if ($field->idnumber === 'hours') {
            // Hours must be 40 or multiples of 40 (40, 80, 120, 160, 200).
            $multiplier = rand(1, 5);
            return $multiplier * 40;
        }

        if ($field->idnumber === 'final_ects') {
            // Credits = hours / 30.
            // We need to get the hours value from the current entry.
            if ($entry && isset($entry->hours)) {
                return (int)($entry->hours / 30);
            }
            // Fallback if hours not set yet.
            return rand(1, 5);
        }

        switch ($field->type) {
            case 'text':
                return 'Test text ' . rand(1, 100);

            case 'textarea':
                return 'Test textarea content ' . rand(1, 100) . "\n\nMore content here.";

            case 'number':
                return rand(1, 100);

            case 'date':
                // Random date within last 30 days.
                return time() - rand(0, 30 * 24 * 60 * 60);

            case 'datetime':
                // Random datetime within last 30 days.
                return time() - rand(0, 30 * 24 * 60 * 60);

            case 'select':
                if (!empty($configdata['options'])) {
                    $options = array_keys($configdata['options']);
                    return $options[array_rand($options)];
                }
                return null;

            case 'checkbox':
                return rand(0, 1);

            default:
                return null;
        }
    }
}

// Get CLI options.
[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'projetvetid' => null,
        'students' => 10,
        'activities' => 5,
        'facetoface' => 3,
    ],
    [
        'h' => 'help',
        'p' => 'projetvetid',
        's' => 'students',
        'a' => 'activities',
        'f' => 'facetoface',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || !$options['projetvetid']) {
    $help = <<<EOT
Generate test data for ProjetVet module.

Options:
-h, --help              Print out this help
-p, --projetvetid       ProjetVet instance ID (required)
-s, --students          Number of students to generate data for (default: 10)
-a, --activities        Number of activities per student (default: 5)
-f, --facetoface        Number of facetoface sessions per student (default: 3)

Example:
\$ php mod/projetvet/cli/generate_test_data.php --projetvetid=1 --students=20 --activities=10 --facetoface=5

Or with short options (note: use = for values):
\$ php mod/projetvet/cli/generate_test_data.php -p=1 -s=20 -a=10 -f=5

EOT;

    echo $help;
    exit(0);
}

$projetvetid = (int)$options['projetvetid'];
$studentcount = (int)$options['students'];
$activitiescount = (int)$options['activities'];
$facetofacecount = (int)$options['facetoface'];

// Verify projetvet exists.
$projetvet = $DB->get_record('projetvet', ['id' => $projetvetid]);
if (!$projetvet) {
    cli_error("ProjetVet instance with ID {$projetvetid} not found.");
}

$cm = get_coursemodule_from_instance('projetvet', $projetvetid);
if (!$cm) {
    cli_error("Course module not found for ProjetVet instance with ID {$projetvetid}.");
}
$context = context_module::instance($cm->id);

cli_heading("Generating test data for ProjetVet: {$projetvet->name}");

// Get enrolled students with submit capability.
$enrolledstudents = get_enrolled_users(
    $context,
    'mod/projetvet:submit',
    0,
    'u.id, u.firstname, u.lastname',
    null,
    0,
    $studentcount
);

if (empty($enrolledstudents)) {
    cli_error("No students with submit capability found in this course.");
}

$generator = new projetvet_cli_generator();

$totalactivities = 0;
$totalfacetoface = 0;

foreach ($enrolledstudents as $student) {
    cli_writeln("Processing student: {$student->firstname} {$student->lastname} (ID: {$student->id})");

    // Generate activities.
    $activities = $generator->generate_activities($student->id, $projetvetid, $activitiescount);
    $totalactivities += count($activities);
    cli_writeln("  - Created {$activitiescount} activities");

    // Generate facetoface sessions.
    $facetoface = $generator->generate_facetoface_sessions($student->id, $projetvetid, $facetofacecount);
    $totalfacetoface += count($facetoface);
    cli_writeln("  - Created {$facetofacecount} facetoface sessions");
}

cli_writeln('');
cli_heading("Summary");
cli_writeln("Students processed: " . count($enrolledstudents));
cli_writeln("Total activities created: {$totalactivities}");
cli_writeln("Total facetoface sessions created: {$totalfacetoface}");
cli_writeln('');
cli_writeln("Done!");
