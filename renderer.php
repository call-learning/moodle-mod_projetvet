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
 * Renderer for mod_projetvet
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_projetvet\local\api\activities;

/**
 * Renderer class for mod_projetvet
 */
class mod_projetvet_renderer extends plugin_renderer_base {
    /**
     * Render the activity list
     *
     * @param stdClass $moduleinstance The projetvet instance
     * @param stdClass $cm The course module
     * @param context_module $context The context
     * @return string HTML to output
     */
    public function render_activity_list($moduleinstance, $cm, $context) {
        global $USER;

        $output = '';

        // Add "New Activity" button.
        $output .= html_writer::start_div('mb-3');
        $output .= html_writer::tag('button',
            get_string('newactivity', 'mod_projetvet'),
            [
                'class' => 'btn btn-primary',
                'data-action' => 'activity-entry-form',
                'data-cmid' => $cm->id,
                'data-projetvetid' => $moduleinstance->id,
                'data-studentid' => $USER->id,
            ]
        );
        $output .= html_writer::end_div();

        // Get the activity list.
        try {
            $activitylist = activities::get_activity_list($moduleinstance->id, $USER->id);
        } catch (Exception $e) {
            // If no activities yet or error, show empty list.
            $activitylist = [];
        }

        if (empty($activitylist)) {
            $output .= html_writer::tag('p', get_string('noactivities', 'mod_projetvet'), ['class' => 'alert alert-info']);
            return $output;
        }

        // Create table.
        $table = new html_table();
        $table->attributes['class'] = 'table table-striped';
        $table->head = [
            get_string('activitytitle', 'mod_projetvet'),
            get_string('year', 'mod_projetvet'),
            get_string('category', 'mod_projetvet'),
            get_string('completed', 'mod_projetvet'),
            get_string('actions', 'mod_projetvet'),
        ];

        foreach ($activitylist as $activity) {
            $row = [];

            // Title.
            $row[] = $activity['title'];

            // Year.
            $row[] = $activity['year'];

            // Category.
            $row[] = $activity['category'];

            // Completed.
            $completedicon = $activity['completed'] ? '✓' : '';
            $row[] = $completedicon;

            // Actions dropdown.
            $actions = '';
            if ($activity['canedit']) {
                $actions .= html_writer::tag('button',
                    get_string('edit'),
                    [
                        'class' => 'btn btn-sm btn-secondary',
                        'data-action' => 'activity-entry-form',
                        'data-cmid' => $cm->id,
                        'data-projetvetid' => $moduleinstance->id,
                        'data-studentid' => $USER->id,
                        'data-entryid' => $activity['id'],
                    ]
                );
                $actions .= ' ';
            }
            if ($activity['candelete']) {
                $actions .= html_writer::tag('button',
                    get_string('delete'),
                    [
                        'class' => 'btn btn-sm btn-danger',
                        'data-action' => 'delete-activity',
                        'data-entryid' => $activity['id'],
                    ]
                );
            }
            $row[] = $actions;

            $table->data[] = $row;
        }

        $output .= html_writer::table($table);

        return $output;
    }
}
