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
 * Action for adding a question page.  Prints an HTML form.
 *
 * @package mod_escape
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/escape/locallib.php');
require_once('editpage_form.php');

// first get the preceeding page
$pageid = required_param('pageid', PARAM_INT);
$id     = required_param('id', PARAM_INT);         // Course Module ID
$qtype  = optional_param('qtype', 0, PARAM_INT);
$edit   = optional_param('edit', false, PARAM_BOOL);
$returnto = optional_param('returnto', null, PARAM_URL);
if (empty($returnto)) {
    $returnto = new moodle_url('/mod/escape/edit.php', array('id' => $id));
    $returnto->set_anchor('escape-' . $pageid);
}

$cm = get_coursemodule_from_id('escape', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$escape = new escape($DB->get_record('escape', array('id' => $cm->instance), '*', MUST_EXIST));

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/escape:edit', $context);

$PAGE->set_url('/mod/escape/editpage.php', array('pageid'=>$pageid, 'id'=>$id, 'qtype'=>$qtype));
$PAGE->set_pagelayout('admin');

if ($edit) {
    $editpage = escape_page::load($pageid, $escape);
    $qtype = $editpage->qtype;
    $edit = true;
} else {
    $edit = false;
}

$jumpto = escape_page::get_jumptooptions($pageid, $escape);
$manager = escape_page_type_manager::get($escape);
$editoroptions = array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$CFG->maxbytes);

// If the previous page was the Question type selection form, this form
// will have a different name (e.g. _qf__escape_add_page_form_selection
// versus _qf__escape_add_page_form_multichoice). This causes confusion
// in moodleform::_process_submission because the array key check doesn't
// tie up with the current form name, which in turn means the "submitted"
// check ends up evaluating as false, thus it's not possible to check whether
// the Question type selection was cancelled. For this reason, a dummy form
// is created here solely to check whether the selection was cancelled.
if ($qtype) {
    $mformdummy = $manager->get_page_form(0, array(
        'editoroptions' => $editoroptions,
        'jumpto'        => $jumpto,
        'escape'        => $escape,
        'edit'          => $edit,
        'maxbytes'      => $PAGE->course->maxbytes,
        'returnto'      => $returnto
    ));
    if ($mformdummy->is_cancelled()) {
        redirect($returnto);
        exit;
    }
}

$mform = $manager->get_page_form($qtype, array(
    'editoroptions' => $editoroptions,
    'jumpto'        => $jumpto,
    'escape'        => $escape,
    'edit'          => $edit,
    'maxbytes'      => $PAGE->course->maxbytes,
    'returnto'      => $returnto
));

if ($mform->is_cancelled()) {
    redirect($returnto);
    exit;
}

if ($edit) {
    $data = $editpage->properties();
    $data->pageid = $editpage->id;
    $data->id = $cm->id;
    $editoroptions['context'] = $context;
    $data = file_prepare_standard_editor($data, 'contents', $editoroptions, $context, 'mod_escape', 'page_contents',  $editpage->id);

    $answerscount = 0;
    $answers = $editpage->get_answers();
    foreach ($answers as $answer) {
        $answereditor = 'answer_editor['.$answerscount.']';
        if (is_array($data->$answereditor)) {
            $answerdata = $data->$answereditor;
            if ($mform->get_answer_format() === ESCAPE_ANSWER_HTML) {
                $answerdraftid = file_get_submitted_draft_itemid($answereditor);
                $answertext = file_prepare_draft_area($answerdraftid, $PAGE->cm->context->id,
                        'mod_escape', 'page_answers', $answer->id, $editoroptions, $answerdata['text']);
                $data->$answereditor = array('text' => $answertext, 'format' => $answerdata['format'], 'itemid' => $answerdraftid);
            } else {
                $data->$answereditor = $answerdata['text'];
            }
        }

        $responseeditor = 'response_editor['.$answerscount.']';
        if (is_array($data->$responseeditor)) {
            $responsedata = $data->$responseeditor;
            if ($mform->get_response_format() === ESCAPE_ANSWER_HTML) {
                $responsedraftid = file_get_submitted_draft_itemid($responseeditor);
                $responsetext = file_prepare_draft_area($responsedraftid, $PAGE->cm->context->id,
                        'mod_escape', 'page_responses', $answer->id, $editoroptions, $responsedata['text']);
                $data->$responseeditor = array('text' => $responsetext, 'format' => $responsedata['format'],
                        'itemid' => $responsedraftid);
            } else {
                $data->$responseeditor = $responsedata['text'];
            }
        }
        $answerscount++;
    }

    $mform->set_data($data);
    $PAGE->navbar->add(get_string('edit'), new moodle_url('/mod/escape/edit.php', array('id'=>$id)));
    $PAGE->navbar->add(get_string('editingquestionpage', 'escape', get_string($mform->qtypestring, 'escape')));
} else {
    // Give the page type being created a chance to override the creation process
    // this is used by endofbranch, cluster, and endofcluster to skip the creation form.
    // IT SHOULD ALWAYS CALL require_sesskey();
    $mform->construction_override($pageid, $escape);

    $data = new stdClass;
    $data->id = $cm->id;
    $data->pageid = $pageid;
    if ($qtype) {
        //TODO: the handling of form for the selection of question type is a bloody hack! (skodak)
        $data->qtype = $qtype;
    }
    $data = file_prepare_standard_editor($data, 'contents', $editoroptions, null);
    $mform->set_data($data);
    $PAGE->navbar->add(get_string('addanewpage', 'escape'), $PAGE->url);
    if ($qtype !== 'unknown') {
        $PAGE->navbar->add(get_string($mform->qtypestring, 'escape'));
    }
}

if ($data = $mform->get_data()) {
    require_sesskey();
    if ($edit) {
        $data->escapeid = $data->id;
        $data->id = $data->pageid;
        unset($data->pageid);
        unset($data->edit);
        $editpage->update($data, $context, $PAGE->course->maxbytes);
    } else {
        $editpage = escape_page::create($data, $escape, $context, $PAGE->course->maxbytes);
    }
    redirect($returnto);
}

$escapeoutput = $PAGE->get_renderer('mod_escape');
echo $escapeoutput->header($escape, $cm, '', false, null, get_string('edit', 'escape'));
$mform->display();
echo $escapeoutput->footer();
