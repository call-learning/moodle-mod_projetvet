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
     * Create thesis data for testing.
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_thesis($record = null) {
        global $DB;

        $record = (object) (array) $record;

        $defaults = [
            'thesis' => 'Test thesis subject for backup/restore testing',
            'otherdata' => '',
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
            debugging('projetvetid is required for thesis', DEBUG_DEVELOPER);
            return null;
        }
        if (!isset($record->userid)) {
            debugging('userid is required for thesis', DEBUG_DEVELOPER);
            return null;
        }

        $thesisid = $DB->insert_record('projetvet_thesis', $record);
        return $DB->get_record('projetvet_thesis', ['id' => $thesisid]);
    }

    /**
     * Create mobility data for testing.
     *
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_mobility($record = null) {
        global $DB;

        $record = (object) (array) $record;

        $defaults = [
            'title' => 'Test mobility program',
            'erasmus' => 1,
            'fmp' => 0,
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
            debugging('projetvetid is required for mobility', DEBUG_DEVELOPER);
            return null;
        }
        if (!isset($record->userid)) {
            debugging('userid is required for mobility', DEBUG_DEVELOPER);
            return null;
        }

        $mobilityid = $DB->insert_record('projetvet_mobility', $record);
        return $DB->get_record('projetvet_mobility', ['id' => $mobilityid]);
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
}
