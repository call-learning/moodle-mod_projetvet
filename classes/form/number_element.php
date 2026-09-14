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
     * Accepts a renderer.
     *
     * Rendering the complete form item here, rather than relying on
     * toHtml(), gives the element access to the renderer's required and error
     * state so that core_form/element-template can render it consistently.
     *
     * @param object $renderer An HTML_QuickForm_Renderer object
     * @param bool $required Whether an element is required
     * @param string|null $error An error message associated with an element
     * @return void
     */
    public function accept(&$renderer, $required = false, $error = null) {
        global $OUTPUT;

        $elementname = $this->getName();
        $this->_generateId();
        $context = $this->export_for_template($OUTPUT);

        $helpbutton = method_exists($this, 'getHelpButton') ? $this->getHelpButton() : '';
        $context = [
            'element' => $context,
            'label' => $this->getLabel(),
            'required' => $required,
            'advanced' => isset($renderer->_advancedElements[$elementname]),
            'helpbutton' => $helpbutton,
            'error' => $error,
        ];
        $html = $OUTPUT->render_from_template('mod_projetvet/form/element_number', $context);

        if ($renderer->_inGroup) {
            $this->_groupElementTemplate = $html;
        }
        if (($renderer->_inGroup) && !empty($this->_groupElementTemplate)) {
            $renderer->_groupElementTemplate = $html;
        } else if (!isset($renderer->_templates[$elementname])) {
            $renderer->_templates[$elementname] = $html;
        }

        if (in_array($elementname, $renderer->_stopFieldsetElements) && $renderer->_fieldsetsOpen > 0) {
            $renderer->_html .= $renderer->_closeFieldsetTemplate;
            $renderer->_fieldsetsOpen--;
        }
        $renderer->_html .= $html;
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $this->_generateId();
        $context = parent::export_for_template($output);
        $context = $context + [
            'min' => $this->getAttribute('min'),
            'max' => $this->getAttribute('max'),
            'step' => $this->getAttribute('step') ?: '1', // Default to 1 for whole numbers.
            'action' => $this->getAttribute('data-action'),
            'string' => $this->getAttribute('data-string'),
        ];

        // The wrapper id is required by the core_form/element-template partial.
        $context['wrapperid'] = 'fitem_' . $context['id'];

        if ($this->getAttribute('required')) {
            $context['required'] = true;
        }

        // Add attributes not rendered explicitly by the template. This also
        // prevents data-action from being picked up by modal form's button
        // disabling selector.
        $attributes = [];
        foreach ($this->_attributes as $name => $value) {
            if (!in_array($name, ['type', 'name', 'id', 'value', 'min', 'max', 'step', 'class', 'data-action', 'data-string'])) {
                $attributes[] = $name . '="' . s($value) . '"';
            }
        }
        $context['attributes'] = implode(' ', $attributes);

        return $context;
    }

    /**
     * Validate the submitted value.
     *
     * Only accepts whole numbers (integers).
     *
     * @param mixed $value Submitted value
     * @return string|null Error message or null if valid
     */
    public function validateSubmitValue($value) {
        // Allow empty values if not required.
        if ($value === '' || $value === null) {
            return null;
        }

        // Check if the value is numeric.
        if (!is_numeric($value)) {
            return get_string('err_numeric', 'form');
        }

        // Check if the value is a whole number (no decimals).
        if (floor($value) != $value) {
            return get_string('err_numeric', 'form');
        }

        // Validate min/max constraints.
        $min = $this->getAttribute('min');
        $max = $this->getAttribute('max');

        if ($min !== null && $value < $min) {
            return get_string('err_numeric', 'form');
        }

        if ($max !== null && $value > $max) {
            return get_string('err_numeric', 'form');
        }

        return null;
    }
}
