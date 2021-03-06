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
 * Branch Table
 *
 * @package mod_escape
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

 /** Branch Table page */
define("ESCAPE_PAGE_BRANCHTABLE",   "20");

class escape_page_type_branchtable extends escape_page {

    protected $type = escape_page::TYPE_STRUCTURE;
    protected $typeid = ESCAPE_PAGE_BRANCHTABLE;
    protected $typeidstring = 'branchtable';
    protected $string = null;
    protected $jumpto = null;

    public function get_typeid() {
        return $this->typeid;
    }
    public function get_typestring() {
        if ($this->string===null) {
            $this->string = get_string($this->typeidstring, 'escape');
        }
        return $this->string;
    }

    /**
     * Gets an array of the jumps used by the answers of this page
     *
     * @return array
     */
    public function get_jumps() {
        global $DB;
        $jumps = array();
        $params = array ("escapeid" => $this->escape->id, "pageid" => $this->properties->id);
        if ($answers = $this->get_answers()) {
            foreach ($answers as $answer) {
                if ($answer->answer === '') {
                    // show only jumps for real branches (==have description)
                    continue;
                }
                $jumps[] = $this->get_jump_name($answer->jumpto);
            }
        } else {
            // We get here is the escape was created on a Moodle 1.9 site and
            // the escape contains question pages without any answers.
            $jumps[] = $this->get_jump_name($this->properties->nextpageid);
        }
        return $jumps;
    }

    public static function get_jumptooptions($firstpage, escape $escape) {
        global $DB, $PAGE;
        $jump = array();
        $jump[0] = get_string("thispage", "escape");
        $jump[ESCAPE_NEXTPAGE] = get_string("nextpage", "escape");
        $jump[ESCAPE_PREVIOUSPAGE] = get_string("previouspage", "escape");
        $jump[ESCAPE_EOL] = get_string("endofescape", "escape");
        $jump[ESCAPE_UNSEENBRANCHPAGE] = get_string("unseenpageinbranch", "escape");
        $jump[ESCAPE_RANDOMPAGE] = get_string("randompageinbranch", "escape");
        $jump[ESCAPE_RANDOMBRANCH] = get_string("randombranch", "escape");

        if (!$firstpage) {
            if (!$apageid = $DB->get_field("escape_pages", "id", array("escapeid" => $escape->id, "prevpageid" => 0))) {
                print_error('cannotfindfirstpage', 'escape');
            }
            while (true) {
                if ($apageid) {
                    $title = $DB->get_field("escape_pages", "title", array("id" => $apageid));
                    $jump[$apageid] = $title;
                    $apageid = $DB->get_field("escape_pages", "nextpageid", array("id" => $apageid));
                } else {
                    // last page reached
                    break;
                }
            }
         }
        return $jump;
    }
    public function get_idstring() {
        return $this->typeidstring;
    }
    public function display($renderer, $attempt) {
        global $PAGE, $CFG;

        $output = '';
        $options = new stdClass;
        $options->para = false;
        $options->noclean = true;

        if ($this->escape->slideshow) {
            $output .= $renderer->slideshow_start($this->escape);
        }
        // We are using level 3 header because the page title is a sub-heading of escape title (MDL-30911).
        $output .= $renderer->heading(format_string($this->properties->title), 3);
        $output .= $renderer->box($this->get_contents(), 'contents');

        $buttons = array();
        $i = 0;
        foreach ($this->get_answers() as $answer) {
            if ($answer->answer === '') {
                // not a branch!
                continue;
            }
            $params = array();
            $params['id'] = $PAGE->cm->id;
            $params['pageid'] = $this->properties->id;
            $params['sesskey'] = sesskey();
            $params['jumpto'] = $answer->jumpto;
            $url = new moodle_url('/mod/escape/continue.php', $params);
            $buttons[] = $renderer->single_button($url, strip_tags(format_text($answer->answer, FORMAT_MOODLE, $options)));
            $i++;
        }
        // Set the orientation
        if ($this->properties->layout) {
            $buttonshtml = $renderer->box(implode("\n", $buttons), 'branchbuttoncontainer horizontal');
        } else {
            $buttonshtml = $renderer->box(implode("\n", $buttons), 'branchbuttoncontainer vertical');
        }
        $output .= $buttonshtml;

        if ($this->escape->slideshow) {
            $output .= $renderer->slideshow_end();
        }

        // Trigger an event: content page viewed.
        $eventparams = array(
            'context' => context_module::instance($PAGE->cm->id),
            'objectid' => $this->properties->id
            );

        $event = \mod_escape\event\content_page_viewed::create($eventparams);
        $event->trigger();

        return $output;
    }

    public function check_answer() {
        global $USER, $DB, $PAGE, $CFG;

        $result = parent::check_answer();

        require_sesskey();
        $newpageid = optional_param('jumpto', null, PARAM_INT);
        // going to insert into escape_branch
        if ($newpageid == ESCAPE_RANDOMBRANCH) {
            $branchflag = 1;
        } else {
            $branchflag = 0;
        }
        if ($grades = $DB->get_records("escape_grades", array("escapeid" => $this->escape->id, "userid" => $USER->id), "grade DESC")) {
            $retries = count($grades);
        } else {
            $retries = 0;
        }

        // First record this page in escape_branch. This record may be needed by escape_unseen_branch_jump.
        $branch = new stdClass;
        $branch->escapeid = $this->escape->id;
        $branch->userid = $USER->id;
        $branch->pageid = $this->properties->id;
        $branch->retry = $retries;
        $branch->flag = $branchflag;
        $branch->timeseen = time();
        $branch->nextpageid = 0;    // Next page id will be set later.
        $branch->id = $DB->insert_record("escape_branch", $branch);

        //  this is called when jumping to random from a branch table
        $context = context_module::instance($PAGE->cm->id);
        if($newpageid == ESCAPE_UNSEENBRANCHPAGE) {
            if (has_capability('mod/escape:manage', $context)) {
                 $newpageid = ESCAPE_NEXTPAGE;
            } else {
                 $newpageid = escape_unseen_question_jump($this->escape, $USER->id, $this->properties->id);  // this may return 0
            }
        }
        // convert jumpto page into a proper page id
        if ($newpageid == 0) {
            $newpageid = $this->properties->id;
        } elseif ($newpageid == ESCAPE_NEXTPAGE) {
            if (!$newpageid = $this->nextpageid) {
                // no nextpage go to end of escape
                $newpageid = ESCAPE_EOL;
            }
        } elseif ($newpageid == ESCAPE_PREVIOUSPAGE) {
            $newpageid = $this->prevpageid;
        } elseif ($newpageid == ESCAPE_RANDOMPAGE) {
            $newpageid = escape_random_question_jump($this->escape, $this->properties->id);
        } elseif ($newpageid == ESCAPE_RANDOMBRANCH) {
            $newpageid = escape_unseen_branch_jump($this->escape, $USER->id);
        }

        // Update record to set nextpageid.
        $branch->nextpageid = $newpageid;
        $DB->update_record("escape_branch", $branch);

        // This will force to redirect to the newpageid.
        $result->inmediatejump = true;
        $result->newpageid = $newpageid;
        return $result;
    }

    public function display_answers(html_table $table) {
        $answers = $this->get_answers();
        $options = new stdClass;
        $options->noclean = true;
        $options->para = false;
        $i = 1;
        foreach ($answers as $answer) {
            if ($answer->answer === '') {
                // not a branch!
                continue;
            }
            $cells = array();
            $cells[] = '<label>' . get_string('branch', 'escape') . ' ' . $i . '</label>: ';
            $cells[] = format_text($answer->answer, $answer->answerformat, $options);
            $table->data[] = new html_table_row($cells);

            $cells = array();
            $cells[] = '<label>' . get_string('jump', 'escape') . ' ' . $i . '</label>: ';
            $cells[] = $this->get_jump_name($answer->jumpto);
            $table->data[] = new html_table_row($cells);

            if ($i === 1){
                $table->data[count($table->data)-1]->cells[0]->style = 'width:20%;';
            }
            $i++;
        }
        return $table;
    }
    public function get_grayout() {
        return 1;
    }
    public function report_answers($answerpage, $answerdata, $useranswer, $pagestats, &$i, &$n) {
        $answers = $this->get_answers();
        $formattextdefoptions = new stdClass;
        $formattextdefoptions->para = false;  //I'll use it widely in this page
        $formattextdefoptions->context = $answerpage->context;

        foreach ($answers as $answer) {
            $data = "<input type=\"button\" class=\"btn btn-secondary\" name=\"$answer->id\" " .
                    "value=\"".s(strip_tags(format_text($answer->answer, FORMAT_MOODLE, $formattextdefoptions)))."\" " .
                    "disabled=\"disabled\"> ";
            $data .= get_string('jumpsto', 'escape', $this->get_jump_name($answer->jumpto));
            $answerdata->answers[] = array($data, "");
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }

    public function update($properties, $context = null, $maxbytes = null) {
        if (empty($properties->display)) {
            $properties->display = '0';
        }
        if (empty($properties->layout)) {
            $properties->layout = '0';
        }
        return parent::update($properties);
    }
    public function add_page_link($previd) {
        global $PAGE, $CFG;
        $addurl = new moodle_url('/mod/escape/editpage.php', array('id'=>$PAGE->cm->id, 'pageid'=>$previd, 'qtype'=>ESCAPE_PAGE_BRANCHTABLE));
        return array('addurl'=>$addurl, 'type'=>ESCAPE_PAGE_BRANCHTABLE, 'name'=>get_string('addabranchtable', 'escape'));
    }
    protected function get_displayinmenublock() {
        return true;
    }
    public function is_unseen($param) {
        global $USER, $DB;
        if (is_array($param)) {
            $seenpages = $param;
            $branchpages = $this->escape->get_sub_pages_of($this->properties->id, array(ESCAPE_PAGE_BRANCHTABLE, ESCAPE_PAGE_ENDOFBRANCH));
            foreach ($branchpages as $branchpage) {
                if (array_key_exists($branchpage->id, $seenpages)) {  // check if any of the pages have been viewed
                    return false;
                }
            }
            return true;
        } else {
            $nretakes = $param;
            if (!$DB->count_records("escape_attempts", array("pageid"=>$this->properties->id, "userid"=>$USER->id, "retry"=>$nretakes))) {
                return true;
            }
            return false;
        }
    }
}

class escape_add_page_form_branchtable extends escape_add_page_form_base {

    public $qtype = ESCAPE_PAGE_BRANCHTABLE;
    public $qtypestring = 'branchtable';
    protected $standard = false;

    public function custom_definition() {
        global $PAGE;

        $mform = $this->_form;
        $escape = $this->_customdata['escape'];

        $firstpage = optional_param('firstpage', false, PARAM_BOOL);

        $jumptooptions = escape_page_type_branchtable::get_jumptooptions($firstpage, $escape);

        if ($this->_customdata['edit']) {
            $mform->setDefault('qtypeheading', get_string('editbranchtable', 'escape'));
        } else {
            $mform->setDefault('qtypeheading', get_string('addabranchtable', 'escape'));
        }

        $mform->addElement('hidden', 'firstpage');
        $mform->setType('firstpage', PARAM_BOOL);
        $mform->setDefault('firstpage', $firstpage);

        $mform->addElement('hidden', 'qtype');
        $mform->setType('qtype', PARAM_INT);

        $mform->addElement('text', 'title', get_string("pagetitle", "escape"), array('size'=>70));
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'server');

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
        
        $this->editoroptions = array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$PAGE->course->maxbytes);
        $mform->addElement('editor', 'contents_editor', get_string("pagecontents", "escape"), null, $this->editoroptions);
        $mform->setType('contents_editor', PARAM_RAW);

        $mform->addElement('checkbox', 'layout', null, get_string("arrangebuttonshorizontally", "escape"));
        $mform->setDefault('layout', true);

        $mform->addElement('checkbox', 'display', null, get_string("displayinleftmenu", "escape"));
        $mform->setDefault('display', true);

        for ($i = 0; $i < $escape->maxanswers; $i++) {
            $mform->addElement('header', 'headeranswer'.$i, get_string('branch', 'escape').' '.($i+1));
            $this->add_answer($i, get_string("description", "escape"), $i == 0);

            $mform->addElement('select', 'jumpto['.$i.']', get_string("jump", "escape"), $jumptooptions);
            if ($i === 0) {
                $mform->setDefault('jumpto['.$i.']', 0);
            } else {
                $mform->setDefault('jumpto['.$i.']', ESCAPE_NEXTPAGE);
            }
        }
    }
}
