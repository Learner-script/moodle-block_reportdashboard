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
 * Describe the module reportdashboard information
 *
 * @module     block_reportdashboard/reportdashboard
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
        'core/ajax',
        'block_learnerscript/report',
        'block_learnerscript/reportwidget',
        'block_learnerscript/schedule',
        'block_learnerscript/helper',
        'block_learnerscript/ajax',
        'block_learnerscript/select2/select2',
        'block_learnerscript/datatables/jquery.dataTables',
        'block_learnerscript/smartfilter',
        'block_learnerscript/flatpickr',
        'core/str',
        'jqueryui',
        'block_learnerscript/bootstrapnotify/bootstrapnotify',
    ],
    function($, Ajax, report, reportwidget, schedule, helper, ajax, select2, dataTable,
        smartfilter, flatpickr, Str) {
        return {
            init: function() {
                    $(document).ajaxStop(function() {
                         $(".loader").fadeOut("slow");
                    });
                    helper.Select2Ajax({});
                    $(".dashboardcourses").change(function() {
                        var courseid = $(this).val();
                        $(".report_courses").val(courseid);
                        reportwidget.DashboardTiles();
                        reportwidget.DashboardWidgets();
                        $(".viewmore").each(function() {
                            $(this).attr('href');
                        });
                    });

                $.ui.dialog.prototype._focusTabbable = $.noop;
                var DheaderPosition = $("#dashboard-header").position();
                $(".sidenav").offset({top: 0});
                $("#internalbsm").offset({top: DheaderPosition.top});
                /**
                 * Select2 Options
                 */
                $("select[data-select2='1']").select2();
                helper.Select2Ajax({
                    action: 'reportlist',
                    multiple: true
                });

                /** 
                 * Custom report report help text
                 */
                $(document).on('click', ".customreporthelptext", function() {
                    var reportid = $(this).data('reportid');
                    report.block_statistics_help(reportid);
                });

                /**
                 * Menu option for report widget on dashboard
                 */
                
                $(document).on('click', ".report_schedule", function(e) {
                    const reportSchedules = document.querySelectorAll('.report_schedule');

                    reportSchedules.forEach(function (element) {
                        element.addEventListener('click', function (e) {
                            const reportid = element.id.split('_')[2];
                            const instanceid = element.id.split('_')[3];
                            const method = element.getAttribute('data-method');

                            require(["block_reportdashboard/reportdashboard", "jqueryui"], function (reportdashboard) {
                                if (typeof reportdashboard[method] === 'function') {
                                    reportdashboard[method]({
                                        reportid: reportid,
                                        instanceid: instanceid
                                    });
                                } else {
                                    console.error(`Method ${method} does not exist on reportdashboard`);
                                }
                                e.preventDefault();
                                e.stopImmediatePropagation();
                                e.stopPropagation();
                            });
                        });
                    });
                });
               /**
                * Filter area
                */
                $(document).on('click', ".filterform #id_filter_clear", function() {
                    $(this).parents('.mform').trigger("reset");
                    var activityelement = $(this).parents('.mform').find('#id_filter_activities');
                    var instancelement = $(this).parents('.block_reportdashboard').find('.report_dashboard_container');
                    var reportid = instancelement.data('reportid');
                    var reporttype = instancelement.data('reporttype');
                    var instanceid = instancelement.data('blockinstance');
                    smartfilter.CourseActivities({courseid: 0, element: activityelement});
                    $(".filterform select[data-select2-ajax='1']").val('0').trigger('change');
                    $('.filterform')[0].reset();
                    $(".filterform #id_filter_clear").attr('disabled', 'disabled');
                    reportwidget.CreateDashboardwidget({reportid: reportid, reporttype: reporttype, instanceid: instanceid});
                });
                $(document).on('change', "select[name='filter_coursecategories']", function() {
                    var categoryid = this.value;
                    var courseelement = $(this).closest('.mform').find('#id_filter_courses');
                    if (courseelement.length != 0) {
                        smartfilter.categoryCourses({categoryid: categoryid, element: courseelement});
                    }
                });
                $(document).on('change', "select[name='filter_courses']", function() {
                    var courseid = this.value;
                    var activityelement = $(this).closest('.mform').find('#id_filter_activities');
                    if (activityelement.length != 0) {
                        smartfilter.CourseActivities({courseid: courseid, element: activityelement});
                    }
                });
                /**
                 * Duration Filter
                 */
                flatpickr('#customrange', {
                    mode: 'range',
                    onOpen: function(selectedDates, dateStr, instance) {
                        instance.clear();
                    },
                    onClose: function(selectedDates) {
                        $('#lsfstartdate').val(selectedDates[0].getTime() / 1000);
                        $('#lsfenddate').val((selectedDates[1].getTime() / 1000) + (60 * 60 * 24));
                        require(['block_learnerscript/reportwidget'], function() {
                            reportwidget.DashboardTiles();
                            reportwidget.DashboardWidgets();
                        });
                    }
                });
                /**
                 * Escape dropdown on click of window
                 * @param {object} event
                 */
                window.onclick = function(event) {
                    if (!event.target.matches('.dropbtn')) {
                        var dropdowns = document.getElementsByClassName("dropdown-content");
                        var i;
                        for (i = 0; i < dropdowns.length; i++) {
                            var openDropdown = dropdowns[i];
                            if ($(openDropdown).hasClass('show')) {
                                $(openDropdown).toggleClass('show');
                            }
                        }
                    }
                };
            },
            sendreportemail: function(args) {
                Str.get_strings([{
                    key: 'sendemail',
                    component: 'block_reportdashboard'
                }]).then(function(s) {
                    var url = M.cfg.wwwroot + '/blocks/learnerscript/ajax.php';
                    args.nodeContent = 'sendreportemail' + args.instanceid;
                    args.action = 'sendreportemail';
                    args.title = s;
                    var AjaxForms = require('block_learnerscript/ajaxforms');
                    AjaxForms.init(args, url);
                });
            },
            reportfilter: function(args) {
                var self = this;
                if ($('.report_filter_' + args.instanceid).length < 1) {
                    var promise = Ajax.call([{
                        methodname: 'block_learnerscript_reportfilter',
                        args: {
                            action: 'reportfilter',
                            reportid: args.reportid,
                            instance: args.instanceid
                        }
                    }]);
                    promise[0].done(function(resp) {
                        $('body').append("<div class='report_filter_" + args.instanceid +
                        "' style='display:none;'>" + resp + "</div>");
                        $("select[data-select2-ajax='1']").each(function() {
                            if (!$(this).hasClass('select2-hidden-accessible')) {
                                helper.Select2Ajax({});
                            }
                        });
                        self.reportFilterFormModal(args);
                         $('.filterform' + args.instanceid + ' .fitemtitle').hide();
                          $('.filterform' + args.instanceid + ' .felement').attr('style', 'margin:0');
                    });
                } else {
                    self.reportFilterFormModal(args);
                }
            },
            reportFilterFormModal: function(args) {
                Str.get_string('reportfilters', 'block_reportdashboard'
                ).then(function(s) {
                    var titleimg = "<img class='dialog_title_icon' alt='Filter' src='" +
                        M.util.image_url("reportfilter", "block_reportdashboard") + "'/>";
                    $(".report_filter_" + args.instanceid).dialog({
                        title: s,
                        dialogClass: 'reportfilter-popup',
                        modal: true,
                        resizable: true,
                        autoOpen: true,
                        draggable: false,
                        width: 420,
                        height: 'auto',
                        appendTo: "#inst" + args.instanceid,
                        position: {
                            my: "center",
                            at: "center",
                            of: "#inst" + args.instanceid,
                            within: "#inst" + args.instanceid
                        },
                        open: function() {
                        $(this).closest(".ui-dialog")
                            .find(".ui-dialog-titlebar-close")
                            .removeClass("ui-dialog-titlebar-close")
                            .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                            var Closebutton = $('.ui-icon-closethick').parent();
                            $(Closebutton).attr({
                                "title": "Close"
                            });

                        $(this).closest(".ui-dialog")
                            .find('.ui-dialog-title').html(titleimg + s);

                        /* Submit button */
                        $(".report_filter_" + args.instanceid + " form  #id_filter_apply").click(function(e) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            if ($("#reportcontainer" + args.instanceid).html().length > 0) {
                                args.reporttype = $("#reportcontainer" + args.instanceid).data('reporttype');
                            } else {
                                args.reporttype = $("#plotreportcontainer" + args.instanceid).data('reporttype');
                            }
                            args.container = '#reporttype_' + args.reportid;

                            require(['block_learnerscript/reportwidget'], function(reportwidget) {
                                reportwidget.CreateDashboardwidget({reportid: args.reportid,
                                                             reporttype: args.reporttype,
                                                             instanceid: args.instanceid});
                                $(".report_filter_" + args.instanceid).dialog('close');
                            });
                            $(".report_filter_" + args.instanceid + " form #id_filter_clear").removeAttr('disabled');
                        });
                    }
                });
                $(".report_filter_" + args.instanceid + " form #id_filter_clear").click(function(e) {
                    e.preventDefault();
                    $(".filterform" + args.instanceid + " select[data-select2-ajax='1']").val('0').trigger('change');
                    $('.filterform' + args.instanceid).trigger("reset");
                    require(['block_learnerscript/reportwidget'], function(reportwidget) {
                        reportwidget.DashboardWidgets(args);
                        $(".report_filter_" + args.instanceid).dialog('close');
                    });
                    $(".report_filter_" + args.instanceid).dialog('close');
                });
            });
            },
            DeleteWidget: function(args) {
                Str.get_string('deletewidget', 'block_reportdashboard'
                ).then(function(s) {
                    $("#delete_dialog" + args.instanceid).dialog({
                        resizable: true,
                        autoOpen: true,
                        width: 460,
                        height: 210,
                        title: s,
                        modal: true,
                        appendTo: "#inst" + args.instanceid,
                        position: {
                            my: "center",
                            at: "center",
                            of: "#inst" + args.instanceid,
                            within: "#inst" + args.instanceid
                        },
                        open: function() {
                            $(this).closest(".ui-dialog")
                                .find(".ui-dialog-titlebar-close")
                                .removeClass("ui-dialog-titlebar-close")
                                .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                                var Closebutton = $('.ui-icon-closethick').parent();
                                $(Closebutton).attr({
                                    "title": "Close"
                                });
                        }
                    });
                });
            },
            /**
             * Schedule report form in popup in dashboard
             * @param  {object} args reportid
             */
            schreportform: function(args) {
                Str.get_string('schedulereport', 'block_reportdashboard'
                ).then(function(s) {
                    var url = M.cfg.wwwroot + '/blocks/learnerscript/ajax.php';
                    args.title = s;
                    args.nodeContent = 'schreportform' + args.instanceid;
                    args.action = 'schreportform';
                    args.courseid = $('[name="filter_courses"]').val();
                    var AjaxForms = require('block_learnerscript/ajaxforms');
                    AjaxForms.init(args, url);
                });
            }
        };
    });
