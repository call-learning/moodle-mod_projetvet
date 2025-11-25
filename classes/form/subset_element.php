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

namespace mod_projetvet\form;

use HTML_QuickForm_static;
use renderer_base;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/static.php');

/**
 * Subset form element - displays a button to open a sub-form and lists saved entries
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subset_element extends HTML_QuickForm_static {
    /** @var string Help button */
    public $_helpbutton = ''; // @codingStandardsIgnoreLine

    /** @var string The subset formset idnumber */
    protected $subsetformsetidnumber = '';

    /** @var int The parent entry ID */
    protected $parententryid = 0;

    /** @var int The student ID */
    protected $studentid = 0;

    /** @var int The course module ID */
    protected $cmid = 0;

    /** @var int The projetvet instance ID */
    protected $projetvetid = 0;

    /** @var string Button text */
    protected $buttontext = '';

    /**
     * Constructor
     *
     * @param string $elementname Element name
     * @param mixed $elementlabel Label(s) for an element
     * @param string $text Text content
     * @param array $attributes Element attributes
     */
    public function __construct($elementname = null, $elementlabel = null, $text = null, $attributes = null) {
        if ($attributes === null) {
            $attributes = [];
        }

        // Extract custom attributes.
        if (isset($attributes['subsetformsetidnumber'])) {
            $this->subsetformsetidnumber = $attributes['subsetformsetidnumber'];
            unset($attributes['subsetformsetidnumber']);
        }

        if (isset($attributes['parententryid'])) {
            $this->parententryid = $attributes['parententryid'];
            unset($attributes['parententryid']);
        }

        if (isset($attributes['studentid'])) {
            $this->studentid = $attributes['studentid'];
            unset($attributes['studentid']);
        }

        if (isset($attributes['cmid'])) {
            $this->cmid = $attributes['cmid'];
            unset($attributes['cmid']);
        }

        if (isset($attributes['projetvetid'])) {
            $this->projetvetid = $attributes['projetvetid'];
            unset($attributes['projetvetid']);
        }

        if (isset($attributes['buttontext'])) {
            $this->buttontext = $attributes['buttontext'];
            unset($attributes['buttontext']);
        }

        parent::__construct($elementname, $elementlabel, $text, $attributes);
        $this->_type = 'subset';
    }

    /**
     * Returns the HTML for this form element.
     *
     * @return string
     */
    public function toHtml() { // @codingStandardsIgnoreLine
        global $OUTPUT;

        $elementname = $this->getName();
        $elementid = $this->getAttribute('id');

        // Get existing entries for this subset.
        $entries = [];
        if ($this->parententryid && $this->subsetformsetidnumber) {
            $entries = $this->get_subset_entries();
        }

        $context = [
            'elementname' => $elementname,
            'elementid' => $elementid,
            'label' => $this->getLabel(),
            'buttontext' => $this->buttontext ?: get_string('addentry', 'mod_projetvet'),
            'subsetformsetidnumber' => $this->subsetformsetidnumber,
            'parententryid' => $this->parententryid,
            'studentid' => $this->studentid,
            'cmid' => $this->cmid,
            'projetvetid' => $this->projetvetid,
            'entries' => $entries,
            'hasentries' => !empty($entries),
            'isfrozen' => $this->isFrozen(),
        ];

        return $OUTPUT->render_from_template('mod_projetvet/form/element_subset', $context);
    }

    /**
     * Get subset entries
     *
     * @return array
     */
    protected function get_subset_entries(): array {
        // Use the API to get entry list.
        $entrylistdata = \mod_projetvet\local\api\entries::get_entry_list(
            $this->projetvetid,
            $this->studentid,
            $this->subsetformsetidnumber,
            $this->parententryid
        );

        if (empty($entrylistdata['activities'])) {
            return [];
        }

        // Build headers from listfields.
        $headers = [];
        foreach ($entrylistdata['listfields'] as $field) {
            $headers[] = ['name' => $field->name];
        }

        // Build rows from activities.
        $rows = [];
        foreach ($entrylistdata['activities'] as $activity) {
            $row = [
                'id' => $activity['id'],
                'fields' => [],
            ];

            foreach ($activity['fields'] as $field) {
                $row['fields'][] = ['value' => $field['displayvalue']];
            }

            $rows[] = $row;
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        return [
            'html' => $this->toHtml(),
        ];
    }
}
