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

use MoodleQuickForm_text;
use renderer_base;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/text.php');

/**
 * Number type form element.
 *
 * Extends the text element to render as HTML5 input type="number"
 * with support for min, max, and step attributes.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class number_element extends MoodleQuickForm_text {
    /**
     * Constructor
     *
     * @param string $elementname Element name
     * @param mixed $elementlabel Label(s) for an element
     * @param array $attributes Element attributes. Supports:
     *                          - 'min': Minimum value
     *                          - 'max': Maximum value
     *                          - 'step': Step increment (e.g., 0.01 for decimals, 1 for integers)
     */
    public function __construct($elementname = null, $elementlabel = null, $attributes = null) {
        parent::__construct($elementname, $elementlabel, $attributes);
        $this->_type = 'number';
    }

    /**
     * Returns the HTML for this form element.
     *
     * @return string
     */
    public function toHtml() { // @codingStandardsIgnoreLine
        global $OUTPUT;

        // Use our custom template for both frozen and unfrozen states.
        $context = $this->export_for_template($OUTPUT);
        $html = $OUTPUT->render_from_template('mod_projetvet/form/element_number', $context);

        // Add hidden field for frozen state to preserve value on submit.
        if ($this->_flagFrozen) {
            $html .= $this->_getPersistantData();
        }

        return $html;
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $this->_generateId();

        $context = [
            'name' => $this->getName(),
            'id' => $this->getAttribute('id'),
            'value' => $this->getValue(),
            'frozen' => $this->_flagFrozen,
            'min' => $this->getAttribute('min'),
            'max' => $this->getAttribute('max'),
            'step' => $this->getAttribute('step'),
            'action' => $this->getAttribute('data-action'),
        ];

        // Add any additional attributes.
        $extraattributes = [];
        foreach ($this->_attributes as $name => $value) {
            if (!in_array($name, ['type', 'name', 'id', 'value', 'min', 'max', 'step', 'class', 'data-action'])) {
                $extraattributes[] = $name . '="' . s($value) . '"';
            }
        }
        $context['extraattributes'] = implode(' ', $extraattributes);

        return $context;
    }
}
