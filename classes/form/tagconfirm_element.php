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

use MoodleQuickForm_selectgroups;
use mod_projetvet\local\persistent\field_data;
use renderer_base;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/selectgroups.php');

/**
 * Tag confirmation form element.
 *
 * Displays previously selected tags from another field in a table format
 * with checkboxes to confirm which ones were actually practiced.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tagconfirm_element extends MoodleQuickForm_selectgroups {
    /** @var string The source tagselect field idnumber */
    protected $sourcefielidnumber = '';

    /** @var array Previously selected tags from source field */
    protected $sourcetags = [];

    /** @var array Grouped options for display */
    protected $groupedoptions = [];

    /** @var int The field ID to get lookup data from */
    protected $lookupfieldid = 0;

    /**
     * Constructor
     *
     * @param string $elementname Element name
     * @param mixed $elementlabel Label(s) for an element
     * @param array $optgrps Data to be used to populate options (or attributes if called with 3 params)
     * @param array $attributes Element attributes. Special options:
     *                          - 'sourcefielidnumber': The idnumber of the source tagselect field
     *                          - 'sourcetags': Previously selected tag IDs
     *                          - 'lookupfieldid': Field ID for getting grouped options
     */
    public function __construct($elementname = null, $elementlabel = null, $optgrps = null, $attributes = null) {
        if ($elementname == null) {
            // This is broken quickforms messing with the constructors.
            return;
        }

        // If optgrps contains our custom attributes, it means it was called with 3 params.
        // In that case, the 3rd param is actually attributes, not optgrps.
        if (is_array($optgrps) && (isset($optgrps['sourcefielidnumber']) || isset($optgrps['sourcetags'])
            || isset($optgrps['lookupfieldid']))) {
            $attributes = $optgrps;
            $optgrps = [];
        }

        if ($attributes === null) {
            $attributes = [];
        }

        // Extract custom attributes.
        if (isset($attributes['sourcefielidnumber'])) {
            $this->sourcefielidnumber = $attributes['sourcefielidnumber'];
            unset($attributes['sourcefielidnumber']);
        }

        if (isset($attributes['sourcetags'])) {
            $this->sourcetags = $attributes['sourcetags'];
            unset($attributes['sourcetags']);
        }

        if (isset($attributes['lookupfieldid'])) {
            $this->lookupfieldid = $attributes['lookupfieldid'];
            $this->groupedoptions = field_data::get_grouped_options($this->lookupfieldid);
            unset($attributes['lookupfieldid']);
        }

        // Call parent constructor with empty optgrps since we'll handle options ourselves.
        parent::__construct($elementname, $elementlabel, [], $attributes);
        $this->_type = 'tagconfirm';
    }

    /**
     * Returns the HTML for this form element.
     *
     * @return string
     */
    public function toHtml() {
        global $OUTPUT;
        $context = $this->export_for_template($OUTPUT);
        return $OUTPUT->render_from_template('mod_projetvet/form/element_tagconfirm_wrapper', $context);
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $elementname = $this->getName();
        $elementid = $this->getAttribute('id');
        $isfrozen = $this->isFrozen();

        // Get currently confirmed values.
        $confirmedvalues = $this->getValue();
        if (!is_array($confirmedvalues)) {
            $confirmedvalues = $confirmedvalues ? json_decode($confirmedvalues, true) : [];
        }
        if (!is_array($confirmedvalues)) {
            $confirmedvalues = [];
        }

        // Build grouped items with checkboxes.
        $groups = [];
        foreach ($this->groupedoptions as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                // Only include items that were selected in the source field.
                if (in_array($item['uniqueid'], $this->sourcetags)) {
                    $items[] = [
                        'uniqueid' => $item['uniqueid'],
                        'name' => $item['name'],
                        'checked' => in_array($item['uniqueid'], $confirmedvalues),
                    ];
                }
            }

            // Only include groups that have items.
            if (!empty($items)) {
                $groups[] = [
                    'heading' => $group['name'],
                    'items' => $items,
                ];
            }
        }

        return [
            'elementname' => $elementname,
            'elementid' => $elementid,
            'label' => $this->getLabel(),
            'groups' => $groups,
            'hasgroups' => !empty($groups),
            'frozen' => $isfrozen,
        ];
    }

    /**
     * Export confirmed values as an array.
     *
     * @param array $submitvalues
     * @param bool $notused
     * @return array
     */
    public function exportValue(&$submitvalues, $notused = false) {
        $valuearray = [];
        $elementname = $this->getName();

        // Collect all checked items.
        $confirmed = [];
        if (isset($submitvalues[$elementname]) && is_array($submitvalues[$elementname])) {
            foreach ($submitvalues[$elementname] as $uniqueid => $value) {
                if ($value) {
                    $confirmed[] = $uniqueid;
                }
            }
        }

        // Return as array (not JSON) to match tagselect behavior.
        $valuearray[$elementname] = $confirmed;
        return $valuearray;
    }
}
