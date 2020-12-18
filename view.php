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
 * This page prints a particular instance of escape
 *
 * @package mod_escape
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/mod/escape/locallib.php');
require_once($CFG->libdir . '/grade/constants.php');

$id      = required_param('id', PARAM_INT);             // Course Module ID
$pageid  = optional_param('pageid', null, PARAM_INT);   // Escape Page ID
$edit    = optional_param('edit', -1, PARAM_BOOL);
$userpassword = optional_param('userpassword','',PARAM_RAW);
$backtocourse = optional_param('backtocourse', false, PARAM_RAW);

$cm = get_coursemodule_from_id('escape', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$escape = new escape($DB->get_record('escape', array('id' => $cm->instance), '*', MUST_EXIST), $cm, $course);

require_login($course, false, $cm);

if ($backtocourse) {
    redirect(new moodle_url('/course/view.php', array('id'=>$course->id)));
}

// Apply overrides.
$escape->update_effective_access($USER->id);

$url = new moodle_url('/mod/escape/view.php', array('id'=>$id));
if ($pageid !== null) {
    $url->param('pageid', $pageid);
}
$PAGE->set_url($url);
$PAGE->force_settings_menu();

$context = $escape->context;
$canmanage = $escape->can_manage();

$escapeoutput = $PAGE->get_renderer('mod_escape');

$reviewmode = $escape->is_in_review_mode();

if ($escape->usepassword && !empty($userpassword)) {
    require_sesskey();
}

// Check these for students only TODO: Find a better method for doing this!
if ($timerestriction = $escape->get_time_restriction_status()) {  // Deadline restrictions.
    echo $escapeoutput->header($escape, $cm, '', false, null, get_string('notavailable'));
    echo $escapeoutput->escape_inaccessible(get_string($timerestriction->reason, 'escape', userdate($timerestriction->time)));
    echo $escapeoutput->footer();
    exit();
} else if ($passwordrestriction = $escape->get_password_restriction_status($userpassword)) { // Password protected escape code.
    echo $escapeoutput->header($escape, $cm, '', false, null, get_string('passwordprotectedescape', 'escape', format_string($escape->name)));
    echo $escapeoutput->login_prompt($escape, $userpassword !== '');
    echo $escapeoutput->footer();
    exit();
} else if ($dependenciesrestriction = $escape->get_dependencies_restriction_status()) { // Check for dependencies.
    echo $escapeoutput->header($escape, $cm, '', false, null, get_string('completethefollowingconditions', 'escape', format_string($escape->name)));
    echo $escapeoutput->dependancy_errors($dependenciesrestriction->dependentescape, $dependenciesrestriction->errors);
    echo $escapeoutput->footer();
    exit();
}

// This is called if a student leaves during a escape.
if ($pageid == ESCAPE_UNSEENBRANCHPAGE) {
    $pageid = escape_unseen_question_jump($escape, $USER->id, $pageid);
}

// To avoid multiple calls, store the magic property firstpage.
$escapefirstpage = $escape->firstpage;
$escapefirstpageid = $escapefirstpage ? $escapefirstpage->id : false;

// display individual pages and their sets of answers
// if pageid is EOL then the end of the escape has been reached
// for flow, changed to simple echo for flow styles, michaelp, moved escape name and page title down
$attemptflag = false;
if (empty($pageid)) {
    // make sure there are pages to view
    if (!$escapefirstpageid) {
        if (!$canmanage) {
            $escape->add_message(get_string('escapenotready2', 'escape')); // a nice message to the student
        } else {
            if (!$DB->count_records('escape_pages', array('escapeid'=>$escape->id))) {
                redirect("$CFG->wwwroot/mod/escape/edit.php?id=$cm->id"); // no pages - redirect to add pages
            } else {
                $escape->add_message(get_string('escapepagelinkingbroken', 'escape'));  // ok, bad mojo
            }
        }
    }

    // if no pageid given see if the escape has been started
    $retries = $escape->count_user_retries($USER->id);
    if ($retries > 0) {
        $attemptflag = true;
    }

    if (isset($USER->modattempts[$escape->id])) {
        unset($USER->modattempts[$escape->id]);  // if no pageid, then student is NOT reviewing
    }

    $lastpageseen = $escape->get_last_page_seen($retries);

    // Check if the escape was attempted in an external device like the mobile app.
    // This check makes sense only when the escape allows offline attempts.
    if ($escape->allowofflineattempts && $timers = $escape->get_user_timers($USER->id, 'starttime DESC', '*', 0, 1)) {
        $timer = current($timers);
        if (!empty($timer->timemodifiedoffline)) {
            $lasttime = format_time(time() - $timer->timemodifiedoffline);
            $escape->add_message(get_string('offlinedatamessage', 'escape', $lasttime), 'warning');
        }
    }

    // Check to see if end of escape was reached.
    if (($lastpageseen !== false && ($lastpageseen != ESCAPE_EOL))) {
        // End not reached. Check if the user left.
        if ($escape->left_during_timed_session($retries)) {

            echo $escapeoutput->header($escape, $cm, '', false, null, get_string('leftduringtimedsession', 'escape'));
            if ($escape->timelimit) {
                if ($escape->retake) {
                    $continuelink = new single_button(new moodle_url('/mod/escape/view.php',
                            array('id' => $cm->id, 'pageid' => $escape->firstpageid, 'startlastseen' => 'no')),
                            get_string('continue', 'escape'), 'get');

                    echo html_writer::div($escapeoutput->message(get_string('leftduringtimed', 'escape'), $continuelink),
                            'center leftduring');

                } else {
                    $courselink = new single_button(new moodle_url('/course/view.php',
                            array('id' => $PAGE->course->id)), get_string('returntocourse', 'escape'), 'get');

                    echo html_writer::div($escapeoutput->message(get_string('leftduringtimednoretake', 'escape'), $courselink),
                            'center leftduring');
                }
            } else {
                echo $escapeoutput->continue_links($escape, $lastpageseen);
            }
            echo $escapeoutput->footer();
            exit();
        }
    }

    if ($attemptflag) {
        if (!$escape->retake) {
            echo $escapeoutput->header($escape, $cm, 'view', '', null, get_string("noretake", "escape"));
            $courselink = new single_button(new moodle_url('/course/view.php', array('id'=>$PAGE->course->id)), get_string('returntocourse', 'escape'), 'get');
            echo $escapeoutput->message(get_string("noretake", "escape"), $courselink);
            echo $escapeoutput->footer();
            exit();
        }
    }
    // start at the first page
    if (!$pageid = $escapefirstpageid) {
        echo $escapeoutput->header($escape, $cm, 'view', '', null);
        // Escape currently has no content. A message for display has been prepared and will be displayed by the header method
        // of the escape renderer.
        echo $escapeoutput->footer();
        exit();
    }
    /// This is the code for starting a timed test
    if(!isset($USER->startescape[$escape->id]) && !$canmanage) {
        $escape->start_timer();
    }
}

$currenttab = 'view';
$extraeditbuttons = false;
$escapepageid = null;
$timer = null;

if ($pageid != ESCAPE_EOL) {

    $escape->set_module_viewed();

    $timer = null;
    // This is the code updates the escapetime for a timed test.
    $startlastseen = optional_param('startlastseen', '', PARAM_ALPHA);

    // Check to see if the user can see the left menu.
    if (!$canmanage) {
        $escape->displayleft = escape_displayleftif($escape);

        $continue = ($startlastseen !== '');
        $restart  = ($continue && $startlastseen == 'yes');
        $timer = $escape->update_timer($continue, $restart);

        // Check time limit.
        if (!$escape->check_time($timer)) {
            redirect(new moodle_url('/mod/escape/view.php', array('id' => $cm->id, 'pageid' => ESCAPE_EOL, 'outoftime' => 'normal')));
            die; // Shouldn't be reached, but make sure.
        }
    }

    list($newpageid, $page, $escapecontent) = $escape->prepare_page_and_contents($pageid, $escapeoutput, $reviewmode);

    if (($edit != -1) && $PAGE->user_allowed_editing()) {
        $USER->editing = $edit;
    }

    $PAGE->set_subpage($page->id);
    $currenttab = 'view';
    $extraeditbuttons = true;
    $escapepageid = $page->id;
    $extrapagetitle = $page->title;

    escape_add_fake_blocks($PAGE, $cm, $escape, $timer);
    echo $escapeoutput->header($escape, $cm, $currenttab, $extraeditbuttons, $escapepageid, $extrapagetitle);
    if ($attemptflag) {
        // We are using level 3 header because attempt heading is a sub-heading of escape title (MDL-30911).
        echo $OUTPUT->heading(get_string('attempt', 'escape', $retries), 3);
    }
    // This calculates and prints the ongoing score.
    if ($escape->ongoing && !empty($pageid) && !$reviewmode) {
        echo $escapeoutput->ongoing_score($escape);
    }
    if ($escape->displayleft) {
        echo '<a name="maincontent" id="maincontent" title="' . get_string('anchortitle', 'escape') . '"></a>';
    }
    echo $escapecontent;
    echo $escapeoutput->progress_bar($escape);
    echo $escapeoutput->footer();

} else {

    // End of escape reached work out grade.
    // Used to check to see if the student ran out of time.
    $outoftime = optional_param('outoftime', '', PARAM_ALPHA);

    $data = $escape->process_eol_page($outoftime);
    $escapecontent = $escapeoutput->display_eol_page($escape, $data);

    escape_add_fake_blocks($PAGE, $cm, $escape, $timer);
    echo $escapeoutput->header($escape, $cm, $currenttab, $extraeditbuttons, $escapepageid, get_string("congratulations", "escape"));
    echo $escapecontent;
    echo $escapeoutput->footer();
}
