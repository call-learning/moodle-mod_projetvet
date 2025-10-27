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

use core_form\dynamic_form;
use moodle_url;
use context;
use context_module;
use mod_projetvet\local\persistent\thesis;

/**
 * Thesis subject form.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class thesis_form extends dynamic_form {

    /**
     * Form definition
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'projetvetid');
        $mform->setType('projetvetid', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('textarea', 'thesis', get_string('thesissubject_field', 'mod_projetvet'), ['rows' => 4]);
        $mform->setType('thesis', PARAM_TEXT);
        $mform->addRule('thesis', null, 'required', null, 'client');

        $mform->addElement('date_selector', 'depositdate', get_string('thesisdepositdate', 'mod_projetvet'), ['optional' => true]);

        $mform->addElement('text', 'director', get_string('thesisdirector', 'mod_projetvet'));
        $mform->setType('director', PARAM_TEXT);
    }

    /**
     * Check if current user has access to this form.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        $cmid = $this->optional_param('cmid', 0, PARAM_INT);
        $context = context_module::instance($cmid);
        require_capability('mod/projetvet:submit', $context);
    }

    /**
     * Process the form submission
     *
     * @return array
     */
    public function process_dynamic_submission() {
        global $USER;

        $data = $this->get_data();
        $projetvetid = $data->projetvetid;
        $userid = $data->userid;

        // Check if thesis record exists.
        $existing = thesis::get_record(['projetvetid' => $projetvetid, 'userid' => $userid]);

        $otherdata = [
            'depositdate' => $data->depositdate ?? null,
            'director' => $data->director ?? '',
        ];

        if ($existing) {
            $existing->set('thesis', $data->thesis);
            $existing->set_other_data_array($otherdata);
            $existing->update();
        } else {
            $thesis = new thesis(0, (object)[
                'projetvetid' => $projetvetid,
                'userid' => $userid,
                'thesis' => $data->thesis,
            ]);
            $thesis->set_other_data_array($otherdata);
            $thesis->create();
        }

        return ['success' => true];
    }

    /**
     * Load in existing data as form defaults
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $projetvetid = $this->optional_param('projetvetid', 0, PARAM_INT);
        $userid = $this->optional_param('userid', 0, PARAM_INT);
        $cmid = $this->optional_param('cmid', 0, PARAM_INT);

        $data = [
            'projetvetid' => $projetvetid,
            'userid' => $userid,
            'cmid' => $cmid,
        ];

        $existing = thesis::get_record(['projetvetid' => $projetvetid, 'userid' => $userid]);
        if ($existing) {
            $data['thesis'] = $existing->get('thesis');
            $otherdata = $existing->get_other_data_array();
            $data['depositdate'] = $otherdata['depositdate'] ?? null;
            $data['director'] = $otherdata['director'] ?? '';
        }

        $this->set_data($data);
    }

    /**
     * Returns form context
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $cmid = $this->optional_param('cmid', 0, PARAM_INT);
        return context_module::instance($cmid);
    }

    /**
     * Returns url to set that can be used for form submission
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $cmid = $this->optional_param('cmid', 0, PARAM_INT);
        return new moodle_url('/mod/projetvet/view.php', ['id' => $cmid]);
    }
}
