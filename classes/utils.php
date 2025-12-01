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

/**
 * Utils class
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {
    /**
     * Get tutor for a student based on shared group membership.
     *
     * Finds users with the configured tutor role who share a group with the student.
     *
     * @param int $studentid The student user ID
     * @param int $cmid The course module ID
     * @return \stdClass|null The tutor user object or null if not found
     */
    public static function get_student_tutor(int $studentid, int $cmid): ?\stdClass {
        global $DB;

        // Get the tutor role shortname from settings.
        $tutoroleshortname = get_config('mod_projetvet', 'tutor_role') ?: 'teacher';

        // Get the course module and context.
        $cm = get_coursemodule_from_id('projetvet', $cmid);
        if (!$cm) {
            return null;
        }

        $context = \context_module::instance($cm->id);

        // Get the role ID.
        $role = $DB->get_record('role', ['shortname' => $tutoroleshortname]);
        if (!$role) {
            return null;
        }

        // Get all groups the student is a member of in this course.
        $studentgroups = groups_get_user_groups($cm->course, $studentid);
        if (empty($studentgroups[0])) {
            return null;
        }

        $groupids = $studentgroups[0];

        // Get all users with the tutor role in this context.
        $tutors = get_role_users($role->id, $context, true);

        if (empty($tutors)) {
            return null;
        }

        // Find a tutor who shares a group with the student.
        foreach ($tutors as $tutor) {
            $tutorgroups = groups_get_user_groups($cm->course, $tutor->id);
            if (!empty($tutorgroups[0])) {
                // Check if tutor and student share any groups.
                $sharedgroups = array_intersect($groupids, $tutorgroups[0]);
                if (!empty($sharedgroups)) {
                    return $tutor;
                }
            }
        }

        return null;
    }

    /**
     * Process a filter and return its value.
     *
     * Filters are dynamic data placeholders that can be used in HTML fields.
     *
     * @param string $filter The filter name (e.g., 'gettutor')
     * @param int $studentid The student ID
     * @param int $cmid The course module ID
     * @return string The filter value
     */
    public static function get_filter(string $filter, int $studentid, int $cmid): string {
        switch ($filter) {
            case 'gettutor':
                $tutor = self::get_student_tutor($studentid, $cmid);
                if ($tutor) {
                    return fullname($tutor);
                }
                return get_string('no_tutor_found', 'mod_projetvet');

            case 'getsuggestedectsfile':
                return self::get_ects_guide_url();

            default:
                return '';
        }
    }

    /**
     * Get users with a specific role in the module context.
     *
     * @param string $roleshortname The role shortname
     * @param int $cmid The course module ID
     * @return array Array of user objects
     */
    public static function get_users_with_role(string $roleshortname, int $cmid): array {
        global $DB;

        // Get the role ID.
        $role = $DB->get_record('role', ['shortname' => $roleshortname]);
        if (!$role) {
            return [];
        }

        // Get the course module and context.
        $cm = get_coursemodule_from_id('projetvet', $cmid);
        if (!$cm) {
            return [];
        }

        $context = \context_module::instance($cm->id);

        // Get all users with this role in this context.
        $users = get_role_users($role->id, $context, true);

        return $users ?: [];
    }

    /**
     * Get the URL for the ECTS attribution guide PDF file.
     *
     * @return string The URL to the PDF file or empty string if not set
     */
    public static function get_ects_guide_url(): string {
        global $CFG;

        $syscontext = \context_system::instance();
        $component = 'mod_projetvet';
        $filearea = 'ectsguide';
        $itemid = 0;

        // Check if a file has been uploaded.
        $fs = get_file_storage();
        $files = $fs->get_area_files($syscontext->id, $component, $filearea, $itemid, 'filename', false);

        if (empty($files)) {
            return '#';
        }

        // Get the first (and should be only) file.
        $file = reset($files);

        $url = \moodle_url::make_pluginfile_url(
            $syscontext->id,
            $component,
            $filearea,
            $itemid,
            $file->get_filepath(),
            $file->get_filename()
        );

        return $url->out();
    }
}
