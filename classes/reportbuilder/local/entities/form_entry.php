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

declare(strict_types=1);

namespace mod_projetvet\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{date, number, select, text};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};
use lang_string;
use stdClass;

/**
 * Form entry entity
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_entry extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'projetvet_form_entry',
            'projetvet_form_set',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entries', 'mod_projetvet');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $entryalias = $this->get_table_alias('projetvet_form_entry');
        $formsetalias = $this->get_table_alias('projetvet_form_set');

        $columns = [];

        // Entry ID column.
        $columns[] = (new column(
            'id',
            new lang_string('entryid', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$entryalias}.id")
            ->set_is_sortable(true);

        // Entry status column.
        $columns[] = (new column(
            'entrystatus',
            new lang_string('status', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$entryalias}.entrystatus")
            ->add_field("{$entryalias}.formsetid", 'formsetid_for_status')
            ->set_is_sortable(true)
            ->add_callback(static function ($value, $row): string {
                global $DB;

                // Get the formset idnumber to determine status messages.
                $formset = $DB->get_record('projetvet_form_set', ['id' => $row->formsetid_for_status], 'idnumber');
                if (!$formset) {
                    return '';
                }

                // Use the API method to get the status message.
                return \mod_projetvet\local\api\entries::get_status_message($value, $formset->idnumber);
            });

        // Time created column.
        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'core'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$entryalias}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Time modified column.
        $columns[] = (new column(
            'timemodified',
            new lang_string('field_timemodified', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$entryalias}.timemodified")
            ->set_is_sortable(true)
            ->add_callback(static function ($value): string {
                return userdate($value, get_string('strftimedatetimeshort', 'langconfig'));
            });

        return $columns;
    }

    /**
     * Returns list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $entryalias = $this->get_table_alias('projetvet_form_entry');

        $filters = [];

        // Entry status filter.
        $filters[] = (new filter(
            select::class,
            'entrystatus',
            new lang_string('status', 'mod_projetvet'),
            $this->get_entity_name(),
            "{$entryalias}.entrystatus"
        ))
            ->add_joins($this->get_joins())
            ->set_options(\mod_projetvet\local\api\entries::get_all_status_messages());

        // Time created filter.
        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'core'),
            $this->get_entity_name(),
            "{$entryalias}.timecreated"
        ))
            ->add_joins($this->get_joins());

        // Time modified filter.
        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('field_timemodified', 'mod_projetvet'),
            $this->get_entity_name(),
            "{$entryalias}.timemodified"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
