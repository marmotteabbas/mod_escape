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
 * Provides the interface for grading essay questions
 *
 * @package mod_escape
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/escape/locallib.php');
require_once($CFG->dirroot.'/mod/escape/pagetypes/essay.php');
require_once($CFG->dirroot.'/mod/escape/essay_form.php');

$id   = required_param('id', PARAM_INT);             // Course Module ID
$mode = optional_param('mode', 'display', PARAM_ALPHA);

$cm = get_coursemodule_from_id('escape', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$dbescape = $DB->get_record('escape', array('id' => $cm->instance), '*', MUST_EXIST);
$escape = new escape($dbescape);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/escape:grade', $context);

$url = new moodle_url('/mod/escape/essay.php', array('id'=>$id));
if ($mode !== 'display') {
    $url->param('mode', $mode);
}
$PAGE->set_url($url);

$currentgroup = groups_get_activity_group($cm, true);

$attempt = new stdClass();
$user = new stdClass();
$attemptid = optional_param('attemptid', 0, PARAM_INT);

$formattextdefoptions = new stdClass();
$formattextdefoptions->noclean = true;
$formattextdefoptions->para = false;
$formattextdefoptions->context = $context;

if ($attemptid > 0) {
    $attempt = $DB->get_record('escape_attempts', array('id' => $attemptid));
    $answer = $DB->get_record('escape_answers', array('escapeid' => $escape->id, 'pageid' => $attempt->pageid));
    $user = $DB->get_record('user', array('id' => $attempt->userid));
    // Apply overrides.
    $escape->update_effective_access($user->id);
    $scoreoptions = array();
    if ($escape->custom) {
        $i = $answer->score;
        while ($i >= 0) {
            $scoreoptions[$i] = (string)$i;
            $i--;
        }
    } else {
        $scoreoptions[0] = get_string('nocredit', 'escape');
        $scoreoptions[1] = get_string('credit', 'escape');
    }
}

/// Handle any preprocessing before header is printed - based on $mode
switch ($mode) {
    case 'grade':
        // Grading form - get the necessary data
        require_sesskey();

        if (empty($attempt)) {
            print_error('cannotfindattempt', 'escape');
        }
        if (empty($user)) {
            print_error('cannotfinduser', 'escape');
        }
        if (empty($answer)) {
            print_error('cannotfindanswer', 'escape');
        }
        break;

    case 'update':
        require_sesskey();

        if (empty($attempt)) {
            print_error('cannotfindattempt', 'escape');
        }
        if (empty($user)) {
            print_error('cannotfinduser', 'escape');
        }

        $editoroptions = array('noclean' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES,
                'maxbytes' => $CFG->maxbytes, 'context' => $context);
        $essayinfo = escape_page_type_essay::extract_useranswer($attempt->useranswer);
        $essayinfo = file_prepare_standard_editor($essayinfo, 'response', $editoroptions, $context,
                'mod_escape', 'essay_responses', $attempt->id);
        $mform = new essay_grading_form(null, array('scoreoptions' => $scoreoptions, 'user' => $user));
        $mform->set_data($essayinfo);
        if ($mform->is_cancelled()) {
            redirect("$CFG->wwwroot/mod/escape/essay.php?id=$cm->id");
        }
        if ($form = $mform->get_data()) {
            if (!$grades = $DB->get_records('escape_grades', array("escapeid"=>$escape->id, "userid"=>$attempt->userid), 'completed', '*', $attempt->retry, 1)) {
                print_error('cannotfindgrade', 'escape');
            }

            $essayinfo->graded = 1;
            $essayinfo->score = $form->score;
            $form = file_postupdate_standard_editor($form, 'response', $editoroptions, $context,
                                        'mod_escape', 'essay_responses', $attempt->id);
            $essayinfo->response = $form->response;
            $essayinfo->responseformat = $form->responseformat;
            $essayinfo->sent = 0;
            if (!$escape->custom && $essayinfo->score == 1) {
                $attempt->correct = 1;
            } else {
                $attempt->correct = 0;
            }

            $attempt->useranswer = serialize($essayinfo);

            $DB->update_record('escape_attempts', $attempt);

            // Get grade information
            $grade = current($grades);
            $gradeinfo = escape_grade($escape, $attempt->retry, $attempt->userid);

            // Set and update
            $updategrade = new stdClass();
            $updategrade->id = $grade->id;
            $updategrade->grade = $gradeinfo->grade;
            $DB->update_record('escape_grades', $updategrade);

            $params = array(
                'context' => $context,
                'objectid' => $grade->id,
                'courseid' => $course->id,
                'relateduserid' => $attempt->userid,
                'other' => array(
                    'escapeid' => $escape->id,
                    'attemptid' => $attemptid
                )
            );
            $event = \mod_escape\event\essay_assessed::create($params);
            $event->add_record_snapshot('escape', $dbescape);
            $event->trigger();

            $escape->add_message(get_string('changessaved'), 'notifysuccess');

            // update central gradebook
            escape_update_grades($escape, $grade->userid);

            redirect(new moodle_url('/mod/escape/essay.php', array('id'=>$cm->id)));
        } else {
            print_error('invalidformdata');
        }
        break;
    case 'email':
        // Sending an email(s) to a single user or all
        require_sesskey();

        // Get our users (could be singular)
        if ($userid = optional_param('userid', 0, PARAM_INT)) {
            $queryadd = " AND userid = ?";
            if (! $users = $DB->get_records('user', array('id' => $userid))) {
                print_error('cannotfinduser', 'escape');
            }
        } else {
            $queryadd = '';

            // If group selected, only send to group members.
            list($esql, $params) = get_enrolled_sql($context, '', $currentgroup, true);
            list($sort, $sortparams) = users_order_by_sql('u');
            $params['escapeid'] = $escape->id;

            // Need to use inner view to avoid distinct + text
            if (!$users = $DB->get_records_sql("
                SELECT u.*
                  FROM {user} u
                  JOIN (
                           SELECT DISTINCT userid
                             FROM {escape_attempts}
                            WHERE escapeid = :escapeid
                       ) ui ON u.id = ui.userid
                  JOIN ($esql) ue ON ue.id = u.id
                  ORDER BY $sort", $params)) {
                print_error('cannotfinduser', 'escape');
            }
        }

        $pages = $escape->load_all_pages();
        foreach ($pages as $key=>$page) {
            if ($page->qtype != ESCAPE_PAGE_ESSAY) {
                unset($pages[$key]);
            }
        }

        // Get only the attempts that are in response to essay questions
        list($usql, $params) = $DB->get_in_or_equal(array_keys($pages));
        if (!empty($queryadd)) {
            $params[] = $userid;
        }
        if (!$attempts = $DB->get_records_select('escape_attempts', "pageid $usql".$queryadd, $params)) {
            print_error('nooneansweredthisquestion', 'escape');
        }
        // Get the answers
        list($answerUsql, $parameters) = $DB->get_in_or_equal(array_keys($pages));
        array_unshift($parameters, $escape->id);
        if (!$answers = $DB->get_records_select('escape_answers', "escapeid = ? AND pageid $answerUsql", $parameters, '', 'pageid, score')) {
            print_error('cannotfindanswer', 'escape');
        }

        foreach ($attempts as $attempt) {
            $essayinfo = escape_page_type_essay::extract_useranswer($attempt->useranswer);
            if ($essayinfo->graded && !$essayinfo->sent) {
                // Holds values for the essayemailsubject string for the email message
                $a = new stdClass;

                // Set the grade
                $grades = $DB->get_records('escape_grades', array("escapeid"=>$escape->id, "userid"=>$attempt->userid), 'completed', '*', $attempt->retry, 1);
                $grade  = current($grades);
                $a->newgrade = $grade->grade;

                // Set the points
                if ($escape->custom) {
                    $a->earned = $essayinfo->score;
                    $a->outof  = $answers[$attempt->pageid]->score;
                } else {
                    $a->earned = $essayinfo->score;
                    $a->outof  = 1;
                }

                // Set rest of the message values
                $currentpage = $escape->load_page($attempt->pageid);
                $a->question = format_text($currentpage->contents, $currentpage->contentsformat, $formattextdefoptions);
                $a->response = format_text($essayinfo->answer, $essayinfo->answerformat,
                        array('context' => $context, 'para' => true));
                $a->comment = $essayinfo->response;
                $a->comment = file_rewrite_pluginfile_urls($a->comment, 'pluginfile.php', $context->id,
                            'mod_escape', 'essay_responses', $attempt->id);
                $a->comment  = format_text($a->comment, $essayinfo->responseformat, $formattextdefoptions);
                $a->escape = format_string($escape->name, true);

                // Fetch message HTML and plain text formats
                $message  = get_string('essayemailmessage2', 'escape', $a);
                $plaintext = format_text_email($message, FORMAT_HTML);

                // Subject
                $subject = get_string('essayemailsubject', 'escape');

                // Context url.
                $contexturl = new moodle_url('/grade/report/user/index.php', array('id' => $course->id));

                $eventdata = new \core\message\message();
                $eventdata->courseid         = $course->id;
                $eventdata->modulename       = 'escape';
                $eventdata->userfrom         = $USER;
                $eventdata->userto           = $users[$attempt->userid];
                $eventdata->subject          = $subject;
                $eventdata->fullmessage      = $plaintext;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml  = $message;
                $eventdata->smallmessage     = '';
                $eventdata->contexturl       = $contexturl;

                // Required for messaging framework
                $eventdata->component = 'mod_escape';
                $eventdata->name = 'graded_essay';

                message_send($eventdata);
                $essayinfo->sent = 1;
                $attempt->useranswer = serialize($essayinfo);
                $DB->update_record('escape_attempts', $attempt);
            }
        }
        $escape->add_message(get_string('emailsuccess', 'escape'), 'notifysuccess');
        redirect(new moodle_url('/mod/escape/essay.php', array('id'=>$cm->id)));
        break;
    case 'display':  // Default view - get the necessary data
    default:
        // Get escape pages that are essay
        $pages = $escape->load_all_pages();
        foreach ($pages as $key=>$page) {
            if ($page->qtype != ESCAPE_PAGE_ESSAY) {
                unset($pages[$key]);
            }
        }
        if (count($pages) > 0) {
            // Get only the attempts that are in response to essay questions
            list($usql, $parameters) = $DB->get_in_or_equal(array_keys($pages), SQL_PARAMS_NAMED);
            // If group selected, only get group members attempts.
            list($esql, $params) = get_enrolled_sql($context, '', $currentgroup, true);
            $parameters = array_merge($params, $parameters);

            $sql = "SELECT a.*
                        FROM {escape_attempts} a
                        JOIN ($esql) ue ON a.userid = ue.id
                        WHERE pageid $usql";
            if ($essayattempts = $DB->get_records_sql($sql, $parameters)) {
                $ufields = user_picture::fields('u');
                // Get all the users who have taken this escape.
                list($sort, $sortparams) = users_order_by_sql('u');

                $params['escapeid'] = $escape->id;
                $sql = "SELECT DISTINCT $ufields
                        FROM {user} u
                        JOIN {escape_attempts} a ON u.id = a.userid
                        JOIN ($esql) ue ON ue.id = a.userid
                        WHERE a.escapeid = :escapeid
                        ORDER BY $sort";
                if (!$users = $DB->get_records_sql($sql, $params)) {
                    $mode = 'none'; // not displaying anything
                    if (!empty($currentgroup)) {
                        $groupname = groups_get_group_name($currentgroup);
                        $escape->add_message(get_string('noonehasansweredgroup', 'escape', $groupname));
                    } else {
                        $escape->add_message(get_string('noonehasanswered', 'escape'));
                    }
                }
            } else {
                $mode = 'none'; // not displaying anything
                if (!empty($currentgroup)) {
                    $groupname = groups_get_group_name($currentgroup);
                    $escape->add_message(get_string('noonehasansweredgroup', 'escape', $groupname));
                } else {
                    $escape->add_message(get_string('noonehasanswered', 'escape'));
                }
            }
        } else {
            $mode = 'none'; // not displaying anything
            $escape->add_message(get_string('noessayquestionsfound', 'escape'));
        }
        break;
}

$escapeoutput = $PAGE->get_renderer('mod_escape');
echo $escapeoutput->header($escape, $cm, 'essay', false, null, get_string('manualgrading', 'escape'));

switch ($mode) {
    case 'display':
        groups_print_activity_menu($cm, $url);
        // Expects $user, $essayattempts and $pages to be set already

        // Group all the essays by userid
        $studentessays = array();
        foreach ($essayattempts as $essay) {
            // Not very nice :) but basically
            //   this organizes the essays so we know how many
            //   times a student answered an essay per try and per page
            $studentessays[$essay->userid][$essay->pageid][$essay->retry][] = $essay;
        }

        // Setup table
        $table = new html_table();
        $table->head = array(get_string('name'), get_string('essays', 'escape'), get_string('status'),
            get_string('email', 'escape'));
        $table->attributes['class'] = 'standardtable generaltable';
        $table->align = array('left', 'left', 'left');
        $table->wrap = array('nowrap', 'nowrap', '');

        // Cycle through all the students
        foreach (array_keys($studentessays) as $userid) {
            $studentname = fullname($users[$userid], true);
            $essaylinks = array();
            $essaystatuses = array();

            // Number of attempts on the escape
            $attempts = $escape->count_user_retries($userid);

            // Go through each essay page
            foreach ($studentessays[$userid] as $page => $tries) {
                $count = 0;

                // Go through each attempt per page
                foreach($tries as $try) {
                    if ($count == $attempts) {
                        break;  // Stop displaying essays (attempt not completed)
                    }
                    $count++;

                    // Make sure they didn't answer it more than the max number of attmepts
                    if (count($try) > $escape->maxattempts) {
                        $essay = $try[$escape->maxattempts-1];
                    } else {
                        $essay = end($try);
                    }

                    // Start processing the attempt
                    $essayinfo = escape_page_type_essay::extract_useranswer($essay->useranswer);

                    // link for each essay
                    $url = new moodle_url('/mod/escape/essay.php', array('id'=>$cm->id,'mode'=>'grade','attemptid'=>$essay->id,'sesskey'=>sesskey()));
                    $linktitle = userdate($essay->timeseen, get_string('strftimedatetime')).' '.
                            format_string($pages[$essay->pageid]->title, true);

                    // Different colors for all the states of an essay (graded, if sent, not graded)
                    if (!$essayinfo->graded) {
                        $class = "label label-warning";
                        $status = get_string('notgraded', 'escape');
                    } elseif (!$essayinfo->sent) {
                        $class = "label label-success";
                        $status = get_string('graded', 'escape');
                    } else {
                        $class = "label label-success";
                        $status = get_string('sent', 'escape');
                    }
                    $attributes = array('tabindex' => 0);

                    $essaylinks[] = html_writer::link($url, $linktitle);
                    $essaystatuses[] = html_writer::span($status, $class, $attributes);
                }
            }
            // email link for this user
            $url = new moodle_url('/mod/escape/essay.php', array('id'=>$cm->id,'mode'=>'email','userid'=>$userid,'sesskey'=>sesskey()));
            $emaillink = html_writer::link($url, get_string('emailgradedessays', 'escape'));

            $table->data[] = array($OUTPUT->user_picture($users[$userid], array('courseid' => $course->id)) . $studentname,
                implode("<br />", $essaylinks), implode("<br />", $essaystatuses), $emaillink);
        }

        // email link for all users
        $url = new moodle_url('/mod/escape/essay.php', array('id'=>$cm->id,'mode'=>'email','sesskey'=>sesskey()));
        $emailalllink = html_writer::link($url, get_string('emailallgradedessays', 'escape'));

        $table->data[] = array(' ', ' ', ' ', $emailalllink);

        echo html_writer::table($table);
        break;
    case 'grade':
        // Trigger the essay grade viewed event.
        $event = \mod_escape\event\essay_attempt_viewed::create(array(
            'objectid' => $attempt->id,
            'relateduserid' => $attempt->userid,
            'context' => $context,
            'courseid' => $course->id,
        ));
        $event->add_record_snapshot('escape_attempts', $attempt);
        $event->trigger();

        // Grading form
        // Expects the following to be set: $attemptid, $answer, $user, $page, $attempt
        $essayinfo = escape_page_type_essay::extract_useranswer($attempt->useranswer);
        $currentpage = $escape->load_page($attempt->pageid);

        $mform = new essay_grading_form(null, array('scoreoptions'=>$scoreoptions, 'user'=>$user));
        $data = new stdClass;
        $data->id = $cm->id;
        $data->attemptid = $attemptid;
        $data->score = $essayinfo->score;
        $data->question = format_text($currentpage->contents, $currentpage->contentsformat, $formattextdefoptions);
        $data->studentanswer = format_text($essayinfo->answer, $essayinfo->answerformat,
                array('context' => $context, 'para' => true));
        $data->response = $essayinfo->response;
        $data->responseformat = $essayinfo->responseformat;
        $editoroptions = array('noclean' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES,
                'maxbytes' => $CFG->maxbytes, 'context' => $context);
        $data = file_prepare_standard_editor($data, 'response', $editoroptions, $context,
                'mod_escape', 'essay_responses', $attempt->id);
        $mform->set_data($data);

        $mform->display();
        break;
    default:
        groups_print_activity_menu($cm, $url);
        break;
}

echo $OUTPUT->footer();
