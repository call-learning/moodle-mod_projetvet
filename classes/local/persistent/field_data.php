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

namespace mod_projetvet\local\persistent;

use core\persistent;
use lang_string;

/**
 * Field lookup data persistent
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_data extends persistent {
    /**
     * Current table
     */
    const TABLE = 'projetvet_field_data';

    /**
     * Return the custom definition of the properties of this model.
     *
     * @return array Where keys are the property names.
     */
    protected static function define_properties() {
        return [
            'fieldid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'fieldid'),
            ],
            'uniqueid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'uniqueid'),
            ],
            'itemtype' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_TEXT,
                'message' => new lang_string('invaliddata', 'projetvet', 'itemtype'),
            ],
            'parent' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'default' => 0,
                'message' => new lang_string('invaliddata', 'projetvet', 'parent'),
            ],
            'name' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_TEXT,
                'message' => new lang_string('invaliddata', 'projetvet', 'name'),
            ],
            'sortorder' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'sortorder'),
            ],
        ];
    }

    /**
     * Get all items for a specific field
     *
     * @param int $fieldid
     * @return array
     */
    public static function get_field_items($fieldid) {
        global $DB;
        $records = $DB->get_records(self::TABLE, ['fieldid' => $fieldid], 'sortorder ASC');
        return $records;
    }

    /**
     * Get lookup map for a field (uniqueid => name)
     *
     * @param int $fieldid
     * @param string $itemtype Optional filter by type (item or heading)
     * @return array
     */
    public static function get_lookup_map($fieldid, $itemtype = null) {
        global $DB;

        $params = ['fieldid' => $fieldid];
        if ($itemtype) {
            $params['itemtype'] = $itemtype;
        }

        $records = $DB->get_records(self::TABLE, $params);
        $map = [];
        foreach ($records as $record) {
            $map[$record->uniqueid] = $record->name;
        }
        return $map;
    }

    /**
     * Get grouped options for tagselect element
     *
     * @param int $fieldid
     * @return array
     */
    public static function get_grouped_options($fieldid) {
        global $DB;

        $records = $DB->get_records(self::TABLE, ['fieldid' => $fieldid], 'sortorder ASC');

        $grouped = [];
        foreach ($records as $record) {
            if ($record->itemtype === 'heading') {
                // Create a group for this heading.
                $groupitems = [];
                // Find all items that belong to this heading.
                foreach ($records as $subrecord) {
                    if ($subrecord->itemtype === 'item' && $subrecord->parent == $record->uniqueid) {
                        $groupitems[] = [
                            'uniqueid' => $subrecord->uniqueid,
                            'name' => $subrecord->name,
                        ];
                    }
                }
                if (!empty($groupitems)) {
                    $grouped[] = [
                        'name' => $record->name,
                        'items' => $groupitems,
                    ];
                }
            }
        }

        return $grouped;
    }

    /**
     * Delete all field data for a specific field
     *
     * @param int $fieldid
     * @return bool
     */
    public static function delete_field_data($fieldid) {
        global $DB;
        return $DB->delete_records(self::TABLE, ['fieldid' => $fieldid]);
    }
}
