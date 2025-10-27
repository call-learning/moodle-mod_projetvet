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
 * Tag select form element helpers.
 *
 * @module     mod_projetvet/tagselect
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';

class TagSelect {

    /**
     * Constructor.
     *
     * @param {string} elementId The ID of the select element
     * @param {number} maxTags Maximum number of tags (0 = unlimited)
     */
    constructor(elementId, maxTags) {
        this.elementId = elementId;
        this.maxTags = maxTags || 0;
        this.selectElement = document.getElementById(elementId);
        this.wrapper = document.querySelector(`[data-element-id="${elementId}"]`);

        if (!this.selectElement || !this.wrapper) {
            return;
        }

        this.popup = this.wrapper.querySelector('[data-region="tagselect-popup"]');
        this.searchInput = this.popup.querySelector('[data-region="tag-search"]');
        this.selectedTagsDisplay = this.wrapper.querySelector('[data-region="selected-tags-display"]');
        this.selectedTagsPopup = this.popup.querySelector('[data-region="selected-tags-popup"]');
        this.tagCount = this.wrapper.querySelector('[data-region="tag-count"]');
        this.warningsRegion = this.popup.querySelector('[data-region="warnings"]');

        this.addEventListeners();
    }

    /**
     * Add event listeners.
     */
    addEventListeners() {
        // Open popup.
        this.wrapper.querySelector('[data-action="open-tagselect"]')?.addEventListener('click', () => {
            this.openPopup();
        });

        // Close popup.
        this.popup.querySelectorAll('[data-action="close-tagselect"]').forEach(btn => {
            btn.addEventListener('click', () => {
                this.closePopup();
            });
        });

        // Save tags.
        this.popup.querySelector('[data-action="save-tags"]')?.addEventListener('click', () => {
            this.saveTags();
        });

        // Select tag from list.
        this.popup.querySelectorAll('[data-action="select-tag"]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const tagId = link.dataset.tagId;
                const tagName = link.dataset.tagName;
                this.addTag(tagId, tagName);
            });
        });

        // Remove tag (display).
        this.wrapper.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('[data-action="remove-tag"]');
            if (removeBtn) {
                e.preventDefault();
                const tagId = removeBtn.dataset.tagId;
                this.removeTag(tagId);
            }
        });

        // Remove tag (popup).
        this.popup.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('[data-action="remove-tag-popup"]');
            if (removeBtn) {
                e.preventDefault();
                const tagId = removeBtn.dataset.tagId;
                this.removeTag(tagId);
            }
        });

        // Search/filter.
        this.searchInput?.addEventListener('input', () => {
            this.filterTags();
        });

        // Toggle all.
        this.popup.querySelector('[data-action="toggle-all"]')?.addEventListener('click', () => {
            this.toggleAll();
        });
    }

    /**
     * Open the popup.
     */
    openPopup() {
        this.popup.classList.remove('d-none');
        this.searchInput?.focus();
    }

    /**
     * Close the popup.
     */
    closePopup() {
        this.popup.classList.add('d-none');
        this.searchInput.value = '';
        this.filterTags();
    }

    /**
     * Save tags and close popup.
     */
    saveTags() {
        this.updateDisplay();
        this.closePopup();
    }

    /**
     * Add a tag.
     *
     * @param {string} tagId
     * @param {string} tagName
     */
    addTag(tagId, tagName) {
        // Check if already selected.
        const option = this.selectElement.querySelector(`option[value="${tagId}"]`);
        if (option && option.selected) {
            return;
        }

        // Check max tags limit.
        const selectedCount = this.selectElement.querySelectorAll('option:checked').length;
        if (this.maxTags > 0 && selectedCount >= this.maxTags) {
            this.updateWarnings(true);
            return;
        }

        // Select the option.
        if (option) {
            option.selected = true;
        }

        // Add to popup display.
        this.addTagBadge(tagId, tagName, this.selectedTagsPopup);

        // Update count and warnings.
        this.updateCount();
        this.updateWarnings(false);
    }

    /**
     * Remove a tag.
     *
     * @param {string} tagId
     */
    removeTag(tagId) {
        const option = this.selectElement.querySelector(`option[value="${tagId}"]`);
        if (option) {
            option.selected = false;
        }

        // Remove from popup display.
        const popupBadge = this.selectedTagsPopup.querySelector(`[data-tag-id="${tagId}"]`);
        if (popupBadge) {
            popupBadge.remove();
        }

        // Remove from main display.
        const displayBadges = this.selectedTagsDisplay.querySelectorAll(`[data-action="remove-tag"]`);
        displayBadges.forEach(btn => {
            if (btn.dataset.tagId === tagId) {
                btn.closest('.badge').remove();
            }
        });

        // Update count and warnings.
        this.updateCount();
        this.updateWarnings(false);
    }

    /**
     * Add a tag badge to a container.
     *
     * @param {string} tagId
     * @param {string} tagName
     * @param {HTMLElement} container
     */
    async addTagBadge(tagId, tagName, container) {
        // Check if already exists.
        if (container.querySelector(`[data-tag-id="${tagId}"]`)) {
            return;
        }

        const action = container === this.selectedTagsPopup ? 'remove-tag-popup' : 'remove-tag';
        const context = {
            tagid: tagId,
            tagname: tagName,
            action: action
        };

        const {html, js} = await Templates.renderForPromise('mod_projetvet/tagselect_badge', context);
        Templates.appendNodeContents(container, html, js);
    }

    /**
     * Update the main display.
     */
    async updateDisplay() {
        this.selectedTagsDisplay.innerHTML = '';
        const selectedOptions = this.selectElement.querySelectorAll('option:checked');

        for (const option of selectedOptions) {
            const context = {
                tagid: option.value,
                tagname: option.textContent,
                action: 'remove-tag'
            };
            const {html, js} = await Templates.renderForPromise('mod_projetvet/tagselect_badge', context);
            Templates.appendNodeContents(this.selectedTagsDisplay, html, js);
        }
    }

    /**
     * Update the tag count.
     */
    updateCount() {
        const count = this.selectElement.querySelectorAll('option:checked').length;
        if (this.tagCount) {
            this.tagCount.textContent = count;
        }
    }

    /**
     * Update warnings display.
     *
     * @param {boolean} showMaxWarning
     */
    updateWarnings(showMaxWarning) {
        if (!this.warningsRegion) {
            return;
        }

        if (showMaxWarning && this.maxTags > 0) {
            this.warningsRegion.textContent = `Maximum of ${this.maxTags} selections allowed.`;
            this.warningsRegion.classList.remove('d-none');
        } else {
            this.warningsRegion.textContent = '';
            this.warningsRegion.classList.add('d-none');
        }
    }

    /**
     * Filter tags based on search input.
     */
    filterTags() {
        const searchTerm = this.searchInput.value.toLowerCase();
        const tagList = this.popup.querySelector('[data-region="tag-list"]');
        const items = tagList.querySelectorAll('[data-action="select-tag"]');

        items.forEach(item => {
            const tagName = item.dataset.tagName.toLowerCase();
            if (tagName.includes(searchTerm)) {
                item.classList.remove('d-none');
                this.highlightMatch(item, searchTerm);
            } else {
                item.classList.add('d-none');
            }
        });
    }

    /**
     * Highlight matching text.
     *
     * @param {HTMLElement} element
     * @param {string} searchTerm
     */
    highlightMatch(element, searchTerm) {
        const tagName = element.dataset.tagName;
        if (!searchTerm) {
            element.innerHTML = tagName;
            return;
        }

        const index = tagName.toLowerCase().indexOf(searchTerm);
        if (index === -1) {
            element.innerHTML = tagName;
            return;
        }

        const before = tagName.slice(0, index);
        const match = tagName.slice(index, index + searchTerm.length);
        const after = tagName.slice(index + searchTerm.length);
        element.innerHTML = `${before}<strong>${match}</strong>${after}`;
    }

    /**
     * Toggle all visible tags.
     */
    toggleAll() {
        const tagList = this.popup.querySelector('[data-region="tag-list"]');
        const visibleItems = tagList.querySelectorAll('[data-action="select-tag"]:not(.d-none)');

        visibleItems.forEach(item => {
            const tagId = item.dataset.tagId;
            const tagName = item.dataset.tagName;
            const option = this.selectElement.querySelector(`option[value="${tagId}"]`);

            if (option && !option.selected) {
                this.addTag(tagId, tagName);
            }
        });
    }
}

const init = (elementId, maxTags) => {
    new TagSelect(elementId, maxTags);
};

export default {
    init: init
};
