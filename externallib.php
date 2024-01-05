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
 * LearnerScript Dashboard block plugin installation.
 *
 * @package    block_reportdashboard
 * @copyright  2023 Moodle India
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/blocks/learnerscript/lib.php');
use block_learnerscript\local\ls;
use block_learnerscript\local\reportbase;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
global $CFG, $DB, $USER, $OUTPUT, $COURSE;
require_login();
/** block_reportdashboard External */
class block_reportdashboard_external extends external_api {
    /**
     * User list parameters description
     * @return external_function_parameters
     */
    public static function userlist_parameters() {
        return new external_function_parameters(
            [
                'term' => new external_value(PARAM_TEXT, 'The current search term in the search box', VALUE_DEFAULT, ''),
                '_type' => new external_value(PARAM_TEXT, 'A "request type", default query', VALUE_DEFAULT, ''),
                'query' => new external_value(PARAM_TEXT, 'Query', VALUE_DEFAULT, ''),
                'action' => new external_value(PARAM_TEXT, 'Action', VALUE_DEFAULT, ''),
                'userlist' => new external_value(PARAM_TEXT, 'Users list', VALUE_DEFAULT, ''),
                'reportid' => new external_value(PARAM_INT, 'Report ID', VALUE_DEFAULT, 0),
                'maximumSelectionLength' => new external_value(PARAM_INT, 'Maximum Selection Length to Search', VALUE_DEFAULT, 0),
                'setminimumInputLength' => new external_value(PARAM_INT, 'Minimum Input Length to Search', VALUE_DEFAULT, 2),
                'courses' => new external_value(PARAM_RAW, 'Course id of report', VALUE_DEFAULT),
            ]
        );
    }
    /**
     * This function displays the list of users based on the search text
     * @param string $term Search text
     * @param string $_type Filter type
     * @param string $query SQL query
     * @param int $action Action
     * @param object $userlist Users list
     * @param int $reportid Report id
     * @param int $maximumSelectionLength Maximum length of the string to search
     * @param int $setminimumInputLength Maximum length of the string to enter
     * @param array $courses Courses list
     */
    public static function userlist($term, $_type, $query, $action, $userlist, $reportid,
                                $maximumSelectionLength, $setminimumInputLength, $courses) {
        global $DB, $SESSION, $USER;
        $context = context_system::instance();
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::userlist_parameters(), ['term' => $term, '_type' => $_type, 'query' => $query,
        'action' => $action, 'userlist' => $userlist, 'reportid' => $reportid, 'maximumSelectionLength' => $maximumSelectionLength,
        'setminimumInputLength' => $setminimumInputLength, 'courses' => $courses]);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

        $params['firstname'] = '%' . $term . '%';
        $params['lastname'] = '%' . $term . '%';
        $params['username'] = '%' . $term . '%';
        $params['email'] = '%' . $term . '%';
        $users = $DB->get_records_sql("SELECT *
                FROM {user}
                WHERE id > 2 AND deleted = 0 AND ("
                . $DB->sql_like('firstname', ':firstname', false) .
                " OR " . $DB->sql_like('lastname', ':lastname', false) .
                " OR " . $DB->sql_like('username', ':username', false) .
                " OR " . $DB->sql_like('email', ':email', false) . ")", $params);
        $reportclass = (new ls)->create_reportclass($reportid);
        $reportclass->courseid = $reportclass->config->courseid;
        if ($reportclass->config->courseid == SITEID) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance($reportclass->config->courseid);
        }
        $data = [];
        $permissions = (isset($reportclass->componentdata['permissions'])) ? $reportclass->componentdata['permissions'] : [];
        $roles = [];
        foreach ($permissions['elements'] as $a => $b) {
            $roles[] = $b['formdata']->roleid;
            $contextlevels[] = $b['formdata']->contextlevel;
        }
        $contextlevel = $SESSION->ls_contextlevel;
        $role = $SESSION->role;
        foreach ($users as $user) {
            if ($user->id > 2) {
                $rolewiseuser = [];
                if (!empty($permissions['elements'])) {
                    list($ctxsql, $params1) = $DB->get_in_or_equal($contextlevels, SQL_PARAMS_NAMED);
                    list($rolesql, $params2) = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED);
                    $rolewiseusers = "SELECT  u.*
                    FROM {user} u
                    JOIN {role_assignments}  AS lra ON lra.userid = u.id
                    JOIN {role} r ON r.id = lra.roleid
                    JOIN {context} ctx ON ctx.id  = lra.contextid
                    WHERE u.confirmed = 1 AND u.suspended = 0  AND u.deleted = 0 AND u.id = :userid
                    AND ctx.contextlevel $ctxsql AND r.id $rolesql";
                    if (isset($role) && ($role == 'manager' || $role == 'editingteacher' || $role == 'teacher'
                    || $role == 'student') && ($contextlevel == CONTEXT_COURSE)) {
                        if ($courses <> SITEID) {
                            $rolewiseusers .= " AND ctx.instanceid = :courses";
                        }
                    }
                    $params = array_merge($params1, $params2, ['userid' => $user->id, 'courses' => $courses]);
                    $rolewiseuser = $DB->get_record_sql($rolewiseusers, $params);
                }
                if (!empty($rolewiseuser)) {
                    $contextlevel = $SESSION->ls_contextlevel;
                    $userroles = (new ls)->get_currentuser_roles($rolewiseuser->id, $contextlevel);
                    $reportclass->userroles = $userroles;
                    if ($reportclass->check_permissions($context, $USER->id)) {
                        $data[] = ['id' => $rolewiseuser->id, 'text' => fullname($rolewiseuser)];
                    }
                }
            } else {
                $userroles = (new ls)->get_currentuser_roles($user->id);
                $reportclass->userroles = $userroles;
                if ($reportclass->check_permissions($context, $user->id)) {
                    $data[] = ['id' => $user->id, 'text' => fullname($user)];
                }
            }
        }
        $return = ['total_count' => count($data), 'items' => $data];
        $data = json_encode($return);
        return $data;
    }
    /**
     * Returns users list
     * @return external_description
     */
    public static function userlist_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    /**
     * Rreports list parameters description
     * @return external_function_parameters
     */
    public static function reportlist_parameters() {
        return new external_function_parameters(
            [
                'search' => new external_value(PARAM_RAW, 'Search value', VALUE_DEFAULT, ''),
            ]
        );
    }
    /**
     * This function returns the list of reports data
     * @param string $search Search text for report
     */
    public static function reportlist($search) {
        global $DB, $USER;
        $context = context_system::instance();
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::reportlist_parameters(), ['search' => $search]);

        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $search = 'admin';
        $sql = "SELECT id, name FROM {block_learnerscript} WHERE visible = 1 AND name LIKE :search";
        $params = ['search' => "'%" . $search ."%'"];
        $courselist = $DB->get_records_sql($sql, $params);
        $activitylist = [];
        foreach ($courselist as $cl) {
            if (!empty($cl)) {
                $checkpermissions = (new reportbase($cl->id))->check_permissions($context, $USER->id);
                if (!empty($checkpermissions) || has_capability('block/learnerscript:managereports', $context)) {
                    $modulelink = html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php',
                                ['id' => $cl->id]), $cl->name, ['id' => 'viewmore_id']);
                    $activitylist[] = ['id' => $cl->id, 'text' => $modulelink];
                }
            }
        }
        $termsdata = [];
        $termsdata['total_count'] = count($activitylist);
        $termsdata['incomplete_results'] = true;
        $termsdata['items'] = $activitylist;
        $return = $termsdata;
        $data = json_encode($return);
        return $data;
    }
    /**
     * Returns reports list
     * @return external_description
     */
    public static function reportlist_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    /**
     * Sendemails parameters description
     * @return external_function_parameters
     */
    public static function sendemails_parameters() {
        return new external_function_parameters(
            [
                'reportid' => new external_value(PARAM_INT, 'Report ID', VALUE_DEFAULT, 0),
                'instance' => new external_value(PARAM_INT, 'Reprot Instance', VALUE_DEFAULT),
                'pageurl' => new external_value(PARAM_LOCALURL, 'Page URL', VALUE_DEFAULT, ''),
            ]
        );

    }
    /**
     * This function is used to send emails for user
     * @param int $reportid Report ID
     * @param int $instance Report instance to send
     * @param string $pageurl Current page URL
     */
    public static function sendemails($reportid, $instance, $pageurl) {
        global $CFG;
        self::set_context(context_system::instance());
        $context = contextsystem::instance();
        // We always must pass webservice params through validate_parameters.
        self::validate_parameters(self::sendemails_parameters(), ['reportid' => $reportid, 'instance' => $instance,
        'pageurl' => $pageurl]);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

        $pageurl = $pageurl ? $pageurl : $CFG->wwwroot . '/blocks/reportdashboard/dashboard.php';
        require_once($CFG->dirroot . '/blocks/reportdashboard/email_form.php');
        $emailform = new analytics_emailform($pageurl, ['reportid' => $reportid, 'AjaxForm' => true, 'instance' => $instance]);
        $return = $emailform->render();
        $data = json_encode($return);
        return $data;
    }
    /**
     * Returns send emails for users data
     * @return external_description
     */
    public static function sendemails_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
}
