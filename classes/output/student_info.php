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
                        'value' => 'Henry Chateau',
                        'haslink' => true,
                        'linktext' => get_string('moreinfo', 'mod_projetvet'),
                    ],
                ],
            ],
            'charts' => [
                $this->get_chart_data(25, get_string('totalcredits', 'mod_projetvet')),
                $this->get_chart_data(60, get_string('creditsbytype', 'mod_projetvet')),
                $this->get_chart_data(75, get_string('tutorinterview', 'mod_projetvet')),
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
     * @param int $percentage The percentage
     * @param string $label The label
     * @return array
     */
    protected function get_chart_data($percentage, $label) {
        $radius = 45;
        $circumference = 2 * pi() * $radius;
        $offset = $circumference - ($percentage / 100) * $circumference;

        return [
            'percentage' => $percentage,
            'label' => $label,
            'radius' => $radius,
            'circumference' => $circumference,
            'offset' => $offset,
        ];
    }
}
