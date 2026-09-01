<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_projetvet\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\user_preference;

/**
 * Privacy provider tests for Projetvet.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends \advanced_testcase {
    /**
     * The tutor information preference is declared in the privacy metadata.
     *
     * @covers ::get_metadata
     */
    public function test_tutor_info_preference_is_declared(): void {
        $collection = new collection('mod_projetvet');

        provider::get_metadata($collection);

        $preferences = array_filter(
            $collection->get_collection(),
            static fn($item): bool => $item instanceof user_preference
        );

        $this->assertCount(1, $preferences);
        $preference = reset($preferences);
        $this->assertEquals('projetvet_tutor_info', $preference->get_name());
    }

    /**
     * The tutor information preference is exported through the Privacy API.
     *
    * @covers ::export_user_preferences
     */
    public function test_tutor_info_preference_is_exported(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_user_preference('projetvet_tutor_info', 'Private tutor information', $user->id);

        \core_privacy\local\request\writer::reset();
        provider::export_user_preferences($user->id);

        $preferences = \core_privacy\local\request\writer::with_context(
            \context_system::instance()
        )->get_user_preferences('mod_projetvet');

        $this->assertEquals(
            'Private tutor information',
            $preferences->projetvet_tutor_info->value
        );
    }
}
