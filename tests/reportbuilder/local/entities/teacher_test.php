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

use core_reportbuilder\system_report_factory;
use context_course;
use mod_projetvet\local\persistent\group_member;
use mod_projetvet\local\persistent\projetvet_group;
use mod_projetvet\local\persistent\teacher_rating;
use mod_projetvet\reportbuilder\local\systemreports\assignments_teachers;
use mod_projetvet\reportbuilder\local\systemreports\assignments_teachers_selection;

/**
 * Tests for the teacher capacity entity and the reports building on it.
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(teacher::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(assignments_teachers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(assignments_teachers_selection::class)]
final class teacher_test extends \advanced_testcase {
    /**
     * Test setup.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create test data: a course with three teachers and five students, and a projetvet instance.
     *
     * @return array
     */
    protected function create_test_data(): array {
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        // Distinctive firstnames: the report rows are keyed by the first field of the fullname
        // column, which is the firstname (per the default fullname format).
        $teacher1 = $generator->create_user(['username' => 'teacher1', 'firstname' => 'Alpha', 'lastname' => 'T1']);
        $teacher2 = $generator->create_user(['username' => 'teacher2', 'firstname' => 'Beta', 'lastname' => 'T2']);
        $teacher3 = $generator->create_user(['username' => 'teacher3', 'firstname' => 'Gamma', 'lastname' => 'T3']);
        $students = [];
        for ($i = 1; $i <= 5; $i++) {
            $students['student' . $i] = $generator->create_user(['username' => 'student' . $i]);
        }

        $generator->enrol_user($teacher1->id, $course->id, 'editingteacher');
        $generator->enrol_user($teacher2->id, $course->id, 'editingteacher');
        $generator->enrol_user($teacher3->id, $course->id, 'editingteacher');
        foreach ($students as $student) {
            $generator->enrol_user($student->id, $course->id, 'student');
        }

        $projetvet = $generator->create_module('projetvet', ['course' => $course->id]);

        return [
            'course' => $course,
            'projetvet' => $projetvet,
            'teacher1' => $teacher1,
            'teacher2' => $teacher2,
            'teacher3' => $teacher3,
            'students' => $students,
        ];
    }

    /**
     * Log in a user with the mod/projetvet:admin capability on the given course, as required
     * by the reports' can_view() check.
     *
     * @param int $courseid
     */
    protected function set_admin_user(int $courseid): void {
        $generator = $this->getDataGenerator();

        $user = $generator->create_user();
        $roleid = $generator->create_role();
        $generator->create_role_capability(
            $roleid,
            ['mod/projetvet:admin' => 'allow'],
            context_course::instance($courseid)
        );
        $generator->enrol_user($user->id, $courseid, $roleid);

        self::setUser($user);
    }

    /**
     * Test that the assembled assignments teachers report query is executable without any
     * additional joins and returns one row per teacher with the expected values.
     */
    public function test_assignments_teachers_report_query(): void {
        global $DB;

        $data = $this->create_test_data();
        $cm = get_coursemodule_from_instance('projetvet', $data['projetvet']->id);
        $pv = $data['projetvet']->id;

        $rating = teacher_rating::get_or_create_rating($data['teacher1']->id, $pv);
        $rating->set('rating', teacher_rating::RATING_EXPERT);
        $rating->create();

        $group = new projetvet_group(0, (object)[
            'projetvetid' => $pv,
            'ownerid' => $data['teacher1']->id,
            'name' => 'Test group',
        ]);
        $group->create();
        $group->add_member($data['students']['student1']->id, group_member::TYPE_STUDENT);
        $group->add_member($data['students']['student2']->id, group_member::TYPE_STUDENT);

        $this->set_admin_user($data['course']->id);

        $report = system_report_factory::create(
            assignments_teachers::class,
            context_course::instance($data['course']->id),
            '',
            '',
            0,
            ['cmid' => $cm->id, 'projetvetid' => $pv]
        );

        // The report adds no joins of its own: the capacity data comes from the entity's sub-queries.
        $this->assertSame([], $report->get_joins());

        [$wheresql, $params] = $report->get_base_condition();
        $this->assertStringNotContainsString('teacherdata', $wheresql);

        $columnsbyid = [];
        $fields = [];
        foreach ($report->get_active_columns() as $column) {
            $columnsbyid[$column->get_unique_identifier()] = $column;
            $fields = array_merge($fields, $column->get_fields());
            $params = array_merge($params, $column->get_params());
        }

        $this->assertArrayHasKey('user:fullnamewithpicturelink', $columnsbyid);
        $this->assertArrayHasKey('teacher:rating', $columnsbyid);
        $this->assertArrayHasKey('teacher:target', $columnsbyid);
        $this->assertArrayHasKey('teacher:current', $columnsbyid);
        $this->assertArrayHasKey('teacher:gap', $columnsbyid);

        // Every parameter referenced in the select fields and the where clause must be provided.
        preg_match_all('/:(\w+)/', implode(' ', $fields) . ' ' . $wheresql, $matches);
        $this->assertSame([], array_values(array_diff($matches[1], array_keys($params))));

        $sql = 'SELECT ' . implode(', ', $fields)
            . ' FROM {user} ' . $report->get_main_table_alias()
            . ' WHERE ' . $wheresql;
        $rows = $DB->get_records_sql($sql, $params);

        // All three teachers are listed (rows keyed by the first field, the firstname).
        $this->assertCount(3, $rows);

        $ratingalias = $columnsbyid['teacher:rating']->get_column_alias();
        $targetalias = $columnsbyid['teacher:target']->get_column_alias();
        $currentalias = $columnsbyid['teacher:current']->get_column_alias();
        $gapalias = $columnsbyid['teacher:gap']->get_column_alias();

        // Teacher 1 (Alpha): expert rating, capacity 12, 2 students, gap 10.
        $this->assertArrayHasKey('Alpha', $rows);
        $alpharow = $rows['Alpha'];
        $this->assertSame('expert', $alpharow->{$ratingalias});
        $this->assertEquals(12, $alpharow->{$targetalias});
        $this->assertEquals(2, $alpharow->{$currentalias});
        $this->assertEquals(10, $alpharow->{$gapalias});

        // Teacher 3 (Gamma): no rating record, default average rating, capacity 8, gap 8.
        $this->assertArrayHasKey('Gamma', $rows);
        $gammarow = $rows['Gamma'];
        $this->assertSame('average', $gammarow->{$ratingalias});
        $this->assertEquals(8, $gammarow->{$targetalias});
        $this->assertEquals(0, $gammarow->{$currentalias});
        $this->assertEquals(8, $gammarow->{$gapalias});
    }

    /**
     * Test that the filterwithcapacity report parameter keeps only the teachers who still have
     * available capacity.
     */
    public function test_filterwithcapacity_keeps_only_teachers_with_capacity(): void {
        global $DB;

        $data = $this->create_test_data();
        $cm = get_coursemodule_from_instance('projetvet', $data['projetvet']->id);
        $pv = $data['projetvet']->id;

        // Teacher 1: expert (capacity 12) with 2 students, so a gap of 10.
        $rating = teacher_rating::get_or_create_rating($data['teacher1']->id, $pv);
        $rating->set('rating', teacher_rating::RATING_EXPERT);
        $rating->create();
        $group1 = new projetvet_group(0, (object)[
            'projetvetid' => $pv,
            'ownerid' => $data['teacher1']->id,
            'name' => 'Group 1',
        ]);
        $group1->create();
        $group1->add_member($data['students']['student1']->id, group_member::TYPE_STUDENT);
        $group1->add_member($data['students']['student2']->id, group_member::TYPE_STUDENT);

        // Teacher 2: novice (capacity 5) with 5 students, so a gap of 0.
        $rating = teacher_rating::get_or_create_rating($data['teacher2']->id, $pv);
        $rating->set('rating', teacher_rating::RATING_NOVICE);
        $rating->create();
        $group2 = new projetvet_group(0, (object)[
            'projetvetid' => $pv,
            'ownerid' => $data['teacher2']->id,
            'name' => 'Group 2',
        ]);
        $group2->create();
        foreach (['student1', 'student2', 'student3', 'student4', 'student5'] as $studentname) {
            $group2->add_member($data['students'][$studentname]->id, group_member::TYPE_STUDENT);
        }

        // Teacher 3 has no rating (default capacity 8) and no students, so a gap of 8.

        $this->set_admin_user($data['course']->id);

        $report = system_report_factory::create(
            assignments_teachers::class,
            context_course::instance($data['course']->id),
            '',
            '',
            0,
            ['cmid' => $cm->id, 'projetvetid' => $pv, 'filterwithcapacity' => 1]
        );

        [$wheresql, $params] = $report->get_base_condition();

        $fields = [];
        foreach ($report->get_active_columns() as $column) {
            $fields = array_merge($fields, $column->get_fields());
            $params = array_merge($params, $column->get_params());
        }

        $sql = 'SELECT ' . implode(', ', $fields)
            . ' FROM {user} ' . $report->get_main_table_alias()
            . ' WHERE ' . $wheresql;
        $rows = $DB->get_records_sql($sql, $params);

        // Only the teachers with a positive gap are listed.
        $this->assertEqualsCanonicalizing(['Alpha', 'Gamma'], array_keys($rows));
    }

    /**
     * Test that the report query returns no rows when the course has no teachers.
     */
    public function test_report_with_no_teachers_returns_no_rows(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->create_user(['username' => 'student1']);
        $projetvet = $generator->create_module('projetvet', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('projetvet', $projetvet->id);

        $this->set_admin_user($course->id);

        $report = system_report_factory::create(
            assignments_teachers::class,
            context_course::instance($course->id),
            '',
            '',
            0,
            ['cmid' => $cm->id, 'projetvetid' => $projetvet->id]
        );

        [$wheresql, $params] = $report->get_base_condition();

        $fields = [];
        foreach ($report->get_active_columns() as $column) {
            $fields = array_merge($fields, $column->get_fields());
            $params = array_merge($params, $column->get_params());
        }

        $sql = 'SELECT ' . implode(', ', $fields)
            . ' FROM {user} ' . $report->get_main_table_alias()
            . ' WHERE ' . $wheresql;
        $rows = $DB->get_records_sql($sql, $params);

        $this->assertSame([], $rows);
    }

    /**
     * Test that the selection report query is executable and exposes the radio selector column
     * in addition to the teacher entity columns.
     */
    public function test_assignments_teachers_selection_report_query(): void {
        global $DB;

        $data = $this->create_test_data();
        $cm = get_coursemodule_from_instance('projetvet', $data['projetvet']->id);
        $pv = $data['projetvet']->id;

        $this->set_admin_user($data['course']->id);

        $report = system_report_factory::create(
            assignments_teachers_selection::class,
            context_course::instance($data['course']->id),
            '',
            '',
            0,
            ['cmid' => $cm->id, 'projetvetid' => $pv, 'selectedteacherid' => $data['teacher1']->id]
        );

        $columnsbyid = [];
        $fields = [];
        [$wheresql, $params] = $report->get_base_condition();
        foreach ($report->get_active_columns() as $column) {
            $columnsbyid[$column->get_unique_identifier()] = $column;
            $fields = array_merge($fields, $column->get_fields());
            $params = array_merge($params, $column->get_params());
        }

        // The selection report adds the radio column in addition to the teacher entity columns.
        $this->assertArrayHasKey('user:select', $columnsbyid);
        $this->assertArrayHasKey('teacher:rating', $columnsbyid);
        $this->assertArrayHasKey('teacher:target', $columnsbyid);
        $this->assertArrayHasKey('teacher:current', $columnsbyid);
        $this->assertArrayHasKey('teacher:gap', $columnsbyid);

        $sql = 'SELECT ' . implode(', ', $fields)
            . ' FROM {user} ' . $report->get_main_table_alias()
            . ' WHERE ' . $wheresql;
        $rows = $DB->get_records_sql($sql, $params);

        // All three teachers are selectable (rows keyed by the first field, the user id of the radio column).
        $this->assertCount(3, $rows);
        $this->assertArrayHasKey($data['teacher1']->id, $rows);
        $this->assertArrayHasKey($data['teacher3']->id, $rows);
    }
}
