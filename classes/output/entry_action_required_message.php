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

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use renderable;
use templatable;

/**
 * Renderable for the "action required" notification (email + Moodle message).
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_action_required_message implements renderable, templatable {
    /** @var bool */
    private $istutor;

    /** @var string */
    private $studentname;

    /** @var string */
    private $entrytitle;

    /** @var string HTML */
    private $statustext;

    /** @var string */
    private $linkurl;

    /**
     * @param bool $istutor Whether the recipient is a tutor
     * @param string $studentname Student full name (for tutor notifications)
     * @param string $entrytitle Entry title
     * @param string $statustext Status HTML badge
     * @param string $linkurl URL to the entry
     */
    public function __construct(
        bool $istutor,
        string $studentname,
        string $entrytitle,
        string $statustext,
        string $linkurl
    ) {
        $this->istutor = $istutor;
        $this->studentname = $studentname;
        $this->entrytitle = $entrytitle;
        $this->statustext = $statustext;
        $this->linkurl = $linkurl;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'istutor' => $this->istutor,
            'isstudent' => !$this->istutor,
            'studentname' => $this->studentname,
            'entrytitle' => $this->entrytitle,
            'statustext' => $this->statustext,
            'linkurl' => $this->linkurl,
        ];
    }
}
