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
 * ProjetVet module data generator class
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_projetvet_generator extends testing_module_generator {
    /**
     * Creates an instance of the module for testing purposes.
     *
     * @param array|stdClass $record data for module being generated. Requires 'course' key
     * @param null|array $options general options for course module, can be merged into $record
     * @return stdClass record from module-defined table with additional field
     *     cmid (corresponding id in course_modules table)
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;

        $defaultsettings = [
            'name' => 'Test ProjetVet',
            'promo' => '2025',
            'currentyear' => 'M1',
            'intro' => 'Test ProjetVet activity for backup/restore testing',
            'introformat' => FORMAT_HTML,
        ];

        foreach ($defaultsettings as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        $instance = parent::create_instance($record, (array)$options);
        return $instance;
    }

    /**
     * Create a form entry for testing.
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_form_entry($record = null) {
        global $DB;

        $record = (object) (array) $record;

        $defaults = [
            'entrystatus' => 0, // Draft.
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 2, // Admin.
        ];

        foreach ($defaults as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        // Ensure we have required fields.
        if (!isset($record->projetvetid)) {
            debugging('projetvetid is required for form entry', DEBUG_DEVELOPER);
            return null;
        }
        if (!isset($record->formsetid)) {
            debugging('formsetid is required for form entry', DEBUG_DEVELOPER);
            return null;
        }
        if (!isset($record->studentid)) {
            debugging('studentid is required for form entry', DEBUG_DEVELOPER);
            return null;
        }

        $entryid = $DB->insert_record('projetvet_form_entry', $record);
        return $DB->get_record('projetvet_form_entry', ['id' => $entryid]);
    }

    /**
     * Create form data for testing.
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_form_data($record = null) {
        global $DB;

        $record = (object) (array) $record;

        $defaults = [
            'intvalue' => 0,
            'decvalue' => 0.0,
            'shortcharvalue' => '',
            'charvalue' => '',
            'textvalue' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 2, // Admin.
        ];

        foreach ($defaults as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        // Ensure we have required fields.
        if (!isset($record->fieldid)) {
            debugging('fieldid is required for form data', DEBUG_DEVELOPER);
            return null;
        }
        if (!isset($record->entryid)) {
            debugging('entryid is required for form data', DEBUG_DEVELOPER);
            return null;
        }

        $dataid = $DB->insert_record('projetvet_form_data', $record);
        return $DB->get_record('projetvet_form_data', ['id' => $dataid]);
    }

    /**
     * Get a form set by idnumber.
     *
     * @param string $idnumber
     * @return stdClass|null
     */
    public function get_formset_by_idnumber(string $idnumber) {
        global $DB;
        return $DB->get_record('projetvet_form_set', ['idnumber' => $idnumber]);
    }

    /**
     * Get form fields for a form set.
     *
     * @param int $formsetid
     * @return array
     */
    public function get_form_fields(int $formsetid) {
        global $DB;

        return $DB->get_records_sql("
            SELECT ff.*
            FROM {projetvet_form_field} ff
            JOIN {projetvet_form_cat} fc ON ff.categoryid = fc.id
            WHERE fc.formsetid = ?
            ORDER BY fc.sortorder, ff.sortorder
        ", [$formsetid]);
    }

    /**
     * Generate random activities entries for a student
     *
     * @param int $studentid User ID
     * @param int $projetvetid ProjetVet instance ID
     * @param int $count Number of entries to create
     * @param int $status Entry status (default: 1 = submitted)
     * @return array Array of created entries
     */
    public function generate_activities(int $studentid, int $projetvetid, int $count = 5, int $status = 1): array {
        global $DB;

        // Get the activities formset.
        $formset = $this->get_formset_by_idnumber('activities');
        if (!$formset) {
            debugging('Activities formset not found', DEBUG_DEVELOPER);
            return [];
        }

        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $entry = $this->create_form_entry([
                'studentid' => $studentid,
                'projetvetid' => $projetvetid,
                'formsetid' => $formset->id,
                'entrystatus' => $status,
            ]);

            // Get all fields for this formset and add random values.
            $fields = $this->get_form_fields($formset->id);
            foreach ($fields as $field) {
                $value = $this->generate_random_field_value($field);
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
     * @param int $status Entry status (default: 1 = submitted)
     * @return array Array of created entries
     */
    public function generate_facetoface_sessions(int $studentid, int $projetvetid, int $count = 3, int $status = 1): array {
        global $DB;

        // Get the facetoface formset.
        $formset = $this->get_formset_by_idnumber('facetoface');
        if (!$formset) {
            debugging('Facetoface formset not found', DEBUG_DEVELOPER);
            return [];
        }

        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $entry = $this->create_form_entry([
                'studentid' => $studentid,
                'projetvetid' => $projetvetid,
                'formsetid' => $formset->id,
                'entrystatus' => $status,
            ]);

            // Get all fields for this formset and add random values.
            $fields = $this->get_form_fields($formset->id);
            foreach ($fields as $field) {
                $value = $this->generate_random_field_value($field);
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
     * @return string|int|null The generated value
     */
    protected function generate_random_field_value(stdClass $field) {
        $configdata = json_decode($field->configdata ?? '{}', true);

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
