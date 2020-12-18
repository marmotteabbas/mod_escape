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
 * Displays the escape statistics.
 *
 * @package mod_escape
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/escape/locallib.php');

$id     = required_param('id', PARAM_INT);    // Course Module ID
$pageid = optional_param('pageid', null, PARAM_INT);    // Escape Page ID
$action = optional_param('action', 'reportoverview', PARAM_ALPHA);  // action to take
$nothingtodisplay = false;

$cm = get_coursemodule_from_id('escape', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$escape = new escape($DB->get_record('escape', array('id' => $cm->instance), '*', MUST_EXIST));

require_login($course, false, $cm);

$currentgroup = groups_get_activity_group($cm, true);

$context = context_module::instance($cm->id);
require_capability('mod/escape:viewreports', $context);

$url = new moodle_url('/mod/escape/report.php', array('id'=>$id));
$url->param('action', $action);
if ($pageid !== null) {
    $url->param('pageid', $pageid);
}
$PAGE->set_url($url);
if ($action == 'reportoverview') {
    $PAGE->navbar->add(get_string('reports', 'escape'));
    $PAGE->navbar->add(get_string('overview', 'escape'));
}

$escapeoutput = $PAGE->get_renderer('mod_escape');

if ($action === 'delete') {
    /// Process any form data before fetching attempts, grades and times
    if (has_capability('mod/escape:edit', $context) and $form = data_submitted() and confirm_sesskey()) {
    /// Cycle through array of userids with nested arrays of tries
        if (!empty($form->attempts)) {
            foreach ($form->attempts as $userid => $tries) {
                // Modifier IS VERY IMPORTANT!  What does it do?
                //      Well, it is for when you delete multiple attempts for the same user.
                //      If you delete try 1 and 3 for a user, then after deleting try 1, try 3 then
                //      becomes try 2 (because try 1 is gone and all tries after try 1 get decremented).
                //      So, the modifier makes sure that the submitted try refers to the current try in the
                //      database - hope this all makes sense :)
                $modifier = 0;

                foreach ($tries as $try => $junk) {
                    $try -= $modifier;

                /// Clean up the timer table by removing using the order - this is silly, it should be linked to specific attempt (skodak)
                    $timers = $escape->get_user_timers($userid, 'starttime', 'id', $try, 1);
                    if ($timers) {
                        $timer = reset($timers);
                        $DB->delete_records('escape_timer', array('id' => $timer->id));
                    }

                    $params = array ("userid" => $userid, "escapeid" => $escape->id);
                    // Remove the grade from the grades tables - this is silly, it should be linked to specific attempt (skodak).
                    $grades = $DB->get_records_sql("SELECT id FROM {escape_grades}
                                                     WHERE userid = :userid AND escapeid = :escapeid
                                                  ORDER BY completed", $params, $try, 1);

                    if ($grades) {
                        $grade = reset($grades);
                        $DB->delete_records('escape_grades', array('id' => $grade->id));
                    }

                /// Remove attempts and update the retry number
                    $DB->delete_records('escape_attempts', array('userid' => $userid, 'escapeid' => $escape->id, 'retry' => $try));
                    $DB->execute("UPDATE {escape_attempts} SET retry = retry - 1 WHERE userid = ? AND escapeid = ? AND retry > ?", array($userid, $escape->id, $try));

                /// Remove seen branches and update the retry number
                    $DB->delete_records('escape_branch', array('userid' => $userid, 'escapeid' => $escape->id, 'retry' => $try));
                    $DB->execute("UPDATE {escape_branch} SET retry = retry - 1 WHERE userid = ? AND escapeid = ? AND retry > ?", array($userid, $escape->id, $try));

                /// update central gradebook
                    escape_update_grades($escape, $userid);

                    $modifier++;
                }
            }
        }
    }
    redirect(new moodle_url($PAGE->url, array('action'=>'reportoverview')));

} else if ($action === 'reportoverview') {
    /**************************************************************************
    this action is for default view and overview view
    **************************************************************************/

    // Get the table and data for build statistics.
    list($table, $data) = escape_get_overview_report_table_and_data($escape, $currentgroup);

    if ($table === false) {
        echo $escapeoutput->header($escape, $cm, $action, false, null, get_string('noescapeattempts', 'escape'));
        if (!empty($currentgroup)) {
            $groupname = groups_get_group_name($currentgroup);
            echo $OUTPUT->notification(get_string('noescapeattemptsgroup', 'escape', $groupname));
        } else {
            echo $OUTPUT->notification(get_string('noescapeattempts', 'escape'));
        }
        groups_print_activity_menu($cm, $url);
        echo $OUTPUT->footer();
        exit();
    }

    echo $escapeoutput->header($escape, $cm, $action, false, null, get_string('overview', 'escape'));
    groups_print_activity_menu($cm, $url);

    $course_context = context_course::instance($course->id);
    if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
        $seeallgradeslink = new moodle_url('/grade/report/grader/index.php', array('id'=>$course->id));
        $seeallgradeslink = html_writer::link($seeallgradeslink, get_string('seeallcoursegrades', 'grades'));
        echo $OUTPUT->box($seeallgradeslink, 'allcoursegrades');
    }

    // Print it all out!
    if (has_capability('mod/escape:edit', $context)) {
        echo  "<form id=\"mod-escape-report-form\" method=\"post\" action=\"report.php\">\n
               <input type=\"hidden\" name=\"sesskey\" value=\"".sesskey()."\" />\n
               <input type=\"hidden\" name=\"id\" value=\"$cm->id\" />\n";
    }

    echo html_writer::table($table);

    if (has_capability('mod/escape:edit', $context)) {
        $checklinks  = '<a id="checkall" href="#">'.get_string('selectall').'</a> / ';
        $checklinks .= '<a id="checknone" href="#">'.get_string('deselectall').'</a>';
        $checklinks .= html_writer::label('action', 'menuaction', false, array('class' => 'accesshide'));
        $options = array('delete' => get_string('deleteselected'));
        $attributes = array('id' => 'actionid', 'class' => 'custom-select m-l-1');
        $checklinks .= html_writer::select($options, 'action', 0, array('' => 'choosedots'), $attributes);
        $PAGE->requires->js_amd_inline("
        require(['jquery'], function($) {
            $('#actionid').change(function() {
                $('#mod-escape-report-form').submit();
            });
            $('#checkall').click(function(e) {
                $('#mod-escape-report-form').find('input:checkbox').prop('checked', true);
                e.preventDefault();
            });
            $('#checknone').click(function(e) {
                $('#mod-escape-report-form').find('input:checkbox').prop('checked', false);
                e.preventDefault();
            });
        });");
        echo $OUTPUT->box($checklinks, 'center');
        echo '</form>';
    }

    // Calculate the Statistics.
    if ($data->avetime == null) {
        $data->avetime = get_string("notcompleted", "escape");
    } else {
        $data->avetime = format_float($data->avetime / $data->numofattempts, 0);
        $data->avetime = format_time($data->avetime);
    }
    if ($data->hightime == null) {
        $data->hightime = get_string("notcompleted", "escape");
    } else {
        $data->hightime = format_time($data->hightime);
    }
    if ($data->lowtime == null) {
        $data->lowtime = get_string("notcompleted", "escape");
    } else {
        $data->lowtime = format_time($data->lowtime);
    }

    if ($data->escapescored) {
        if ($data->numofattempts == 0) {
            $data->avescore = get_string("notcompleted", "escape");
        } else {
            $data->avescore = format_float($data->avescore, 2) . '%';
        }
        if ($data->highscore === null) {
            $data->highscore = get_string("notcompleted", "escape");
        } else {
            $data->highscore .= '%';
        }
        if ($data->lowscore === null) {
            $data->lowscore = get_string("notcompleted", "escape");
        } else {
            $data->lowscore .= '%';
        }

        // Display the full stats for the escape.
        echo $OUTPUT->heading(get_string('escapestats', 'escape'), 3);
        $stattable = new html_table();
        $stattable->head = array(get_string('averagescore', 'escape'), get_string('averagetime', 'escape'),
                                get_string('highscore', 'escape'), get_string('lowscore', 'escape'),
                                get_string('hightime', 'escape'), get_string('lowtime', 'escape'));
        $stattable->align = array('center', 'center', 'center', 'center', 'center', 'center');
        $stattable->wrap = array('nowrap', 'nowrap', 'nowrap', 'nowrap', 'nowrap', 'nowrap');
        $stattable->attributes['class'] = 'standardtable generaltable';
        $stattable->data[] = array($data->avescore, $data->avetime, $data->highscore, $data->lowscore, $data->hightime, $data->lowtime);

    } else {
        // Display simple stats for the escape.
        echo $OUTPUT->heading(get_string('escapestats', 'escape'), 3);
        $stattable = new html_table();
        $stattable->head = array(get_string('averagetime', 'escape'), get_string('hightime', 'escape'),
                                get_string('lowtime', 'escape'));
        $stattable->align = array('center', 'center', 'center');
        $stattable->wrap = array('nowrap', 'nowrap', 'nowrap');
        $stattable->attributes['class'] = 'standardtable generaltable';
        $stattable->data[] = array($data->avetime, $data->hightime, $data->lowtime);
    }

    echo html_writer::table($stattable);
} else if ($action === 'reportdetail') {
    /**************************************************************************
    this action is for a student detailed view and for the general detailed view

    General flow of this section of the code
    1.  Generate a object which holds values for the statistics for each question/answer
    2.  Cycle through all the pages to create a object.  Foreach page, see if the student actually answered
        the page.  Then process the page appropriatly.  Display all info about the question,
        Highlight correct answers, show how the user answered the question, and display statistics
        about each page
    3.  Print out info about the try (if needed)
    4.  Print out the object which contains all the try info

**************************************************************************/
    echo $escapeoutput->header($escape, $cm, $action, false, null, get_string('detailedstats', 'escape'));
    groups_print_activity_menu($cm, $url);

    $course_context = context_course::instance($course->id);
    if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
        $seeallgradeslink = new moodle_url('/grade/report/grader/index.php', array('id'=>$course->id));
        $seeallgradeslink = html_writer::link($seeallgradeslink, get_string('seeallcoursegrades', 'grades'));
        echo $OUTPUT->box($seeallgradeslink, 'allcoursegrades');
    }

    $formattextdefoptions = new stdClass;
    $formattextdefoptions->para = false;  //I'll use it widely in this page
    $formattextdefoptions->overflowdiv = true;

    $userid = optional_param('userid', null, PARAM_INT); // if empty, then will display the general detailed view
    $try    = optional_param('try', null, PARAM_INT);

    list($answerpages, $userstats) = escape_get_user_detailed_report_data($escape, $userid, $try);

    /// actually start printing something
    $table = new html_table();
    $table->wrap = array();
    $table->width = "60%";
    if (!empty($userid)) {
        // if looking at a students try, print out some basic stats at the top

            // print out users name
            //$headingobject->lastname = $students[$userid]->lastname;
            //$headingobject->firstname = $students[$userid]->firstname;
            //$headingobject->attempt = $try + 1;
            //print_heading(get_string("studentattemptescape", "escape", $headingobject));
        echo $OUTPUT->heading(get_string('attempt', 'escape', $try+1), 3);

        $table->head = array();
        $table->align = array('right', 'left');
        $table->attributes['class'] = 'generaltable';

        if (empty($userstats->gradeinfo)) {
            $table->align = array("center");

            $table->data[] = array(get_string("notcompleted", "escape"));
        } else {
            $user = $DB->get_record('user', array('id' => $userid));

            $gradeinfo = escape_grade($escape, $try, $user->id);

            $table->data[] = array(get_string('name').':', $OUTPUT->user_picture($user, array('courseid'=>$course->id)).fullname($user, true));
            $table->data[] = array(get_string("timetaken", "escape").":", format_time($userstats->timetotake));
            $table->data[] = array(get_string("completed", "escape").":", userdate($userstats->completed));
            $table->data[] = array(get_string('rawgrade', 'escape').':', $userstats->gradeinfo->earned.'/'.$userstats->gradeinfo->total);
            $table->data[] = array(get_string("grade", "escape").":", $userstats->grade."%");
        }
        echo html_writer::table($table);

        // Don't want this class for later tables
        $table->attributes['class'] = '';
    }

    foreach ($answerpages as $page) {
        $table->align = array('left', 'left');
        $table->size = array('70%', null);
        $table->attributes['class'] = 'generaltable';
        unset($table->data);
        if ($page->grayout) { // set the color of text
            $fontstart = html_writer::start_tag('span', array('class' => 'dimmed_text'));
            $fontend = html_writer::end_tag('span');
            $fontstart2 = $fontstart;
            $fontend2 = $fontend;
        } else {
            $fontstart = '';
            $fontend = '';
            $fontstart2 = '';
            $fontend2 = '';
        }

        $table->head = array($fontstart2.$page->qtype.": ".format_string($page->title).$fontend2, $fontstart2.get_string("classstats", "escape").$fontend2);
        $table->data[] = array($fontstart.get_string("question", "escape").": <br />".$fontend.$fontstart2.$page->contents.$fontend2, " ");
        $table->data[] = array($fontstart.get_string("answer", "escape").":".$fontend, ' ');
        // apply the font to each answer
        if (!empty($page->answerdata) && !empty($page->answerdata->answers)) {
            foreach ($page->answerdata->answers as $answer){
                $modified = array();
                foreach ($answer as $single) {
                    // need to apply a font to each one
                    $modified[] = $fontstart2.$single.$fontend2;
                }
                $table->data[] = $modified;
            }
            if (isset($page->answerdata->response)) {
                $table->data[] = array($fontstart.get_string("response", "escape").": <br />".$fontend
                        .$fontstart2.$page->answerdata->response.$fontend2, " ");
            }
            $table->data[] = array($page->answerdata->score, " ");
        } else {
            $table->data[] = array(get_string('didnotanswerquestion', 'escape'), " ");
        }
        echo html_writer::start_tag('div', array('class' => 'no-overflow'));
        echo html_writer::table($table);
        echo html_writer::end_tag('div');
    }
} else {
    print_error('unknowaction');
}

/// Finish the page
echo $OUTPUT->footer();
