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
 * Upgrade script for mod_projetvet.
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade this plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_projetvet_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025102300) {

        // Define table projetvet_thesis to be created.
        $table = new xmldb_table('projetvet_thesis');

        // Adding fields to table projetvet_thesis.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('projetvetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('thesis', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('otherdata', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table projetvet_thesis.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('projetvetid', XMLDB_KEY_FOREIGN, ['projetvetid'], 'projetvet', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table projetvet_thesis.
        $table->add_index('projetvet_user', XMLDB_INDEX_NOTUNIQUE, ['projetvetid', 'userid']);

        // Conditionally launch create table for projetvet_thesis.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Projetvet savepoint reached.
        upgrade_mod_savepoint(true, 2025102300, 'projetvet');
    }

    if ($oldversion < 2025102301) {

        // Define fields promo and currentyear to be added to projetvet.
        $table = new xmldb_table('projetvet');
        $field = new xmldb_field('promo', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'introformat');

        // Conditionally launch add field promo.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('currentyear', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'promo');

        // Conditionally launch add field currentyear.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table projetvet_mobility to be created.
        $table = new xmldb_table('projetvet_mobility');

        // Adding fields to table projetvet_mobility.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('projetvetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('title', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('erasmus', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('fmp', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table projetvet_mobility.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('projetvetid', XMLDB_KEY_FOREIGN, ['projetvetid'], 'projetvet', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table projetvet_mobility.
        $table->add_index('projetvet_user', XMLDB_INDEX_NOTUNIQUE, ['projetvetid', 'userid']);

        // Conditionally launch create table for projetvet_mobility.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Projetvet savepoint reached.
        upgrade_mod_savepoint(true, 2025102301, 'projetvet');
    }

    if ($oldversion < 2025102302) {

        // Define table projetvet_field_data to be created.
        $table = new xmldb_table('projetvet_field_data');

        // Adding fields to table projetvet_field_data.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('uniqueid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemtype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'item');
        $table->add_field('parent', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table projetvet_field_data.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fieldid', XMLDB_KEY_FOREIGN, ['fieldid'], 'projetvet_act_field', ['id']);

        // Adding indexes to table projetvet_field_data.
        $table->add_index('fieldid_uniqueid', XMLDB_INDEX_UNIQUE, ['fieldid', 'uniqueid']);
        $table->add_index('fieldid_itemtype', XMLDB_INDEX_NOTUNIQUE, ['fieldid', 'itemtype']);

        // Conditionally launch create table for projetvet_field_data.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Import data from JSON files for tagselect fields.
        $fields = $DB->get_records('projetvet_act_field', ['type' => 'tagselect']);
        foreach ($fields as $field) {
            $jsonfile = null;
            if ($field->idnumber === 'competency') {
                $jsonfile = $CFG->dirroot . '/mod/projetvet/data/complist.json';
            } else if ($field->idnumber === 'category') {
                $jsonfile = $CFG->dirroot . '/mod/projetvet/data/categories.json';
            }

            if ($jsonfile && file_exists($jsonfile)) {
                $json = file_get_contents($jsonfile);
                $data = json_decode($json, true);

                if (!empty($data)) {
                    foreach ($data as $item) {
                        $record = new stdClass();
                        $record->fieldid = $field->id;
                        $record->uniqueid = $item['uniqueid'];
                        $record->itemtype = $item['type'];
                        $record->parent = $item['parent'];
                        $record->name = $item['name'];
                        $record->sortorder = $item['sortorder'];
                        $record->timecreated = time();
                        $record->timemodified = time();

                        $DB->insert_record('projetvet_field_data', $record);
                    }
                }
            }
        }

        // Projetvet savepoint reached.
        upgrade_mod_savepoint(true, 2025102302, 'projetvet');
    }

    return true;
}
