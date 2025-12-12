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

namespace mod_projetvet\task;

defined('MOODLE_INTERNAL') || die();

use core\task\adhoc_task;
use mod_projetvet\local\notifications;

/**
 * Adhoc task to notify the next actor when an entry status changes.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_action_required extends adhoc_task {
    /**
     * Execute the task.
     */
    public function execute() {
        $data = $this->get_custom_data();
        if (empty($data) || empty($data->entryid) || empty($data->cmid)) {
            mtrace('mod_projetvet:entry_action_required: missing custom data');
            return;
        }

        $oldstatus = isset($data->oldstatus) ? (int)$data->oldstatus : null;
        $newstatus = isset($data->newstatus) ? (int)$data->newstatus : null;

        try {
            notifications::send_entry_action_required((int)$data->entryid, (int)$data->cmid, $oldstatus, $newstatus);
        } catch (\Throwable $e) {
            // Let the task fail so it is logged by Moodle.
            throw $e;
        }
    }
}
