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
 * Form for editing LearnerScript dashboard block instances.
 * @package   block_reportdashboard
 * @copyright 2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->libdir . '/badgeslib.php');

$sessionuserid = optional_param('filter_users', '', PARAM_INT);
$contextlevel = optional_param('contextlevel', 10, PARAM_INT);
$roleshortname = optional_param('role', '', PARAM_TEXT);

use block_learnerscript\local\ls;
use block_learnerscript\local\querylib;

global $CFG, $SITE, $PAGE, $OUTPUT, $DB, $SESSION;

$context = context_system::instance();
$PAGE->set_pagetype('site-index');
$PAGE->set_pagelayout('course');
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/blocks/reportdashboard/profilepage.php');
$PAGE->set_title(get_string('profilepage', 'block_reportdashboard'));
require_login();
$PAGE->requires->css('/blocks/reportdashboard/css/radioslider/radios-to-slider.min.css');
$PAGE->requires->css('/blocks/reportdashboard/css/flatpickr.min.css');
$PAGE->requires->css('/blocks/learnerscript/css/datatables/fixedHeader.dataTables.min.css');
$PAGE->requires->css('/blocks/learnerscript/css/datatables/responsive.dataTables.min.css');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->js_call_amd('block_reportdashboard/reportdashboard', 'profileuserfunction');
$PAGE->requires->css('/blocks/learnerscript/css/select2/select2.min.css');
$PAGE->requires->css('/blocks/learnerscript/css/datatables/jquery.dataTables.min.css');

$encourses = enrol_get_my_courses();
$currentusercourse = array_key_last($encourses);
$coursecontext = context_course::instance($currentusercourse);

$capabilityrole = get_roles_with_capability('block/learnerscript:learnerreportaccess');
if (!empty($capabilityrole)) {
    $currentrole = current($capabilityrole)->shortname;
}

$userid = ($roleshortname == $currentrole) ? $USER->id : $sessionuserid;

$SESSION->ls_contextlevel = $contextlevel;
$SESSION->role = $roleshortname;
$role = $SESSION->role;
echo $OUTPUT->header();

if (!is_siteadmin()) {
    if ($userid != $USER->id && has_capability('block/learnerscript:learnerreportaccess', $coursecontext)) {
        throw new moodle_exception(get_string('badpermissions', 'block_learnerscript'));
    } else if (!has_capability('block/learnerscript:learnerreportaccess', $coursecontext)
        && !has_capability('block/learnerscript:teacherreportsaccess', $coursecontext)) {
        throw new moodle_exception(get_string('badpermissions', 'block_learnerscript'));
    }
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
} else {
    $dashboardlink = 0;
}
$systemcontext = context_system::instance();
$siteadmin = is_siteadmin() || has_capability('block/learnerscript:managereports', $systemcontext);

if ($userid) {
    $dashboardcourse = [];
    // User filter.
    if ($siteadmin) {
        $dashboardcourse = $DB->get_records_select('course' , 'id <> :id' , ['id' => SITEID] , '' ,
        'id, fullname');
    } else if (has_capability('block/learnerscript:teacherreportsaccess', $coursecontext)) {
        $dashboardcourse = (new querylib)->get_rolecourses($USER->id, $SESSION->role, $SESSION->ls_contextlevel,
        SITEID, '', '');
    }
    if (!empty($dashboardcourse)) {
        foreach ($dashboardcourse as $selectedcourse) {
            $courseslist[] = $selectedcourse->id;
        }
    }

    $coursesql = ' ';
    $ucoursesql = '';
    $joinsql = '';
    $params = [];
    if (!empty($courseslist)) {
        list($coursesql, $params) = $DB->get_in_or_equal($courseslist, SQL_PARAMS_NAMED);
        $ucoursesql = " AND c.id $coursesql";
    }
    $users = $DB->get_records_sql("SELECT DISTINCT u.*
                            FROM {course} c
                            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                            JOIN {role_assignments} ra ON ra.contextid = ctx.id
                            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                            JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                            WHERE 1 = 1 $ucoursesql",
                            $params);

    foreach ($users as $user) {
        if ($user->id == $sessionuserid) {
            $ppdashboard[] = ['id' => $user->id,
            'fullname' => $user->firstname . ' ' . $user->lastname,
            'selecteduser' => 'selected', ];
        } else {
            $ppdashboard[] = ['id' => $user->id, 'fullname' => $user->firstname . ' ' . $user->lastname,
                                'selecteduser' => '', ];
        }
    }

    if (!empty($ppdashboard)) {
        $data['userslist'] = array_values($ppdashboard);
        $data['coursedashboard'] = 1;
        $data['wwwroot'] = $CFG->wwwroot;
    }
    $userinfo = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $defaultpicture = new moodle_url("/pix/user35.png");
    $userpicture = new user_picture($userinfo);
    $userpicture->size = 1;
    $userinfo->profileimage = !empty($userpicture) ? $userpicture->get_url($PAGE)->out(false) : $defaultpicture;

    $userinfo->userfullname = !empty($userinfo->firstname) ? ($userinfo->firstname . ' ' . $userinfo->lastname) : 'NA';
    $userinfo->lastlogin = !empty($userinfo->lastaccess) ? userdate($userinfo->lastaccess) : '--';
    $totaltimespent = $DB->get_field_sql("SELECT SUM(timespent) AS timespent FROM {block_ls_coursetimestats}
                                WHERE 1 = 1 AND userid = :userid", ['userid' => $userid]);
    $timespent = !empty($totaltimespent) ? (new ls)->strtime($totaltimespent) : 0;
    $userinfo->totaltimespent = $timespent;
    $avgtimespent = $DB->get_field_sql("SELECT AVG(timespent) AS timespent FROM {block_ls_coursetimestats}
                                WHERE 1 = 1 AND userid = :userid", ['userid' => $userid]);
    $avgtime = !empty($avgtimespent) ? (new ls)->strtime($avgtimespent) : 0;
    $userinfo->avgtimespent = $avgtime;

    // Badges.
    $badgeslist = badges_get_user_badges($userid);

    $userbadgesinfo = [];
    foreach ($badgeslist as $badge) {
        if ($badge->id > 0) {
            $batchinstance = new badge($badge->id);
            $badgecontext = $batchinstance->get_context();
            $badgeimageurl = moodle_url::make_pluginfile_url($badgecontext->id, 'badges', 'badgeimage',
                            $badge->id, '/', 'f3', false);
        } else {
            $badgeimageurl = $OUTPUT->image_url('i/badge');
        }
        $userbadgesinfo[] = ['badgeid' => $badge->id, 'badgename' => $badge->name, 'badgeimage' => $badgeimageurl];
    }

    // Enrolments.
    $enrolcourses = enrol_get_all_users_courses($userid);

    $completedcoursessql = "SELECT COUNT(DISTINCT cc.course) AS completed
                          FROM {role_assignments} ra
                          JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                          JOIN {context} ctx ON ctx.id = ra.contextid
                          JOIN {course} c ON c.id = ctx.instanceid AND c.visible = 1
                          JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ra.userid
                          AND cc.timecompleted > 0
                          WHERE ra.userid = :userid ";
    $completedcoursescount = $DB->get_field_sql($completedcoursessql, ['userid' => $userid]);

    $userinfo->enrolledcoursescoursescount = !empty($enrolcourses) ? count($enrolcourses) : 0;
    $userinfo->completedcoursescount = $completedcoursescount;
    $userinfo->inprogresscoursescount = !empty($enrolcourses) ? count($enrolcourses) - $completedcoursescount : 0;
    $userinfo->progresspercent = !empty($userinfo->enrolledcoursescoursescount) ?
                round(($userinfo->completedcoursescount / $userinfo->enrolledcoursescoursescount) * 100) : 0;

    $courseslistarray = [];
    $usercourseinfo = [];
    if (!empty($enrolcourses)) {
        foreach ($enrolcourses as $k => $v) {
            $courseslistarray[] = $v->id;
            $usercourseinfo[] = ['id' => $v->id, 'coursename' => $v->fullname];
        }
    }
    $joinsql = " AND ((cm.availability IS NOT NULL AND cm.id IN (
                SELECT cmd.id
                FROM {course_modules} cmd
                JOIN {groups} g ON g.courseid = cm.course
                JOIN {groups_members} gm ON gm.groupid = g.id
                WHERE gm.userid = :gmuserid
                AND cmd.visible = 1
                AND cmd.availability LIKE CONCAT('%\"type\":\"group\",\"id\":', g.id, '%')))
                OR (cm.availability IS NULL)) ";
    if (!empty($enrolcourses)) {
        list($csql, $params) = $DB->get_in_or_equal($courseslistarray, SQL_PARAMS_NAMED);
        $params['cmvisible'] = 1;
        $params['deletioninprogress'] = 0;
        $params['gmuserid'] = $userid;
        // Assignments.
        $assignmentscountsql = $DB->get_records_sql("SELECT cm.id FROM {course_modules} cm
                        JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                        WHERE 1 = 1 AND cm.visible = :cmvisible AND
                        cm.deletioninprogress = :deletioninprogress AND cm.course $csql $joinsql",
                        $params);
        $userinfo->assignmentscount = count($assignmentscountsql);

        $i = 0;
        $j = 0;
        foreach ($assignmentscountsql as $assigncountid) {
            $assignmodulecompletions = $DB->get_records('course_modules_completion',
                            ['coursemoduleid' => $assigncountid->id, 'userid' => $userid]);
            if (!empty($assignmodulecompletions)) {
                $i++;
            } else {
                $j++;
            }
        }
        $userinfo->assigncompleted = $i;
        $userinfo->assigninprogress = $j;
        $userinfo->assignmentprogress = !empty($userinfo->assignmentscount) ? round(($i / $userinfo->assignmentscount) * 100) : 0;
        // Quizzes.
        $quizzescountsql = $DB->get_records_sql("SELECT cm.id FROM {course_modules} cm
                                JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                                WHERE 1 = 1 AND cm.visible = :cmvisible AND
                                cm.deletioninprogress = :deletioninprogress AND cm.course $csql $joinsql",
                                $params);
        $userinfo->quizzescount = count($quizzescountsql);
        $a = 0;
        $b = 0;
        foreach ($quizzescountsql as $quizzescountid) {
            $quizmodulecompletions = $DB->get_records('course_modules_completion',
                            ['coursemoduleid' => $quizzescountid->id, 'userid' => $userid]);
            if (!empty($quizmodulecompletions)) {
                $a++;
            } else {
                $b++;
            }
        }
        $userinfo->quizcompleted = $a;
        $userinfo->quizinprogress = $b;
        $userinfo->quizprogress = !empty($userinfo->quizzescount) ? round(($a / $userinfo->quizzescount) * 100) : 0;

        // Scorm.
        $scormcountsql = $DB->get_records_sql("SELECT cm.id FROM {course_modules} cm
                                JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
                                WHERE 1 = 1 AND cm.visible = :cmvisible AND
                                cm.deletioninprogress = :deletioninprogress AND cm.course $csql $joinsql",
                                $params);
        $userinfo->scormcount = count($scormcountsql);
        $m = 0;
        $n = 0;
        foreach ($scormcountsql as $scormcountid) {
            $scormmodulecompletions = $DB->get_records('course_modules_completion', ['coursemoduleid' => $scormcountid->id]);
            if (!empty($scormmodulecompletions)) {
                $m++;
            } else {
                $n++;
            }
        }
        $userinfo->scormcompleted = $m;
        $userinfo->scorminprogress = $n;
        $userinfo->scormprogress = !empty($userinfo->scormcount) ? round(($m / $userinfo->scormcount) * 100) : 0;

    } else {
        $userinfo->assignmentscount = 0;
        $userinfo->assigncompleted = 0;
        $userinfo->assigninprogress = 0;
        $userinfo->assignmentprogress = 0;
        $userinfo->quizzescount = 0;
        $userinfo->quizcompleted = 0;
        $userinfo->quizinprogress = 0;
        $userinfo->quizprogress = 0;
        $userinfo->scormcount = 0;
        $userinfo->scormcompleted = 0;
        $userinfo->scorminprogress = 0;
        $userinfo->scormprogress = 0;
    }

    // User grades.
    if (!empty($enrolcourses)) {
        $params['gmuserid'] = $userid;
        $params['userid'] = $userid;

        $avggrade = $DB->get_record_sql("SELECT AVG(gg.finalgrade) AS finalgrade
                                FROM {grade_grades} gg
                                JOIN {grade_items} gi ON gi.id = gg.itemid
                                JOIN {course_modules} cm ON cm.course = gi.courseid
                                WHERE gi.itemtype = 'course' AND gg.userid = :userid
                                AND gi.courseid $csql $joinsql", $params);
        $userinfo->avggrade = !empty($avggrade->finalgrade) ? round($avggrade->finalgrade, 2) : 0;
        $avggradeper = $DB->get_record_sql("SELECT (SUM(gg.finalgrade)/SUM(gi.grademax))*100 AS avgper
                                FROM {grade_grades} gg
                                JOIN {grade_items} gi ON gi.id = gg.itemid
                                JOIN {course_modules} cm ON cm.course = gi.courseid
                                WHERE gi.itemtype = 'course' AND gg.userid = :userid
                                AND gi.courseid $csql $joinsql GROUP BY gi.grademax", $params, IGNORE_MULTIPLE);
        $userinfo->avggradepercentage = !empty($avggradeper->avgper) ? round($avggradeper->avgper, 0) : 0;
        $highestgrade = $DB->get_record_sql("SELECT MAX(gg.finalgrade) AS finalgrade
                                    FROM {grade_grades} gg
                                    JOIN {grade_items} gi ON gi.id = gg.itemid
                                    JOIN {course_modules} cm ON cm.course = gi.courseid
                                    WHERE gi.itemtype = 'course' AND gg.userid = :userid
                                    AND gi.courseid $csql $joinsql", $params);

        $lowestgrade = $DB->get_record_sql("SELECT MIN(gg.finalgrade) AS finalgrade
                                FROM {grade_grades} gg
                                JOIN {grade_items} gi ON gi.id = gg.itemid
                                JOIN {course_modules} cm ON cm.course = gi.courseid
                                WHERE gi.itemtype = 'course' AND gg.userid = :userid
                                AND gi.courseid $csql $joinsql", $params);
        $userinfo->highestgrade = !empty($highestgrade->finalgrade) ? round($highestgrade->finalgrade, 2) : 0;
        $userinfo->lowestgrade = !empty($lowestgrade->finalgrade) ? round($lowestgrade->finalgrade, 2) : 0;
    } else {
        $userinfo->avggrade = 0;
        $userinfo->highestgrade = 0;
        $userinfo->lowestgrade = 0;
        $userinfo->avggradepercentage = 0;
    }

    // Activity progress.
    $coursetimespentreport = $DB->get_record('block_learnerscript', ['type' => 'sql', 'name' => 'Timespent each course'],
                                '*', IGNORE_MULTIPLE);
    $reportcontenttypes = (new ls)->cr_listof_reporttypes($coursetimespentreport->id);
    $reportid = $coursetimespentreport->id;
    $reportinstance = $coursetimespentreport->id;
    $reporttype = key($reportcontenttypes);

    // Recent activities.

    $modules = $DB->get_fieldset_select('modules', 'name', '', ['visible' => 1]);

    $aliases = [];
    foreach ($modules as $modulename) {
        $aliases[] = $modulename;
        $activities[] = "'$modulename'";
        $fields1[] = "COALESCE($modulename.name,'')";
    }
    $activitynames = implode(',', $fields1);

    $moduleactivities = "SELECT recacc.cmid, m.name AS module,
                        CONCAT($activitynames) AS activityname, recacc.timeaccess
                        FROM {block_recentlyaccesseditems} recacc
                        JOIN {course_modules} cm ON cm.id = recacc.cmid
                        JOIN {modules} m ON cm.module = m.id
                        JOIN {course} c ON c.id = cm.course
                        JOIN {context} ctx ON ctx.instanceid = c.id
                        JOIN {role_assignments} ra ON ra.contextid = ctx.id
                        JOIN {user} u ON u.id = ra.userid AND recacc.userid = u.id
                        ";
    foreach ($aliases as $alias) {
        $moduleactivities .= " LEFT JOIN {".$alias."} $alias ON $alias.id = cm.instance AND m.name = '$alias'";
    }
    $moduleactivities .= " WHERE u.id = :userid and cm.visible = :cmvisible
                            AND cm.deletioninprogress = :deletioninprogress $joinsql
                            ORDER BY recacc.id DESC LIMIT 5";
    $recentactivities = $DB->get_records_sql($moduleactivities, ['userid' => $userid,
                    'cmvisible' => 1, 'deletioninprogress' => 0, 'gmuserid' => $userid]);

    $recentactivitieslist = [];
    $i = 0;
    foreach ($recentactivities as $k => $v) {
        $timaccess = (new ls)->strtime(time() - ($v->timeaccess));
        $timaccessremoveimg = preg_replace("/<img[^>]+\>/i", "", $timaccess);
        $timaccessarray = explode(" ", $timaccessremoveimg, 2);
        $recentactivitieslist[$i]['activityid'] = $v->cmid;
        $recentactivitieslist[$i]['activityname'] = $v->activityname;
        $recentactivitieslist[$i]['module'] = $v->module;
        $recentactivitieslist[$i]['timeccessday'] = $timaccessarray[0];
        $recentactivitieslist[$i]['timeccesstime'] = $timaccessarray[1];
        $i++;
    }

    // No login courses.
    $inactivedate = strtotime("-10 days");

    $accesscourses = $DB->get_records_sql("SELECT c.id, c.fullname AS course,
                            MAX(ul.timeaccess) AS timeaccess
                            FROM {user_lastaccess} ul
                            JOIN {course} c ON c.id = ul.courseid
                            WHERE ul.userid = :userid AND c.visible = :visible
                            GROUP BY c.id", ['userid' => $userid,
                            'visible' => 1, ]);
    foreach ($accesscourses as $c) {
        $courseacesslist[] = $c->id;
    }
    $accsql = '';
    $coureaccesssql = '';
    if (!empty($accesscourses)) {
        list($accsql, $params) = $DB->get_in_or_equal($courseacesslist, SQL_PARAMS_NAMED, 'param', false, false);
        $coureaccesssql .= " AND c.id $accsql";
    }
    $params['accessuserid'] = $userid;
    $params['accessvisible'] = 1;
    $params['timeaccess'] = $inactivedate;
    $params['userid'] = $userid;
    $params['accesstime'] = $inactivedate;
    $recentaccesscourses = $DB->get_records_sql("SELECT a.* FROM (
                        SELECT c.id AS courseid, c.fullname AS course,
                        MAX(ul.timeaccess) AS timeaccess
                        FROM {user_lastaccess} ul
                        JOIN {course} c ON c.id = ul.courseid
                        WHERE ul.userid = :accessuserid AND c.visible = :accessvisible
                        AND ul.timeaccess < :timeaccess GROUP BY c.id
                        UNION
                        SELECT c.id AS courseid, c.fullname AS course,
                        ra.timemodified AS timeaccess
                        FROM {course} c
                        JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                        JOIN {role_assignments}  ra ON ra.contextid = ctx.id
                        JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                        JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1 AND u.deleted = 0
                        WHERE c.visible = 1
                        AND u.id = :userid AND ra.timemodified < :accesstime $coureaccesssql) AS a
                        WHERE 1 = 1 ORDER BY a.timeaccess ASC
                        LIMIT 5", $params);
    $recentaccesscourseslist = [];
    $courseimage = '';
    if (!empty($recentaccesscourses)) {
        foreach ($recentaccesscourses as $r => $c) {
            $courseprogress = $DB->get_field_sql("SELECT ROUND((COUNT(distinct cc.course) / COUNT(DISTINCT c.id)) *100, 2)
                            AS progress
                            FROM {role_assignments} ra
                            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                            JOIN {course} c ON c.id = ctx.instanceid AND  c.visible = 1
                       LEFT JOIN {course_completions} cc ON cc.course = ctx.instanceid AND cc.userid = ra.userid
                             AND cc.timecompleted > 0 WHERE ra.userid = :userid AND c.id = :courseid",
                             ['userid' => $userid, 'courseid' => $c->courseid]);
            $courseaccess = (new ls)->strtime(time() - ($c->timeaccess));
            $courseaccessrmvimg = preg_replace("/<img[^>]+\>/i", "", $courseaccess);
            $courseaccessarray = explode(" ", $courseaccessrmvimg, 2);

            $course = $DB->get_record('course', ['id' => $c->courseid], '*', IGNORE_MISSING);
            $courseimage = \core_course\external\course_summary_exporter::get_course_image($course);
            if (!$courseimage) {
                $courseimage = $OUTPUT->get_generated_image_for_id($c->courseid);
            }
            $recentaccesscourseslist[] = ['course' => $c->course, 'courseid' => $c->courseid,
                        'timeaccessday' => $courseaccessarray[0], 'timeaccesstime' => $courseaccessarray[1],
                        'courseprogress' => ($courseprogress > 0) ? round($courseprogress, 0) : 0, ];
        }
    }

    // Coursesoverview.
    $coursesoverviewreport = $DB->get_record('block_learnerscript', ['type' => 'coursesoverview'], '*', IGNORE_MULTIPLE);
    $coursesoverviewreportid = $coursesoverviewreport->id;
    $coursesoverviewinstance = $coursesoverviewreport->id;
    $coursesoverviewtype = 'table';

    $userstatus = $DB->get_field_sql("SELECT suspended FROM {user} WHERE id = :userid",
                    ['userid' => $userid]);
    // User LMS access.
    $userlmsaccess = $DB->get_field('block_ls_userlmsaccess', 'logindata', ['userid' => $userid]);
    $lmsaccess = json_decode($userlmsaccess);
    if (!empty($lmsaccess)) {
        $daystimedata = (new ls)->get_daystimesslot();
        for ($i = 0; $i < count($daystimedata['timeslots']); $i++) {
            if (!empty($lmsaccess->data->$i)) {
                $lmsaccess->data->$i->label = $lmsaccess->data->$i->label;
                $lmsaccess->data->$i->data = $lmsaccess->data->$i->data;
            } else {
                $timelabels = new stdClass;
                $timelabels->label = $daystimedata['timings'][$i];
                $timelabels->data = [];
                $lmsaccess->data->$i = $timelabels;
            }
            for ($q = 0; $q < count($daystimedata['weekdayslist']); $q++) {
                if (is_object($lmsaccess->data->$i->data)) {
                    $lmsaccess->data->$i->data = (array)$lmsaccess->data->$i->data;
                }
                if (!empty($lmsaccess->data->$i->data[$q])) {
                    $lmsaccess->data->$i->data[$q] = $lmsaccess->data->$i->data[$q];
                } else {
                    $lmsaccess->data->$i->data[$q] = 0;
                }
            }
            ksort($lmsaccess->data->$i->data);
        }
        $lmsaccess->data = (array)$lmsaccess->data;
        ksort($lmsaccess->data);
    }
    echo $OUTPUT->render_from_template('block_reportdashboard/profilepage/profilepage',
                                            ['userinfo' => $userinfo,
                                                'userbadgesinfo' => $userbadgesinfo,
                                                'usercourseinfo' => $usercourseinfo,
                                                'coursesoverviewreportid' => $coursesoverviewreportid,
                                                'coursesoverviewinstance' => $coursesoverviewinstance,
                                                'coursesoverviewtype' => $coursesoverviewtype,
                                                'userid' => $userid,
                                                'reportid' => $reportid,
                                                'reportinstance' => $reportinstance,
                                                'reporttype' => $reporttype,
                                                'recentactivities' => $recentactivitieslist,
                                                'recentaccesscourses' => $recentaccesscourseslist,
                                                'courseimage' => $courseimage,
                                                'userlmsaccessdata' => $userlmsaccess,
                                                'userdata' => $data,
                                                'role' => $roleshortname,
                                                'contextlevel' => $contextlevel,
                                                'issiteadmin' => $siteadmin,
                                                'userstatus' => $userstatus,
                                                'studentrole' => ($roleshortname == $currentrole) ? $roleshortname : '',
                                                'dashboardlink' => $dashboardlink, ]);
    $PAGE->requires->js_call_amd('block_learnerscript/report', 'generate_plotgraph',
                                                [$lmsaccess]);
} else {
    throw new \moodle_exception('useridmissing', 'block_reportdashboard');
}
echo $OUTPUT->footer();
