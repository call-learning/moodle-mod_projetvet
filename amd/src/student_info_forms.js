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
 * Module for handling thesis and mobility modal forms.
 *
 * @module     mod_projetvet/student_info_forms
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';

/**
 * Initialize the module.
 */
export const init = () => {
    // Handle thesis form button clicks.
    document.addEventListener('click', (e) => {
        const thesisButton = e.target.closest('[data-action="thesis-form"]');
        if (thesisButton) {
            e.preventDefault();
            showThesisForm(thesisButton);
        }

        const mobilityButton = e.target.closest('[data-action="mobility-form"]');
        if (mobilityButton) {
            e.preventDefault();
            showMobilityForm(mobilityButton);
        }
    });
};

/**
 * Show the thesis form modal.
 *
 * @param {HTMLElement} button The button that was clicked
 */
const showThesisForm = (button) => {
    const cmid = button.dataset.cmid;
    const projetvetid = button.dataset.projetvetid;
    const userid = button.dataset.userid;

    const modalForm = new ModalForm({
        formClass: 'mod_projetvet\\form\\thesis_form',
        args: {
            cmid: cmid,
            projetvetid: projetvetid,
            userid: userid,
        },
        returnFocus: button,
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => {
        window.location.reload();
    });

    modalForm.show();
};

/**
 * Show the mobility form modal.
 *
 * @param {HTMLElement} button The button that was clicked
 */
const showMobilityForm = (button) => {
    const cmid = button.dataset.cmid;
    const projetvetid = button.dataset.projetvetid;
    const userid = button.dataset.userid;

    const modalForm = new ModalForm({
        formClass: 'mod_projetvet\\form\\mobility_form',
        args: {
            cmid: cmid,
            projetvetid: projetvetid,
            userid: userid,
        },
        returnFocus: button,
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => {
        window.location.reload();
    });

    modalForm.show();
};
