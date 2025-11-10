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
 * Activity entry form modal
 *
 * @module     mod_projetvet/projetvet_form
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Repository from 'mod_projetvet/repository';

export const init = () => {

    const submitEventHandler = () => {
        window.location.reload();
    };

    // Handle create/edit activity button clicks.
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-action="activity-entry-form"]')) {
            return;
        }
        const button = event.target.closest('[data-action="activity-entry-form"]');
        event.preventDefault();

        const titleString = button.dataset.entryid ? 'editactivity' : 'newactivity';

        const modalForm = new ModalForm({
            moduleName: 'core/modal',
            modalConfig: {
                title: getString(titleString, 'mod_projetvet'),
            },
            formClass: '\\mod_projetvet\\form\\projetvet_form',
            args: {
                ...button.dataset,
            },
        });

        // Intercept form submission to show dialog only if switch is not checked.

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, submitEventHandler);
        modalForm.show();
    });

    // Handle form button clicks for save/submit actions.
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.projetvet-form-button')) {
            return;
        }
        const button = event.target.closest('.projetvet-form-button');
        event.preventDefault();

        const entrystatus = button.dataset.entrystatus;
        const actionType = button.dataset.actionType;
        const form = button.closest('form');

        // Handle email action.
        if (actionType === 'email' && form) {
            const studentEmailInput = form.querySelector('input[name="studentemail"]');
            if (studentEmailInput && studentEmailInput.value) {
                const email = studentEmailInput.value;
                const activityTitleInput = form.querySelector('input[name="activity_title"]');
                const activityTitle = activityTitleInput ? activityTitleInput.value : 'votre activité';
                const subject = encodeURIComponent('Discussion concernant: ' + activityTitle);
                const body = encodeURIComponent('Bonjour,\n\nJe souhaiterais discuter avec vous concernant votre activité.\n\n');
                window.location.href = `mailto:${email}?subject=${subject}&body=${body}`;
            }
            return;
        }

        if (form) {
            // Add a hidden field to indicate button submission.
            let buttonField = form.querySelector('input[name="button_entrystatus"]');
            if (!buttonField) {
                buttonField = document.createElement('input');
                buttonField.type = 'hidden';
                buttonField.name = 'button_entrystatus';
                form.appendChild(buttonField);
            }
            buttonField.value = entrystatus;

            // Submit the form.
            form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
        }
    });

    // Handle delete activity button clicks.
    document.addEventListener('click', async(event) => {
        if (!event.target.closest('[data-action="delete-activity"]')) {
            return;
        }
        event.preventDefault();

        const button = event.target.closest('[data-action="delete-activity"]');
        const entryid = button.dataset.entryid;

        if (!entryid) {
            return;
        }

        const confirmString = await getString('confirmdeleteactivity', 'mod_projetvet');

        Notification.confirm(
            getString('confirm'),
            confirmString,
            getString('delete'),
            getString('cancel'),
            async() => {
                try {
                    await Repository.deleteEntry({entryid: parseInt(entryid)});
                    window.location.reload();
                } catch (error) {
                    Notification.exception(error);
                }
            }
        );
    });
};
