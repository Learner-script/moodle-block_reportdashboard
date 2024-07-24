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
 * Local Report Dashboard.
 * @package   block_reportdashboard
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_reportdashboard\local;
use block_learnerscript\local\ls as ls;
use context_system;
/**
 * Report Dashboard
 */
class reportdashboard {
     /**
      * This function delete the widgets from dashboard
      * @param  int $deletereport Delete report confirmation
      * @param  int $blockinstanceid Report block instance id
      * @param  int $reportid Report ID
      */
    public function delete_widget($deletereport, $blockinstanceid, $reportid = false) {
        global $DB, $SESSION;
        $context = context_system::instance();
        if ($deletereport == 1) {
            $report = $DB->get_record('block_learnerscript',  ['id' => $reportid]);
            if ($DB->delete_records('block_instances', ['blockname' => 'reportdashboard', 'id' => $blockinstanceid])) {
                (new ls)->delete_report($report, $context);
            }
            if (empty($SESSION->role)) {
                $redirecturl = new \moodle_url('/blocks/reportdashboard/dashboard.php');
            } else {
                $redirecturl = new \moodle_url('/blocks/reportdashboard/dashboard.php', ['role' => $SESSION->role]);
            }
            header("Location: $redirecturl");
        } else if ($deletereport == 0) {
            if (empty($SESSION->role)) {
                $redirecturl = new \moodle_url('/blocks/reportdashboard/dashboard.php');
            } else {
                $redirecturl = new \moodle_url('/blocks/reportdashboard/dashboard.php', ['role' => $SESSION->role]);
            }
            header("Location: $redirecturl");
        }
        return true;
    }
    /**
     * This function checks the report dashboard data
     * @param  int $dashboardid Dashboard id
     */
    public function is_dashboardempty($dashboardid) {
        global $DB, $USER;
        $sql = "SELECT configdata FROM {block_instances}
                 WHERE subpagepattern = :subpagepattern";
        $params = [];
        $params['subpagepattern'] = $dashboardid;
        $reportcount = 0;
        $blocksdata = $DB->get_fieldset_sql($sql, $params);
        foreach ($blocksdata as $key => $value) {
            $value = unserialize(base64_decode($value));
            $report = $DB->get_record('block_learnerscript', ['id' => $value->reportlist]);
            if (isset($report->id)) {
                $haspermission = (new ls)->cr_check_report_permissions($report, $USER->id, context_system::instance());
                if ($haspermission) {
                    $reportcount++;
                }
            }
        }
        return ($reportcount > 0);
    }
    /**
     * This function deletes all the dashboard instances
     * @param  string $role Role
     * @param  int $deletedashboard Dashboard delete
     * @param  int $contextlevel Context level
     * @param  int $blockinstanceid Block instance id
     */
    public function delete_dashboard_instances($role, $deletedashboard, $contextlevel, $blockinstanceid = 0) {
        global $DB;
        $pagetypepattern = 'blocks-reportdashboard-dashboard';
        if (!empty($role) && $contextlevel > 10) {
            $pagetypepattern .= '-' . $role . '_' . $contextlevel;
        } else if (!empty($role)) {
            $pagetypepattern .= '-' . $role;
        }

        $lsinstancessql = "SELECT id, id AS instance
                             FROM {block_instances}
                            WHERE pagetypepattern = :pagetypepattern";
        $params['pagetypepattern'] = $pagetypepattern;
        if ($deletedashboard != 1) {
            $params['subpagepattern'] = $deletedashboard;
            $lsinstancessql .= " AND subpagepattern = :subpagepattern ";
        }
        if ($blockinstanceid > 0) {
            $params['blockinstanceid'] = $blockinstanceid;
            $lsinstancessql .= " AND id = :blockinstanceid ";
        }
        $lsinstances = $DB->get_records_sql_menu($lsinstancessql, $params);
        if (!empty($lsinstances)) {
            blocks_delete_instances($lsinstances);
        }
        return true;
    }
}
