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

use mod_projetvet\local\persistent\thesis;
use mod_projetvet\local\persistent\mobility;
use renderer_base;
use renderable;
use templatable;

/**
 * Student info renderable class.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_info implements renderable, templatable {
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
     * Constructor.
     *
     * @param object $moduleinstance The module instance
     * @param object $cm The course module
     * @param int $studentid The student ID
     */
    public function __construct($moduleinstance, $cm, $studentid) {
        $this->moduleinstance = $moduleinstance;
        $this->cm = $cm;
        $this->studentid = $studentid;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $thesisrecord = thesis::get_record(['projetvetid' => $this->moduleinstance->id, 'userid' => $this->studentid]);
        $mobilityrecord = mobility::get_record(['projetvetid' => $this->moduleinstance->id, 'userid' => $this->studentid]);

        // Get chart data using the chart_data class.
        $totalects = chart_data::get_total_ects($this->moduleinstance->id, $this->studentid);
        $targetects = get_config('mod_projetvet', 'target_ects') ?: 20;

        $ectsbyrank = chart_data::get_ects_by_rank($this->moduleinstance->id, $this->studentid);
        $targetranka = get_config('mod_projetvet', 'target_rank_a_percentage') ?: 75;
        $targetrankb = get_config('mod_projetvet', 'target_rank_b_percentage') ?: 25;

        $completedinterviews = chart_data::get_completed_interviews($this->moduleinstance->id, $this->studentid);
        $targetinterviews = get_config('mod_projetvet', 'target_interviews') ?: 20;

        // Get the student's tutor.
        $tutor = \mod_projetvet\utils::get_student_tutor($this->studentid, $this->cm->id);
        $tutorname = $tutor ? fullname($tutor) : get_string('notutorassigned', 'mod_projetvet');

        $data = [
            'infotable' => [
                'rows' => [
                    [
                        'label' => get_string('promoyear', 'mod_projetvet'),
                        'value' => $this->moduleinstance->promo ?? '-',
                    ],
                    [
                        'label' => get_string('yearincourse', 'mod_projetvet'),
                        'value' => $this->moduleinstance->currentyear ?? '-',
                    ],
                    [
                        'label' => get_string('tutor', 'mod_projetvet'),
                        'value' => $tutorname,
                        'haslink' => true,
                        'linktext' => get_string('moreinfo', 'mod_projetvet'),
                    ],
                ],
            ],
            'charts' => [
                $this->get_chart_data($totalects, $targetects, get_string('totalcredits', 'mod_projetvet')),
                $this->get_rank_chart_data($ectsbyrank, $targetranka, $targetrankb, get_string('creditsbyrank', 'mod_projetvet')),
                $this->get_chart_data($completedinterviews, $targetinterviews, get_string('tutorinterview', 'mod_projetvet')),
            ],
        ];

        // Thesis subject row.
        $thesisrow = ['label' => get_string('thesissubject', 'mod_projetvet')];
        if ($thesisrecord) {
            $thesisrow['value'] = format_text($thesisrecord->get('thesis'), FORMAT_PLAIN);
        }
        $thesisrow['hasbutton'] = true;
        $thesisrow['buttontext'] = get_string('setsubject', 'mod_projetvet');
        $thesisrow['buttonaction'] = 'thesis-form';
        $thesisrow['cmid'] = $this->cm->id;
        $thesisrow['projetvetid'] = $this->moduleinstance->id;
        $thesisrow['userid'] = $this->studentid;
        $data['infotable']['rows'][] = $thesisrow;

        // Mobility row.
        $mobilityrow = ['label' => get_string('internationalmobility', 'mod_projetvet')];
        if ($mobilityrecord && $mobilityrecord->get('title')) {
            $mobilityrow['value'] = $mobilityrecord->get('title');
        }
        $mobilityrow['hasbutton'] = true;
        $mobilityrow['buttontext'] = get_string('settitle', 'mod_projetvet');
        $mobilityrow['buttonaction'] = 'mobility-form';
        $mobilityrow['cmid'] = $this->cm->id;
        $mobilityrow['projetvetid'] = $this->moduleinstance->id;
        $mobilityrow['userid'] = $this->studentid;

        $data['infotable']['rows'][] = $mobilityrow;

        return $data;
    }

    /**
     * Get chart data for a circular progress chart.
     *
     * @param int $current The current value
     * @param int $total The total value
     * @param string $label The label
     * @return array
     */
    protected function get_chart_data($current, $total, $label) {
        $radius = 45;
        $circumference = 2 * pi() * $radius;
        $percentage = $total > 0 ? round(($current / $total) * 100) : 0;
        $offset = $circumference - ($percentage / 100) * $circumference;

        return [
            'current' => $current,
            'total' => $total,
            'percentage' => $percentage,
            'label' => $label,
            'radius' => $radius,
            'circumference' => $circumference,
            'offset' => $offset,
            'isrank' => false,
        ];
    }

    /**
     * Get chart data for a rank-based circular progress chart.
     *
     * @param array $ectsbyrank Array with 'rank_a' and 'rank_b' values
     * @param int $targetranka Target percentage for rank A
     * @param int $targetrankb Target percentage for rank B
     * @param string $label The label
     * @return array
     */
    protected function get_rank_chart_data($ectsbyrank, $targetranka, $targetrankb, $label) {
        $radius = 45;
        $circumference = 2 * pi() * $radius;

        $ranka = $ectsbyrank['rank_a'];
        $rankb = $ectsbyrank['rank_b'];

        // Get total target ECTS from settings (same as first chart).
        $targetects = get_config('mod_projetvet', 'target_ects') ?: 20;

        // Calculate fill percentages based on total target ECTS.
        // E.g., 3 ECTS out of 20 target = 15% fill.
        $percentagea = $targetects > 0 ? round(($ranka / $targetects) * 100) : 0;
        $percentageb = $targetects > 0 ? round(($rankb / $targetects) * 100) : 0;

        // Offset for rank A (clockwise from top).
        $offseta = $circumference - ($percentagea / 100) * $circumference;

        // Offset for rank B (counterclockwise).
        $offsetb = $circumference - ($percentageb / 100) * $circumference;

        return [
            'isrank' => true,
            'label' => $label,
            'radius' => $radius,
            'circumference' => $circumference,
            'rank_a' => $ranka,
            'rank_b' => $rankb,
            'target_rank_a' => $targetranka,
            'target_rank_b' => $targetrankb,
            'percentage_a' => $percentagea,
            'percentage_b' => $percentageb,
            'offset_a' => $offseta,
            'offset_b' => $offsetb,
        ];
    }
}
