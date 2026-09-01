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

namespace mod_projetvet\local\api;

use mod_projetvet\local\persistent\group_member;
use mod_projetvet\local\persistent\projetvet_group;

/**
 * Groups API for projetvet
 *
 * Provides centralized methods for group and membership operations
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groups {
    /**
     * Get student count for groups where user is PRIMARY tutor only
     *
     * This is used for capacity planning and target calculations.
     * Only counts students in groups the teacher owns.
     *
     * @param int $userid The user ID
     * @param int $projetvetid The projetvet instance ID
     * @return int Student count
     */
    public static function get_primary_student_count(int $userid, int $projetvetid): int {
        // Get groups where this user is the owner (primary tutor).
        $groups = projetvet_group::get_by_owner($userid, $projetvetid);

        if (empty($groups)) {
            return 0;
        }

        $studentcount = 0;

        foreach ($groups as $group) {
            $studentcount += $group->get_student_count();
        }

        return $studentcount;
    }

    /**
     * Get student groups
     *
     * @param int $userid The user ID
     * @param int $projetvetid The projetvet instance ID
     * @return array Array of group_member objects
     */
    public static function get_student_groups(int $userid, int $projetvetid): array {
        return self::get_user_memberships(
            $userid,
            $projetvetid,
            group_member::TYPE_STUDENT
        );
    }

    /**
     * Get all group memberships for a user
     *
     * @param int $userid The user ID
     * @param int|null $projetvetid Optional: filter by projetvet instance
     * @param string|null $membertype Optional: filter by member type
     * @return array Array of group_member objects
     */
    public static function get_user_memberships(int $userid, ?int $projetvetid = null, ?string $membertype = null): array {
        global $DB;

        $sql = "SELECT gm.*
                  FROM {projetvet_group_members} gm";

        if ($projetvetid) {
            $sql .= " JOIN {projetvet_groups} g ON g.id = gm.groupid";
        }

        $sql .= " WHERE gm.userid = :userid";
        $params = ['userid' => $userid];

        if ($projetvetid) {
            $sql .= " AND g.projetvetid = :projetvetid";
            $params['projetvetid'] = $projetvetid;
        }

        if ($membertype) {
            $sql .= " AND gm.membertype = :membertype";
            $params['membertype'] = $membertype;
        }

        $sql .= " ORDER BY gm.timecreated ASC";

        $records = $DB->get_records_sql($sql, $params);

        $memberships = [];
        foreach ($records as $record) {
            $memberships[] = new group_member(0, $record);
        }

        return $memberships;
    }

    /**
     * Get available capacity for a teacher based on their rating and current primary student load
     *
     * @param int $userid The teacher's user ID
     * @param int $projetvetid The projetvet instance ID
     * @return int Available capacity (can be negative if over capacity)
     */
    public static function get_teacher_available_capacity(int $userid, int $projetvetid): int {
        // Get teacher's target capacity based on rating.
        $rating = \mod_projetvet\local\persistent\teacher_rating::get_or_create_rating($userid, $projetvetid);
        $target = $rating->get_capacity();

        // Get current student count (primary groups only).
        $current = self::get_primary_student_count($userid, $projetvetid);

        return $target - $current;
    }

    /**
     * Set teacher rating for a specific user in a projetvet instance
     *
     * @param int $userid The teacher's user ID
     * @param int $projetvetid The projetvet instance ID
     * @param string $newrating The new rating value (expert, average, novice)
     * @return \mod_projetvet\local\persistent\teacher_rating The updated rating persistent
     */
    public static function set_teacher_rating(
        int $userid,
        int $projetvetid,
        string $newrating
    ): \mod_projetvet\local\persistent\teacher_rating {
        // Get or create teacher rating.
        $rating = \mod_projetvet\local\persistent\teacher_rating::get_or_create_rating($userid, $projetvetid);

        // Update rating if changed.
        if ($rating->get('rating') !== $newrating) {
            $rating->set('rating', $newrating);

            // Save to database if it's a new record.
            if (!$rating->get('id')) {
                $rating->create();
            } else {
                $rating->update();
            }
        }

        return $rating;
    }

    /**
     * Get available teachers for selection (excluding current teacher)
     *
     * @param int $cmid Course module ID
     * @param int $excludeteacherid Teacher ID to exclude from list
     * @return array Array of teacher options with 'uniqueid' and 'name' keys
     */
    public static function get_available_teachers(int $cmid, int $excludeteacherid): array {
        $modcontext = \context_module::instance($cmid);
        $coursecontext = $modcontext->get_course_context();

        // Get teachers (users with approve capability).
        $enrolledteachers = get_enrolled_users(
            $coursecontext,
            'mod/projetvet:approve',
            0,
            'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename',
            'u.lastname, u.firstname'
        );

        $teacheroptions = [];
        foreach ($enrolledteachers as $user) {
            // Skip the current teacher.
            if ($user->id == $excludeteacherid) {
                continue;
            }

            $teacheroptions[] = [
                'uniqueid' => $user->id,
                'name' => fullname($user),
            ];
        }

        return $teacheroptions;
    }

    /**
     * Get available students for selection (excluding students already in a group)
     *
     * @param int $cmid Course module ID
     * @param int $projetvetid Projetvet instance ID
     * @param int|null $currentgroupid Optional: current group ID to allow re-selection of existing members
     * @return array Array of student options with 'uniqueid' and 'name' keys
     */
    public static function get_available_students(int $cmid, int $projetvetid, ?int $currentgroupid = null): array {
        // Get all students in the course.
        $allstudents = self::get_all_students($cmid);

        // Get all students already in groups for this projetvet instance.
        $assignedstudents = self::get_all_assigned_students($projetvetid, $currentgroupid);

        // Filter out students already in another group.
        $availablestudents = [];
        foreach ($allstudents as $student) {
            if (!in_array($student['uniqueid'], $assignedstudents)) {
                $availablestudents[] = $student;
            }
        }

        return $availablestudents;
    }

    /**
     * Get all enrolled students in the course (not filtering by assignment status)
     *
     * @param int $cmid Course module ID
     * @return array Array of student options with 'uniqueid' and 'name' keys
     */
    public static function get_all_students(int $cmid): array {
        global $DB;

        $studentsjoin = self::get_all_students_join($cmid, 'u.id');

        if ($studentsjoin->cannotmatchanyrows) {
            return [];
        }

        $users = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                    u.middlename, u.alternatename
              FROM {user} u
              {$studentsjoin->joins}
             WHERE {$studentsjoin->wheres}
             ORDER BY u.lastname, u.firstname",
            $studentsjoin->params
        );

        $studentoptions = [];
        foreach ($users as $user) {
            $studentoptions[] = [
                'uniqueid' => $user->id,
                'name' => fullname($user),
            ];
        }

        return $studentoptions;
    }

    /**
     * Get the SQL join fragment that restricts a query to the enrolled students in the course.
     *
     * A student is an enrolled user who has the submit capability but does not have the
     * approve capability (i.e. is not a teacher). This is the single source of truth used by
     * {@see self::get_all_students()} so the array and join variants never diverge.
     *
     * @param int $cmid Course module ID.
     * @param string $useridcolumn The column reference of the user id in the target query
     *     (e.g. "u.id" or "rbalias0.id").
     * @return \core\dml\sql_join Joins, wheres and params to apply to the target query.
     */
    public static function get_all_students_join(int $cmid, string $useridcolumn): \core\dml\sql_join {
        return self::get_enrolled_capability_join(
            self::get_course_context_from_cmid($cmid),
            'mod/projetvet:submit',
            'mod/projetvet:approve',
            $useridcolumn
        );
    }

    /**
     * Get all teachers in the course with approve capability
     *
     * @param int $cmid Course module ID
     * @return array Array of teacher user IDs
     */
    public static function get_all_teachers(int $cmid): array {
        global $DB;

        $teachersjoin = self::get_all_teachers_join($cmid, 'u.id');

        if ($teachersjoin->cannotmatchanyrows) {
            return [];
        }

        $users = $DB->get_records_sql(
            "SELECT u.id
              FROM {user} u
              {$teachersjoin->joins}
             WHERE {$teachersjoin->wheres}
             ORDER BY u.lastname ASC, u.firstname ASC",
            $teachersjoin->params
        );

        return array_column($users, 'id');
    }

    /**
     * Get the SQL join fragment that restricts a query to the enrolled teachers in the course.
     *
     * A teacher is an enrolled user who has the approve capability. This is the single source
     * of truth used by {@see self::get_all_teachers()} so the array and join variants never
     * diverge.
     *
     * @param int $cmid Course module ID.
     * @param string $useridcolumn The column reference of the user id in the target query
     *     (e.g. "u.id" or "rbalias0.id").
     * @return \core\dml\sql_join Joins, wheres and params to apply to the target query.
     */
    public static function get_all_teachers_join(int $cmid, string $useridcolumn): \core\dml\sql_join {
        return self::get_enrolled_capability_join(
            self::get_course_context_from_cmid($cmid),
            'mod/projetvet:approve',
            null,
            $useridcolumn
        );
    }

    /**
     * Get all student IDs already assigned to groups in this projetvet instance
     *
     * @param int $projetvetid Projetvet instance ID
     * @param int|null $excludegroupid Optional: exclude students from this group ID
     * @return array Array of user IDs
     */
    private static function get_all_assigned_students(int $projetvetid, ?int $excludegroupid = null): array {
        global $DB;

        $sql = "SELECT DISTINCT gm.userid
                  FROM {projetvet_group_members} gm
                  JOIN {projetvet_groups} g ON g.id = gm.groupid
                 WHERE g.projetvetid = :projetvetid
                   AND gm.membertype = :membertype";

        $params = [
            'projetvetid' => $projetvetid,
            'membertype' => group_member::TYPE_STUDENT,
        ];

        if ($excludegroupid) {
            $sql .= " AND gm.groupid != :excludegroupid";
            $params['excludegroupid'] = $excludegroupid;
        }

        $records = $DB->get_records_sql($sql, $params);
        return array_keys($records);
    }

    /**
     * Sync student memberships for a group.
     *
     * Removes current student members not in the provided array, and adds missing ones.
     *
     * @param int $groupid Group ID
     * @param array $studentuserids Student user IDs to keep/add
     * @return int Number of members added
     */
    public static function sync_group_students(int $groupid, array $studentuserids): int {
        $addedcount = 0;
        $group = new projetvet_group($groupid);
        $currentstudents = $group->get_members(group_member::TYPE_STUDENT);

        // Remove students that are not in the provided array.
        foreach ($currentstudents as $currentmember) {
            $userid = $currentmember->get('userid');
            if (!in_array($userid, $studentuserids)) {
                $currentmember->delete();
            }
        }

        // Add students that are missing.
        foreach ($studentuserids as $studentuserid) {
            $existing = group_member::get_membership($groupid, $studentuserid);
            if (!$existing) {
                $member = new group_member(0, (object)[
                    'groupid' => $groupid,
                    'userid' => $studentuserid,
                    'membertype' => group_member::TYPE_STUDENT,
                ]);
                $member->create();
                $addedcount++;
            }
        }

        return $addedcount;
    }

    /**
     * Sync secondary tutor memberships for a group.
     *
     * Removes current secondary tutor members not in the provided array, and adds missing ones.
     *
     * @param int $groupid Group ID
     * @param array $teacheruserids Secondary tutor user IDs to keep/add
     * @return int Number of members added
     */
    public static function sync_group_secondary_tutors(int $groupid, array $teacheruserids): int {
        $addedcount = 0;
        $group = new projetvet_group($groupid);
        $currentsecondaryteachers = $group->get_members(group_member::TYPE_SECONDARY_TUTOR);

        // Remove secondary tutors that are not in the provided array.
        foreach ($currentsecondaryteachers as $currentmember) {
            $userid = $currentmember->get('userid');
            if (!in_array($userid, $teacheruserids)) {
                $currentmember->delete();
            }
        }

        // Add secondary tutors that are missing.
        foreach ($teacheruserids as $teacheruserid) {
            $existing = group_member::get_membership($groupid, $teacheruserid);
            if (!$existing) {
                $member = new group_member(0, (object)[
                    'groupid' => $groupid,
                    'userid' => $teacheruserid,
                    'membertype' => group_member::TYPE_SECONDARY_TUTOR,
                ]);
                $member->create();
                $addedcount++;
            }
        }

        return $addedcount;
    }

    /**
     * Add members to a group
     *
     * Syncs group membership: removes students not in the provided array and adds new ones.
     *
     * @param int $groupid Group ID
     * @param array $teacheruserids Array of teacher user IDs to add as secondary tutors
     * @param array $studentuserids Array of student user IDs to add
     * @return int Number of members added
     */
    public static function add_members(
        int $groupid,
        array $teacheruserids = [],
        array $studentuserids = []
    ): int {
        $addedstudents = self::sync_group_students($groupid, $studentuserids);
        $addedteachers = self::sync_group_secondary_tutors($groupid, $teacheruserids);
        return $addedstudents + $addedteachers;
    }

    /**
     * Get primary tutor for a student
     *
     * Returns the user object of the primary tutor (group owner) for a student,
     * or null if student is not in any group.
     *
     * @param int $studentid The student's user ID
     * @param int $projetvetid The projetvet instance ID
     * @return \stdClass|null User object of primary tutor or null
     */
    public static function get_student_primary_tutor(int $studentid, int $projetvetid): ?\stdClass {
        $memberships = self::get_student_groups($studentid, $projetvetid);

        if (empty($memberships)) {
            return null;
        }

        // Get the first group (students should only be in one group per projetvet).
        $membership = reset($memberships);
        $group = $membership->get_group();

        // Get the owner (primary tutor).
        $ownerid = $group->get('ownerid');

        return \core_user::get_user($ownerid);
    }

    /**
     * Get secondary tutors for a student
     *
     * Returns array of user objects for all secondary tutors in the student's group.
     *
     * @param int $studentid The student's user ID
     * @param int $projetvetid The projetvet instance ID
     * @return array Array of user objects
     */
    public static function get_student_secondary_tutors(int $studentid, int $projetvetid): array {
        $memberships = self::get_student_groups($studentid, $projetvetid);

        if (empty($memberships)) {
            return [];
        }

        // Get the first group (students should only be in one group per projetvet).
        $membership = reset($memberships);
        $group = $membership->get_group();

        // Get all secondary tutors in this group.
        $members = $group->get_members(group_member::TYPE_SECONDARY_TUTOR);

        $tutors = [];
        foreach ($members as $member) {
            $tutors[] = $member->get_user();
        }

        return $tutors;
    }

    /**
     * Assign students to a group, ensuring each student belongs to only one group
     *
     * This method removes students from any existing groups in this projetvet instance
     * before adding them to the target group.
     *
     * @param int $groupid The target group ID
     * @param array $studentids Array of student user IDs to assign
     * @param int $projetvetid The projetvet instance ID
     * @return int Number of students assigned
     */
    public static function assign_students_to_group(int $groupid, array $studentids, int $projetvetid): int {
        $assignedcount = 0;

        foreach ($studentids as $studentid) {
            // Remove student from any existing groups in this projetvet instance.
            $existingmemberships = self::get_user_memberships(
                $studentid,
                $projetvetid,
                group_member::TYPE_STUDENT
            );

            foreach ($existingmemberships as $membership) {
                // Only delete if not already in the target group.
                if ($membership->get('groupid') != $groupid) {
                    $membership->delete();
                }
            }

            // Check if student is already a member of the target group.
            $existing = group_member::get_membership($groupid, $studentid);
            if (!$existing) {
                $member = new group_member(0, (object)[
                    'groupid' => $groupid,
                    'userid' => $studentid,
                    'membertype' => group_member::TYPE_STUDENT,
                ]);
                $member->create();
                $assignedcount++;
            }
        }

        return $assignedcount;
    }

    /**
     * Get all students for a specific tutor
     *
     * Returns array of student user IDs that are in groups where the specified user
     * is either a primary tutor (owner) or secondary tutor.
     *
     * @param int $tutorid The tutor's user ID
     * @param int $projetvetid The projetvet instance ID
     * @return array Array of student user IDs
     */
    public static function get_students_for_tutor(int $tutorid, int $projetvetid): array {
        global $DB;

        // Get all groups where this user is owner (primary tutor).
        $ownedgroups = projetvet_group::get_by_owner($tutorid, $projetvetid);
        $groupids = array_map(function ($group) {
            return $group->get('id');
        }, $ownedgroups);

        // Get all groups where this user is a secondary tutor.
        $secondarymemberships = self::get_user_memberships(
            $tutorid,
            $projetvetid,
            group_member::TYPE_SECONDARY_TUTOR
        );

        foreach ($secondarymemberships as $membership) {
            $groupid = $membership->get('groupid');
            if (!in_array($groupid, $groupids)) {
                $groupids[] = $groupid;
            }
        }

        if (empty($groupids)) {
            return [];
        }

        // Get all students from these groups.
        [$insql, $params] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);
        $params['membertype'] = group_member::TYPE_STUDENT;

        $sql = "SELECT DISTINCT gm.userid
                  FROM {projetvet_group_members} gm
                 WHERE gm.groupid $insql
                   AND gm.membertype = :membertype";

        $records = $DB->get_records_sql($sql, $params);
        return array_keys($records);
    }

    /**
     * Get the course context for a course module ID.
     *
     * @param int $cmid Course module ID
     * @return \context_course
     */
    private static function get_course_context_from_cmid(int $cmid): \context_course {
        return \context_module::instance($cmid)->get_course_context();
    }

    /**
     * Build a SQL join fragment that restricts a query to enrolled users who have a capability
     * and, optionally, do not have another capability.
     *
     * It is built from the core {@see get_enrolled_join()} and {@see get_with_capability_join()}
     * fragments, so it stays consistent with the rest of Moodle. The optional negative capability
     * is expressed as a NOT IN subquery on a separate alias (u2) so its internal role join does not
     * collide with the main query scope.
     *
     * @param \context_course $coursecontext The course context.
     * @param string $positivecapability Capability the user must have.
     * @param string|null $negativecapability Capability the user must not have (optional).
     * @param string $useridcolumn The column reference of the user id in the target query.
     * @return \core\dml\sql_join Joins, wheres and params.
     */
    private static function get_enrolled_capability_join(
        \context_course $coursecontext,
        string $positivecapability,
        ?string $negativecapability = null,
        string $useridcolumn = 'u.id'
    ): \core\dml\sql_join {
        // Enrolled in the course (JOIN to user_enrolments + enrol).
        $enrolledjoin = get_enrolled_join($coursecontext, $useridcolumn);

        // Has the required capability (JOIN to the matching role assignments).
        $positivejoin = get_with_capability_join($coursecontext, $positivecapability, $useridcolumn);

        $joins  = trim(implode("\n", array_filter([$enrolledjoin->joins, $positivejoin->joins])));
        $wheres = implode(' AND ', array_filter([$enrolledjoin->wheres, $positivejoin->wheres]));
        $params = $enrolledjoin->params + $positivejoin->params;
        $cannotmatchanyrows = $enrolledjoin->cannotmatchanyrows || $positivejoin->cannotmatchanyrows;

        // Exclude users who have the negative capability (e.g. teachers) via a NOT IN subquery.
        if ($negativecapability !== null) {
            $negativejoin = get_with_capability_join($coursecontext, $negativecapability, 'u2.id');
            $wheres .= " AND {$useridcolumn} NOT IN (SELECT u2.id FROM {user} u2 "
                . $negativejoin->joins . ' WHERE ' . $negativejoin->wheres . ')';
            $params += $negativejoin->params;
        }

        return new \core\dml\sql_join($joins, $wheres, $params, $cannotmatchanyrows);
    }
}
