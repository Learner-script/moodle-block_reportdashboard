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
 * Form for editing Cobalt report dashboard block instances.
 * @package   block_reportdashboard
 * @copyright 2023 Moodle India
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/blocklib.php');
use block_learnerscript\local\ls as ls;
use block_reportdashboard\local\reportdashboard;
/**
 * Reportdashboard block class
 */
class block_reportdashboard extends block_base {
    /**
     * Sets the block title.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_reportdashboard');
    }
    /**
     * Allows the block to load any JS it requires into the page.
     *
     * By default this function simply permits the user to dock the block if it is dockable.
     *
     * Left null as of MDL-64506.
     */
    public function get_required_javascript() {
        $reportcontenttype = (isset($this->config->reportcontenttype) &&
            !empty($this->config->reportcontenttype)) ? $this->config->reportcontenttype : '';
        $reportslist = isset($this->config->reportlist) ? $this->config->reportlist : '';
        $instance = isset($this->instance->id) ? $this->instance->id : '';
        $this->page->requires->js(new moodle_url('/blocks/learnerscript/js/highchart.js'));
        $this->page->requires->jquery();
        $this->page->requires->jquery_plugin('ui-css');
        $this->page->requires->js_call_amd('block_learnerscript/reportwidget', 'CreateDashboardwidget'
            , [['reportid' => $reportslist, 'reporttype' => $reportcontenttype, 'instanceid' => $instance ]]);
        $this->page->requires->js_call_amd('block_learnerscript/smartfilter', 'SelectDuration');
        $this->page->requires->js_call_amd('block_learnerscript/smartfilter', 'ReportContenttypes');
    }
    /**
     * Subclasses should override this and return true if the
     * subclass block has a settings.php file.
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }
    /**
     * Which page types this block may appear on.
     *
     * The information returned here is processed by the
     * @link blocks_name_allowed_in_format() function. Look there if you need
     * to know exactly how this works.
     *
     * Default case: everything except mod and tag.
     *
     * @return array page-type prefix => true/false.
     */
    public function applicable_formats() {
        return ['all' => true];
    }
    /**
     * This function is called on your subclass right after an instance is loaded
     * Use this function to act on instance data just after it's loaded and before anything else is done
     * For instance: if your block will have different title's depending on location (site, course, blog, etc)
     */
    public function specialization() {
        $this->title = isset($this->config->title) ? format_string($this->config->title) : format_string(
            get_string('newreportdashboardblock', 'block_reportdashboard'));
    }
    /**
     * Are you going to allow multiple instances of each block?
     * If yes, then it is assumed that the block WILL USE per-instance configuration
     * @return boolean
     */
    public function instance_allow_multiple() {
        return true;
    }
    /**
     * Default return is false - header will be shown
     * @return boolean
     */
    public function hide_header() {
        return true;
    }
    /**
     * Parent class version of this function simply returns NULL
     * This should be implemented by the derived class to return
     * the content object.
     *
     * @return object
     */
    public function get_content() {
        global $DB, $COURSE;
        $this->page->requires->css('/blocks/learnerscript/css/fixedHeader.dataTables.min.css');
        $this->page->requires->css('/blocks/learnerscript/css/responsive.dataTables.min.css');
        $this->page->requires->css('/blocks/learnerscript/css/jquery.dataTables.min.css');
        $this->page->requires->css('/blocks/reportdashboard/css/radios-to-slider.min.css');
        $this->page->requires->css('/blocks/reportdashboard/css/flatpickr.min.css');
        $this->page->requires->css('/blocks/learnerscript/css/select2.min.css');

        $delete = optional_param('delete', 0, PARAM_BOOL);
        $deledbui = optional_param('bui_deleteid', 0, PARAM_INT);
        $buihideid = optional_param('bui_hideid', 0, PARAM_INT);
        foreach (['week', 'month', 'year', 'custom', 'all'] as $key) {
            $durations[] = ['key' => $key, 'value' => get_string($key, 'block_reportdashboard')];
        }

        $context = context_system::instance();
        $output = $this->page->get_renderer('block_reportdashboard');
        if ($this->content !== null) {
            return $this->content;
        }
        $filteropt = new stdClass;
        $filteropt->overflowdiv = true;
        if ($this->content_is_trusted()) {
            $filteropt->noclean = true;
        }
        $this->content = new stdClass;
        $this->content->footer = '';
        $this->content->text = "";
        if (isset($this->config->reportlist) && $this->config->reportlist &&
            $DB->record_exists('block_learnerscript', ['id' => $this->config->reportlist, 'visible' => 1])) {
            $reportid = $this->config->reportlist;
            $pickedcolor = isset($this->config->tilescolourpicker) ? $this->config->tilescolourpicker : '#FFF';

            $instanceid = $this->instance->id;
            if (!$report = $DB->get_record('block_learnerscript', ['id' => $reportid])) {
                throw new moodle_exception('reportdoesnotexists', 'block_learnerscript');
            }
            if (($buihideid == $this->instance->id) && confirm_sesskey()) {
                if ($buihideid) {
                    $visibility = 0;
                } else {
                    $visibility = 1;
                }
                blocks_set_visibility($this->instance, $this->page, $visibility);
                redirect(new moodle_url($this->page->url));
            }
            if ($delete && confirm_sesskey()) {
                (new reportdashboard)->delete_widget($report, $deledbui, $reportid);
            }
            $reportrecord = new \block_learnerscript\local\reportbase($report->id);
            $reportrecord->customheader = true; // For not to display Form Header.
            $filterrecords = (new ls)->cr_unserialize($reportrecord->config->components);
            if (!empty($filterrecords['filters']['elements'])) {
                $filtersarray = $filterrecords;
            } else {
                $filtersarray = [];
            }
            $reportrecord->reportcontenttype = (isset($this->config->reportcontenttype) &&
                !empty($this->config->reportcontenttype)) ? $this->config->reportcontenttype : '';
            $reportrecord->instanceid = $instanceid;
            $properties = new stdClass();
            $properties->courseid = $COURSE->id;
            $reportclass = (new ls)->create_reportclass($reportid, $properties);
            $disableheader = isset($this->config->disableheader) ? $this->config->disableheader : 0;
            $reportduration = !empty($this->config->reportduration) ? $this->config->reportduration : 'all';
            $blocktitle = !empty($this->config->blocktitle) ? $this->config->blocktitle : '';

            switch ($reportduration) {
                case 'week':
                    $startduration = strtotime("-1 week");
                    break;
                case 'month':
                    $startduration = strtotime("-1 month");
                    break;
                case 'year':
                    $startduration = strtotime("-1 year");
                    break;
                default:
                    $startduration = 0;
                    break;
            }
            $reportclass->params = [];
            $methodnames = [];
            $reportclass->params['filter_courses'] = $COURSE->id == SITEID ? 0 : $COURSE->id;
            if (has_capability('block/learnerscript:designreport', $context) && !$disableheader) {
                if ($reportclass->parent === true) {
                    $methodnames[] = "schreportform";
                }
                $methodnames[] = "sendreportemail";
            }
            if (!empty($filtersarray) && !$disableheader) {
                $methodnames[] = "reportfilter";
            }

            $exports = [];
            if (!empty($reportclass->config->export) && !$disableheader) {
                $exports = explode(',', $reportclass->config->export);
            }

            $reportcontenttypesarray = (new ls)->cr_listof_reporttypes($reportid, true, false);
            $reportcontenttypes = [];
            foreach ($reportcontenttypesarray as $rptcontenttypes) {
                $reportcontenttypes[] = ['key' => $rptcontenttypes['chartid'], 'value' => $rptcontenttypes['chartname']];
            }
            $pagetype = explode('-', $this->page->pagetype);
            if (in_array('reportdashboard', $pagetype) && !$disableheader) {
                $editactions = true;
            } else {
                $editactions = false;
            }

            $exportparams = '';
            if (!empty($reportclass->params)) {
                foreach ($reportclass->params as $key => $val) {
                    $exportparams .= "&$key=$val";
                }
            }

            $widgetheader = new \block_reportdashboard\output\widgetheader((object) ["methodname" => $methodnames,
                "reportid" => $reportid,
                "instanceid" => $instanceid, "reportvisible" => $report->visible,
                "exports" => $exports, "reportname" => $report->name,
                "reportcontenttype" => (isset($this->config->reportcontenttype) &&
                    !empty($this->config->reportcontenttype)) ? $this->config->reportcontenttype : '',
                "reportcontenttypes" => $reportcontenttypes,
                "editactions" => $editactions,
                "disableheader" => $disableheader,
                "exportparams" => $exportparams,
                "durations" => $durations,
                "startduration" => $startduration,
                "endduration" => time(),
                "blocktitle" => $blocktitle, ]);
            $reportarea = new \block_reportdashboard\output\reportarea((object)["reportid" => $reportid,
                "instanceid" => $instanceid,
                "reportcontenttype" => (isset($this->config->reportcontenttype)
                    && !empty($this->config->reportcontenttype)) ? $this->config->reportcontenttype : '',
                "reportname" => $report->name,
                "reportduration" => ($reportduration == 'all') ? '' : get_string($reportduration, 'block_reportdashboard'),
                "stylecolorpicker" => $pickedcolor,
                "disableheader" => $disableheader,
                "blocktitle" => $blocktitle, ]);
            $this->content->text .= html_writer::start_div('reportdashboard_header', []);

            $this->content->text .= $output->render($widgetheader);

            $this->content->text .= html_writer::end_div();
            $this->content->text .= $output->render($reportarea);
            $this->content->text .= html_writer::tag('input', '', ['type' => 'hidden', 'id' => 'ls_courseid',
                                    'value' => " . $COURSE->id . ", ]);
        } else {
            if (is_siteadmin()) {
                $this->content->text .= get_string('configurationmessage', 'block_reportdashboard');
            } else {
                $this->content->text .= '';
            }
        }
        unset($filteropt); // Memory footprint.
        return $this->content;
    }
    /**
     * Serialize and store config data
     * @param stdClass $data
     * @param stdClass $nolongerused
     */
    public function instance_config_save($data, $nolongerused = false) {
        $config = clone ($data);
        parent::instance_config_save($config, $nolongerused);
    }
    /**
     * Delete everything related to this instance if you have been using persistent storage other than the configdata field.
     * @return boolean
     */
    public function instance_delete() {
        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'block_reportdashboard');
        return true;
    }
    /**
     * Page is completely private, nobody else may see content there.
     * @return boolean
     */
    public function content_is_trusted() {
        global $SCRIPT;

        if (!$context = context::instance_by_id($this->instance->parentcontextid, IGNORE_MISSING)) {
            return false;
        }
        // Find out if this block is on the profile page.
        if ($context->contextlevel == CONTEXT_USER) {
            if ($SCRIPT === '/blocks/reportdashboard/dashboard.php') {
                // This is exception - page is completely private, nobody else may see content there.
                // That is why we allow JS here.
                return true;
            } else {
                // No JS on public personal pages, it would be a big security issue.
                return false;
            }
        }
        return true;
    }
    /**
     * The block should only be dockable when the title of the block is not empty
     * and when parent allows docking.
     * @return bool
     */
    public function instance_can_be_docked() {
        return false;
    }
    /**
     * Add custom reportdashboard attributes to aid with theming and styling.
     * @return array
     */
    public function reportdashboard_attributes() {
        global $CFG;
        $attributes = parent::reportdashboard_attributes();
        if (!empty($CFG->block_reportdashboard_allowcssclasses)) {
            if (!empty($this->config->classes)) {
                $attributes['class'] .= ' ' . $this->config->classes;
            }
        }
        return $attributes;
    }
}
