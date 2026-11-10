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

namespace mod_projetvet\form;

use mod_projetvet\local\persistent\group_member;
use mod_projetvet\local\persistent\projetvet_group;

/**
 * Tests for the group member edit form.
 *
 * @package   mod_projetvet
 * @category  test
 * @copyright 2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(edit_member_form::class)]
final class edit_member_form_test extends \advanced_testcase {
    /** @var array Test data (course, users, module, group). */
    protected array $data = [];

    /**
     * Test setup.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        $this->data['course'] = $generator->create_course();
        $this->data['teacher'] = $generator->create_user([
            'username' => 'teacher1',
            'firstname' => 'Alice',
            'lastname' => 'Tutor',
        ]);
        $this->data['student1'] = $generator->create_user(['username' => 'student1']);
        $this->data['student2'] = $generator->create_user(['username' => 'student2']);

        $generator->enrol_user($this->data['teacher']->id, $this->data['course']->id, 'editingteacher');
        $generator->enrol_user($this->data['student1']->id, $this->data['course']->id, 'student');
        $generator->enrol_user($this->data['student2']->id, $this->data['course']->id, 'student');

        $module = $generator->create_module('projetvet', ['course' => $this->data['course']->id]);
        $this->data['projetvetid'] = $module->id;
        $this->data['cmid'] = $module->cmid;

        // The teacher already has a group with one student.
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $this->data['projetvetid'],
            'ownerid' => $this->data['teacher']->id,
            'name' => 'Existing group',
        ]);
        $group->create();
        $group->add_member($this->data['student1']->id, group_member::TYPE_STUDENT);
        $this->data['groupid'] = $group->get('id');
    }

    /**
     * A no-op manual assignment answers with the component no-change message.
     */
    public function test_noop_assignment_reports_component_nochanges_message(): void {
        $submitdata = edit_member_form::mock_ajax_submit([
            'cmid' => $this->data['cmid'],
            'groupid' => $this->data['groupid'],
            'projetvetid' => $this->data['projetvetid'],
            'teacherid' => $this->data['teacher']->id,
            'studentuserid' => [(string) $this->data['student1']->id],
        ]);

        $form = new edit_member_form(null, null, 'post', '', null, true, $submitdata, true);
        $form->set_data_for_dynamic_submission();
        $result = $form->process_dynamic_submission();

        $this->assertTrue($result['result']);
        $this->assertSame(get_string('nochanges', 'mod_projetvet'), $result['message']);

        // The membership is untouched.
        $members = group_member::get_records([
            'groupid' => $this->data['groupid'],
            'membertype' => group_member::TYPE_STUDENT,
        ]);
        $this->assertCount(1, $members);
        $this->assertEquals((int) $this->data['student1']->id, (int) reset($members)->get('userid'));
    }
}
