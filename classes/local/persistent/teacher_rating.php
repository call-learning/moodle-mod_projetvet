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

namespace mod_projetvet\local\persistent;

use core\persistent;
use lang_string;

/**
 * Teacher rating persistent
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_rating extends persistent {
    /**
     * Current table
     */
    const TABLE = 'projetvet_teacher_rating';

    /** Rating: expert teacher */
    const RATING_EXPERT = 'expert';

    /** Rating: average teacher */
    const RATING_AVERAGE = 'average';

    /** Rating: novice teacher */
    const RATING_NOVICE = 'novice';

    /** Capacity for expert teachers */
    const CAPACITY_EXPERT = 12;

    /** Capacity for average teachers */
    const CAPACITY_AVERAGE = 8;

    /** Capacity for novice teachers */
    const CAPACITY_NOVICE = 5;

    /** A1 student acceptance: yes */
    const ACCEPTS_A1_YES = 1;

    /** A1 student acceptance: no */
    const ACCEPTS_A1_NO = 0;

    /** Tutor is active and open to new assignments. */
    const STATUS_ACTIVE_OPEN = 0;

    /** Tutor is active but closed to new assignments. */
    const STATUS_ACTIVE_CLOSED = 1;

    /** Tutor is temporarily unavailable. */
    const STATUS_TEMPORARILY_UNAVAILABLE = 2;

    /** Tutor is inactive. */
    const STATUS_INACTIVE = 3;

    /**
     * Return the custom definition of the properties of this model.
     *
     * @return array Where keys are the property names.
     */
    protected static function define_properties() {
        return [
            'userid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'userid'),
            ],
            'projetvetid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'projetvetid'),
            ],
            'rating' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_TEXT,
                'default' => self::RATING_AVERAGE,
                'message' => new lang_string('invaliddata', 'projetvet', 'rating'),
            ],
            'status' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'default' => self::STATUS_ACTIVE_OPEN,
                'message' => new lang_string('invaliddata', 'projetvet', 'status'),
            ],
            'acceptsa1' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'default' => self::ACCEPTS_A1_YES,
                'message' => new lang_string('invaliddata', 'projetvet', 'acceptsa1'),
            ],
        ];
    }

    /**
     * Validate rating
     *
     * @param string $value
     * @return true|lang_string
     */
    protected function validate_rating($value) {
        $validratings = [
            self::RATING_EXPERT,
            self::RATING_AVERAGE,
            self::RATING_NOVICE,
        ];

        if (!in_array($value, $validratings)) {
            return new lang_string('invalidrating', 'projetvet');
        }

        return true;
    }

    /**
     * Validate tutor availability status.
     *
     * @param int $value
     * @return true|lang_string
     */
    protected function validate_status($value) {
        $validstatuses = [
            self::STATUS_ACTIVE_OPEN,
            self::STATUS_ACTIVE_CLOSED,
            self::STATUS_TEMPORARILY_UNAVAILABLE,
            self::STATUS_INACTIVE,
        ];

        if (!in_array((int) $value, $validstatuses, true)) {
            return new lang_string('invaliddata', 'projetvet', 'status');
        }

        return true;
    }

    /**
     * Get the tutor availability status.
     *
     * @return int
     */
    public function get_availability_status(): int {
        return (int) ($this->get('status') ?? self::STATUS_ACTIVE_OPEN);
    }

    /**
     * Whether the tutor can receive new assignments.
     *
     * @return bool
     */
    public function is_open_for_new_assignments(): bool {
        return $this->get_availability_status() === self::STATUS_ACTIVE_OPEN;
    }

    /**
     * Get the localized status string for a raw status value.
     *
     * @param int $status
     * @return string
     */
    public static function get_status_string_for(int $status): string {
        $statusstrings = [
            self::STATUS_ACTIVE_OPEN => 'teacher_status_active_open',
            self::STATUS_ACTIVE_CLOSED => 'teacher_status_active_closed',
            self::STATUS_TEMPORARILY_UNAVAILABLE => 'teacher_status_temporarily_unavailable',
            self::STATUS_INACTIVE => 'teacher_status_inactive',
        ];

        if (!array_key_exists($status, $statusstrings)) {
            return '';
        }

        return get_string($statusstrings[$status], 'mod_projetvet');
    }

    /**
     * Get the localized short status string for a raw status value.
     *
     * @param int $status
     * @return string
     */
    public static function get_status_short_string_for(int $status): string {
        $statusstrings = [
            self::STATUS_ACTIVE_OPEN => 'teacher_status_active_open_short',
            self::STATUS_ACTIVE_CLOSED => 'teacher_status_active_closed_short',
            self::STATUS_TEMPORARILY_UNAVAILABLE => 'teacher_status_temporarily_unavailable_short',
            self::STATUS_INACTIVE => 'teacher_status_inactive_short',
        ];

        if (!array_key_exists($status, $statusstrings)) {
            return '';
        }

        return get_string($statusstrings[$status], 'mod_projetvet');
    }

    /**
     * Get the Bootstrap badge class for a raw status value.
     *
     * @param int $status
     * @return string
     */
    public static function get_status_badge_class_for(int $status): string {
        return [
            self::STATUS_ACTIVE_OPEN => 'bg-success',
            self::STATUS_ACTIVE_CLOSED => 'bg-info',
            self::STATUS_TEMPORARILY_UNAVAILABLE => 'bg-warning text-dark',
            self::STATUS_INACTIVE => 'bg-danger',
        ][$status] ?? '';
    }

    /**
     * Validate A1 student acceptance
     *
     * @param mixed $value
     * @return true|lang_string
     */
    protected function validate_acceptsa1($value) {
        $validvalues = [
            self::ACCEPTS_A1_YES,
            self::ACCEPTS_A1_NO,
        ];

        if (!in_array((int) $value, $validvalues, true)) {
            return new lang_string('invaliddata', 'projetvet', 'acceptsa1');
        }

        return true;
    }

    /**
     * Whether this tutor accepts new A1 students
     *
     * @return bool
     */
    public function accepts_a1(): bool {
        return (int) $this->get('acceptsa1') === self::ACCEPTS_A1_YES;
    }

    /**
     * Get the A1 student acceptance value for a tutor, defaulting to "yes" when
     * no rating record exists.
     *
     * @param int $userid The tutor's user ID
     * @param int $projetvetid The projetvet instance ID
     * @return int One of ACCEPTS_A1_YES or ACCEPTS_A1_NO
     */
    public static function get_a1_acceptance_for(int $userid, int $projetvetid): int {
        $rating = self::get_user_rating($userid, $projetvetid);

        return $rating === null ? self::ACCEPTS_A1_YES : (int) $rating->get('acceptsa1');
    }

    /**
     * Get the target capacity based on rating
     *
     * @return int
     */
    public function get_capacity(): int {
        $capacities = [
            self::RATING_EXPERT => self::CAPACITY_EXPERT,
            self::RATING_AVERAGE => self::CAPACITY_AVERAGE,
            self::RATING_NOVICE => self::CAPACITY_NOVICE,
        ];

        return $capacities[$this->get('rating')] ?? self::CAPACITY_AVERAGE;
    }

    /**
     * Get the localized rating string
     *
     * @return string
     */
    public function get_rating_string(): string {
        return get_string('rating_' . $this->get('rating'), 'mod_projetvet');
    }

    /**
     * Check if this is an expert rating
     *
     * @return bool
     */
    public function is_expert(): bool {
        return $this->get('rating') === self::RATING_EXPERT;
    }

    /**
     * Check if this is an average rating
     *
     * @return bool
     */
    public function is_average(): bool {
        return $this->get('rating') === self::RATING_AVERAGE;
    }

    /**
     * Check if this is a novice rating
     *
     * @return bool
     */
    public function is_novice(): bool {
        return $this->get('rating') === self::RATING_NOVICE;
    }

    /**
     * Get rating for a specific user in a projetvet instance
     *
     * @param int $userid
     * @param int $projetvetid
     * @return teacher_rating|null
     */
    public static function get_user_rating(int $userid, int $projetvetid): ?teacher_rating {
        $records = self::get_records(['userid' => $userid, 'projetvetid' => $projetvetid]);
        return empty($records) ? null : reset($records);
    }

    /**
     * Get or create rating for a user (returns average if not found)
     *
     * @param int $userid
     * @param int $projetvetid
     * @return teacher_rating
     */
    public static function get_or_create_rating(int $userid, int $projetvetid): teacher_rating {
        $rating = self::get_user_rating($userid, $projetvetid);

        if (!$rating) {
            $rating = new self(0, (object)[
                'userid' => $userid,
                'projetvetid' => $projetvetid,
                'rating' => self::RATING_AVERAGE,
                'status' => self::STATUS_ACTIVE_OPEN,
            ]);
        }

        return $rating;
    }

    /**
     * Get all ratings for a projetvet instance
     *
     * @param int $projetvetid
     * @return array Array of teacher_rating objects indexed by userid
     */
    public static function get_all_ratings(int $projetvetid): array {
        $records = self::get_records(['projetvetid' => $projetvetid]);
        $ratings = [];

        foreach ($records as $record) {
            $ratings[$record->get('userid')] = $record;
        }

        return $ratings;
    }

    /**
     * Get a tutor's availability status, defaulting to active and open when no
     * settings record exists.
     *
     * @param int $userid
     * @param int $projetvetid
     * @return int
     */
    public static function get_status_for(int $userid, int $projetvetid): int {
        $rating = self::get_user_rating($userid, $projetvetid);

        return $rating === null ? self::STATUS_ACTIVE_OPEN : $rating->get_availability_status();
    }

    /**
     * Get the localized string for a raw rating value
     *
     * An empty string is returned for unknown values so callers rendering report
     * data can safely pass through values they did not compute themselves.
     *
     * @param string $rating
     * @return string
     */
    public static function get_rating_string_for(string $rating): string {
        $validratings = [
            self::RATING_EXPERT,
            self::RATING_AVERAGE,
            self::RATING_NOVICE,
        ];

        if (!in_array($rating, $validratings, true)) {
            return '';
        }

        return get_string('rating_' . $rating, 'mod_projetvet');
    }

    /**
     * Get capacity for a specific rating value
     *
     * @param string $rating
     * @return int
     */
    public static function get_capacity_for_rating(string $rating): int {
        $capacities = [
            self::RATING_EXPERT => self::CAPACITY_EXPERT,
            self::RATING_AVERAGE => self::CAPACITY_AVERAGE,
            self::RATING_NOVICE => self::CAPACITY_NOVICE,
        ];

        return $capacities[$rating] ?? self::CAPACITY_AVERAGE;
    }
}
