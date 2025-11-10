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
 * Test page for tagselect form element
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/projetvet/test_tagselect.php'));
$PAGE->set_title('Tag Select Test');
$PAGE->set_heading('Tag Select Form Element Test');

/**
 * Test form for tagselect element
 */
class tagselect_test_form extends moodleform {
    /**
     * Define the form
     */
    protected function definition() {
        global $CFG;
        $mform = $this->_form;

        // Register the custom form element.
        MoodleQuickForm::registerElementType(
            'tagselect',
            "$CFG->dirroot/mod/projetvet/classes/form/tagselect_element.php",
            'mod_projetvet\form\tagselect_element'
        );

        $mform->addElement('html', '<h3>Example 1: Simple Options</h3>');

        // Simple flat options.
        $simpleoptions = [
            '1' => 'Red',
            '2' => 'Blue',
            '3' => 'Green',
            '4' => 'Yellow',
            '5' => 'Purple',
        ];

        $mform->addElement(
            'tagselect',
            'colors',
            'Select Colors',
            $simpleoptions,
            [
                'rowname' => 'Choose your favorite colors',
                'maxtags' => 3,
            ]
        );
        $mform->addHelpButton('colors', 'colors_help', 'mod_projetvet');

        $mform->addElement('html', '<hr><h3>Example 2: Grouped Options</h3>');

        // Grouped options.
        $groupedoptions = [
            [
                'name' => 'Fruits',
                'items' => [
                    ['uniqueid' => 'apple', 'name' => 'Apple'],
                    ['uniqueid' => 'banana', 'name' => 'Banana'],
                    ['uniqueid' => 'orange', 'name' => 'Orange'],
                    ['uniqueid' => 'grape', 'name' => 'Grape'],
                ],
            ],
            [
                'name' => 'Vegetables',
                'items' => [
                    ['uniqueid' => 'carrot', 'name' => 'Carrot'],
                    ['uniqueid' => 'broccoli', 'name' => 'Broccoli'],
                    ['uniqueid' => 'spinach', 'name' => 'Spinach'],
                    ['uniqueid' => 'tomato', 'name' => 'Tomato'],
                ],
            ],
            [
                'name' => 'Grains',
                'items' => [
                    ['uniqueid' => 'rice', 'name' => 'Rice'],
                    ['uniqueid' => 'wheat', 'name' => 'Wheat'],
                    ['uniqueid' => 'oats', 'name' => 'Oats'],
                ],
            ],
        ];

        $mform->addElement(
            'tagselect',
            'food',
            'Select Food Items',
            [],
            [
                'groupedoptions' => $groupedoptions,
                'rowname' => 'Food Selection',
                'maxtags' => 0, // Unlimited.
            ]
        );

        $mform->addElement('html', '<hr><h3>Example 3: Competencies from JSON</h3>');

        // Load from complist.json if available.
        $competencies = $this->load_competencies();
        if (!empty($competencies)) {
            $mform->addElement(
                'tagselect',
                'competencies',
                'Select Competencies',
                [],
                [
                    'groupedoptions' => $competencies,
                    'rowname' => 'Competencies',
                    'maxtags' => 5,
                ]
            );
        } else {
            $mform->addElement('html', '<p class="alert alert-info">complist.json not found</p>');
        }

        $this->add_action_buttons(true, 'Submit Test');
    }

    /**
     * Load competencies from JSON file
     *
     * @return array
     */
    protected function load_competencies() {
        global $CFG;

        $jsonfile = $CFG->dirroot . '/mod/projetvet/data/complist.json';
        if (!file_exists($jsonfile)) {
            return [];
        }

        $json = file_get_contents($jsonfile);
        $data = json_decode($json, true);

        if (empty($data)) {
            return [];
        }

        // Parse the flat array structure with parent relationships.
        $grouped = [];
        foreach ($data as $item) {
            if ($item['type'] === 'heading') {
                // Create a group for this heading.
                $groupitems = [];
                // Find all items that belong to this heading.
                foreach ($data as $subitem) {
                    if ($subitem['type'] === 'item' && $subitem['parent'] == $item['uniqueid']) {
                        $groupitems[] = [
                            'uniqueid' => $subitem['uniqueid'],
                            'name' => $subitem['name'],
                        ];
                    }
                }
                if (!empty($groupitems)) {
                    $grouped[] = [
                        'name' => $item['name'],
                        'items' => $groupitems,
                    ];
                }
            }
        }

        return $grouped;
    }
}

$form = new tagselect_test_form();

if ($data = $form->get_data()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading('Form Submitted Successfully');

    echo '<div class="alert alert-success">';
    echo '<h4>Submitted Values:</h4>';
    echo '<dl class="row">';

    if (isset($data->colors)) {
        echo '<dt class="col-sm-3">Colors:</dt>';
        echo '<dd class="col-sm-9">' . implode(', ', $data->colors) . '</dd>';
    }

    if (isset($data->food)) {
        echo '<dt class="col-sm-3">Food:</dt>';
        echo '<dd class="col-sm-9">' . implode(', ', $data->food) . '</dd>';
    }

    if (isset($data->competencies)) {
        echo '<dt class="col-sm-3">Competencies:</dt>';
        echo '<dd class="col-sm-9">' . implode(', ', $data->competencies) . '</dd>';
    }

    echo '</dl>';
    echo '</div>';

    echo '<p><a href="' . $PAGE->url . '" class="btn btn-primary">Try Again</a></p>';

    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading('Tag Select Form Element Test');

echo '<div class="alert alert-info">';
echo '<p><strong>Instructions:</strong></p>';
echo '<ul>';
echo '<li>Click the "Select tags" button to open the popup</li>';
echo '<li>Use the search box to filter options</li>';
echo '<li>Click on items to select them</li>';
echo '<li>Click the × button on badges to remove selections</li>';
echo '<li>Click "Save" to close the popup</li>';
echo '<li>Submit the form to see the selected values</li>';
echo '</ul>';
echo '</div>';

$form->display();

echo $OUTPUT->footer();
