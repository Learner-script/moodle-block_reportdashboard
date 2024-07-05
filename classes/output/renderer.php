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

/**
 * Block Report Dashboard renderer.
 * @package   block_reportdashboard
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use block_learnerscript\local\ls as ls;
/**
 * block_reportdashboard
 */
class block_reportdashboard_renderer extends plugin_renderer_base {
    /**
     * Returns the widget template
     * @param stdClass $page
     */
    public function render_widgetheader($page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('block_reportdashboard/widgetheader', $data);
    }
    /**
     * Returns the reportarea template
     * @param stdClass $page
     */
    public function render_reportarea($page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('block_reportdashboard/reportarea', $data);
    }
    /**
     * Returns the dashboard template
     * @param stdClass $page
     */
    public function render_dashboardheader($page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('block_reportdashboard/dashboardheader', $data);
    }
}
