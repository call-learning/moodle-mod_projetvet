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

namespace mod_projetvet\local\importer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/csvlib.class.php');
use csv_import_reader;
use moodle_exception;
use mod_projetvet\local\api\groups;
use mod_projetvet\local\persistent\group_member;
use mod_projetvet\local\persistent\projetvet_group;
use mod_projetvet\local\persistent\teacher_rating;

/**
 * Group importer for CSV uploads
 *
 * @package   mod_projetvet
 * @copyright 2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_importer {
    /**
     * @var int $courseid
     */
    protected $courseid;

    /**
     * @var int $cmid
     */
    protected $cmid;

    /**
     * @var int $projetvetid
     */
    protected $projetvetid;

    /**
     * Constructor
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $projetvetid
     */
    public function __construct(int $courseid, int $cmid, int $projetvetid) {
        $this->courseid = $courseid;
        $this->cmid = $cmid;
        $this->projetvetid = $projetvetid;
    }

    /**
     * Import groups from a CSV file.
     *
     * Expected CSV format:
     * teacher,teacherrating,student1,student2,student3,...
     *
     * A "secondaryteacher" column of files made before the substitute tutors is ignored.
     *
     * The file is imported fully or not at all: the whole content is read and validated
     * against the A1 assignment rule before any data is written, and the writes are applied
     * inside a database transaction so that any failure rolls back the entire import.
     *
     * @param string $filepath
     * @param string $delimiter
     * @param string $encoding
     * @throws moodle_exception
     */
    public function import(string $filepath, string $delimiter = 'comma', string $encoding = 'UTF-8') {
        global $DB;

        $iid = csv_import_reader::get_new_iid('projetvet_groups');
        $csvreader = new csv_import_reader($iid, 'projetvet_groups');

        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new moodle_exception('cannotreadfile', 'mod_projetvet', '', $filepath);
        }

        try {
            $csvreader->load_csv_content($content, $encoding, $delimiter);
            $columns = $csvreader->get_columns();

            if (empty($columns) || !in_array('teacher', $columns)) {
                throw new moodle_exception('invalidcsvstructure', 'mod_projetvet');
            }

            // Find column indices.
            $teacheridx = array_search('teacher', $columns);
            $ratingidx = array_search('teacherrating', $columns);

            // Find student columns (student1, student2, etc.).
            $studentindices = [];
            foreach ($columns as $idx => $colname) {
                if (preg_match('/^student(\d+)$/', $colname)) {
                    $studentindices[] = $idx;
                }
            }

            // Read every data row of the file.
            $rows = [];
            $csvreader->init();
            while ($row = $csvreader->next()) {
                if (!empty($row[$teacheridx])) {
                    $rows[] = $row;
                }
            }

            // Validation pass (read-only): resolve every referenced user and check the
            // primary-assignment eligibility for each tutor's complete batch, against
            // the current database state. If anything fails, abort before writing a
            // single row so the file is imported fully or not at all.
            $this->validate_rows($rows, $teacheridx, $ratingidx, $studentindices);

            // Write pass, inside a transaction so that any failure rolls back the whole import.
            $transaction = $DB->start_delegated_transaction();
            try {
                foreach ($rows as $row) {
                    $this->apply_row($row, $teacheridx, $ratingidx, $studentindices);
                }
                $transaction->allow_commit();
            } catch (\exception $e) {
                $transaction->rollback($e);
                throw $e;
            }
        } finally {
            $csvreader->cleanup();
            $csvreader->close();
        }
    }

    /**
     * Read-only validation of the import rows.
     *
     * Resolves every referenced user and checks the complete primary-assignment eligibility rule
     * for each tutor. Throws before any data is written when a tutor cannot receive the complete
     * batch of students from the file.
     *
     * @param array $rows The CSV data rows.
     * @param int $teacheridx Index of the teacher column.
     * @param int|false $ratingidx Index of the teacherrating column.
     * @param int[] $studentindices Indices of the student columns.
     * @throws moodle_exception
     */
    protected function validate_rows(
        array $rows,
        int $teacheridx,
        int|false $ratingidx,
        array $studentindices
    ): void {
        global $DB;

        $studentidsbyteacher = [];

        foreach ($rows as $row) {
            $teacherusername = trim($row[$teacheridx]);
            $teacher = $DB->get_record('user', ['username' => $teacherusername], '*', IGNORE_MISSING);

            if (!$teacher) {
                debugging("Teacher not found: $teacherusername", DEBUG_DEVELOPER);
                continue;
            }

            foreach ($studentindices as $idx) {
                if (empty($row[$idx])) {
                    continue;
                }

                $studentusername = trim($row[$idx]);
                $student = $DB->get_record('user', ['username' => $studentusername], '*', IGNORE_MISSING);

                if (!$student) {
                    debugging("Student not found: $studentusername", DEBUG_DEVELOPER);
                    continue;
                }

                $studentidsbyteacher[$teacher->id][] = (int) $student->id;
            }
        }

        foreach ($studentidsbyteacher as $teacherid => $studentids) {
            $eligibility = groups::check_student_assignment_eligibility(
                (int) $teacherid,
                $this->projetvetid,
                $studentids
            );
            if (!$eligibility['allowed']) {
                throw new moodle_exception('assignmentblocked', 'mod_projetvet', '', $eligibility['reason']);
            }
        }
    }

    /**
     * Apply a single import row to the database.
     *
     * Called from the write pass, inside the import transaction.
     *
     * @param array $row The CSV data row.
     * @param int $teacheridx Index of the teacher column.
     * @param int|false $ratingidx Index of the teacherrating column.
     * @param int[] $studentindices Indices of the student columns.
     */
    protected function apply_row(
        array $row,
        int $teacheridx,
        int|false $ratingidx,
        array $studentindices
    ): void {
        global $DB;

        $teacherusername = trim($row[$teacheridx]);
        $teacher = $DB->get_record('user', ['username' => $teacherusername], '*', IGNORE_MISSING);

        if (!$teacher) {
            return;
        }

        // Set teacher rating if provided.
        if ($ratingidx !== false && !empty($row[$ratingidx])) {
            $ratingvalue = trim($row[$ratingidx]);
            // Validate rating value.
            $validratings = [
                teacher_rating::RATING_EXPERT,
                teacher_rating::RATING_AVERAGE,
                teacher_rating::RATING_NOVICE,
            ];
            if (in_array($ratingvalue, $validratings)) {
                groups::set_teacher_rating($teacher->id, $this->projetvetid, $ratingvalue);
            }
        }

        // Get or create group for this teacher.
        $existinggroups = projetvet_group::get_by_owner($teacher->id, $this->projetvetid);
        if (!empty($existinggroups)) {
            $group = reset($existinggroups);
        } else {
            // Create new group.
            $groupname = fullname($teacher);
            $group = new projetvet_group(0, (object)[
                'name' => $groupname,
                'ownerid' => $teacher->id,
                'projetvetid' => $this->projetvetid,
            ]);
            $group->create();
        }

        // Add students and remove any stale assignment from another group. A student must have
        // only one primary group within a ProjetVet instance.
        $studentids = [];
        foreach ($studentindices as $idx) {
            if (empty($row[$idx])) {
                continue;
            }

            $studentusername = trim($row[$idx]);
            $student = $DB->get_record('user', ['username' => $studentusername], '*', IGNORE_MISSING);

            if (!$student) {
                continue;
            }

            $studentids[] = (int) $student->id;
        }

        if (!empty($studentids)) {
            groups::assign_students_to_group($group->get('id'), $studentids, $this->projetvetid);
        }
    }

    /**
     * Add or update a group member
     *
     * @param int $groupid
     * @param int $userid
     * @param string $membertype
     * @return void
     */
    protected function add_or_update_member(int $groupid, int $userid, string $membertype): void {
        // Check if member already exists in this group.
        $existing = group_member::get_records([
            'groupid' => $groupid,
            'userid' => $userid,
        ]);

        if (!empty($existing)) {
            // Update existing member if type changed.
            $member = reset($existing);
            if ($member->get('membertype') !== $membertype) {
                $member->set('membertype', $membertype);
                $member->update();
            }
        } else {
            // Create new member.
            $member = new group_member(0, (object)[
                'groupid' => $groupid,
                'userid' => $userid,
                'membertype' => $membertype,
            ]);
            $member->create();
        }
    }
}
