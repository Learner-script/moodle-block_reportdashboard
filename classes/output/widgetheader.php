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
 * Form for editing LearnerScript report dashboard block instances.
 * @package   block_reportdashboard
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_reportdashboard\output;
use renderable;
use renderer_base;
use templatable;
use stdClass;
use moodle_url;

/**
 * Widget header
 */
class widgetheader implements renderable, templatable {
    /** @var string $sometext Some text to show how to pass data to a template. */
    /** @var object $methodname. */
    public $methodname = null;
    /** @var $reportid */
    public $reportid;
    /** @var $instanceid */
    public $instanceid;
    /** @var $reportvisible */
    public $reportvisible;
    /** @var $reportcontenttype */
    public $reportcontenttype;
    /** @var string $reportnameheading */
    public $reportnameheading;
    /** @var string $reporttitle */
    public $reporttitle;
    /** @var array $reportcontenttypes */
    public $reportcontenttypes;
    /** @var $editactions */
    public $editactions;
    /** @var $durations */
    public $durations;
    /** @var $exportparams */
    public $exportparams;
    /** @var $startduration */
    public $startduration;
    /** @var $endduration */
    public $endduration;
    /** @var $exports */
    public $exports;
    /** @var $disableheader */
    public $disableheader;
    /** @var $currenttime */
    public $currenttime;
    /**
     * Constructor.
     *
     * @param stdClass $data
     */
    public function __construct($data) {
        $this->methodname = $data->methodname;
        $this->reportid = $data->reportid;
        $this->instanceid = $data->instanceid;
        $this->exports = $data->exports;
        $this->reportvisible = $data->reportvisible;
        $this->reportcontenttype = $data->reportcontenttype;
        $this->reportnameheading = substr($data->reportname, 0, 35);
        $this->reporttitle = $data->reportname;
        $this->reportcontenttypes = $data->reportcontenttypes;
        $this->editactions = $data->editactions;
        $this->disableheader = $data->disableheader;
        $this->exportparams = $data->exportparams;
        $this->durations = $data->durations;
        $this->startduration = $data->startduration;
        $this->endduration = $data->endduration;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $OUTPUT, $USER, $PAGE, $DB, $SESSION;
        $data = [];
        if (!empty($this->methodname)) {
            foreach ($this->methodname as $method) {
                $data['action'][] = ["method" => $method,
                                   "icon" => $OUTPUT->image_url($method, "block_reportdashboard"),
                                   "title" => get_string($method, 'block_reportdashboard'),
                                  ];
            }
        }
        $report = $DB->get_record('block_learnerscript', ['id' => $this->reportid]);
        $data['editicon'] = $OUTPUT->image_url('edit_icon', 'block_reportdashboard');
        $data['reportid'] = $this->reportid;
        $data['instanceid'] = $this->instanceid;
        $data['showhide'] = (!empty($this->reportvisible)) ? get_string('hide') : get_string('show');
        $data['sesskey'] = $USER->sesskey;
        $data['exports'] = $this->exports;
        $data['download_icon'] = $OUTPUT->image_url('download_icon', 'block_reportdashboard');
        $data['designreportcap'] = (has_capability('block/learnerscript:managereports', \context_system::instance())
                                    && !$this->disableheader) ? true : false;
        $data['is_siteadmin'] = is_siteadmin();
        $data['widgetheader'] = (!empty($this->methodname) || !empty($this->exports) || $data['designreportcap']) ? true : false;
        $data['reportcontenttype'] = $this->reportcontenttype;
        $data['reportinstance'] = $this->instanceid ? $this->instanceid : $this->reportid;
        $data['report_option_innerstatus'] = $data['designreportcap'] || !empty($data['action']) ? true : false;
        $hideurl = new moodle_url($PAGE->url, ['sesskey' => sesskey(), 'bui_hideid' => $this->instanceid]);
        $data['hideactionurl'] = $hideurl;
        $data['reportnameheading'] = $this->reportnameheading;
        if (strlen($this->reportnameheading) != strlen($this->reporttitle)) {
            $data['reportnameheading'] .= "...";
        }
        $data['reporttitle'] = $this->reporttitle;
        $data['reportcontenttypes'] = $this->reportcontenttypes;
        $data['reportcontenttypeslist'] = count($this->reportcontenttypes) > 1 ? true : false;
        $data['editactions'] = $this->editactions;
        $data['disableheader'] = $this->disableheader;
        $data['exportparams'] = $this->exportparams;
        $data['dashboardrole'] = isset($SESSION->role) ? $SESSION->role : '';
        $data['dashboardcontextlevel'] = isset($SESSION->ls_contextlevel) ? $SESSION->ls_contextlevel : 10;
        $data['dashboardurl'] = $PAGE->subpage;
        $data['durations'] = $this->durations;
        $data['endduration'] = $this->endduration;
        $data['startduration'] = $this->startduration;
        if ($report->type == 'sql') {
            $data['helpimgsql'] = "1";
        }
        return $data;
    }
}
