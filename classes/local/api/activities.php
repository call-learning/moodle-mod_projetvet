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
use mod_projetvet\local\persistent\act_cat;
use mod_projetvet\local\persistent\act_data;
use mod_projetvet\local\persistent\act_entry;
use mod_projetvet\local\persistent\act_field;
use stdClass;

/**
 * Class activities
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activities {
    /**
     * Get the activity form structure.
     *
     * @return array
     */
    public static function get_activity_structure(): array {
        $actstructure = cache::make('mod_projetvet', 'activitystructures');
        if ($actstructure->get('activitystructure')) {
            return $actstructure->get('activitystructure');
        }
        $categories = act_cat::get_records([], 'sortorder');
        $fields = act_field::get_records([], 'sortorder');
        $data = [];
        foreach ($categories as $category) {
            $data[$category->get('id')] = (object) [
                'id' => $category->get('id'),
                'name' => $category->get('name'),
                'description' => $category->get('description'),
                'fields' => [],
            ];
        }
        foreach ($fields as $field) {
            $data[$field->get('categoryid')]->fields[] = (object) [
                'id' => $field->get('id'),
                'idnumber' => $field->get('idnumber'),
                'name' => $field->get('name'),
                'type' => $field->get('type'),
                'description' => $field->get('description'),
                'configdata' => $field->get('configdata'),
            ];
        }
        $actstructure->set('activitystructure', array_values($data));
        return array_values($data);
    }

    /**
     * Get an activity entry
     *
     * @param int $entryid
     * @return stdClass
     */
    public static function get_entry(int $entryid): stdClass {
        $structure = self::get_activity_structure();
        $actentry = act_entry::get_record(['id' => $entryid]);
        if (empty($actentry)) {
            throw new \moodle_exception('entry_not_found', 'projetvet', '', $entryid);
        }
        return self::do_get_entry_content($structure, $actentry);
    }

    /**
     * Entry structure content
     *
     * @param array $actstructure
     * @param act_entry $actentry
     * @return object
     */
    private static function do_get_entry_content(array $actstructure, act_entry $actentry): object {
        $data = act_data::get_records(['entryid' => $actentry->get('id')], 'timecreated');
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
            'categories' => $activity,
            'canedit' => $actentry->can_edit(),
            'candelete' => $actentry->can_delete(),
        ];
        return $record;
    }

    /**
     * Create an activity entry
     *
     * @param int $projetvetid The projetvet instance id
     * @param int $studentid The student id
     * @param array $fields The fields
     *
     * @return int
     */
    public static function create_activity(int $projetvetid, int $studentid, array $fields): int {
        // Create the activity entry.
        $entry = new act_entry();
        $entry->set('projetvetid', $projetvetid);
        $entry->set('studentid', $studentid);
        $entry->create();
        $entry->save();

        // Create the activity data.
        foreach ($fields as $fieldid => $value) {
            $data = new act_data();
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
     * @return void
     */
    public static function update_activity(int $entryid, array $fields): void {
        // Update the activity.
        $entry = act_entry::get_record(['id' => $entryid]);
        if (empty($entry)) {
            throw new \moodle_exception('entry_not_found', 'projetvet', '', $entryid);
        }
        if (!$entry->can_edit()) {
            throw new \moodle_exception('cannoteditactivity', 'projetvet');
        }
        foreach ($fields as $fieldid => $value) {
            $data = act_data::get_record(['entryid' => $entryid, 'fieldid' => $fieldid]);
            if ($data) {
                $data->set_value($value);
                $data->update();
            } else {
                $data = new act_data();
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
    public static function delete_activity(int $entryid): bool {
        $entry = new act_entry($entryid);
        if (empty($entry)) {
            throw new \moodle_exception('entry_not_found', 'projetvet', '', $entryid);
        }
        if (!$entry->can_delete()) {
            throw new \moodle_exception('cannotdeleteactivity', 'projetvet');
        }
        try {
            // Delete all associated data first.
            $datarecords = act_data::get_records(['entryid' => $entryid]);
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
     * @return array
     */
    public static function get_activity_list(int $projetvetid, int $studentid): array {
        $entries = self::get_entries($projetvetid, $studentid);
        $activitylist = [];
        foreach ($entries->activities as $activity) {
            $activitylist[] = [
                'id' => $activity->id,
                'title' => self::get_activity_field_value($activity, 'activity_title'),
                'year' => self::get_activity_field_value($activity, 'year', true),
                'category' => self::get_activity_field_value($activity, 'category', true),
                'completed' => self::get_activity_field_value($activity, 'completed'),
                'canedit' => $activity->canedit,
                'candelete' => $activity->candelete,
            ];
        }
        return $activitylist;
    }

    /**
     * Get the activity entries
     *
     * @param int $projetvetid The projetvet instance id
     * @param int $studentid The user id
     * @return stdClass
     */
    public static function get_entries(int $projetvetid, int $studentid): stdClass {
        $structure = self::get_activity_structure();
        $entries = act_entry::get_records(['studentid' => $studentid, 'projetvetid' => $projetvetid]);
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
    private static function get_activity_field_value(mixed $activity, string $fieldidnumber, bool $displayvalue = false) {
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
