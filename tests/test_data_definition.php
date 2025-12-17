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

use mod_projetvet\local\persistent\form_set;
use mod_projetvet\local\persistent\form_cat;
use mod_projetvet\local\persistent\form_field;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\persistent\form_data;

/**
 * Projetvet Trait for data test definition.
 *
 * @package     mod_projetvet
 * @copyright   2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait test_data_definition {
    /**
     * Prepare scenario
     *
     * @param string $datasetname
     * @return void
     */
    public function prepare_scenario(string $datasetname): void {
        $generator = $this->getDataGenerator();
        $projetvetgenerator = $generator->get_plugin_generator('mod_projetvet');
        $this->generates_definition(
            $this->{'get_data_definition_' . $datasetname}(),
            $generator,
            $projetvetgenerator
        );
    }

    /**
     * Generates instances and modules
     *
     * @param array $datadefinition
     * @param object $generator
     * @param object $projetvetgenerator
     * @return void
     */
    public function generates_definition(array $datadefinition, object $generator, object $projetvetgenerator): void {
        global $DB;
        $users = [];

        foreach ($datadefinition as $coursename => $data) {
            $course = $generator->create_course(['shortname' => $coursename]);

            // Create users and enrol them.
            foreach ($data['users'] as $role => $usernames) {
                foreach ($usernames as $username) {
                    if (!isset($users[$username])) {
                        $users[$username] = $generator->create_user(['username' => $username]);
                    }
                    $generator->enrol_user($users[$username]->id, $course->id, $role);
                }
            }

            // Create groups.
            foreach ($data['groups'] as $groupname => $groupdata) {
                $group = $generator->create_group(['courseid' => $course->id, 'name' => $groupname]);
                foreach ($groupdata['users'] as $username) {
                    $generator->create_group_member(['groupid' => $group->id, 'userid' => $users[$username]->id]);
                }
            }

            // Create activities.
            foreach ($data['activities'] as $activityname => $activityinfo) {
                $activitymodule = [
                    'course' => $course->id,
                    'name' => $activityname,
                ];

                $module = $generator->create_module('projetvet', $activitymodule);

                // Create form sets if they don't exist.
                if (!empty($activityinfo['formsets'])) {
                    foreach ($activityinfo['formsets'] as $formsetdef) {
                        $existingset = form_set::get_record(['idnumber' => $formsetdef['idnumber']]);
                        if (!$existingset) {
                            $formset = new form_set();
                            $formset->set('idnumber', $formsetdef['idnumber']);
                            $formset->set('name', $formsetdef['name']);
                            $formset->set('description', $formsetdef['description'] ?? '');
                            $formset->set('sortorder', $formsetdef['sortorder'] ?? 0);
                            $formset->create();
                        }
                    }
                }

                // Create form categories.
                if (!empty($activityinfo['categories'])) {
                    foreach ($activityinfo['categories'] as $categorydef) {
                        $formset = form_set::get_record(['idnumber' => $categorydef['formsetidnumber']]);
                        $existingcat = form_cat::get_record(['idnumber' => $categorydef['idnumber']]);
                        if (!$existingcat && $formset) {
                            $category = new form_cat();
                            $category->set('formsetid', $formset->get('id'));
                            $category->set('idnumber', $categorydef['idnumber']);
                            $category->set('name', $categorydef['name']);
                            $category->set('description', $categorydef['description'] ?? '');
                            $category->set('capability', $categorydef['capability'] ?? null);
                            $category->set('entrystatus', $categorydef['entrystatus'] ?? 0);
                            $category->set('statusmsg', $categorydef['statusmsg'] ?? null);
                            $category->set('sortorder', $categorydef['sortorder'] ?? 0);
                            $category->create();
                        }
                    }
                }

                // Create form fields.
                if (!empty($activityinfo['fields'])) {
                    foreach ($activityinfo['fields'] as $fielddef) {
                        $category = form_cat::get_record(['idnumber' => $fielddef['categoryidnumber']]);
                        $existingfield = form_field::get_record(['idnumber' => $fielddef['idnumber']]);
                        if (!$existingfield && $category) {
                            $field = new form_field();
                            $field->set('categoryid', $category->get('id'));
                            $field->set('idnumber', $fielddef['idnumber']);
                            $field->set('name', $fielddef['name']);
                            $field->set('type', $fielddef['type']);
                            $field->set('description', $fielddef['description'] ?? '');
                            $field->set('sortorder', $fielddef['sortorder'] ?? 0);
                            $field->set('configdata', $fielddef['configdata'] ?? null);
                            $field->set('capability', $fielddef['capability'] ?? null);
                            $field->set('entrystatus', $fielddef['entrystatus'] ?? 0);
                            $field->set('listorder', $fielddef['listorder'] ?? 0);
                            $field->create();
                        }
                    }
                }

                // Create entries.
                if (!empty($activityinfo['entries'])) {
                    foreach ($activityinfo['entries'] as $entrydef) {
                        $formset = form_set::get_record(['idnumber' => $entrydef['formsetidnumber']]);
                        if ($formset) {
                            $entry = new form_entry();
                            $entry->set('projetvetid', $module->id);
                            $entry->set('studentid', $users[$entrydef['student']]->id);
                            $entry->set('formsetid', $formset->get('id'));
                            $entry->set('parententryid', $entrydef['parententryid'] ?? 0);
                            $entry->set('entrystatus', $entrydef['entrystatus'] ?? 0);
                            $entry->create();

                            // Add field data.
                            if (!empty($entrydef['data'])) {
                                foreach ($entrydef['data'] as $fieldidnumber => $value) {
                                    $field = form_field::get_record(['idnumber' => $fieldidnumber]);
                                    if ($field) {
                                        $data = new form_data();
                                        $data->set('entryid', $entry->get('id'));
                                        $data->set('fieldid', $field->get('id'));

                                        // Determine value type based on field type.
                                        if (in_array($field->get('type'), ['number', 'date', 'datetime', 'checkbox'])) {
                                            $data->set('intvalue', (int)$value);
                                            $data->set('textvalue', null);
                                        } else {
                                            $data->set('intvalue', null);
                                            $data->set('textvalue', $value);
                                        }
                                        $data->create();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Data definition for set_1 - Basic activities form
     *
     * @return array $datadefinition
     */
    private function get_data_definition_set_1(): array {
        return [
            'course 1' => [
                'users' => [
                    'student' => ['student1', 'student2'],
                    'editingteacher' => ['teacher1'],
                    'manager' => ['manager1'],
                ],
                'groups' => [
                    'group1' => [
                        'users' => ['student1', 'teacher1'],
                    ],
                    'group2' => [
                        'users' => ['student2', 'teacher1'],
                    ],
                ],
                'activities' => [
                    'Activity 1' => [
                        'formsets' => [
                            [
                                'idnumber' => 'activities',
                                'name' => 'Activities',
                                'description' => 'Activity form set',
                                'sortorder' => 1,
                            ],
                        ],
                        'categories' => [
                            [
                                'formsetidnumber' => 'activities',
                                'idnumber' => 'general_info',
                                'name' => 'General Information',
                                'description' => 'Basic activity information',
                                'capability' => 'submit',
                                'entrystatus' => 0,
                                'statusmsg' => 'draft',
                                'sortorder' => 1,
                            ],
                            [
                                'formsetidnumber' => 'activities',
                                'idnumber' => 'details',
                                'name' => 'Activity Details',
                                'description' => 'Detailed activity information',
                                'capability' => 'submit',
                                'entrystatus' => 0,
                                'statusmsg' => 'draft',
                                'sortorder' => 2,
                            ],
                        ],
                        'fields' => [
                            [
                                'categoryidnumber' => 'general_info',
                                'idnumber' => 'activity_title',
                                'name' => 'Activity Title',
                                'type' => 'text',
                                'description' => 'The title of the activity',
                                'sortorder' => 1,
                                'entrystatus' => 0,
                                'listorder' => 1,
                            ],
                            [
                                'categoryidnumber' => 'general_info',
                                'idnumber' => 'activity_date',
                                'name' => 'Activity Date',
                                'type' => 'date',
                                'description' => 'Date of the activity',
                                'sortorder' => 2,
                                'entrystatus' => 0,
                                'listorder' => 2,
                            ],
                            [
                                'categoryidnumber' => 'details',
                                'idnumber' => 'description',
                                'name' => 'Description',
                                'type' => 'textarea',
                                'description' => 'Detailed description',
                                'sortorder' => 1,
                                'entrystatus' => 0,
                                'listorder' => 0,
                            ],
                            [
                                'categoryidnumber' => 'details',
                                'idnumber' => 'hours_spent',
                                'name' => 'Hours Spent',
                                'type' => 'number',
                                'description' => 'Number of hours',
                                'sortorder' => 2,
                                'entrystatus' => 0,
                                'listorder' => 3,
                            ],
                        ],
                        'entries' => [
                            [
                                'formsetidnumber' => 'activities',
                                'student' => 'student1',
                                'entrystatus' => 0,
                                'parententryid' => 0,
                                'data' => [
                                    'activity_title' => 'First Activity',
                                    'activity_date' => strtotime('2025-01-15'),
                                    'description' => 'This is my first activity',
                                    'hours_spent' => 5,
                                ],
                            ],
                            [
                                'formsetidnumber' => 'activities',
                                'student' => 'student1',
                                'entrystatus' => 1,
                                'parententryid' => 0,
                                'data' => [
                                    'activity_title' => 'Second Activity',
                                    'activity_date' => strtotime('2025-02-20'),
                                    'description' => 'This is my second activity',
                                    'hours_spent' => 8,
                                ],
                            ],
                            [
                                'formsetidnumber' => 'activities',
                                'student' => 'student2',
                                'entrystatus' => 0,
                                'parententryid' => 0,
                                'data' => [
                                    'activity_title' => 'Student 2 Activity',
                                    'activity_date' => strtotime('2025-01-20'),
                                    'description' => 'Activity by student 2',
                                    'hours_spent' => 3,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
