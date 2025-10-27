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

use MoodleQuickForm_autocomplete;
use renderer_base;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/autocomplete.php');

/**
 * Tag select form element with popup UI.
 *
 * This element provides a better UI than the standard autocomplete,
 * displaying a popup with a searchable list of items organized in groups.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tagselect_element extends MoodleQuickForm_autocomplete {

    /** @var array Grouped options for display in the popup */
    protected $groupedoptions = [];

    /** @var int Maximum number of tags that can be selected */
    protected $maxtags = 0;

    /** @var string Row name for display */
    protected $rowname = '';

    /**
     * Constructor
     *
     * @param string $elementName Element name
     * @param mixed $elementLabel Label(s) for an element
     * @param array $options Flat array of options or grouped array
     * @param array $attributes Element attributes. Special options:
     *                          - 'groupedoptions': Array of grouped options [['name' => 'Group', 'items' => [...]]]
     *                          - 'maxtags': Maximum number of selections allowed (0 = unlimited)
     *                          - 'rowname': Display name for the row
     */
    public function __construct($elementName = null, $elementLabel = null, $options = [], $attributes = null) {
        if ($elementName == null) {
            // This is broken quickforms messing with the constructors.
            return;
        }

        if ($attributes === null) {
            $attributes = [];
        }

        // Extract custom attributes.
        if (isset($attributes['groupedoptions'])) {
            $this->groupedoptions = $attributes['groupedoptions'];
            unset($attributes['groupedoptions']);
        }

        if (isset($attributes['maxtags'])) {
            $this->maxtags = (int) $attributes['maxtags'];
            unset($attributes['maxtags']);
        }

        if (isset($attributes['rowname'])) {
            $this->rowname = $attributes['rowname'];
            unset($attributes['rowname']);
        }

        // Always enable multiple selection.
        $attributes['multiple'] = 'multiple';

        // If grouped options are provided, flatten them for the parent autocomplete.
        if (!empty($this->groupedoptions)) {
            $flatoptions = [];
            foreach ($this->groupedoptions as $group) {
                if (isset($group['items'])) {
                    foreach ($group['items'] as $item) {
                        if (isset($item['uniqueid']) && isset($item['name'])) {
                            $flatoptions[$item['uniqueid']] = $item['name'];
                        }
                    }
                }
            }
            $options = $flatoptions;
        }

        parent::__construct($elementName, $elementLabel, $options, $attributes);
        $this->_type = 'tagselect';
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

        $html = $OUTPUT->render_from_template('mod_projetvet/form/element_tagselect', $context);

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
        $context = parent::export_for_template($output);

        // Add our custom context.
        $context['groupedoptions'] = $this->groupedoptions;
        $context['maxtags'] = $this->maxtags;
        $context['rowname'] = $this->rowname ?: $this->getLabel();

        // Get currently selected values.
        $selectedvalues = $this->getValue();
        if (!is_array($selectedvalues)) {
            $selectedvalues = $selectedvalues ? [$selectedvalues] : [];
        }

        // Build selected tags for display.
        $selectedtags = [];
        foreach ($selectedvalues as $value) {
            if (isset($context['options'])) {
                foreach ($context['options'] as $option) {
                    if ($option['value'] == $value) {
                        $selectedtags[] = [
                            'tagid' => $value,
                            'tagname' => $option['text'],
                            'action' => 'remove-tag',
                        ];
                        break;
                    }
                }
            }
        }
        $context['selectedtags'] = $selectedtags;
        $context['hasmaxtags'] = $this->maxtags > 0 && count($selectedtags) >= $this->maxtags;

        return $context;
    }
}
