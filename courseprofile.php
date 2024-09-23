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

$courseid = optional_param('filter_courses', SITEID, PARAM_INT);
$contextlevel = optional_param('contextlevel', 10, PARAM_INT);
$roleshortname = optional_param('role', '', PARAM_TEXT);

use block_learnerscript\local\ls;
use block_learnerscript\local\querylib;

global $PAGE, $OUTPUT, $DB, $SESSION, $USER;

require_login();

$context = context_system::instance();
$PAGE->set_pagetype('site-index');
$PAGE->set_pagelayout('course');
$PAGE->set_context($context);
$PAGE->set_url('/blocks/reportdashboard/courseprofile.php');
$PAGE->set_title(get_string('courseprofile', 'block_learnerscript'));

$PAGE->requires->css('/blocks/learnerscript/css/datatables/fixedHeader.dataTables.min.css');
$PAGE->requires->css('/blocks/learnerscript/css/datatables/responsive.dataTables.min.css');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->js_call_amd('block_reportdashboard/reportdashboard', 'profileuserfunction');
$PAGE->requires->css('/blocks/learnerscript/css/select2/select2.min.css');
$PAGE->requires->css('/blocks/learnerscript/css/datatables/jquery.dataTables.min.css');

$encourses = enrol_get_my_courses();
$currentusercourse = array_key_last($encourses);
$coursecontext = context_course::instance($currentusercourse);
if(!is_siteadmin()) {
    if (!has_capability('block/learnerscript:teacherreportsaccess', $coursecontext)) {
        throw new moodle_exception(get_string('badpermissions', 'block_learnerscript'));
    }
}
echo $OUTPUT->header();
$SESSION->ls_contextlevel = $contextlevel;
$SESSION->role = $roleshortname;
$role = $SESSION->role;
$siteadmin = is_siteadmin() || has_capability('block/learnerscript:managereports', $context);

$data = [];
$dashboardcourse = (is_siteadmin() || has_capability('block/learnerscript:managereports', $context)) ?
        $DB->get_records_select('course' , 'id <> :id' , ['id' => SITEID] , '' ,
    'id,fullname') : (new querylib)->get_rolecourses($USER->id, $SESSION->role, $SESSION->ls_contextlevel,
    SITEID, '', '');
foreach ($dashboardcourse as $selectedcourse) {
    if ($selectedcourse->id == $courseid) {
        $cpdashboard[] = ['id' => $selectedcourse->id, 'fullname' => $selectedcourse->fullname, 'selectedcourse' => 'selected'];
    } else {
        $cpdashboard[] = ['id' => $selectedcourse->id, 'fullname' => $selectedcourse->fullname, 'selectedcourse' => ''];
    }
}
if (!empty($cpdashboard)) {
    $data['courselist'] = array_values($cpdashboard);
    $data['coursedashboard'] = 1;
}

$courseinfo = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Course details.
$currentcoursecontext = context_course::instance($courseinfo->id);

$courseinfo->enrollmentscount = count_enrolled_users($currentcoursecontext, '');
$courseinfo->studentcount = count_enrolled_users($currentcoursecontext, 'block/learnerscript:learnerreportaccess');
$courseinfo->editingteachercount = count_enrolled_users($currentcoursecontext, 'block/learnerscript:teacherreportsaccess');

$courseinfo->otherenrolcount = $courseinfo->enrollmentscount -
                    ($courseinfo->studentcount + $courseinfo->editingteachercount);
$courseinfo->otherenrolcount = ($courseinfo->otherenrolcount > 0) ? $courseinfo->otherenrolcount : 0;

$courseactiivties = get_course_mods($courseinfo->id);
$courseinfo->activitiescount = count(array_keys($courseactiivties));

$totaltimespent = $DB->get_record_sql("SELECT SUM(timespent) AS timespent FROM {block_ls_coursetimestats}
                    WHERE 1 = 1 AND courseid = :courseid", ['courseid' => $courseid]);

$timespent = !empty($totaltimespent->timespent) ? (new ls)->strtime($totaltimespent->timespent) : 0;
$courseinfo->totaltimespent = $timespent;

$avgtimespent = $DB->get_record_sql("SELECT AVG(timespent) AS timespent FROM {block_ls_coursetimestats}
                    WHERE 1 = 1 AND courseid = :courseid", ['courseid' => $courseid]);

$avgtime = !empty($avgtimespent->timespent) ? (new ls)->strtime($avgtimespent->timespent) : 0;
$courseinfo->avgtimespent = $avgtime;

switch ($courseinfo->groupmode) {
    case SEPARATEGROUPS:
        $groupmode = get_string('groupsseparate', 'group');
        break;
    case VISIBLEGROUPS:
        $groupmode = get_string('groupsvisible', 'group');
        break;
    case NOGROUPS:
        $groupmode = get_string('groupsnone', 'group');
        break;
    default:
        break;
}
$courseinfo->groups = $groupmode ? $groupmode : '';
// Course progress.
$completionsql = "SELECT DISTINCT ra.userid
                FROM {course} c
                JOIN {context} ctx ON ctx.instanceid = c.id
                JOIN {role_assignments} ra ON ra.contextid = ctx.id
                JOIN {role} r ON r.id = ra.roleid
                JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1 AND u.deleted = 0
                AND u.suspended = 0
                JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
                WHERE ctx.contextlevel = 50 AND c.visible = 1
                AND c.id = :courseid AND cc.timecompleted IS NOT NULL";
$completioncount = $DB->get_records_sql($completionsql, ['courseid' => $courseid]);
$courseinfo->completioncount = count($completioncount);

$courseinfo->inprogresscount = $courseinfo->studentcount - $courseinfo->completioncount;
$courseinfo->progresspercent = !empty($courseinfo->studentcount) ?
                round(($courseinfo->completioncount / $courseinfo->studentcount) * 100) : 0;

// Course grades.
$avggrade = $DB->get_record_sql("SELECT AVG(gg.finalgrade) AS finalgrade FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gi.id = gg.itemid
                            WHERE gi.itemtype = 'course' AND gi.courseid = :courseid", ['courseid' => $courseid]);
$avggradeper = $DB->get_record_sql("SELECT (SUM(gg.finalgrade)/SUM(gi.grademax))*100 AS avgper
                                FROM {grade_grades} gg
                                JOIN {grade_items} gi ON gi.id = gg.itemid
                                WHERE gi.itemtype = 'course'
                                AND gi.courseid = :courseid", ['courseid' => $courseid]);
$courseinfo->avggradepercentage = !empty($avggradeper->avgper) ? round($avggradeper->avgper, 0) : 0;
$courseinfo->avggrade = !empty($avggrade->finalgrade) ? round($avggrade->finalgrade, 0) : 0;
$highestgrade = $DB->get_field_sql("SELECT MAX(gg.finalgrade) AS finalgrade FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gi.id = gg.itemid
                            WHERE gi.itemtype = 'course' AND gi.courseid = :courseid", ['courseid' => $courseid]);
$lowestgrade = $DB->get_field_sql("SELECT MIN(gg.finalgrade) AS finalgrade FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gi.id = gg.itemid
                            WHERE gi.itemtype = 'course' AND gi.courseid = :courseid", ['courseid' => $courseid]);
$courseinfo->highestgrade = !empty($highestgrade->finalgrade) ? round($highestgrade->finalgrade, 2) : 0;
$courseinfo->lowestgrade = !empty($lowestgrade->finalgrade) ? round($lowestgrade->finalgrade, 2) : 0;

// Activities.
$activitiescountsql = $DB->get_records_sql("SELECT cm.id FROM {course_modules} cm
                        JOIN {modules} m ON m.id = cm.module
                        WHERE 1 = 1 AND cm.course = :courseid
                        AND cm.visible = :cmvisible AND cm.deletioninprogress = :deletioninprogress",
                        ['courseid' => $courseid, 'cmvisible' => 1, 'deletioninprogress' => 0]);
$e = 0;
$f = 0;
foreach ($activitiescountsql as $activitycountid) {
    $activitycompletions = $DB->get_records('course_modules_completion', ['coursemoduleid' => $activitycountid->id]);
    if ((count($activitycompletions) == $courseinfo->studentcount) && !empty($courseinfo->studentcount)) {
        $e++;
    } else {
        $f++;
    }
}
$courseinfo->activitycompleted = $e;
$courseinfo->activityinprogress = $f;
$courseinfo->activityprogress = !empty($courseinfo->activitiescount) ? round(($e / $courseinfo->activitiescount) * 100) : 0;

// Assignments.
$assignmentscountsql = $DB->get_records_sql("SELECT cm.id FROM {course_modules} cm
                        JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                        WHERE 1 = 1 AND cm.course = :courseid
                        AND cm.visible = :cmvisible AND cm.deletioninprogress = :deletioninprogress",
                        ['courseid' => $courseid, 'cmvisible' => 1, 'deletioninprogress' => 0]);
$courseinfo->assignmentscount = count($assignmentscountsql);
$i = 0;
$j = 0;
foreach ($assignmentscountsql as $assigncountid) {
    $assignmodulecompletions = $DB->get_records('course_modules_completion', ['coursemoduleid' => $assigncountid->id]);
    if (count($assignmodulecompletions) == $courseinfo->studentcount && !empty($courseinfo->studentcount)) {
        $i++;
    } else {
        $j++;
    }
}
$courseinfo->assigncompleted = $i;
$courseinfo->assigninprogress = $j;
$courseinfo->assignmentprogress = !empty($courseinfo->assignmentscount) ? round(($i / $courseinfo->assignmentscount) * 100) : 0;
// Quizzes.
$quizzescountsql = $DB->get_records_sql("SELECT cm.id FROM {course_modules} cm
                        JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                        WHERE 1 = 1 AND cm.course = :courseid
                        AND cm.visible = :cmvisible AND cm.deletioninprogress = :deletioninprogress",
                        ['courseid' => $courseid, 'cmvisible' => 1, 'deletioninprogress' => 0]);
$courseinfo->quizzescount = count($quizzescountsql);
$a = 0;
$b = 0;
foreach ($quizzescountsql as $quizzescountid) {
    $quizmodulecompletions = $DB->get_records('course_modules_completion', ['coursemoduleid' => $quizzescountid->id]);
    if (count($quizmodulecompletions) == $courseinfo->studentcount && !empty($courseinfo->studentcount)) {
        $a++;
    } else {
        $b++;
    }
}
$courseinfo->quizcompleted = $a;
$courseinfo->quizinprogress = $b;
$courseinfo->quizprogress = !empty($courseinfo->quizzescount) ? round(($a / $courseinfo->quizzescount) * 100) : 0;
// Scorm.
$scormcountsql = $DB->get_records_sql("SELECT cm.id FROM {course_modules} cm
                        JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
                        WHERE 1 = 1 AND cm.course = :courseid
                        AND cm.visible = :cmvisible AND cm.deletioninprogress = :deletioninprogress",
                        ['courseid' => $courseid, 'cmvisible' => 1, 'deletioninprogress' => 0]);
$courseinfo->scormcount = count($scormcountsql);
$m = 0;
$n = 0;
foreach ($scormcountsql as $scormcountid) {
    $scormmodulecompletions = $DB->get_records('course_modules_completion', ['coursemoduleid' => $scormcountid->id]);
    if (count($scormmodulecompletions) == $courseinfo->studentcount && !empty($courseinfo->studentcount)) {
        $m++;
    } else {
        $n++;
    }
}
$courseinfo->scormcompleted = $m;
$courseinfo->scorminprogress = $n;
$courseinfo->scormprogress = !empty($courseinfo->scormcount) ? round(($m / $courseinfo->scormcount) * 100) : 0;

// Badges.
$badgeslist = $DB->get_records('badge', ['courseid' => $courseid], '', 'id, name');
$coursebadgesinfo = [];
foreach ($badgeslist as $badge) {
    $batchinstance = new badge($badge->id);
    $badgecontext = $batchinstance->get_context();
    $logourl = moodle_url::make_pluginfile_url($badgecontext->id, 'badges', 'badgeimage', $badge->id, '/', 'f3', false);
    $coursebadgesinfo[] = ['badgeid' => $badge->id, 'badgename' => $badge->name, 'badgeimage' => $logourl];
}

// Activity progress.
$activityprogressreport = $DB->get_record('block_learnerscript', ['type' => 'usercourses', 'name' => 'Activity Progress'],
                            '*', IGNORE_MULTIPLE);
$reportcontenttypes = (new ls)->cr_listof_reporttypes($activityprogressreport->id);
$reportid = $activityprogressreport->id;
$reportinstance = $activityprogressreport->id;
$reporttype = key($reportcontenttypes);

// Top learners.
$toplearnersreport = $DB->get_record('block_learnerscript', ['type' => 'usercourses', 'name' => 'Top Learners'],
                            'id', IGNORE_MULTIPLE);
$toplearnerreportid = $toplearnersreport->id;
$toplearnerreportinstance = $toplearnersreport->id;
$toplearnerreporttype = 'table';

// Activities.
$activitiesreport = $DB->get_record('block_learnerscript', ['type' => 'courseactivities'],
                            'id', IGNORE_MULTIPLE);
$activitiesreportid = $activitiesreport->id;
$activitiesreportinstance = $activitiesreport->id;
$activitiesreporttype = 'table';

// Timeline.
$currenttime = time();
$timelinesql = "SELECT a.* FROM (SELECT a.name, a.allowsubmissionsfromdate AS timestart, m.name AS module, cm.id
                                FROM {assign} a
                                JOIN {course_modules} cm ON cm.instance = a.id
                                JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                                WHERE 1 = 1 AND a.allowsubmissionsfromdate >= :assigntime
                                AND a.course = :courseid AND cm.visible = :cvisible
                                AND cm.deletioninprogress = :deletioninprogress
                                UNION
                                SELECT q.name, q.timeopen AS timestart, m.name AS module, cm.id
                                FROM {quiz} q
                                JOIN {course_modules} cm ON cm.instance = q.id
                                JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                                WHERE 1 = 1 AND q.timeopen >= :quiztime
                                AND q.course = :qcourseid AND cm.visible = :qcvisible
                                AND cm.deletioninprogress = :qdeletioninprogress
                                UNION
                                SELECT s.name, s.timeopen AS timestart, m.name AS module, cm.id
                                FROM {scorm} s
                                JOIN {course_modules} cm ON cm.instance = s.id
                                JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
                                WHERE 1 = 1 AND s.timeopen >= :scormtime
                                AND s.course = :scourseid AND cm.visible = :scvisible
                                AND cm.deletioninprogress = :sdeletioninprogress) AS a";
$timelinerecords = $DB->get_records_sql($timelinesql, ['courseid' => $courseid, 'qcourseid' => $courseid,
                    'scourseid' => $courseid, 'cvisible' => '1', 'deletioninprogress' => '0',
                    'qcvisible' => '1', 'qdeletioninprogress' => '0', 'scvisible' => '1',
                    'sdeletioninprogress' => '0', 'assigntime' => $currenttime,
                    'quiztime' => $currenttime, 'scormtime' => $currenttime, ]);
$timelinerecordslist = [];
foreach ($timelinerecords as $timelinerecord) {
    $timaccess = userdate($timelinerecord->timestart);
    $timelinerecordslist[] = ['cmid' => $timelinerecord->id,
    'activityname' => $timelinerecord->name, 'activitymodule' => $timelinerecord->module, 'timestart' => $timaccess, ];
}
echo $OUTPUT->render_from_template('block_reportdashboard/courseprofile/courseprofile',
                                        ['courseinfo' => $courseinfo,
                                                'reportid' => $reportid,
                                                'reporttype' => $reporttype,
                                                'reportinstance' => $reportinstance,
                                                'toplearnerreportid' => $toplearnerreportid,
                                                'toplearnerreporttype' => $toplearnerreporttype,
                                                'toplearnerreportinstance' => $toplearnerreportinstance,
                                                'activitiesreportid' => $activitiesreportid,
                                                'activitiesreporttype' => $activitiesreporttype,
                                                'activitiesreportinstance' => $activitiesreportinstance,
                                                'coursebadgesinfo' => $coursebadgesinfo,
                                                'coursedata' => $data,
                                                'courseid' => $courseid,
                                                'timelinerecordslist' => $timelinerecordslist,
                                                'role' => $roleshortname,
                                                'contextlevel' => $contextlevel,
                                            'issiteadmin' => $siteadmin,
                                            'studentrole' => ($roleshortname) ? get_config('block_learnerscript', 'role') : '', ]);
echo $OUTPUT->footer();
