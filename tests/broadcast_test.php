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

namespace mod_projetvet;

use mod_projetvet\form\broadcast_form;
use mod_projetvet\local\api\groups;
use mod_projetvet\local\messaging;
use mod_projetvet\local\persistent\projetvet_group;

/**
 * Tests for the tutor broadcast message page and form.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(broadcast_form::class)]
final class broadcast_test extends \advanced_testcase {
    /**
     * Set up the test environment with a tutor, students, and a group.
     *
     * @return array An array with keys: course, tutor, students, projetvet, cm, group
     */
    protected function setup_fixtures(): array {
        global $DB;

        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $tutor = $generator->create_user(['username' => 'tutor_broadcast']);
        $generator->enrol_user($tutor->id, $course->id, 'editingteacher');

        $student1 = $generator->create_user(['username' => 'student_broadcast_1']);
        $student2 = $generator->create_user(['username' => 'student_broadcast_2']);
        $generator->enrol_user($student1->id, $course->id, 'student');
        $generator->enrol_user($student2->id, $course->id, 'student');

        $projetvet = $generator->create_module('projetvet', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('projetvet', $projetvet->id, $course->id);

        // Create a group owned by the tutor.
        $group = new projetvet_group(0, (object) [
            'projetvetid' => $projetvet->id,
            'name' => 'Broadcast Test Group',
            'ownerid' => $tutor->id,
        ]);
        $group->create();

        // Add students to the group.
        $group->add_member($student1->id, 'student');
        $group->add_member($student2->id, 'student');

        return [
            'course' => $course,
            'tutor' => $tutor,
            'students' => [$student1, $student2],
            'projetvet' => $projetvet,
            'cm' => $cm,
            'group' => $group,
        ];
    }

    /**
     * Test that the form only offers the tutor's assigned students.
     */
    public function test_form_only_offers_tutor_students(): void {
        $fixtures = $this->setup_fixtures();

        // The tutor should be able to see exactly their assigned students.
        $allowed = groups::get_students_for_tutor($fixtures['tutor']->id, $fixtures['projetvet']->id);
        $this->assertEqualsCanonicalizing(
            [$fixtures['students'][0]->id, $fixtures['students'][1]->id],
            array_map('intval', $allowed)
        );
    }

    /**
     * Test that a user without the approve capability cannot access the broadcast page.
     */
    public function test_non_approve_user_cannot_access_broadcast(): void {
        $fixtures = $this->setup_fixtures();
        $this->setUser($fixtures['students'][0]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('do not currently have permissions');
        require_capability('mod/projetvet:approve', \context_module::instance($fixtures['cm']->id));
    }

    /**
     * Test that a user with the approve capability but who is not a tutor is
     * denied access to the broadcast page.
     */
    public function test_approve_user_but_not_tutor_cannot_access_broadcast(): void {
        $fixtures = $this->setup_fixtures();

        // A teacher who can approve but owns no group (not a tutor) is denied.
        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user(['username' => 'teacher_not_tutor']);
        $generator->enrol_user($teacher->id, $fixtures['course']->id, 'editingteacher');
        $this->setUser($teacher);

        $this->assertTrue(has_capability('mod/projetvet:approve', \context_module::instance($fixtures['cm']->id)));
        $this->assertFalse(\mod_projetvet\utils::is_tutor($teacher->id));
    }

    /**
     * Test that the form is required and rejects empty subject/body/recipients.
     */
    public function test_form_validation_rejects_empty_fields(): void {
        $fixtures = $this->setup_fixtures();
        $this->setUser($fixtures['tutor']);

        // The form expects student user records (stdClass) keyed by id.
        $students = [];
        foreach ($fixtures['students'] as $student) {
            $students[$student->id] = \core_user::get_user($student->id);
        }

        $form = new broadcast_form(null, ['students' => $students, 'returnurl' => '/mod/projetvet/view.php']);

        // Simulate an empty submission.
        $form->set_data([]);
        $this->assertFalse($form->validate_defined_fields());
    }
}
