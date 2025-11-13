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

namespace mod_projetvet\output;

use mod_projetvet\local\api\entries;
use mod_projetvet\local\persistent\form_entry;
use renderer_base;
use renderable;
use templatable;
use moodle_url;

/**
 * Activity list renderable class.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_list implements renderable, templatable {
    /**
     * @var object $moduleinstance The module instance.
     */
    protected $moduleinstance;

    /**
     * @var object $cm The course module.
     */
    protected $cm;

    /**
     * @var int $studentid The student ID.
     */
    protected $studentid;

    /**
     * @var bool $isteacher Whether the viewer is a teacher.
     */
    protected $isteacher;

    /**
     * @var string $formsetidnumber The form set idnumber.
     */
    protected $formsetidnumber;

    /**
     * Constructor.
     *
     * @param object $moduleinstance The module instance
     * @param object $cm The course module
     * @param int $studentid The student ID
     * @param bool $isteacher Whether the viewer is a teacher
     * @param string $formsetidnumber The form set idnumber (default: 'activities')
     */
    public function __construct($moduleinstance, $cm, $studentid, $isteacher = false, $formsetidnumber = 'activities') {
        $this->moduleinstance = $moduleinstance;
        $this->cm = $cm;
        $this->studentid = $studentid;
        $this->isteacher = $isteacher;
        $this->formsetidnumber = $formsetidnumber;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $USER;

        $data = [
            'isteacher' => $this->isteacher,
            'cmid' => $this->cm->id,
            'projetvetid' => $this->moduleinstance->id,
            'studentid' => $this->studentid,
            'formsetidnumber' => $this->formsetidnumber,
            'addbuttonlabel' => get_string('new' . $this->formsetidnumber, 'mod_projetvet'),
        ];

        // Back link and student name for teachers viewing a student.
        if ($this->isteacher && $this->studentid != $USER->id) {
            $data['showbacklink'] = true;
            $data['backurl'] = (new moodle_url('/mod/projetvet/view.php', ['id' => $this->cm->id]))->out(false);
            $student = \core_user::get_user($this->studentid);
            $data['studentname'] = fullname($student);
        }

        // Get activities and field structure.
        try {
            $listdata = entries::get_entry_list($this->moduleinstance->id, $this->studentid, $this->formsetidnumber);
            $activitylist = $listdata['activities'];
            $listfields = $listdata['listfields'];
            $capabilities = $listdata['capabilities'];
        } catch (\Exception $e) {
            $activitylist = [];
            $listfields = [];
        }

        // Prepare list field headers.
        $data['listfields'] = [];

        foreach ($listfields as $field) {
            $data['listfields'][] = [
                'name' => $field->name,
                'idnumber' => $field->idnumber,
            ];
        }

        $data['hasactivities'] = !empty($activitylist);
        $data['activities'] = [];

        foreach ($activitylist as $activity) {
            $activitydata = [
                'fields' => $activity['fields'], // Dynamic fields based on listorder.
                'entryid' => $activity['id'],
                'entrystatus' => $activity['entrystatus'],
                'statustext' => $capabilities[$activity['entrystatus']] . ' ' . $activity['entrystatus'],
                'statusclass' => 'badge-secondary',
            ];
            if (!empty($activitydata['fields'])) {
                $activitydata['fields'][0]['isfirst'] = true;
            }

            if (!$this->isteacher) {
                // Students can edit and delete their own activities.
                $activitydata['canedit'] = $activity['canedit'];
                $activitydata['candelete'] = $activity['candelete'];
            }
            $activitydata['canview'] = true;

            $data['activities'][] = $activitydata;
        }

        return $data;
    }
}
