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

use mod_projetvet\external\assign_teacher;
use mod_projetvet\local\api\groups;
use mod_projetvet\local\persistent\group_member;
use mod_projetvet\local\persistent\projetvet_group;

/**
 * Assign teacher external function test.
 *
 * @package   mod_projetvet
 * @copyright 2026 Laurent David <laurent@call-learning.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(assign_teacher::class)]
final class assign_teacher_test extends \advanced_testcase {
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
     * Test successful assignment of students to a teacher.
     */
    public function test_assign_teacher_success(): void {
        $this->setAdminUser();

        $result = assign_teacher::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$this->data['student1']->id, $this->data['student2']->id],
            $this->data['teacher']->id
        );

        $this->assertTrue($result['result']);
        $this->assertStringContainsString('2', $result['message']);

        // The students are now members of the teacher's group.
        $tutor = groups::get_student_primary_tutor($this->data['student1']->id, $this->data['projetvetid']);
        $this->assertNotNull($tutor);
        $this->assertEquals($this->data['teacher']->id, $tutor->id);
        $tutor = groups::get_student_primary_tutor($this->data['student2']->id, $this->data['projetvetid']);
        $this->assertNotNull($tutor);
        $this->assertEquals($this->data['teacher']->id, $tutor->id);
    }

    /**
     * Test the manager archetype (holder of mod/projetvet:admin) can assign.
     */
    public function test_assign_teacher_as_manager(): void {
        $this->setUser($this->data['manager']);

        $result = assign_teacher::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$this->data['student1']->id],
            $this->data['teacher']->id
        );

        $this->assertTrue($result['result']);
    }

    /**
     * Test a user without mod/projetvet:admin is rejected.
     */
    public function test_assign_teacher_requires_capability(): void {
        // The editingteacher role does not hold mod/projetvet:admin (only manager does).
        $this->setUser($this->data['teacher']);

        try {
            assign_teacher::execute(
                $this->data['cmid'],
                $this->data['projetvetid'],
                [$this->data['student1']->id],
                $this->data['teacher']->id
            );
            $this->fail('Expected capability exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
    }

    /**
     * Test an empty student list is rejected.
     */
    public function test_assign_teacher_requires_studentids(): void {
        $this->setAdminUser();

        try {
            assign_teacher::execute($this->data['cmid'], $this->data['projetvetid'], [], $this->data['teacher']->id);
            $this->fail('Expected nouseridprovided exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nouseridprovided', $e->errorcode);
        }
    }

    /**
     * Test a missing teacher is rejected.
     */
    public function test_assign_teacher_requires_teacherid(): void {
        $this->setAdminUser();

        try {
            assign_teacher::execute(
                $this->data['cmid'],
                $this->data['projetvetid'],
                [$this->data['student1']->id],
                0
            );
            $this->fail('Expected assignselectteacher exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('assignselectteacher', $e->errorcode);
        }
    }

    /**
     * Test a teacher outside the course cannot be selected through the web service.
     */
    public function test_assign_teacher_requires_eligible_teacher(): void {
        $this->setAdminUser();
        $outsideteacher = $this->getDataGenerator()->create_user();

        $this->expectException(\invalid_parameter_exception::class);
        assign_teacher::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$this->data['student1']->id],
            $outsideteacher->id
        );
    }

    /**
     * Test a user outside the course cannot be selected as a student through the web service.
     */
    public function test_assign_teacher_requires_eligible_students(): void {
        $this->setAdminUser();
        $outsidestudent = $this->getDataGenerator()->create_user();

        $this->expectException(\invalid_parameter_exception::class);
        assign_teacher::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$outsidestudent->id],
            $this->data['teacher']->id
        );
    }

    /**
     * Test the course module and projetvet instance must match.
     */
    public function test_assign_teacher_requires_matching_instance(): void {
        $this->setAdminUser();
        $othermodule = $this->getDataGenerator()->create_module('projetvet', [
            'course' => $this->data['course']->id,
        ]);

        $this->expectException(\invalid_parameter_exception::class);
        assign_teacher::execute(
            $this->data['cmid'],
            $othermodule->id,
            [$this->data['student1']->id],
            $this->data['teacher']->id
        );
    }

    /**
     * Test the assigned students land in a single group owned by the teacher.
     */
    public function test_assign_teacher_creates_single_group(): void {
        $this->setAdminUser();

        assign_teacher::execute(
            $this->data['cmid'],
            $this->data['projetvetid'],
            [$this->data['student1']->id, $this->data['student2']->id],
            $this->data['teacher']->id
        );

        $ownedgroups = projetvet_group::get_by_owner($this->data['teacher']->id, $this->data['projetvetid']);
        $this->assertCount(1, $ownedgroups);
        $group = reset($ownedgroups);
        $memberids = array_map(
            fn($m) => (int) $m->get('userid'),
            array_values($group->get_members(group_member::TYPE_STUDENT))
        );
        $this->assertContains((int) $this->data['student1']->id, $memberids);
        $this->assertContains((int) $this->data['student2']->id, $memberids);
    }
}
