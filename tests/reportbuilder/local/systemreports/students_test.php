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

namespace mod_projetvet\reportbuilder\local\systemreports;

use core_reportbuilder\local\entities\user;
use mod_projetvet\local\api\groups;

/**
 * Tests for the students system report.
 *
 * @package   mod_projetvet
 * @copyright 2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(students::class)]
final class students_test extends \advanced_testcase {
    /**
     * Test setup.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create test data: a course with one teacher and three students, and a projetvet instance.
     *
     * @return array
     */
    protected function create_test_data(): array {
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $teacher = $generator->create_user(['username' => 'teacher1']);
        $student1 = $generator->create_user(['username' => 'student1']);
        $student2 = $generator->create_user(['username' => 'student2']);
        $student3 = $generator->create_user(['username' => 'student3']);

        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($student1->id, $course->id, 'student');
        $generator->enrol_user($student2->id, $course->id, 'student');
        $generator->enrol_user($student3->id, $course->id, 'student');

        $projetvet = $generator->create_module('projetvet', ['course' => $course->id]);

        return [
            'course' => $course,
            'projetvet' => $projetvet,
            'teacher' => $teacher,
            'student1' => $student1,
            'student2' => $student2,
            'student3' => $student3,
        ];
    }

    /**
     * Test that add_student_scope_join() renames the core join parameters to reportbuilder names,
     * applies a coherent join + where fragment against the report's real user alias and returns
     * only the enrolled students (excluding teachers) when executed.
     *
     * This is the code path that previously failed when core join parameters (ej1_*, eu1_*)
     * reached reportbuilder's validate_params().
     */
    public function test_add_student_scope_join(): void {
        global $DB;

        $data = $this->create_test_data();
        $cm = get_coursemodule_from_instance('projetvet', $data['projetvet']->id);

        // Use the same user alias the report generates for its main {user} table.
        $entityuser = new user();
        $alias = $entityuser->get_table_alias('user');

        $join = groups::get_all_students_join($cm->id, "{$alias}.id");

        // The private helper is only reachable via initialise() (which needs the web context), so it is tested directly.
        $report = (new \ReflectionClass(students::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(students::class, 'add_student_scope_join');
        $method->invoke($report, $join);

        $joinsql = implode("\n", $report->get_joins());
        [$wheresql, $params] = $report->get_base_condition();

        // Reportbuilder only accepts rbparam-style names; this is what the original error rejected.
        foreach (array_keys($params) as $paramname) {
            $this->assertMatchesRegularExpression('/^rbparam\d+$/', $paramname);
        }

        // Every parameter referenced in the fragment must be provided (otherwise the query breaks).
        preg_match_all('/:(\w+)/', $joinsql . ' ' . $wheresql, $matches);
        $this->assertSame([], array_values(array_diff($matches[1], array_keys($params))));

        // The fragment must be scoped to the report's real user alias, not a bare "u".
        $this->assertStringContainsString("{$alias}.id", $joinsql);
        $this->assertStringContainsString("{$alias}.id", $wheresql);

        // The assembled query must be executable and return only the enrolled students.
        $rows = $DB->get_records_sql(
            "SELECT {$alias}.id
               FROM {user} {$alias}
               {$joinsql}
              WHERE {$wheresql}",
            $params
        );

        $joinedids = array_keys($rows);
        sort($joinedids);

        $expected = [$data['student1']->id, $data['student2']->id, $data['student3']->id];
        sort($expected);
        $this->assertEquals($expected, $joinedids);
        $this->assertNotContains($data['teacher']->id, $joinedids);
    }
}
