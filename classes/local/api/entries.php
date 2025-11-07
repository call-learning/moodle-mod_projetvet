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

namespace mod_projetvet\local\api;

use cache;
use core\invalid_persistent_exception;
use mod_projetvet\local\persistent\form_cat;
use mod_projetvet\local\persistent\form_data;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\persistent\form_field;
use mod_projetvet\local\persistent\form_set;
use stdClass;

/**
 * Class entries - Generic API for form entries
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entries {
    /**
     * Get the form structure for a form set.
     *
     * @param string $formsetidnumber The form set idnumber (default: 'activities')
     * @return array
     */
    public static function get_form_structure(string $formsetidnumber = 'activities'): array {
        $actstructure = cache::make('mod_projetvet', 'activitystructures');
        $cachekey = 'activitystructure_' . $formsetidnumber;
        if ($actstructure->get($cachekey)) {
            return $actstructure->get($cachekey);
        }

        // Get the form set.
        $formset = form_set::get_record(['idnumber' => $formsetidnumber]);
        if (!$formset) {
            return [];
        }

        $categories = form_cat::get_records(['formsetid' => $formset->get('id')], 'sortorder');
        $fields = form_field::get_records([], 'sortorder');
        $data = [];
        foreach ($categories as $category) {
            $data[$category->get('id')] = (object) [
                'id' => $category->get('id'),
                'name' => $category->get('name'),
                'description' => $category->get('description'),
                'capability' => $category->get('capability'),
                'entrystatus' => $category->get('entrystatus'),
                'fields' => [],
            ];
        }
        foreach ($fields as $field) {
            // Only include fields for categories in this form set.
            if (isset($data[$field->get('categoryid')])) {
                $data[$field->get('categoryid')]->fields[] = (object) [
                    'id' => $field->get('id'),
                    'idnumber' => $field->get('idnumber'),
                    'name' => $field->get('name'),
                    'type' => $field->get('type'),
                    'description' => $field->get('description'),
                    'configdata' => $field->get('configdata'),
                    'capability' => $field->get('capability'),
                    'entrystatus' => $field->get('entrystatus'),
                    'listorder' => $field->get('listorder'),
                ];
            }
        }
        $actstructure->set($cachekey, array_values($data));
        return array_values($data);
    }

    /**
     * Get an activity entry
     *
     * @param int $entryid
     * @return stdClass
     */
    public static function get_entry(int $entryid): stdClass {
        $actentry = form_entry::get_record(['id' => $entryid]);
        if (empty($actentry)) {
            throw new \moodle_exception('entry_not_found', 'projetvet', '', $entryid);
        }

        // Get the form set from the entry to load the correct structure.
        $formset = form_set::get_record(['id' => $actentry->get('formsetid')]);
        $formsetidnumber = $formset ? $formset->get('idnumber') : 'activities';
        $structure = self::get_form_structure($formsetidnumber);

        return self::do_get_entry_content($structure, $actentry);
    }

    /**
     * Entry structure content
     *
     * @param array $actstructure
     * @param form_entry $actentry
     * @return object
     */
    private static function do_get_entry_content(array $actstructure, form_entry $actentry): object {
        $data = form_data::get_records(['entryid' => $actentry->get('id')], 'timecreated');
        $activity = [];

        foreach ($actstructure as $category) {
            $fields = [];
            foreach ($category->fields as $field) {
                $fielddata = null;
                foreach ($data as $dataitem) {
                    if ($dataitem->get('fieldid') == $field->id) {
                        $fielddata = $dataitem;
                        break;
                    }
                }
                $fields[] = (object) [
                    'id' => $field->id,
                    'idnumber' => $field->idnumber,
                    'name' => $field->name,
                    'type' => $field->type,
                    'value' => $fielddata ? $fielddata->get_value() : '',
                    'displayvalue' => $fielddata ? $fielddata->get_display_value() : '',
                ];
            }
            $activity[] = (object) [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'fields' => $fields,
            ];
        }
        $record = (object) [
            'id' => $actentry->get('id'),
            'projetvetid' => $actentry->get('projetvetid'),
            'studentid' => $actentry->get('studentid'),
            'timecreated' => $actentry->get('timecreated'),
            'usermodified' => $actentry->get('usermodified'),
            'entrystatus' => $actentry->get('entrystatus'),
            'categories' => $activity,
            'canedit' => $actentry->can_edit(),
            'candelete' => $actentry->can_delete(),
        ];
        return $record;
    }

    /**
     * Create an activity entry
     *
     * @param int $projetvetid The projetvet id
     * @param int $studentid The student id
     * @param array $fields The fields
     * @param int $entrystatus The entry status (default STATUS_DRAFT)
     * @param string $formsetidnumber The form set idnumber (default: 'activities')
     *
     * @return int
     */
    public static function create_entry(
        int $projetvetid,
        int $studentid,
        array $fields,
        int $entrystatus = form_entry::STATUS_DRAFT,
        string $formsetidnumber = 'activities'
    ): int {
        // Get the form set.
        $formset = form_set::get_record(['idnumber' => $formsetidnumber]);
        if (!$formset) {
            throw new \moodle_exception('formsetnotfound', 'projetvet', '', $formsetidnumber);
        }

        // Create the activity entry.
        $entry = new form_entry();
        $entry->set('projetvetid', $projetvetid);
        $entry->set('formsetid', $formset->get('id'));
        $entry->set('studentid', $studentid);
        $entry->set('entrystatus', $entrystatus);
        $entry->create();
        $entry->save();

        // Create the activity data.
        foreach ($fields as $fieldid => $value) {
            $data = new form_data();
            $data->set('fieldid', $fieldid);
            $data->set('entryid', $entry->get('id'));
            $data->set_value($value);
            $data->create();
            $data->save();
        }
        return $entry->get('id');
    }

    /**
     * Update an activity entry
     *
     * @param int $entryid The entry id
     * @param array $fields The fields
     * @param int|null $entrystatus The entry status (null = no change)
     * @return void
     */
    public static function update_entry(int $entryid, array $fields, ?int $entrystatus = null): void {
        // Update the activity.
        $entry = form_entry::get_record(['id' => $entryid]);
        if (empty($entry)) {
            throw new \moodle_exception('entry_not_found', 'projetvet', '', $entryid);
        }
        if (!$entry->can_edit()) {
            throw new \moodle_exception('cannoteditactivity', 'projetvet');
        }

        // Update entry status if provided.
        if ($entrystatus !== null) {
            $entry->set('entrystatus', $entrystatus);
            $entry->update();
        }

        foreach ($fields as $fieldid => $value) {
            $data = form_data::get_record(['entryid' => $entryid, 'fieldid' => $fieldid]);
            if ($data) {
                $data->set_value($value);
                $data->update();
            } else {
                $data = new form_data();
                $data->set('fieldid', $fieldid);
                $data->set('entryid', $entryid);
                $data->set_value($value);
                $data->create();
            }
            $data->save();
        }
    }

    /**
     * Delete an activity entry
     *
     * @param int $entryid The entry id
     * @return bool
     */
    public static function delete_entry(int $entryid): bool {
        $entry = new form_entry($entryid);
        if (empty($entry)) {
            throw new \moodle_exception('entry_not_found', 'projetvet', '', $entryid);
        }
        if (!$entry->can_delete()) {
            throw new \moodle_exception('cannotdeleteactivity', 'projetvet');
        }
        try {
            // Delete all associated data first.
            $datarecords = form_data::get_records(['entryid' => $entryid]);
            foreach ($datarecords as $data) {
                $data->delete();
            }
            // Then delete the entry.
            $entry->delete();
        } catch (invalid_persistent_exception $e) {
            throw new \moodle_exception('cannotdeleteactivity', 'projetvet', '', $e->getMessage());
        }
        return true;
    }

    /**
     * Get the activity entries for a student
     *
     * @param int $projetvetid The projetvet instance id
     * @param int $studentid The user id
     * @param string $formsetidnumber The form set idnumber (default: 'activities')
     * @return array
     */
    public static function get_entry_list(int $projetvetid, int $studentid, string $formsetidnumber = 'activities'): array {
        $entries = self::get_entries($projetvetid, $studentid, $formsetidnumber);
        $structure = self::get_form_structure($formsetidnumber);

        // Get fields with listorder > 0, sorted by listorder.
        $listfields = [];
        foreach ($structure as $category) {
            foreach ($category->fields as $field) {
                if ($field->listorder > 0) {
                    $listfields[$field->listorder] = $field;
                }
            }
        }
        ksort($listfields);

        $activitylist = [];
        foreach ($entries->activities as $activity) {
            $activitydata = [
                'id' => $activity->id,
                'fields' => [],
                'entrystatus' => $activity->entrystatus,
                'canedit' => $activity->canedit,
                'candelete' => $activity->candelete,
            ];

            // Add dynamic fields based on listorder.
            foreach ($listfields as $field) {
                $activitydata['fields'][] = [
                    'idnumber' => $field->idnumber,
                    'name' => $field->name,
                    'value' => self::get_entry_field_value($activity, $field->idnumber),
                    'displayvalue' => self::get_entry_field_value($activity, $field->idnumber, true),
                ];
            }

            $activitylist[] = $activitydata;
        }

        return [
            'activities' => $activitylist,
            'listfields' => array_values($listfields),
        ];
    }

    /**
     * Get the activity entries
     *
     * @param int $projetvetid The projetvet instance id
     * @param int $studentid The user id
     * @param string $formsetidnumber The form set idnumber (default: 'activities')
     * @return stdClass
     */
    public static function get_entries(int $projetvetid, int $studentid, string $formsetidnumber = 'activities'): stdClass {
        $structure = self::get_form_structure($formsetidnumber);

        // Get the form set to filter entries.
        $formset = form_set::get_record(['idnumber' => $formsetidnumber]);
        if (!$formset) {
            return (object) [
                'activities' => [],
                'structure' => $structure,
            ];
        }

        $entries = form_entry::get_records([
            'studentid' => $studentid,
            'projetvetid' => $projetvetid,
            'formsetid' => $formset->get('id'),
        ]);
        $activities = [];
        foreach ($entries as $entry) {
            $activities[] = self::do_get_entry_content($structure, $entry);
        }
        return (object) [
            'activities' => $activities,
            'structure' => $structure,
        ];
    }

    /**
     * Get activity field value across categories
     *
     * @param mixed $activity
     * @param string $fieldidnumber
     * @param bool $displayvalue
     * @return mixed|null
     */
    private static function get_entry_field_value(mixed $activity, string $fieldidnumber, bool $displayvalue = false) {
        foreach ($activity->categories as $category) {
            foreach ($category->fields as $field) {
                if ($field->idnumber === $fieldidnumber) {
                    return $displayvalue ? $field->displayvalue : $field->value;
                }
            }
        }
        return null;
    }
}
