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

/**
 * Tests for the teacher_rating persistent.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(teacher_rating::class)]
final class teacher_rating_test extends \advanced_testcase {
    /**
     * Legacy rating codes keep their student capacity limits.
     */
    public function test_legacy_rating_codes_keep_their_capacity(): void {
        $this->assertSame(12, teacher_rating::get_capacity_for_rating(teacher_rating::RATING_EXPERT));
        $this->assertSame(8, teacher_rating::get_capacity_for_rating(teacher_rating::RATING_AVERAGE));
        $this->assertSame(5, teacher_rating::get_capacity_for_rating(teacher_rating::RATING_NOVICE));

        // Unknown values fall back to the default capacity instead of failing.
        $this->assertSame(teacher_rating::CAPACITY_AVERAGE, teacher_rating::get_capacity_for_rating('unknown'));
    }

    /**
     * Legacy rating codes are displayed as the Capacity A/B/C labels.
     */
    public function test_legacy_rating_codes_display_as_capacity_labels(): void {
        $this->assertSame('Capacity A', teacher_rating::get_rating_string_for(teacher_rating::RATING_EXPERT));
        $this->assertSame('Capacity B', teacher_rating::get_rating_string_for(teacher_rating::RATING_AVERAGE));
        $this->assertSame('Capacity C', teacher_rating::get_rating_string_for(teacher_rating::RATING_NOVICE));

        // Unknown values render nothing rather than an invalid label.
        $this->assertSame('', teacher_rating::get_rating_string_for('unknown'));
    }

    /**
     * Existing stored rating records are rendered with the capacity labels without migration.
     */
    public function test_stored_rating_record_renders_capacity_label(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user();
        $course = $generator->create_course();
        $projetvet = $generator->create_module('projetvet', ['course' => $course->id]);

        $rows = [
            [teacher_rating::RATING_EXPERT, 'Capacity A', 12],
            [teacher_rating::RATING_AVERAGE, 'Capacity B', 8],
            [teacher_rating::RATING_NOVICE, 'Capacity C', 5],
        ];

        foreach ($rows as [$ratingcode, $label, $capacity]) {
            $stored = teacher_rating::get_or_create_rating($teacher->id, $projetvet->id);
            $stored->set('rating', $ratingcode);
            $stored->save();

            $record = teacher_rating::get_user_rating($teacher->id, $projetvet->id);
            $this->assertSame($ratingcode, $record->get('rating'));
            $this->assertSame($label, $record->get_rating_string());
            $this->assertSame($capacity, $record->get_capacity());
        }
    }
}
