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

namespace mod_projetvet\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use lang_string;
use mod_projetvet\local\persistent\group_member;
use mod_projetvet\local\persistent\teacher_rating;

/**
 * Teacher capacity entity for projetvet reports
 *
 * Provides the rating, target capacity, current student count and gap columns shared by the
 * assignments teachers reports. The values are computed with correlated sub-queries against the
 * rating and group tables, scoped to the projetvet instance the report was created for, so no
 * temporary table or cache is required.
 *
 * The report must assign its own user table alias to this entity before calling
 * {@see base::add_entity()} so that the sub-queries correlate against the report's main user table.
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher extends base {

    /**
     * Constructor.
     *
     * @param int $projetvetid The projetvet instance id the teacher data is scoped to.
     */
    public function __construct(private readonly int $projetvetid) {
    }

    /**
     * Initialise the entity.
     *
     * @return self
     */
    public function initialise(): self {
        foreach ($this->get_available_columns() as $column) {
            $this->add_column($column);
        }

        foreach ($this->get_available_filters() as $filter) {
            $this->add_filter($filter);
        }

        foreach ($this->get_available_conditions() as $condition) {
            $this->add_condition($condition);
        }

        return $this;
    }

    /**
     * The list of table names this entity uses.
     *
     * The user table is declared because the sub-queries correlate against the report's main
     * user table alias, assigned by the report via {@see base::set_table_alias()}.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'user',
            teacher_rating::TABLE,
            group_member::TABLE,
            'projetvet_groups',
        ];
    }

    /**
     * The default machine-readable name for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('teacher', 'mod_projetvet');
    }

    /**
     * Returns the columns this entity makes available to reports.
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        $columns = [];

        // Rating column. The raw rating value is localized at render time so sorting happens on the
        // stable value, not on a translated string. Teachers without an explicit rating default to average.
        $ratingpvparam = database::generate_param_name();
        $columns[] = (new column(
            'rating',
            new lang_string('teacher_rating', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_field($this->get_rating_sql($ratingpvparam), 'rating', [$ratingpvparam => $this->projetvetid])
            ->set_is_sortable(true)
            ->add_callback(static function (?string $value): string {
                return teacher_rating::get_rating_string_for((string)$value);
            });

        // Target capacity column, derived from the rating.
        $targetpvparam = database::generate_param_name();
        $columns[] = (new column(
            'target',
            new lang_string('teacher_target', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->get_capacity_sql($targetpvparam), 'target', [$targetpvparam => $this->projetvetid])
            ->set_is_sortable(true);

        // Current student count column.
        $currentpvparam = database::generate_param_name();
        $currentmtparam = database::generate_param_name();
        $columns[] = (new column(
            'current',
            new lang_string('teacher_current', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field(
                $this->get_current_students_sql($currentpvparam, $currentmtparam),
                'current',
                [
                    $currentpvparam => $this->projetvetid,
                    $currentmtparam => group_member::TYPE_STUDENT,
                ]
            )
            ->set_is_sortable(true);

        // Gap column = target capacity - current student count. The capacity part and the
        // current student count part each need their own instance id parameter, because a
        // named query parameter may only occur once in a query.
        $capacitypvparam = database::generate_param_name();
        $groupspvparam = database::generate_param_name();
        $mtparam = database::generate_param_name();
        $columns[] = (new column(
            'gap',
            new lang_string('teacher_gap', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field(
                $this->get_gap_sql($capacitypvparam, $groupspvparam, $mtparam),
                'gap',
                [
                    $capacitypvparam => $this->projetvetid,
                    $groupspvparam => $this->projetvetid,
                    $mtparam => group_member::TYPE_STUDENT,
                ]
            )
            ->set_is_sortable(true);

        return $columns;
    }

    /**
     * Returns the SQL expression resolving the rating of the teacher referenced by the report's
     * main user table, defaulting to the average rating when no explicit rating exists.
     *
     * @param string $pvparam Reportbuilder parameter name holding the projetvet instance id,
     *     see {@see database::generate_param_name()}.
     * @return string
     */
    public function get_rating_sql(string $pvparam): string {
        $useralias = $this->get_table_alias('user');

        return "COALESCE((SELECT rating FROM {" . teacher_rating::TABLE . "} "
            . "WHERE userid = {$useralias}.id AND projetvetid = :{$pvparam}), '"
            . teacher_rating::RATING_AVERAGE . "')";
    }

    /**
     * Returns the SQL expression resolving the target capacity of the teacher referenced by the
     * report's main user table. The mapping from rating to capacity is generated from the
     * teacher_rating capacity constants so it stays in sync with {@see teacher_rating::get_capacity()}.
     *
     * @param string $pvparam Reportbuilder parameter name holding the projetvet instance id,
     *     see {@see database::generate_param_name()}.
     * @return string
     */
    public function get_capacity_sql(string $pvparam): string {
        $capacities = [
            teacher_rating::RATING_EXPERT => teacher_rating::CAPACITY_EXPERT,
            teacher_rating::RATING_AVERAGE => teacher_rating::CAPACITY_AVERAGE,
            teacher_rating::RATING_NOVICE => teacher_rating::CAPACITY_NOVICE,
        ];

        $whens = [];
        foreach ($capacities as $rating => $capacity) {
            $whens[] = "WHEN '{$rating}' THEN {$capacity}";
        }

        return 'CASE ' . $this->get_rating_sql($pvparam) . ' ' . implode(' ', $whens)
            . ' ELSE ' . teacher_rating::CAPACITY_AVERAGE . ' END';
    }

    /**
     * Returns the SQL expression counting the students assigned to the teacher referenced by the
     * report's main user table in the given projetvet instance.
     *
     * @param string $pvparam Reportbuilder parameter name holding the projetvet instance id,
     *     see {@see database::generate_param_name()}.
     * @param string $mtparam Reportbuilder parameter name holding the member type,
     *     see {@see database::generate_param_name()}.
     * @return string
     */
    public function get_current_students_sql(string $pvparam, string $mtparam): string {
        $useralias = $this->get_table_alias('user');

        return "(SELECT COUNT(1) FROM {" . group_member::TABLE . "} gm "
            . "JOIN {projetvet_groups} g ON g.id = gm.groupid "
            . "WHERE g.ownerid = {$useralias}.id AND g.projetvetid = :{$pvparam} "
            . "AND gm.membertype = :{$mtparam})";
    }

    /**
     * Returns the SQL expression for the remaining capacity of the teacher referenced by the
     * report's main user table: target capacity minus current student count.
     *
     * The capacity part and the current student count part each receive their own instance id
     * parameter, because a named query parameter may only occur once in a query even though
     * both hold the same value.
     *
     * @param string $capacitypvparam Reportbuilder parameter name holding the projetvet instance id
     *     used by the capacity part, see {@see database::generate_param_name()}.
     * @param string $groupspvparam Reportbuilder parameter name holding the projetvet instance id
     *     used by the current student count part, see {@see database::generate_param_name()}.
     * @param string $mtparam Reportbuilder parameter name holding the member type,
     *     see {@see database::generate_param_name()}.
     * @return string
     */
    public function get_gap_sql(string $capacitypvparam, string $groupspvparam, string $mtparam): string {
        return $this->get_capacity_sql($capacitypvparam) . ' - ' . $this->get_current_students_sql($groupspvparam, $mtparam);
    }
}
