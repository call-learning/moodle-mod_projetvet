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
 * Behat steps for mod_projetvet
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;

/**
 * Behat steps definitions for mod_projetvet
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_projetvet extends behat_base {
    /**
     * Opens the activity entry form for creating a new entry
     *
     * @Given /^I click on create new activity entry for "(?P<formsetidnumber_string>(?:[^"]|\\")*)"$/
     * @param string $formsetidnumber The form set idnumber (activities, facetoface, etc.)
     * @throws ExpectationException
     */
    public function i_click_on_create_new_activity_entry($formsetidnumber) {
        $button = $this->find(
            'css',
            '[data-action="activity-entry-form"][data-formsetidnumber="' . $formsetidnumber . '"][data-entryid="0"]'
        );

        if (!$button) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Create new entry button',
                'css',
                '[data-action="activity-entry-form"][data-formsetidnumber="' . $formsetidnumber . '"]'
            );
        }

        $button->click();
        $this->wait_for_pending_js();
    }

    /**
     * Opens the tagselect popup for a specific field
     *
     * @Given /^I open tagselect for field "(?P<fieldidnumber_string>(?:[^"]|\\")*)"$/
     * @param string $fieldidnumber The field idnumber
     * @throws ExpectationException
     */
    public function i_open_tagselect_for_field($fieldidnumber) {
        // Find the field wrapper by data-fieldidnumber attribute.
        $fieldwrapper = $this->find(
            'css',
            '[data-fieldidnumber="' . $fieldidnumber . '"]'
        );

        if (!$fieldwrapper) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Field wrapper',
                'css',
                '[data-fieldidnumber="' . $fieldidnumber . '"]'
            );
        }

        // Find the open-tagselect button within this field.
        $button = $this->find(
            'css',
            '[data-action="open-tagselect"]',
            new ExpectationException('Tagselect button not found for field ' . $fieldidnumber, $this->getSession()),
            $fieldwrapper
        );

        $button->click();
        $this->wait_for_pending_js();
    }

    /**
     * Opens the tagselect popup for a field by element name
     *
     * @Given /^I open tagselect for "(?P<elementname_string>(?:[^"]|\\")*)"$/
     * @param string $elementname The element name (e.g., "Category")
     * @throws ExpectationException
     */
    public function i_open_tagselect_for_element_name($elementname) {
        // Find the open-tagselect button by data-element-name attribute.
        $button = $this->find(
            'css',
            '[data-action="open-tagselect"][data-element-name="' . $elementname . '"]'
        );

        if (!$button) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Tagselect button for ' . $elementname,
                'css',
                '[data-action="open-tagselect"][data-element-name="' . $elementname . '"]'
            );
        }

        $button->click();
        $this->wait_for_pending_js();
    }

    /**
     * Selects a tag in the tagselect popup by clicking on it
     *
     * @Given /^I select tag "(?P<tagname_string>(?:[^"]|\\")*)" in tagselect popup$/
     * @param string $tagname The tag name or part of it
     * @throws ExpectationException
     */
    public function i_select_tag_in_tagselect_popup($tagname) {
        // Find the tagselect popup.
        $popup = $this->find('css', '[data-region="tagselect-popup"]');

        if (!$popup) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Tagselect popup',
                'css',
                '[data-region="tagselect-popup"]'
            );
        }

        // Find all tags and look for one containing the text.
        $tags = $popup->findAll('css', '[data-action="select-tag"]');
        $found = false;

        foreach ($tags as $tag) {
            if (stripos($tag->getText(), $tagname) !== false) {
                $tag->click();
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new ExpectationException(
                'Tag "' . $tagname . '" not found in tagselect popup',
                $this->getSession()
            );
        }

        $this->wait_for_pending_js();
    }

    /**
     * Saves the selected tags in the tagselect popup
     *
     * @Given /^I save tags in tagselect popup$/
     * @throws ExpectationException
     */
    public function i_save_tags_in_tagselect_popup() {
        $popup = $this->find('css', '[data-region="tagselect-popup"]');

        if (!$popup) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Tagselect popup',
                'css',
                '[data-region="tagselect-popup"]'
            );
        }

        $savebutton = $this->find(
            'css',
            '[data-action="save-tags"]',
            new ExpectationException('Save tags button not found', $this->getSession()),
            $popup
        );

        $savebutton->click();
        $this->wait_for_pending_js();
    }

    /**
     * Clicks on a button with specific data-action within a region
     * // phpcs:disable moodle.Files.LineLength.TooLong
     * @Given /^I click on "(?P<action_string>(?:[^"]|\\")*)" buttonaction in the "(?P<regionname_string>(?:[^"]|\\")*)" "(?P<selectortype_string>(?:[^"]|\\")*)"$/
     * // phpcs:enable moodle.Files.LineLength.TooLong
     * @param string $action The data-action attribute value
     * @param string $regionname The region identifier
     * @param string $selectortype The type of selector (region, css_element, etc.)
     * @throws ExpectationException
     */
    public function i_click_on_buttonaction_in_region($action, $regionname, $selectortype) {
        // Find the region/container.
        $selector = $selectortype === 'region' ? '[data-region="' . $regionname . '"]' : $regionname;
        $container = $this->find('css', $selector);

        if (!$container) {
            throw new ElementNotFoundException(
                $this->getSession(),
                ucfirst($selectortype) . ' ' . $regionname,
                'css',
                $selector
            );
        }

        // Find the button with data-action within this container.
        $button = $container->find('css', '[data-action="' . $action . '"]');

        if (!$button) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Button with data-action="' . $action . '"',
                'css',
                '[data-action="' . $action . '"]'
            );
        }

        // Ensure button is visible before clicking.
        if (!$button->isVisible()) {
            throw new ExpectationException(
                'Button with data-action="' . $action . '" is not visible',
                $this->getSession()
            );
        }

        $button->click();
        $this->wait_for_pending_js();
    }

    /**
     * Checks if an entry with specific title exists in the entry list
     *
     * @Then /^I should see entry with title "(?P<title_string>(?:[^"]|\\")*)" in the entry list$/
     * @param string $title The entry title
     * @throws ExpectationException
     */
    public function i_should_see_entry_with_title($title) {
        $entrylist = $this->find('css', '[data-region="entry-list"]');

        if (!$entrylist) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Entry list',
                'css',
                '[data-region="entry-list"]'
            );
        }

        $text = $entrylist->getText();
        if (stripos($text, $title) === false) {
            throw new ExpectationException(
                'Entry with title "' . $title . '" not found in entry list',
                $this->getSession()
            );
        }
    }

    /**
     * Clicks the edit button for a specific entry
     *
     * @Given /^I click edit for entry with title "(?P<title_string>(?:[^"]|\\")*)"$/
     * @param string $title The entry title
     * @throws ExpectationException
     */
    public function i_click_edit_for_entry($title) {
        // Find the entry row containing the title.
        $entrylist = $this->find('css', '[data-region="entry-list"]');

        if (!$entrylist) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Entry list',
                'css',
                '[data-region="entry-list"]'
            );
        }

        // Find all rows.
        $rows = $entrylist->findAll('css', 'tr');
        $found = false;

        foreach ($rows as $row) {
            if (stripos($row->getText(), $title) !== false) {
                // Find edit button in this row.
                $editbutton = $row->find('css', '[data-action="activity-entry-form"]');
                if ($editbutton) {
                    $editbutton->click();
                    $this->wait_for_pending_js();
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            throw new ExpectationException(
                'Entry with title "' . $title . '" not found or edit button not available',
                $this->getSession()
            );
        }
    }

    /**
     * Waits for pending JavaScript and AJAX to complete
     */
    public function wait_for_pending_js() {
        $this->getSession()->wait(1000);
    }

    /**
     * Clicks the view activities link for a specific student
     *
     * @Given /^I view activities for student "(?P<studentname_string>(?:[^"]|\\")*)"$/
     * @param string $studentname The student's full name
     * @throws ExpectationException
     */
    public function i_view_activities_for_student($studentname) {
        // Find the table row containing the student name.
        $table = $this->find('css', 'table.table');

        if (!$table) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Student list table',
                'css',
                'table.table'
            );
        }

        // Find all rows.
        $rows = $table->findAll('css', 'tbody tr');
        $found = false;

        foreach ($rows as $row) {
            if (stripos($row->getText(), $studentname) !== false) {
                // Find the "View activities" link in this row.
                $link = $row->find('css', 'a[data-toggle="dropdown"]');
                if ($link) {
                    $link->click();
                    $this->wait_for_pending_js();
                    $view = $row->find('css', 'a[aria-label="View Activities"]');
                    $view->click();
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            throw new ExpectationException(
                'Student "' . $studentname . '" not found or view activities link not available',
                $this->getSession()
            );
        }
    }

    /**
     * Clicks a form button by its text (ignoring icons)
     *
     * @Given /^I click on form button "(?P<buttontext_string>(?:[^"]|\\")*)"$/
     * @param string $buttontext The button text
     * @throws ExpectationException
     */
    public function i_click_on_form_button($buttontext) {
        // Find all submit buttons.
        $buttons = $this->find_all('css', 'button.projetvet-form-button');

        $found = false;
        foreach ($buttons as $button) {
            $value = $button->getValue();
            $text = $button->getText();

            // Check if button text contains our search text (strips HTML/icons).
            if (stripos($value, $buttontext) !== false || stripos(strip_tags($text), $buttontext) !== false) {
                $button->click();
                $this->wait_for_pending_js();
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new ExpectationException(
                'Form button with text "' . $buttontext . '" not found',
                $this->getSession()
            );
        }
    }
}
