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
 * This file plays the mediafile set in escape settings.
 *
 *  If there is a way to use the resource class instead of this code, please change to do so
 *
 *
 * @package mod_escape
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/escape/locallib.php');

$id = required_param('id', PARAM_INT);    // Course Module ID
$printclose = optional_param('printclose', 0, PARAM_INT);

$cm = get_coursemodule_from_id('escape', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$escape = new escape($DB->get_record('escape', array('id' => $cm->instance), '*', MUST_EXIST), $cm);

require_login($course, false, $cm);

// Apply overrides.
$escape->update_effective_access($USER->id);

$context = $escape->context;
$canmanage = $escape->can_manage();

$url = new moodle_url('/mod/escape/mediafile.php', array('id'=>$id));
if ($printclose !== '') {
    $url->param('printclose', $printclose);
}
$PAGE->set_url($url);
$PAGE->set_pagelayout('popup');
$PAGE->set_title($course->shortname);

$escapeoutput = $PAGE->get_renderer('mod_escape');

// Get the mimetype
$mimetype = mimeinfo("type", $escape->mediafile);

if ($printclose) {  // this is for framesets
    if ($escape->mediaclose) {
        echo $escapeoutput->header($escape, $cm);
        echo $OUTPUT->box('<form><div><input type="button" onclick="top.close();" value="'.get_string("closewindow").'" /></div></form>', 'escapemediafilecontrol');
        echo $escapeoutput->footer();
    }
    exit();
}

// Check access restrictions.
if ($timerestriction = $escape->get_time_restriction_status()) {  // Deadline restrictions.
    echo $escapeoutput->header($escape, $cm, '', false, null, get_string('notavailable'));
    echo $escapeoutput->escape_inaccessible(get_string($timerestriction->reason, 'escape', userdate($timerestriction->time)));
    echo $escapeoutput->footer();
    exit();
} else if ($passwordrestriction = $escape->get_password_restriction_status(null)) { // Password protected escape code.
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

echo $escapeoutput->header($escape, $cm);

// print the embedded media html code
echo $OUTPUT->box(escape_get_media_html($escape, $context));

if ($escape->mediaclose) {
   echo '<div class="escapemediafilecontrol">';
   echo $OUTPUT->close_window_button();
   echo '</div>';
}

echo $escapeoutput->footer();
