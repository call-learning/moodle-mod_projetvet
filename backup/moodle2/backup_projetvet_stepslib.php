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
 * Provides all the settings and steps to perform one complete backup of the activity
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_projetvet_activity_structure_step extends backup_activity_structure_step {
    /**
     * Backup structure
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separately.
        $projetvet = new backup_nested_element(
            'projetvet',
            ['id'],
            ['name', 'intro', 'introformat', 'timemodified']
        );

        // Form structure elements (configuration data - always backed up).
        $formsets = new backup_nested_element('formsets');
        $formset = new backup_nested_element('formset', ['id'], [
            'idnumber', 'name', 'description', 'sortorder', 'timecreated', 'timemodified', 'usermodified',
        ]);

        $formcats = new backup_nested_element('formcats');
        $formcat = new backup_nested_element('formcat', ['id'], [
            'formsetid', 'idnumber', 'name', 'description', 'capability', 'entrystatus', 'statusmsg',
            'sortorder', 'timecreated', 'timemodified', 'usermodified',
        ]);

        $formfields = new backup_nested_element('formfields');
        $formfield = new backup_nested_element('formfield', ['id'], [
            'categoryid', 'idnumber', 'name', 'type', 'description', 'sortorder', 'configdata',
            'capability', 'entrystatus', 'listorder', 'timecreated', 'timemodified', 'usermodified',
        ]);

        $fielddatas = new backup_nested_element('fielddatas');
        $fielddata = new backup_nested_element('fielddata', ['id'], [
            'fieldid', 'uniqueid', 'itemtype', 'parent', 'name', 'sortorder', 'timecreated', 'timemodified',
        ]);

        // User data elements (only when userinfo is included).
        $formentries = new backup_nested_element('formentries');
        $formentry = new backup_nested_element('formentry', ['id'], [
            'projetvetid', 'formsetid', 'studentid', 'parententryid', 'entrystatus', 'timecreated', 'timemodified', 'usermodified',
        ]);

        $formdatas = new backup_nested_element('formdatas');
        $formdata = new backup_nested_element('formdata', ['id'], [
            'fieldid', 'entryid', 'intvalue', 'decvalue', 'shortcharvalue', 'charvalue', 'textvalue',
            'timecreated', 'timemodified', 'usermodified',
        ]);

        $theses = new backup_nested_element('theses');
        $thesis = new backup_nested_element('thesis', ['id'], [
            'projetvetid', 'userid', 'thesis', 'otherdata', 'timecreated', 'timemodified', 'usermodified',
        ]);

        $mobilities = new backup_nested_element('mobilities');
        $mobility = new backup_nested_element('mobility', ['id'], [
            'projetvetid', 'userid', 'title', 'erasmus', 'fmp', 'timecreated', 'timemodified', 'usermodified',
        ]);

        // Build the tree.
        // Configuration data hierarchy.
        $projetvet->add_child($formsets);
        $formsets->add_child($formset);

        $formset->add_child($formcats);
        $formcats->add_child($formcat);

        $formcat->add_child($formfields);
        $formfields->add_child($formfield);

        $formfield->add_child($fielddatas);
        $fielddatas->add_child($fielddata);

        // User data hierarchy.
        $projetvet->add_child($formentries);
        $formentries->add_child($formentry);

        $formentry->add_child($formdatas);
        $formdatas->add_child($formdata);

        $projetvet->add_child($theses);
        $theses->add_child($thesis);

        $projetvet->add_child($mobilities);
        $mobilities->add_child($mobility);

        // Define sources.
        $projetvet->set_source_table('projetvet', ['id' => backup::VAR_ACTIVITYID]);

        // Configuration data sources (always included).
        $formset->set_source_table('projetvet_form_set', []);
        $formcat->set_source_table('projetvet_form_cat', ['formsetid' => backup::VAR_PARENTID]);
        $formfield->set_source_table('projetvet_form_field', ['categoryid' => backup::VAR_PARENTID]);
        $fielddata->set_source_table('projetvet_field_data', ['fieldid' => backup::VAR_PARENTID]);

        // User data sources (only when userinfo is included).
        if ($userinfo) {
            $formentry->set_source_table('projetvet_form_entry', ['projetvetid' => backup::VAR_ACTIVITYID]);
            $formdata->set_source_table('projetvet_form_data', ['entryid' => backup::VAR_PARENTID]);
            $thesis->set_source_table('projetvet_thesis', ['projetvetid' => backup::VAR_ACTIVITYID]);
            $mobility->set_source_table('projetvet_mobility', ['projetvetid' => backup::VAR_ACTIVITYID]);
        }

        // Define id annotations.
        $formset->annotate_ids('user', 'usermodified');
        $formcat->annotate_ids('user', 'usermodified');
        $formcat->annotate_ids('formset', 'formsetid');
        $formfield->annotate_ids('user', 'usermodified');
        $formfield->annotate_ids('formcat', 'categoryid');
        $fielddata->annotate_ids('formfield', 'fieldid');

        if ($userinfo) {
            $formentry->annotate_ids('user', 'studentid');
            $formentry->annotate_ids('user', 'usermodified');
            $formentry->annotate_ids('formset', 'formsetid');
            $formentry->annotate_ids('formentry', 'parententryid');
            $formdata->annotate_ids('user', 'usermodified');
            $formdata->annotate_ids('formfield', 'fieldid');
            $formdata->annotate_ids('formentry', 'entryid');
            $thesis->annotate_ids('user', 'userid');
            $thesis->annotate_ids('user', 'usermodified');
            $mobility->annotate_ids('user', 'userid');
            $mobility->annotate_ids('user', 'usermodified');
        }

        // Define file annotations.
        $projetvet->annotate_files('mod_projetvet', 'intro', null);

        // Return the root element (projetvet), wrapped into standard activity structure.
        return $this->prepare_activity_structure($projetvet);
    }
}
