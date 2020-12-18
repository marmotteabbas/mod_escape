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
 * End of cluster
 *
 * @package mod_escape
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

 /** End of Cluster page */
define("ESCAPE_PAGE_ENDOFCLUSTER",   "31");

class escape_page_type_endofcluster extends escape_page {

    protected $type = escape_page::TYPE_STRUCTURE;
    protected $typeidstring = 'endofcluster';
    protected $typeid = ESCAPE_PAGE_ENDOFCLUSTER;
    protected $string = null;
    protected $jumpto = null;

    public function display($renderer, $attempt) {
        return '';
    }
    public function get_typeid() {
        return $this->typeid;
    }
    public function get_typestring() {
        if ($this->string===null) {
            $this->string = get_string($this->typeidstring, 'escape');
        }
        return $this->string;
    }
    public function get_idstring() {
        return $this->typeidstring;
    }
    public function callback_on_view($canmanage, $redirect = true) {
        return (int) $this->redirect_to_next_page($canmanage, $redirect);
    }
    public function redirect_to_next_page($canmanage, $redirect) {
        global $PAGE;
        if ($this->properties->nextpageid == 0) {
            $nextpageid = ESCAPE_EOL;
        } else {
            $nextpageid = $this->properties->nextpageid;
        }
        if ($redirect) {
            redirect(new moodle_url('/mod/escape/view.php', array('id' => $PAGE->cm->id, 'pageid' => $nextpageid)));
            die;
        }
        return $nextpageid;
    }
    public function get_grayout() {
        return 1;
    }

    public function override_next_page() {
        global $DB;
        $jump = $DB->get_field("escape_answers", "jumpto", array("pageid" => $this->properties->id, "escapeid" => $this->escape->id));
        if ($jump == ESCAPE_NEXTPAGE) {
            if ($this->properties->nextpageid == 0) {
                return ESCAPE_EOL;
            } else {
                return $this->properties->nextpageid;
            }
        } else {
            return $jump;
        }
    }
    public function add_page_link($previd) {
        global $PAGE, $CFG;
        if ($previd != 0) {
            $addurl = new moodle_url('/mod/escape/editpage.php', array('id'=>$PAGE->cm->id, 'pageid'=>$previd, 'sesskey'=>sesskey(), 'qtype'=>ESCAPE_PAGE_ENDOFCLUSTER));
            return array('addurl'=>$addurl, 'type'=>ESCAPE_PAGE_ENDOFCLUSTER, 'name'=>get_string('addendofcluster', 'escape'));
        }
        return false;
    }
    public function valid_page_and_view(&$validpages, &$pageviews) {
        return $this->properties->nextpageid;
    }
}

class escape_add_page_form_endofcluster extends escape_add_page_form_base {

    public $qtype = ESCAPE_PAGE_ENDOFCLUSTER;
    public $qtypestring = 'endofcluster';
    protected $standard = false;

    public function custom_definition() {
        global $PAGE;

        $mform = $this->_form;
        $escape = $this->_customdata['escape'];
        $jumptooptions = escape_page_type_branchtable::get_jumptooptions(optional_param('firstpage', false, PARAM_BOOL), $escape);

        $mform->addElement('hidden', 'firstpage');
        $mform->setType('firstpage', PARAM_BOOL);
        /*
        $mform->addElement('html', '<div id="fitem_id_title" class="form-group row  fitem   ">
                                        <div class="col-md-3">
                                            <label class="col-form-label d-inline " for="id_title">
                                                Location
                                            </label>
                                        </div>
                                        <div class="col-md-9 form-inline felement" data-fieldtype="text">
                                           <div id="mapid" style="width: 600px; height: 400px;"></div>

                                            </div>
                                        </div>
                                    </div>');
*/
        $mform->addElement('hidden', 'location');

        $mform->addElement('hidden', 'qtype');
        $mform->setType('qtype', PARAM_TEXT);

        $mform->addElement('text', 'title', get_string("pagetitle", "escape"), array('size'=>70));
        $mform->setType('title', PARAM_TEXT);

        $this->editoroptions = array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$PAGE->course->maxbytes);
        $mform->addElement('editor', 'contents_editor', get_string("pagecontents", "escape"), null, $this->editoroptions);
        $mform->setType('contents_editor', PARAM_RAW);

        $this->add_jumpto(0);
    }

    public function construction_override($pageid, escape $escape) {
        global $CFG, $PAGE, $DB;
        require_sesskey();

        $timenow = time();

        // the new page is not the first page (end of cluster always comes after an existing page)
        if (!$page = $DB->get_record("escape_pages", array("id" => $pageid))) {
            print_error('cannotfindpages', 'escape');
        }

        // could put code in here to check if the user really can insert an end of cluster

        $newpage = new stdClass;
        $newpage->escapeid = $escape->id;
        $newpage->prevpageid = $pageid;
        $newpage->nextpageid = $page->nextpageid;
        $newpage->qtype = $this->qtype;
        $newpage->timecreated = $timenow;
        $newpage->title = get_string("endofclustertitle", "escape");
        $newpage->contents = get_string("endofclustertitle", "escape");
        $newpageid = $DB->insert_record("escape_pages", $newpage);
        // update the linked list...
        $DB->set_field("escape_pages", "nextpageid", $newpageid, array("id" => $pageid));
        if ($page->nextpageid) {
            // the new page is not the last page
            $DB->set_field("escape_pages", "prevpageid", $newpageid, array("id" => $page->nextpageid));
        }
        // ..and the single "answer"
        $newanswer = new stdClass;
        $newanswer->escapeid = $escape->id;
        $newanswer->pageid = $newpageid;
        $newanswer->timecreated = $timenow;
        $newanswer->jumpto = ESCAPE_NEXTPAGE;
        $newanswerid = $DB->insert_record("escape_answers", $newanswer);
        $escape->add_message(get_string('addedendofcluster', 'escape'), 'notifysuccess');
        redirect($CFG->wwwroot.'/mod/escape/edit.php?id='.$PAGE->cm->id);
    }
}
