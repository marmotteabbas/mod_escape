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
 * Action for processing page answers by users
 *
 * @package mod_escape
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

/** Require the specific libraries */
require_once("../../config.php");
require_once($CFG->dirroot.'/mod/escape/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('escape', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$escape = new escape($DB->get_record('escape', array('id' => $cm->instance), '*', MUST_EXIST), $cm, $course);

require_login($course, false, $cm);
require_sesskey();

// Apply overrides.
$escape->update_effective_access($USER->id);

$context = $escape->context;
$canmanage = $escape->can_manage();
$escapeoutput = $PAGE->get_renderer('mod_escape');

$url = new moodle_url('/mod/escape/continue.php', array('id'=>$cm->id));
$PAGE->set_url($url);
$PAGE->set_pagetype('mod-escape-view');
$PAGE->navbar->add(get_string('continue', 'escape'));

// This is the code updates the escape time for a timed test
// get time information for this user
if (!$canmanage) {
    $escape->displayleft = escape_displayleftif($escape);
    $timer = $escape->update_timer();
    if (!$escape->check_time($timer)) {
        redirect(new moodle_url('/mod/escape/view.php', array('id' => $cm->id, 'pageid' => ESCAPE_EOL, 'outoftime' => 'normal')));
        die; // Shouldn't be reached, but make sure.
    }
} else {
    $timer = new stdClass;
}

// record answer (if necessary) and show response (if none say if answer is correct or not)
$page = $escape->load_page(required_param('pageid', PARAM_INT));

$reviewmode = $escape->is_in_review_mode();

// Process the page responses.
$result = $escape->process_page_responses($page);

if ($result->nodefaultresponse || $result->inmediatejump) {
    // Don't display feedback or force a redirecto to newpageid.
    redirect(new moodle_url('/mod/escape/view.php', array('id'=>$cm->id,'pageid'=>$result->newpageid)));
}

// Set Messages.
$escape->add_messages_on_page_process($page, $result, $reviewmode);

$PAGE->set_url('/mod/escape/view.php', array('id' => $cm->id, 'pageid' => $page->id));
$PAGE->set_subpage($page->id);

/// Print the header, heading and tabs
escape_add_fake_blocks($PAGE, $cm, $escape, $timer);
echo $escapeoutput->header($escape, $cm, 'view', true, $page->id, get_string('continue', 'escape'));

if ($escape->displayleft) {
    echo '<a name="maincontent" id="maincontent" title="'.get_string('anchortitle', 'escape').'"></a>';
}
// This calculates and prints the ongoing score message
if ($escape->ongoing && !$reviewmode) {
    echo $escapeoutput->ongoing_score($escape);
}
if (!$reviewmode) {
    echo format_text($result->feedback, FORMAT_MOODLE, array('context' => $context, 'noclean' => true));
}

// User is modifying attempts - save button and some instructions
if (isset($USER->modattempts[$escape->id])) {
    $content = $OUTPUT->box(get_string("gotoendofescape", "escape"), 'center');
    $content .= $OUTPUT->box(get_string("or", "escape"), 'center');
    $content .= $OUTPUT->box(get_string("continuetonextpage", "escape"), 'center');
    $url = new moodle_url('/mod/escape/view.php', array('id' => $cm->id, 'pageid' => ESCAPE_EOL));
    echo $content . $OUTPUT->single_button($url, get_string('finish', 'escape'));
}

// Review button back
if (!$result->correctanswer && !$result->noanswer && !$result->isessayquestion && !$reviewmode && $escape->review && !$result->maxattemptsreached) {
    $url = new moodle_url('/mod/escape/view.php', array('id' => $cm->id, 'pageid' => $page->id));
    echo $OUTPUT->single_button($url, get_string('reviewquestionback', 'escape'));
}

$url = new moodle_url('/mod/escape/view.php', array('id'=>$cm->id, 'pageid'=>$result->newpageid));
if ($escape->review && !$result->correctanswer && !$result->noanswer && !$result->isessayquestion && !$result->maxattemptsreached) {
    // Button to continue the escape (the page to go is configured by the teacher).
    echo $OUTPUT->single_button($url, get_string('reviewquestioncontinue', 'escape'));
} else {
    // Normal continue button
    echo $OUTPUT->single_button($url, get_string('continue', 'escape'));
}

echo $escapeoutput->footer();
