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

use HTML_QuickForm_checkbox;
use renderer_base;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/checkbox.php');

/**
 * Switch form element (toggle).
 *
 * This element provides a Bootstrap 4 custom switch UI component.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class switch_element extends HTML_QuickForm_static {
    /** @var string Help button */
    public $_helpbutton = ''; // @codingStandardsIgnoreLine

    /**
     * Constructor
     *
     * @param string $elementname Element name
     * @param mixed $elementlabel Label(s) for an element
     * @param string $text Text to display next to the switch
     * @param mixed $attributes Element attributes
     */
    public function __construct($elementname = null, $elementlabel = null, $text = '', $attributes = null) {
        if ($elementname == null) {
            // This is broken quickforms messing with the constructors.
            return;
        }

        parent::__construct($elementname, $elementlabel, $text, $attributes);
        $this->_type = 'switch';
    }

    /**
     * Accepts a renderer
     *
     * @param object $renderer An HTML_QuickForm_Renderer object
     * @param bool $required Whether an element is required
     * @param string $error An error message associated with an element
     * @return void
     */
    public function accept(&$renderer, $required = false, $error = null) {
        global $OUTPUT;

        $elementname = $this->getName();

        // Make sure the element has an id.
        $this->_generateId();

        $advanced = isset($renderer->_advancedElements[$elementname]);
        $elementcontext = $this->export_for_template($OUTPUT);

        $helpbutton = '';
        if (method_exists($this, 'getHelpButton')) {
            $helpbutton = $this->getHelpButton();
        }

        $label = $this->getLabel();
        $text = '';
        if (method_exists($this, 'getText')) {
            // There currently exists code that adds a form element with an empty label.
            // If this is the case then set the label to the description.
            if (empty($label)) {
                $label = $this->getText();
            } else {
                $text = $this->getText();
            }
        }

        $context = [
            'element' => $elementcontext,
            'label' => $label,
            'text' => $text,
            'required' => $required,
            'advanced' => $advanced,
            'helpbutton' => $helpbutton,
            'error' => $error,
        ];

        $html = $OUTPUT->render_from_template('mod_projetvet/form/element_switch', $context);

        if ($renderer->_inGroup) {
            $this->_groupElementTemplate = $html;
        }
        if (($renderer->_inGroup) && !empty($renderer->_groupElementTemplate)) {
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
        $context = [
            'id' => $this->getAttribute('id'),
            'name' => $this->getName(),
            'value' => '1', // Checkbox value is always 1 when checked.
            'checked' => $this->getChecked(),
            'frozenvalue' => $this->getFrozenHtml(),
            'text' => $this->_text,
        ];

        // Add any data attributes.
        $attributes = $this->getAttributes();
        $dataattributes = [];
        foreach ($attributes as $name => $value) {
            if (strpos($name, 'data-') === 0) {
                $dataattributes[] = [
                    'name' => substr($name, 5), // Remove 'data-' prefix.
                    'value' => $value,
                ];
            }
        }
        $context['dataattributes'] = $dataattributes;

        return $context;
    }
}
