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

namespace mod_projetvet\external;

use mod_projetvet\external\get_assign_teacher_modal;
use mod_projetvet\local\api\groups;

/**
 * Get assign teacher modal external function test.
 *
 * @package   mod_projetvet
 * @copyright 2026 Laurent David <laurent@call-learning.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_assign_teacher_modal::class)]
final class get_assign_teacher_modal_test extends \advanced_testcase {
    /** @var array Test data (course, users, module). */
    protected array $data = [];

    /**
     * Test setup.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        $this->data['course'] = $generator->create_course();
        $this->data['teacher'] = $generator->create_user(['username' => 'teacher1', 'firstname' => 'Alice', 'lastname' => 'Tutor']);
        $this->data['student1'] = $generator->create_user(['username' => 'student1']);
        $this->data['student2'] = $generator->create_user(['username' => 'student2']);
        $this->data['manager'] = $generator->create_user(['username' => 'manager1']);

        $generator->enrol_user($this->data['teacher']->id, $this->data['course']->id, 'editingteacher');
        $generator->enrol_user($this->data['student1']->id, $this->data['course']->id, 'student');
        $generator->enrol_user($this->data['student2']->id, $this->data['course']->id, 'student');
        $generator->enrol_user($this->data['manager']->id, $this->data['course']->id, 'manager');

        $module = $generator->create_module('projetvet', ['course' => $this->data['course']->id]);
        $this->data['projetvetid'] = $module->id;
        $this->data['cmid'] = $module->cmid;
    }

    /**
     * Test the popup body is returned with no preselection for unassigned students.
     */
    public function test_get_modal_no_preselection(): void {
        $this->setAdminUser();

        $result = get_assign_teacher_modal::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$this->data['student1']->id, $this->data['student2']->id]
        );

        $this->assertIsString($result['html']);
        $this->assertNotEmpty($result['html']);
        $this->assertIsString($result['javascript']);
        // Students are not assigned yet, so no teacher is preselected.
        $this->assertEquals(0, $result['preselectedteacherid']);

        // The report (and its selection radios) is present.
        $this->assertStringContainsString('teacher-select-radio', $result['html']);
        // The teachers report is rendered standalone: the only <form> in the body is the
        // report's own filter form (there is no surrounding modal form it is nested in).
        $this->assertSame(1, substr_count($result['html'], '<form'));
    }

    /**
     * Test the teacher already assigned to the students is preselected.
     */
    public function test_get_modal_preselects_assigned_teacher(): void {
        $this->setAdminUser();

        // Assign both students to the teacher first.
        groups::assign_students_to_teacher(
            $this->data['teacher']->id,
            [$this->data['student1']->id, $this->data['student2']->id],
            $this->data['projetvetid']
        );

        $result = get_assign_teacher_modal::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$this->data['student1']->id, $this->data['student2']->id]
        );

        $this->assertEquals($this->data['teacher']->id, $result['preselectedteacherid']);
        // The radio for the preselected teacher is checked in the markup.
        $this->assertStringContainsString(
            'id="teacher-radio-' . $this->data['teacher']->id . '"',
            $result['html']
        );
    }

    /**
     * Test no teacher is preselected when only some selected students are assigned.
     */
    public function test_get_modal_does_not_preselect_for_mixed_assignment(): void {
        $this->setAdminUser();

        groups::assign_students_to_teacher(
            $this->data['teacher']->id,
            [$this->data['student1']->id],
            $this->data['projetvetid']
        );

        $result = get_assign_teacher_modal::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$this->data['student1']->id, $this->data['student2']->id]
        );

        $this->assertSame(0, $result['preselectedteacherid']);
    }

    /**
     * Test users outside the course cannot be included in the modal.
     */
    public function test_get_modal_requires_eligible_students(): void {
        $this->setAdminUser();
        $outsidestudent = $this->getDataGenerator()->create_user();

        $this->expectException(\invalid_parameter_exception::class);
        get_assign_teacher_modal::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$outsidestudent->id]
        );
    }

    /**
     * Test the course module and projetvet instance must match for the modal.
     */
    public function test_get_modal_requires_matching_instance(): void {
        $this->setAdminUser();
        $othermodule = $this->getDataGenerator()->create_module('projetvet', [
            'course' => $this->data['course']->id,
        ]);

        $this->expectException(\invalid_parameter_exception::class);
        get_assign_teacher_modal::execute(
            $this->data['cmid'],
            $othermodule->id,
            [$this->data['student1']->id]
        );
    }

    /**
     * Test a user without mod/projetvet:admin is rejected.
     */
    public function test_get_modal_requires_capability(): void {
        // A student does not hold mod/projetvet:admin.
        $this->setUser($this->data['student1']);

        try {
            get_assign_teacher_modal::execute(
                $this->data['cmid'],
                $this->data['projetvetid'],
                [$this->data['student1']->id]
            );
            $this->fail('Expected capability exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
    }

    /**
     * Test the manager archetype (holder of mod/projetvet:admin) can fetch the body.
     */
    public function test_get_modal_as_manager(): void {
        $this->setUser($this->data['manager']);

        $result = get_assign_teacher_modal::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$this->data['student1']->id]
        );

        $this->assertNotEmpty($result['html']);
    }
}
