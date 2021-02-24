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
 * Cluster
 *
 * @package mod_escape
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

 /** Start of Cluster page */
define("ESCAPE_PAGE_CLUSTER",   "30");

class escape_page_type_cluster extends escape_page {

    protected $type = escape_page::TYPE_STRUCTURE;
    protected $typeidstring = 'cluster';
    protected $typeid = ESCAPE_PAGE_CLUSTER;
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
    public function get_grayout() {
        return 1;
    }
    public function callback_on_view($canmanage, $redirect = true) {
        global $USER;
        if (!$canmanage) {
            // Get the next page in the escape cluster jump
            return (int) $this->escape->cluster_jump($this->properties->id);
        } else {
            // get the next page
            return (int) $this->properties->nextpageid;
        }
    }
    public function override_next_page() {
        global $USER;
        return $this->escape->cluster_jump($this->properties->id);
    }
 /*   public function add_page_link($previd) {
        global $PAGE, $CFG;
        $addurl = new moodle_url('/mod/escape/editpage.php', array('id'=>$PAGE->cm->id, 'pageid'=>$previd, 'sesskey'=>sesskey(), 'qtype'=>ESCAPE_PAGE_CLUSTER));
        return array('addurl'=>$addurl, 'type'=>ESCAPE_PAGE_CLUSTER, 'name'=>get_string('addcluster', 'escape'));
    }*/
    public function valid_page_and_view(&$validpages, &$pageviews) {
        $validpages[$this->properties->id] = 1;  // add the cluster page as a valid page
        foreach ($this->escape->get_sub_pages_of($this->properties->id, array(ESCAPE_PAGE_ENDOFCLUSTER)) as $subpage) {
            if (in_array($subpage->id, $pageviews)) {
                unset($pageviews[array_search($subpage->id, $pageviews)]);  // remove it
                // since the user did see one page in the cluster, add the cluster pageid to the viewedpageids
                if (!in_array($this->properties->id, $pageviews)) {
                    $pageviews[] = $this->properties->id;
                }
            }
        }
        return $this->properties->nextpageid;
    }
}

class escape_add_page_form_cluster extends escape_add_page_form_base {

    public $qtype = ESCAPE_PAGE_CLUSTER;
    public $qtypestring = 'cluster';
    protected $standard = false;

    public function custom_definition() {
        global $PAGE;

        $mform = $this->_form;
        $escape = $this->_customdata['escape'];
        $jumptooptions = escape_page_type_branchtable::get_jumptooptions(optional_param('firstpage', false, PARAM_BOOL), $escape);

        $mform->addElement('hidden', 'firstpage');
        $mform->setType('firstpage', PARAM_BOOL);
        
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
                                    </div>
                                    <span id="deletegps" 
                                    onclick="require(\'mod_escape/map_pilot\').clearMarkers();" 
                                    >Delete GPS Point</span>');

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
        global $PAGE, $CFG, $DB;
        require_sesskey();

        $timenow = time();

        if ($pageid == 0) {
            if ($escape->has_pages()) {
                if (!$page = $DB->get_record("escape_pages", array("prevpageid" => 0, "escapeid" => $escape->id))) {
                    print_error('cannotfindpagerecord', 'escape');
                }
            } else {
                // This is the ONLY page
                $page = new stdClass;
                $page->id = 0;
            }
        } else {
            if (!$page = $DB->get_record("escape_pages", array("id" => $pageid))) {
                print_error('cannotfindpagerecord', 'escape');
            }
        }
        $newpage = new stdClass;
        $newpage->escapeid = $escape->id;
        $newpage->prevpageid = $pageid;
        if ($pageid != 0) {
            $newpage->nextpageid = $page->nextpageid;
        } else {
            $newpage->nextpageid = $page->id;
        }
        $newpage->qtype = $this->qtype;
        $newpage->timecreated = $timenow;
        $newpage->title = get_string("clustertitle", "escape");
        $newpage->contents = get_string("clustertitle", "escape");
        $newpageid = $DB->insert_record("escape_pages", $newpage);
        // update the linked list...
        if ($pageid != 0) {
            $DB->set_field("escape_pages", "nextpageid", $newpageid, array("id" => $pageid));
        }

        if ($pageid == 0) {
            $page->nextpageid = $page->id;
        }
        if ($page->nextpageid) {
            // the new page is not the last page
            $DB->set_field("escape_pages", "prevpageid", $newpageid, array("id" => $page->nextpageid));
        }
        // ..and the single "answer"
        $newanswer = new stdClass;
        $newanswer->escapeid = $escape->id;
        $newanswer->pageid = $newpageid;
        $newanswer->timecreated = $timenow;
        $newanswer->jumpto = ESCAPE_CLUSTERJUMP;
        $newanswerid = $DB->insert_record("escape_answers", $newanswer);
        $escape->add_message(get_string('addedcluster', 'escape'), 'notifysuccess');
        redirect($CFG->wwwroot.'/mod/escape/edit.php?id='.$PAGE->cm->id);
    }
}