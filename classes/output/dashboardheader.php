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
 * LearnerScript Report Dashboard Header
 *
 * @package    block_reportdashboard
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_reportdashboard\output;
use renderable;
use renderer_base;
use templatable;
use stdClass;
use context_system;
use block_learnerscript\local\ls as ls;
use block_reportdashboard\local\reportdashboard as reportdashboard;

/**
 * Dashboard header
 */
class dashboardheader implements renderable, templatable {
    /** @var $editingon */
    public $editingon;

    /** @var $configuredinstances */
    public $configuredinstances;

    /** @var $getdashboardname */
    public $getdashboardname;

    /** @var $dashboardurl */
    public $dashboardurl;

    /**
     * Constructor
     * @param stdClass $data
     */
    public function __construct($data) {
        $this->editingon = $data->editingon;
        $this->configuredinstances = $data->configuredinstances;
        isset($data->getdashboardname) ? $this->getdashboardname = $data->getdashboardname : null;
        $this->dashboardurl = $data->dashboardurl;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $SESSION, $USER, $DB;
        $data = [];
        $switchableroles = (new ls)->switchrole_options();
        $data['editingon'] = $this->editingon;
        $data['issiteadmin'] = is_siteadmin();
        if (has_capability('block/learnerscript:managereports', context_system::instance())) {
            $data['managerrole'] = get_string('manager', 'block_reportdashboard');
        }
        $data['dashboardurl'] = $this->dashboardurl;
        $data['configuredinstances'] = $this->configuredinstances;
        $dashboardlist = [];
        $dashboardlist = $this->get_dashboard_reportscount();
        $data['sesskey'] = sesskey();
        if (count($dashboardlist)) {
            $data['get_dashboardname'] = $dashboardlist;
        }

        $data['role'] = $SESSION->role;
        $data['contextlevel'] = $SESSION->ls_contextlevel;
        if (!is_siteadmin()) {
            $sitewideuserroles = get_user_roles_sitewide_accessdata($USER->id);
            $userroles = [];
            foreach ($sitewideuserroles['ra'] as $key => $t) {
                $contextrecord = $DB->get_field_sql("SELECT count(ra.roleid)
                            FROM {role_assignments} ra
                            JOIN {context} c ON c.id = ra.contextid AND c.contextlevel = 50
                            WHERE c.path = :path", ['path' => $key], IGNORE_MULTIPLE);
                if (!empty($contextrecord)) {
                    $userroles[] = $contextrecord;
                }
            }
            $dashboardlink = count(array_unique($userroles)) > 1 ? 1 : 0;
            $data['dashboardlink'] = $dashboardlink;
        } else {
            $data['dashboardlink'] = 0;
        }

        return array_merge($data, $switchableroles);
    }
    /**
     * Get Dashboard reportscount
     */
    private function get_dashboard_reportscount() {
        global $DB, $SESSION;
        $role = $SESSION->role;
        if (!empty($role) && !is_siteadmin()) {
            $params['pagetypepattern'] = '%blocks-reportdashboard-dashboard-' . $role . '%';
            $getreports = $DB->get_records_sql("SELECT DISTINCT(subpagepattern) FROM {block_instances}
                            WHERE 1 = 1 AND " .
                            $DB->sql_like('pagetypepattern', ':pagetypepattern', false), $params);
        } else {
            $params['pagetypepattern'] = '%blocks-reportdashboard-dashboard%';
            $getreports = $DB->get_records_sql("SELECT DISTINCT(subpagepattern) FROM {block_instances}
                           WHERE 1 = 1 AND " .
                            $DB->sql_like('pagetypepattern', ':pagetypepattern', false), $params);
        }
        $dashboardname = [];
        $i = 0;
        if (!empty($getreports)) {
            foreach ($getreports as $getreport) {
                $dashboardname[$getreport->subpagepattern] = $getreport->subpagepattern;
            }
        } else {
            $dashboardname['Dashboard'] = get_string('dashboard', 'block_reportdashboard');
        }
        $getdashboardname = [];
        foreach ($dashboardname as $key => $value) {
            if ($value != 'Dashboard' && !(new reportdashboard)->is_dashboardempty($key)) {
                continue;
            }
            $params['subpage'] = "'%" . $key ."%'";
            $getreports = $DB->count_records_sql("SELECT COUNT(id) FROM {block_instances} WHERE 1 = 1
                            AND " . $DB->sql_like('subpagepattern', ':subpage', false), $params);
            $getdashboardname[$i]['name'] = ucfirst($value);
            $getdashboardname[$i]['pagetypepattern'] = $value;
            $getdashboardname[$i]['random'] = $i;
            $getdashboardname[$i]['default'] = 0;
            $i++;
        }
        return $getdashboardname;
    }
}
