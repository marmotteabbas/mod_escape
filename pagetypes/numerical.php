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
 * Numerical
 *
 * @package mod_escape
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

/** Numerical question type */
define("ESCAPE_PAGE_NUMERICAL",     "8");

class escape_page_type_numerical extends escape_page {

    protected $type = escape_page::TYPE_QUESTION;
    protected $typeidstring = 'numerical';
    protected $typeid = ESCAPE_PAGE_NUMERICAL;
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
    public function display($renderer, $attempt) {
        global $USER, $CFG, $PAGE;
        $mform = new escape_display_answer_form_shortanswer($CFG->wwwroot.'/mod/escape/continue.php', array('contents'=>$this->get_contents(), 'escapeid'=>$this->escape->id));
        $data = new stdClass;
        $data->id = $PAGE->cm->id;
        $data->pageid = $this->properties->id;
        if (isset($USER->modattempts[$this->escape->id])) {
            $data->answer = s($attempt->useranswer);
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
    public function check_answer() {
        global $CFG;
        $result = parent::check_answer();

        $mform = new escape_display_answer_form_shortanswer($CFG->wwwroot.'/mod/escape/continue.php', array('contents'=>$this->get_contents()));
        $data = $mform->get_data();
        
        //Full the data by curl appeal
        if (!$data) {
            $data = new stdClass;
            $data->Submibutton = "Submit";
            $data->pageid = required_param('pageid', PARAM_RAW);
            $data->id = $PAGE->cm->id;
            $data->answer = array_shift(required_param('answerorid', PARAM_RAW));
        }
        require_sesskey();

        $formattextdefoptions = new stdClass();
        $formattextdefoptions->noclean = true;
        $formattextdefoptions->para = false;

        // set defaults
        $result->response = '';
        $result->newpageid = 0;

        if (!isset($data->answer) || !is_numeric($data->answer)) {
            $result->noanswer = true;
            return $result;
        } else {
            // Just doing default PARAM_RAW, not doing PARAM_INT because it could be a float.
            $result->useranswer = (float)$data->answer;
        }
        $result->studentanswer = $result->userresponse = $result->useranswer;
        $answers = $this->get_answers();
        foreach ($answers as $answer) {
            $answer = parent::rewrite_answers_urls($answer);
            if (strpos($answer->answer, ':')) {
                // there's a pairs of values
                list($min, $max) = explode(':', $answer->answer);
                $minimum = (float) $min;
                $maximum = (float) $max;
            } else {
                // there's only one value
                $minimum = (float) $answer->answer;
                $maximum = $minimum;
            }
            if (($result->useranswer >= $minimum) && ($result->useranswer <= $maximum)) {
                $result->newpageid = $answer->jumpto;
                $result->response = format_text($answer->response, $answer->responseformat, $formattextdefoptions);
                if ($this->escape->jumpto_is_correct($this->properties->id, $result->newpageid)) {
                    $result->correctanswer = true;
                }
                if ($this->escape->custom) {
                    if ($answer->score > 0) {
                        $result->correctanswer = true;
                    } else {
                        $result->correctanswer = false;
                    }
                }
                $result->answerid = $answer->id;
                return $result;
            }
        }
        return $result;
    }

    public function display_answers(html_table $table) {
        $answers = $this->get_answers();
        $options = new stdClass;
        $options->noclean = true;
        $options->para = false;
        $i = 1;
        foreach ($answers as $answer) {
            $answer = parent::rewrite_answers_urls($answer, false);
            $cells = array();
            if ($this->escape->custom && $answer->score > 0) {
                // if the score is > 0, then it is correct
                $cells[] = '<label class="correct">' . get_string('answer', 'escape') . ' ' . $i . '</label>:';
            } else if ($this->escape->custom) {
                $cells[] = '<label>' . get_string('answer', 'escape') . ' ' . $i . '</label>:';
            } else if ($this->escape->jumpto_is_correct($this->properties->id, $answer->jumpto)) {
                // underline correct answers
                $cells[] = '<span class="correct">' . get_string('answer', 'escape') . ' ' . $i . '</span>:' . "\n";
            } else {
                $cells[] = '<label class="correct">' . get_string('answer', 'escape') . ' ' . $i . '</label>:';
            }
            $cells[] = format_text($answer->answer, $answer->answerformat, $options);
            $table->data[] = new html_table_row($cells);

            $cells = array();
            $cells[] = '<label>' . get_string('response', 'escape') . ' ' . $i . '</label>:';
            $cells[] = format_text($answer->response, $answer->responseformat, $options);
            $table->data[] = new html_table_row($cells);

            $cells = array();
            $cells[] = '<label>' . get_string('score', 'escape') . '</label>:';
            $cells[] = $answer->score;
            $table->data[] = new html_table_row($cells);

            $cells = array();
            $cells[] = '<label>' . get_string('jump', 'escape') . '</label>:';
            $cells[] = $this->get_jump_name($answer->jumpto);
            $table->data[] = new html_table_row($cells);
            if ($i === 1){
                $table->data[count($table->data)-1]->cells[0]->style = 'width:20%;';
            }
            $i++;
        }
        return $table;
    }
    public function stats(array &$pagestats, $tries) {
        if(count($tries) > $this->escape->maxattempts) { // if there are more tries than the max that is allowed, grab the last "legal" attempt
            $temp = $tries[$this->escape->maxattempts - 1];
        } else {
            // else, user attempted the question less than the max, so grab the last one
            $temp = end($tries);
        }
        if (isset($pagestats[$temp->pageid][$temp->useranswer])) {
            $pagestats[$temp->pageid][$temp->useranswer]++;
        } else {
            $pagestats[$temp->pageid][$temp->useranswer] = 1;
        }
        if (isset($pagestats[$temp->pageid]["total"])) {
            $pagestats[$temp->pageid]["total"]++;
        } else {
            $pagestats[$temp->pageid]["total"] = 1;
        }
        return true;
    }

    public function report_answers($answerpage, $answerdata, $useranswer, $pagestats, &$i, &$n) {
        $answers = $this->get_answers();
        $formattextdefoptions = new stdClass;
        $formattextdefoptions->para = false;  //I'll use it widely in this page
        foreach ($answers as $answer) {
            if ($useranswer == null && $i == 0) {
                // I have the $i == 0 because it is easier to blast through it all at once.
                if (isset($pagestats[$this->properties->id])) {
                    $stats = $pagestats[$this->properties->id];
                    $total = $stats["total"];
                    unset($stats["total"]);
                    foreach ($stats as $valentered => $ntimes) {
                        $data = '<input class="form-control" type="text" size="50" ' .
                                'disabled="disabled" readonly="readonly" value="'.s($valentered).'" />';
                        $percent = $ntimes / $total * 100;
                        $percent = round($percent, 2);
                        $percent .= "% ".get_string("enteredthis", "escape");
                        $answerdata->answers[] = array($data, $percent);
                    }
                } else {
                    $answerdata->answers[] = array(get_string("nooneansweredthisquestion", "escape"), " ");
                }
                $i++;
            } else if ($useranswer != null && ($answer->id == $useranswer->answerid || ($answer == end($answers) &&
                    empty($answerdata->answers)))) {
                // Get in here when the user answered or for the last answer.
                $data = '<input class="form-control" type="text" size="50" ' .
                        'disabled="disabled" readonly="readonly" value="'.s($useranswer->useranswer).'">';
                if (isset($pagestats[$this->properties->id][$useranswer->useranswer])) {
                    $percent = $pagestats[$this->properties->id][$useranswer->useranswer] / $pagestats[$this->properties->id]["total"] * 100;
                    $percent = round($percent, 2);
                    $percent .= "% ".get_string("enteredthis", "escape");
                } else {
                    $percent = get_string("nooneenteredthis", "escape");
                }
                $answerdata->answers[] = array($data, $percent);

                if ($answer->id == $useranswer->answerid) {
                    if ($answer->response == null) {
                        if ($useranswer->correct) {
                            $answerdata->response = get_string("thatsthecorrectanswer", "escape");
                        } else {
                            $answerdata->response = get_string("thatsthewronganswer", "escape");
                        }
                    } else {
                        $answerdata->response = $answer->response;
                    }
                    if ($this->escape->custom) {
                        $answerdata->score = get_string("pointsearned", "escape").": ".$answer->score;
                    } elseif ($useranswer->correct) {
                        $answerdata->score = get_string("receivedcredit", "escape");
                    } else {
                        $answerdata->score = get_string("didnotreceivecredit", "escape");
                    }
                } else {
                    $answerdata->response = get_string("thatsthewronganswer", "escape");
                    if ($this->escape->custom) {
                        $answerdata->score = get_string("pointsearned", "escape").": 0";
                    } else {
                        $answerdata->score = get_string("didnotreceivecredit", "escape");
                    }
                }
            }
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }
}

class escape_add_page_form_numerical extends escape_add_page_form_base {

    public $qtype = 'numerical';
    public $qtypestring = 'numerical';
    protected $answerformat = '';
    protected $responseformat = ESCAPE_ANSWER_HTML;

    public function custom_definition() {
        
        $this->_form->addElement('html', '<div id="fitem_id_title" class="form-group row  fitem   ">
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

        $this->_form->addElement('hidden', 'location');
        
        for ($i = 0; $i < $this->_customdata['escape']->maxanswers; $i++) {
            $this->_form->addElement('header', 'answertitle'.$i, get_string('answer').' '.($i+1));
            $this->add_answer($i, null, ($i < 1));
            $this->add_response($i);
            $this->add_jumpto($i, null, ($i == 0 ? ESCAPE_NEXTPAGE : ESCAPE_THISPAGE));
            $this->add_score($i, null, ($i===0)?1:0);
        }
    }
}

class escape_display_answer_form_numerical extends moodleform {

    public function definition() {
        global $USER, $OUTPUT;
        $mform = $this->_form;
        $contents = $this->_customdata['contents'];

        // Disable shortforms.
        $mform->setDisableShortforms();

        $mform->addElement('header', 'pageheader');

        $mform->addElement('html', $OUTPUT->container($contents, 'contents'));

        $hasattempt = false;
        $attrs = array('size'=>'50', 'maxlength'=>'200');
        if (isset($this->_customdata['escapeid'])) {
            $escapeid = $this->_customdata['escapeid'];
            if (isset($USER->modattempts[$escapeid]->useranswer)) {
                $attrs['readonly'] = 'readonly';
                $hasattempt = true;
            }
        }
        $options = new stdClass;
        $options->para = false;
        $options->noclean = true;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);

        $mform->addElement('text', 'answer', get_string('youranswer', 'escape'), $attrs);
        $mform->setType('answer', PARAM_FLOAT);

        if ($hasattempt) {
            $this->add_action_buttons(null, get_string("nextpage", "escape"));
        } else {
            $this->add_action_buttons(null, get_string("submit", "escape"));
        }
    }

}
