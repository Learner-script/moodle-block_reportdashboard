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

use block_learnerscript\local\ls;

/**
 * Form for editing HTML block instances.
 * @package    block_reportdashboard
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The definition of the fields to use.
 * @package    block_reportdashboard
 * @param MoodleQuickForm $mform
 */
class block_reportdashboard_edit_form extends block_edit_form {
    /**
     * The definition of the fields to use.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $DB, $USER, $SESSION;

        $userrolesql = "SELECT CONCAT(ra.roleid, '_',c.contextlevel) AS rolecontext, r.shortname, c.contextlevel
                FROM {role_assignments} ra
                JOIN {context} c ON c.id = ra.contextid
                JOIN {role} r ON r.id = ra.roleid
                WHERE 1 = 1 AND ra.userid = :userid AND (";
        $userroleparams['userid'] = $USER->id;
        $i = 0;
        foreach ($USER->access['ra'] as $key => $value) {
            $i++;
            $statsql[] = $DB->sql_like('c.path', ":queryparam$i");
            $userroleparams["queryparam$i"] = $key;
        }
        $userrolesql .= implode(" OR ", $statsql);

        $userrolesql .= ") GROUP BY ra.roleid, c.contextlevel, r.shortname";
        $userroles = $DB->get_record_sql($userrolesql, $userroleparams, IGNORE_MISSING);
        if (!empty($userroles)) {
            $roleshortname = $userroles->shortname;
            if ($roleshortname == 'editingteacher' && $userroles->contextlevel == 10) {
                $rolecontextlevel = 50;
            } else {
                $rolecontextlevel = $userroles->contextlevel;
            }
        } else {
            $roleshortname = 0;
            $rolecontextlevel = 0;
        }
        $SESSION->role = $roleshortname;
        $SESSION->ls_contextlevel = $rolecontextlevel;

        $this->page->requires->js('/blocks/reporttiles/js/jscolor.js', true);
        // Fields for editing HTML block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $reportlist = $DB->get_records_select_menu('block_learnerscript', "global=1 AND visible=1 AND type!='statistics'",
                                                        null, '', 'id, name');
        ksort($reportlist);
        $reports = [];
        $reports[0] = get_string('selectreport', 'block_reporttiles');
        $reportdashboard = true;
        $rolereports = (new ls)->listofreportsbyrole(false, false, false, true, $reportdashboard);
        foreach ($rolereports as $report) {
            $reports[$report['id']] = $report['name'];
        }

        $mform->addElement('text', 'config_blocktitle', get_string('blocktitle', 'block_reportdashboard'));
        $mform->setType('config_blocktitle', PARAM_TEXT);

        $mform->addElement('select', 'config_reportlist', get_string('listofreports', 'block_reportdashboard'), $reports);
        $durations = ['all' => get_string('all', 'block_reportdashboard'),
                      'week' => get_string('week', 'block_reportdashboard'),
                      'month' => get_string('month', 'block_reportdashboard'),
                      'year' => get_string('year', 'block_reportdashboard'), ];
        $mform->addElement('select', 'config_reportduration', get_string('reportduration', 'block_reportdashboard'), $durations);
        $mform->addElement('advcheckbox', 'config_disableheader', get_string('disableheader', 'block_reportdashboard'),
                            'Disable widget header and actions', ['group' => 1], [0, 1]);
        $this->page->requires->yui_module('moodle-block_reportdashboard-reportselect', 'M.block_reportdashboard.init_reportselect',
                            [['formid' => $mform->getAttribute('id')]]);
        $mform->addElement('hidden', 'reportcontenttype');
        $mform->setType('reportcontenttype', PARAM_TEXT);

        $tilescolourpicker = get_string('tilesbackground', 'block_reporttiles');
        $mform->addElement('text', 'config_tilescolourpicker', $tilescolourpicker,
            ['data-class' => 'jscolor', 'value' => '12445f']);
        $mform->setType('config_tilescolourpicker', PARAM_TEXT);
        $mform->registerNoSubmitButton('updatereportselect');

        $mform->addElement('submit', 'updatereportselect', get_string('updatereportselect', 'block_reportdashboard'));
    }
    /**
     * Load in existing data as form defaults
     *
     * @param stdClass $defaults
     * @return void
     */
    public function set_data($defaults) {

        if (!$this->block->user_can_edit() && !empty($this->block->config->title)) {
            // If a title has been set but the user cannot edit it format it nicely.
            $title = $this->block->config->title;
            $defaults->config_title = format_string($title, true, $this->page->context);
            // Remove the title from the config so that parent::set_data doesn't set it.
            unset($this->block->config->title);
        }
        parent::set_data($defaults);
        // Restore $text.
        if (!isset($this->block->config)) {
            $this->block->config = new stdClass();
        }
        if (isset($title)) {
            // Reset the preserved title.
            $this->block->config->title = $title;
        }
    }

}
