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
 * Essay
 *
 * @package mod_escape
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

/** Essay question type */
define("ESCAPE_PAGE_ESSAY", "10");

class escape_page_type_essay extends escape_page {

    protected $type = escape_page::TYPE_QUESTION;
    protected $typeidstring = 'essay';
    protected $typeid = ESCAPE_PAGE_ESSAY;
    protected $string = null;

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

    /**
     * Unserialize attempt useranswer and add missing responseformat if needed
     * for compatibility with old records.
     *
     * @param string $useranswer serialized object
     * @return object
     */
    static public function extract_useranswer($useranswer) {
        $essayinfo = unserialize($useranswer);
        if (!isset($essayinfo->responseformat)) {
            $essayinfo->response = text_to_html($essayinfo->response, false, false);
            $essayinfo->responseformat = FORMAT_HTML;
        }
        return $essayinfo;
    }

    public function display($renderer, $attempt) {
        global $PAGE, $CFG, $USER;

        $mform = new escape_display_answer_form_essay($CFG->wwwroot.'/mod/escape/continue.php', array('contents'=>$this->get_contents(), 'escapeid'=>$this->escape->id));

        $data = new stdClass;
        $data->id = $PAGE->cm->id;
        $data->pageid = $this->properties->id;
        if (isset($USER->modattempts[$this->escape->id])) {
            $essayinfo = self::extract_useranswer($attempt->useranswer);
            $data->answer = $essayinfo->answer;
        }
        $mform->set_data($data);

        // Trigger an event question viewed.
        $eventparams = array(
            'context' => context_module::instance($PAGE->cm->id),
            'objectid' => $this->properties->id,
            'other' => array(
                    'pagetype' => $this->get_typestring()
                )
            );

        $event = \mod_escape\event\question_viewed::create($eventparams);
        $event->trigger();
        return $mform->display();
    }
    public function create_answers($properties) {
        global $DB;
        // now add the answers
        $newanswer = new stdClass;
        $newanswer->escapeid = $this->escape->id;
        $newanswer->pageid = $this->properties->id;
        $newanswer->timecreated = $this->properties->timecreated;

        if (isset($properties->jumpto[0])) {
            $newanswer->jumpto = $properties->jumpto[0];
        }
        if (isset($properties->score[0])) {
            $newanswer->score = $properties->score[0];
        }
        $newanswer->id = $DB->insert_record("escape_answers", $newanswer);
        $answers = array($newanswer->id => new escape_page_answer($newanswer));
        $this->answers = $answers;
        return $answers;
    }
    public function check_answer() {
        global $PAGE, $CFG;
        $result = parent::check_answer();
        $result->isessayquestion = true;

        $mform = new escape_display_answer_form_essay($CFG->wwwroot.'/mod/escape/continue.php', array('contents'=>$this->get_contents()));
        $data = $mform->get_data();
        
        //Full the data by curl appeal
        if (!$data) {
            $data = new stdClass;
            $data->Submibutton = "Submit";
            $data->pageid = required_param('pageid', PARAM_RAW);
            $data->id = $PAGE->cm->id;
            $data->answer = required_param('answerorid', PARAM_RAW);
        }
        require_sesskey();

        if (!$data) {
            $result->inmediatejump = true;
            $result->newpageid = $this->properties->id;
            return $result;
        }

        if (is_array($data->answer)) {
            $studentanswer = $data->answer['text'];
            $studentanswerformat = $data->answer['format'];
        } else {
            $studentanswer = $data->answer;
            $studentanswerformat = FORMAT_HTML;
        }

        if (trim($studentanswer) === '') {
            $result->noanswer = true;
            return $result;
        }

        $answers = $this->get_answers();
        foreach ($answers as $answer) {
            $result->answerid = $answer->id;
            $result->newpageid = $answer->jumpto;
        }

        $userresponse = new stdClass;
        $userresponse->sent=0;
        $userresponse->graded = 0;
        $userresponse->score = 0;
        $userresponse->answer = $studentanswer;
        $userresponse->answerformat = $studentanswerformat;
        $userresponse->response = '';
        $userresponse->responseformat = FORMAT_HTML;
        $result->userresponse = serialize($userresponse);
        $result->studentanswerformat = $studentanswerformat;
        $result->studentanswer = $studentanswer;
        return $result;
    }
    public function update($properties, $context = null, $maxbytes = null) {
        global $DB, $PAGE;
        $answers  = $this->get_answers();
        $properties->id = $this->properties->id;
        $properties->escapeid = $this->escape->id;
        $properties->timemodified = time();
        $properties = file_postupdate_standard_editor($properties, 'contents', array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$PAGE->course->maxbytes), context_module::instance($PAGE->cm->id), 'mod_escape', 'page_contents', $properties->id);
        $DB->update_record("escape_pages", $properties);

        // Trigger an event: page updated.
        \mod_escape\event\page_updated::create_from_escape_page($this, $context)->trigger();

        if (!array_key_exists(0, $this->answers)) {
            $this->answers[0] = new stdClass;
            $this->answers[0]->escapeid = $this->escape->id;
            $this->answers[0]->pageid = $this->id;
            $this->answers[0]->timecreated = $this->timecreated;
        }
        if (isset($properties->jumpto[0])) {
            $this->answers[0]->jumpto = $properties->jumpto[0];
        }
        if (isset($properties->score[0])) {
            $this->answers[0]->score = $properties->score[0];
        }
        if (!isset($this->answers[0]->id)) {
            $this->answers[0]->id =  $DB->insert_record("escape_answers", $this->answers[0]);
        } else {
            $DB->update_record("escape_answers", $this->answers[0]->properties());
        }

        return true;
    }
    public function stats(array &$pagestats, $tries) {
        if(count($tries) > $this->escape->maxattempts) { // if there are more tries than the max that is allowed, grab the last "legal" attempt
            $temp = $tries[$this->escape->maxattempts - 1];
        } else {
            // else, user attempted the question less than the max, so grab the last one
            $temp = end($tries);
        }
        $essayinfo = self::extract_useranswer($temp->useranswer);
        if ($essayinfo->graded) {
            if (isset($pagestats[$temp->pageid])) {
                $essaystats = $pagestats[$temp->pageid];
                $essaystats->totalscore += $essayinfo->score;
                $essaystats->total++;
                $pagestats[$temp->pageid] = $essaystats;
            } else {
                $essaystats = new stdClass();
                $essaystats->totalscore = $essayinfo->score;
                $essaystats->total = 1;
                $pagestats[$temp->pageid] = $essaystats;
            }
        }
        return true;
    }
    public function report_answers($answerpage, $answerdata, $useranswer, $pagestats, &$i, &$n) {
        global $PAGE, $DB;

        $formattextdefoptions = new stdClass();
        $formattextdefoptions->noclean = true;
        $formattextdefoptions->para = false;
        $formattextdefoptions->context = $answerpage->context;
        $answers = $this->get_answers();

        foreach ($answers as $answer) {
            $hasattempts = $DB->record_exists('escape_attempts', ['answerid' => $answer->id]);
            if ($useranswer != null) {
                $essayinfo = self::extract_useranswer($useranswer->useranswer);
                if ($essayinfo->response == null) {
                    $answerdata->response = get_string("nocommentyet", "escape");
                } else {
                    $essayinfo->response = file_rewrite_pluginfile_urls($essayinfo->response, 'pluginfile.php',
                            $answerpage->context->id, 'mod_escape', 'essay_responses', $useranswer->id);
                    $answerdata->response  = format_text($essayinfo->response, $essayinfo->responseformat, $formattextdefoptions);
                }
                if (isset($pagestats[$this->properties->id])) {
                    $percent = $pagestats[$this->properties->id]->totalscore / $pagestats[$this->properties->id]->total * 100;
                    $percent = round($percent, 2);
                    $percent = get_string("averagescore", "escape").": ". $percent ."%";
                } else {
                    // dont think this should ever be reached....
                    $percent = get_string("nooneansweredthisquestion", "escape");
                }
                if ($essayinfo->graded) {
                    if ($this->escape->custom) {
                        $answerdata->score = get_string("pointsearned", "escape").": " . $essayinfo->score;
                    } elseif ($essayinfo->score) {
                        $answerdata->score = get_string("receivedcredit", "escape");
                    } else {
                        $answerdata->score = get_string("didnotreceivecredit", "escape");
                    }
                } else {
                    $answerdata->score = get_string("havenotgradedyet", "escape");
                }
            } else {
                $essayinfo = new stdClass();
                if ($hasattempts && has_capability('mod/escape:grade', $answerpage->context)) {
                    $essayinfo->answer = html_writer::link(new moodle_url("/mod/escape/essay.php",
                        ['id' => $PAGE->cm->id]), get_string("viewessayanswers", "escape"));
                } else {
                    $essayinfo->answer = "";
                }
                $essayinfo->answerformat = null;
            }

            // The essay question has been graded.
            if (isset($pagestats[$this->properties->id])) {
                $avescore = $pagestats[$this->properties->id]->totalscore / $pagestats[$this->properties->id]->total;
                $avescore = round($avescore, 2);
                $avescore = get_string("averagescore", "escape").": ". $avescore ;
            } else {
                $avescore = $hasattempts ? get_string("essaynotgradedyet", "escape") :
                        get_string("nooneansweredthisquestion", "escape");
            }
            // This is the student's answer so it should be cleaned.
            $answerdata->answers[] = array(format_text($essayinfo->answer, $essayinfo->answerformat,
                    array('para' => true, 'context' => $answerpage->context)), $avescore);
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }
    public function is_unanswered($nretakes) {
        global $DB, $USER;
        if (!$DB->count_records("escape_attempts", array('pageid'=>$this->properties->id, 'userid'=>$USER->id, 'retry'=>$nretakes))) {
            return true;
        }
        return false;
    }
    public function requires_manual_grading() {
        return true;
    }
    public function get_earnedscore($answers, $attempt) {
        $essayinfo = self::extract_useranswer($attempt->useranswer);
        return $essayinfo->score;
    }
}

class escape_add_page_form_essay extends escape_add_page_form_base {

    public $qtype = 'essay';
    public $qtypestring = 'essay';

    public function custom_definition() {
        
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

        $this->_form->addElement('hidden', 'location');

        $this->add_jumpto(0);
        $this->add_score(0, null, 1);

    }
}

class escape_display_answer_form_essay extends moodleform {

    public function definition() {
        global $USER, $OUTPUT;
        $mform = $this->_form;
        $contents = $this->_customdata['contents'];

        $hasattempt = false;
        $attrs = '';
        $useranswer = '';
        $useranswerraw = '';
        if (isset($this->_customdata['escapeid'])) {
            $escapeid = $this->_customdata['escapeid'];
            if (isset($USER->modattempts[$escapeid]->useranswer) && !empty($USER->modattempts[$escapeid]->useranswer)) {
                $attrs = array('disabled' => 'disabled');
                $hasattempt = true;
                $useranswertemp = escape_page_type_essay::extract_useranswer($USER->modattempts[$escapeid]->useranswer);
                $useranswer = htmlspecialchars_decode($useranswertemp->answer, ENT_QUOTES);
                $useranswerraw = $useranswertemp->answer;
            }
        }

        // Disable shortforms.
        $mform->setDisableShortforms();

        $mform->addElement('header', 'pageheader');

        $mform->addElement('html', $OUTPUT->container($contents, 'contents'));

        $options = new stdClass;
        $options->para = false;
        $options->noclean = true;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);

        if ($hasattempt) {
            $mform->addElement('hidden', 'answer', $useranswerraw);
            $mform->setType('answer', PARAM_RAW);
            $mform->addElement('html', $OUTPUT->container(get_string('youranswer', 'escape'), 'youranswer'));
            $mform->addElement('html', $OUTPUT->container($useranswer, 'reviewessay'));
            $this->add_action_buttons(null, get_string("nextpage", "escape"));
        } else {
            $mform->addElement('editor', 'answer', get_string('youranswer', 'escape'), null, null);
            $mform->setType('answer', PARAM_RAW);
            $this->add_action_buttons(null, get_string("submit", "escape"));
        }
    }
}
