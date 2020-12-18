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
 * Form to define a new instance of escape or edit an instance.
 * It is used from /course/modedit.php.
 *
 * @package mod_escape
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/escape/locallib.php');

class mod_escape_mod_form extends moodleform_mod {

    protected $course = null;

    public function __construct($current, $section, $cm, $course) {
        $this->course = $course;
        parent::__construct($current, $section, $cm, $course);
    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function mod_escape_mod_form($current, $section, $cm, $course) {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct($current, $section, $cm, $course);
    }

    function definition() {
        global $CFG, $COURSE, $DB, $OUTPUT;

        $mform    = $this->_form;

        $escapeconfig = get_config('mod_escape');

        $mform->addElement('header', 'general', get_string('general', 'form'));

        /** Legacy slideshow width element to maintain backwards compatibility */
        $mform->addElement('hidden', 'width');
        $mform->setType('width', PARAM_INT);
        $mform->setDefault('width', $escapeconfig->slideshowwidth);

        /** Legacy slideshow height element to maintain backwards compatibility */
        $mform->addElement('hidden', 'height');
        $mform->setType('height', PARAM_INT);
        $mform->setDefault('height', $escapeconfig->slideshowheight);

        /** Legacy slideshow background color element to maintain backwards compatibility */
        $mform->addElement('hidden', 'bgcolor');
        $mform->setType('bgcolor', PARAM_TEXT);
        $mform->setDefault('bgcolor', $escapeconfig->slideshowbgcolor);

        /** Legacy media popup width element to maintain backwards compatibility */
        $mform->addElement('hidden', 'mediawidth');
        $mform->setType('mediawidth', PARAM_INT);
        $mform->setDefault('mediawidth', $escapeconfig->mediawidth);

        /** Legacy media popup height element to maintain backwards compatibility */
        $mform->addElement('hidden', 'mediaheight');
        $mform->setType('mediaheight', PARAM_INT);
        $mform->setDefault('mediaheight', $escapeconfig->mediaheight);

        /** Legacy media popup close button element to maintain backwards compatibility */
        $mform->addElement('hidden', 'mediaclose');
        $mform->setType('mediaclose', PARAM_BOOL);
        $mform->setDefault('mediaclose', $escapeconfig->mediaclose);

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        // Appearance.
        $mform->addElement('header', 'appearancehdr', get_string('appearance'));

        $filemanageroptions = array();
        $filemanageroptions['filetypes'] = '*';
        $filemanageroptions['maxbytes'] = $this->course->maxbytes;
        $filemanageroptions['subdirs'] = 0;
        $filemanageroptions['maxfiles'] = 1;

        $mform->addElement('filemanager', 'mediafile', get_string('mediafile', 'escape'), null, $filemanageroptions);
        $mform->addHelpButton('mediafile', 'mediafile', 'escape');
        $mform->setAdvanced('mediafile', $escapeconfig->mediafile_adv);

        $mform->addElement('selectyesno', 'progressbar', get_string('progressbar', 'escape'));
        $mform->addHelpButton('progressbar', 'progressbar', 'escape');
        $mform->setDefault('progressbar', $escapeconfig->progressbar);
        $mform->setAdvanced('progressbar', $escapeconfig->progressbar_adv);

        $mform->addElement('selectyesno', 'ongoing', get_string('ongoing', 'escape'));
        $mform->addHelpButton('ongoing', 'ongoing', 'escape');
        $mform->setDefault('ongoing', $escapeconfig->ongoing);
        $mform->setAdvanced('ongoing', $escapeconfig->ongoing_adv);

        $mform->addElement('selectyesno', 'displayleft', get_string('displayleftmenu', 'escape'));
        $mform->addHelpButton('displayleft', 'displayleftmenu', 'escape');
        $mform->setDefault('displayleft', $escapeconfig->displayleftmenu);
        $mform->setAdvanced('displayleft', $escapeconfig->displayleftmenu_adv);

        $options = array();
        for($i = 100; $i >= 0; $i--) {
            $options[$i] = $i.'%';
        }
        $mform->addElement('select', 'displayleftif', get_string('displayleftif', 'escape'), $options);
        $mform->addHelpButton('displayleftif', 'displayleftif', 'escape');
        $mform->setDefault('displayleftif', $escapeconfig->displayleftif);
        $mform->setAdvanced('displayleftif', $escapeconfig->displayleftif_adv);

        $mform->addElement('selectyesno', 'slideshow', get_string('slideshow', 'escape'));
        $mform->addHelpButton('slideshow', 'slideshow', 'escape');
        $mform->setDefault('slideshow', $escapeconfig->slideshow);
        $mform->setAdvanced('slideshow', $escapeconfig->slideshow_adv);

        $numbers = array();
        for ($i = 20; $i > 1; $i--) {
            $numbers[$i] = $i;
        }

        $mform->addElement('select', 'maxanswers', get_string('maximumnumberofanswersbranches', 'escape'), $numbers);
        $mform->setDefault('maxanswers', $escapeconfig->maxanswers);
        $mform->setAdvanced('maxanswers', $escapeconfig->maxanswers_adv);
        $mform->setType('maxanswers', PARAM_INT);
        $mform->addHelpButton('maxanswers', 'maximumnumberofanswersbranches', 'escape');

        $mform->addElement('selectyesno', 'feedback', get_string('displaydefaultfeedback', 'escape'));
        $mform->addHelpButton('feedback', 'displaydefaultfeedback', 'escape');
        $mform->setDefault('feedback', $escapeconfig->defaultfeedback);
        $mform->setAdvanced('feedback', $escapeconfig->defaultfeedback_adv);

        // Get the modules.
        if ($mods = get_course_mods($COURSE->id)) {
            $modinstances = array();
            foreach ($mods as $mod) {
                // Get the module name and then store it in a new array.
                if ($module = get_coursemodule_from_instance($mod->modname, $mod->instance, $COURSE->id)) {
                    // Exclude this escape, if it's already been saved.
                    if (!isset($this->_cm->id) || $this->_cm->id != $mod->id) {
                        $modinstances[$mod->id] = $mod->modname.' - '.$module->name;
                    }
                }
            }
            asort($modinstances); // Sort by module name.
            $modinstances=array(0=>get_string('none'))+$modinstances;

            $mform->addElement('select', 'activitylink', get_string('activitylink', 'escape'), $modinstances);
            $mform->addHelpButton('activitylink', 'activitylink', 'escape');
            $mform->setDefault('activitylink', 0);
            $mform->setAdvanced('activitylink', $escapeconfig->activitylink_adv);
        }

        // Availability.
        $mform->addElement('header', 'availabilityhdr', get_string('availability'));

        $mform->addElement('date_time_selector', 'available', get_string('available', 'escape'), array('optional'=>true));
        $mform->setDefault('available', 0);

        $mform->addElement('date_time_selector', 'deadline', get_string('deadline', 'escape'), array('optional'=>true));
        $mform->setDefault('deadline', 0);

        // Time limit.
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'escape'),
                array('optional' => true));
        $mform->addHelpButton('timelimit', 'timelimit', 'escape');
        $mform->setAdvanced('timelimit', $escapeconfig->timelimit_adv);
        $mform->setDefault('timelimit', $escapeconfig->timelimit);

        $mform->addElement('selectyesno', 'usepassword', get_string('usepassword', 'escape'));
        $mform->addHelpButton('usepassword', 'usepassword', 'escape');
        $mform->setDefault('usepassword', $escapeconfig->password);
        $mform->setAdvanced('usepassword', $escapeconfig->password_adv);

        $mform->addElement('passwordunmask', 'password', get_string('password', 'escape'));
        $mform->setDefault('password', '');
        $mform->setAdvanced('password', $escapeconfig->password_adv);
        $mform->setType('password', PARAM_RAW);
        $mform->disabledIf('password', 'usepassword', 'eq', 0);
        $mform->disabledIf('passwordunmask', 'usepassword', 'eq', 0);

        // Dependent on.
        if ($this->current && isset($this->current->dependency) && $this->current->dependency) {
            $mform->addElement('header', 'dependencyon', get_string('prerequisiteescape', 'escape'));
            $mform->addElement('static', 'warningobsolete',
                get_string('warning', 'escape'),
                get_string('prerequisiteisobsolete', 'escape'));
            $options = array(0 => get_string('none'));
            if ($escapes = get_all_instances_in_course('escape', $COURSE)) {
                foreach ($escapes as $escape) {
                    if ($escape->id != $this->_instance) {
                        $options[$escape->id] = format_string($escape->name, true);
                    }

                }
            }
            $mform->addElement('select', 'dependency', get_string('dependencyon', 'escape'), $options);
            $mform->addHelpButton('dependency', 'dependencyon', 'escape');
            $mform->setDefault('dependency', 0);

            $mform->addElement('text', 'timespent', get_string('timespentminutes', 'escape'));
            $mform->setDefault('timespent', 0);
            $mform->setType('timespent', PARAM_INT);
            $mform->disabledIf('timespent', 'dependency', 'eq', 0);

            $mform->addElement('checkbox', 'completed', get_string('completed', 'escape'));
            $mform->setDefault('completed', 0);
            $mform->disabledIf('completed', 'dependency', 'eq', 0);

            $mform->addElement('text', 'gradebetterthan', get_string('gradebetterthan', 'escape'));
            $mform->setDefault('gradebetterthan', 0);
            $mform->setType('gradebetterthan', PARAM_INT);
            $mform->disabledIf('gradebetterthan', 'dependency', 'eq', 0);
        } else {
            $mform->addElement('hidden', 'dependency', 0);
            $mform->setType('dependency', PARAM_INT);
            $mform->addElement('hidden', 'timespent', 0);
            $mform->setType('timespent', PARAM_INT);
            $mform->addElement('hidden', 'completed', 0);
            $mform->setType('completed', PARAM_INT);
            $mform->addElement('hidden', 'gradebetterthan', 0);
            $mform->setType('gradebetterthan', PARAM_INT);
            $mform->setConstants(array('dependency' => 0, 'timespent' => 0,
                    'completed' => 0, 'gradebetterthan' => 0));
        }

        // Allow to enable offline escapes only if the Mobile services are enabled.
        if ($CFG->enablemobilewebservice) {
            $mform->addElement('selectyesno', 'allowofflineattempts', get_string('allowofflineattempts', 'escape'));
            $mform->addHelpButton('allowofflineattempts', 'allowofflineattempts', 'escape');
            $mform->setDefault('allowofflineattempts', 0);
            $mform->setAdvanced('allowofflineattempts');
            $mform->disabledIf('allowofflineattempts', 'timelimit[number]', 'neq', 0);

            $mform->addElement('static', 'allowofflineattemptswarning', '',
                    $OUTPUT->notification(get_string('allowofflineattempts_help', 'escape'), 'warning'));
            $mform->setAdvanced('allowofflineattemptswarning');
        } else {
            $mform->addElement('hidden', 'allowofflineattempts', 0);
            $mform->setType('allowofflineattempts', PARAM_INT);
        }

        // Flow control.
        $mform->addElement('header', 'flowcontrol', get_string('flowcontrol', 'escape'));

        $mform->addElement('selectyesno', 'modattempts', get_string('modattempts', 'escape'));
        $mform->addHelpButton('modattempts', 'modattempts', 'escape');
        $mform->setDefault('modattempts', $escapeconfig->modattempts);
        $mform->setAdvanced('modattempts', $escapeconfig->modattempts_adv);

        $mform->addElement('selectyesno', 'review', get_string('displayreview', 'escape'));
        $mform->addHelpButton('review', 'displayreview', 'escape');
        $mform->setDefault('review', $escapeconfig->displayreview);
        $mform->setAdvanced('review', $escapeconfig->displayreview_adv);

        $numbers = array();
        for ($i = 10; $i > 0; $i--) {
            $numbers[$i] = $i;
        }
        $mform->addElement('select', 'maxattempts', get_string('maximumnumberofattempts', 'escape'), $numbers);
        $mform->addHelpButton('maxattempts', 'maximumnumberofattempts', 'escape');
        $mform->setDefault('maxattempts', $escapeconfig->maximumnumberofattempts);
        $mform->setAdvanced('maxattempts', $escapeconfig->maximumnumberofattempts_adv);

        $defaultnextpages = array();
        $defaultnextpages[0] = get_string('normal', 'escape');
        $defaultnextpages[ESCAPE_UNSEENPAGE] = get_string('showanunseenpage', 'escape');
        $defaultnextpages[ESCAPE_UNANSWEREDPAGE] = get_string('showanunansweredpage', 'escape');
        $mform->addElement('select', 'nextpagedefault', get_string('actionaftercorrectanswer', 'escape'), $defaultnextpages);
        $mform->addHelpButton('nextpagedefault', 'actionaftercorrectanswer', 'escape');
        $mform->setDefault('nextpagedefault', $escapeconfig->defaultnextpage);
        $mform->setAdvanced('nextpagedefault', $escapeconfig->defaultnextpage_adv);

        $numbers = array();
        for ($i = 100; $i >= 0; $i--) {
            $numbers[$i] = $i;
        }
        $mform->addElement('select', 'maxpages', get_string('numberofpagestoshow', 'escape'), $numbers);
        $mform->addHelpButton('maxpages', 'numberofpagestoshow', 'escape');
        $mform->setDefault('maxpages', $escapeconfig->numberofpagestoshow);
        $mform->setAdvanced('maxpages', $escapeconfig->numberofpagestoshow_adv);

        // Grade.
        $this->standard_grading_coursemodule_elements();

        // No header here, so that the following settings are displayed in the grade section.

        $mform->addElement('selectyesno', 'practice', get_string('practice', 'escape'));
        $mform->addHelpButton('practice', 'practice', 'escape');
        $mform->setDefault('practice', $escapeconfig->practice);
        $mform->setAdvanced('practice', $escapeconfig->practice_adv);

        $mform->addElement('selectyesno', 'custom', get_string('customscoring', 'escape'));
        $mform->addHelpButton('custom', 'customscoring', 'escape');
        $mform->setDefault('custom', $escapeconfig->customscoring);
        $mform->setAdvanced('custom', $escapeconfig->customscoring_adv);

        $mform->addElement('selectyesno', 'retake', get_string('retakesallowed', 'escape'));
        $mform->addHelpButton('retake', 'retakesallowed', 'escape');
        $mform->setDefault('retake', $escapeconfig->retakesallowed);
        $mform->setAdvanced('retake', $escapeconfig->retakesallowed_adv);

        $options = array();
        $options[0] = get_string('usemean', 'escape');
        $options[1] = get_string('usemaximum', 'escape');
        $mform->addElement('select', 'usemaxgrade', get_string('handlingofretakes', 'escape'), $options);
        $mform->addHelpButton('usemaxgrade', 'handlingofretakes', 'escape');
        $mform->setDefault('usemaxgrade', $escapeconfig->handlingofretakes);
        $mform->setAdvanced('usemaxgrade', $escapeconfig->handlingofretakes_adv);
        $mform->disabledIf('usemaxgrade', 'retake', 'eq', '0');

        $numbers = array();
        for ($i = 100; $i >= 0; $i--) {
            $numbers[$i] = $i;
        }
        $mform->addElement('select', 'minquestions', get_string('minimumnumberofquestions', 'escape'), $numbers);
        $mform->addHelpButton('minquestions', 'minimumnumberofquestions', 'escape');
        $mform->setDefault('minquestions', $escapeconfig->minimumnumberofquestions);
        $mform->setAdvanced('minquestions', $escapeconfig->minimumnumberofquestions_adv);

//-------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
// buttons
        $this->add_action_buttons();
    }

    /**
     * Enforce defaults here
     *
     * @param array $defaultvalues Form defaults
     * @return void
     **/
    public function data_preprocessing(&$defaultvalues) {
        if (isset($defaultvalues['conditions'])) {
            $conditions = unserialize($defaultvalues['conditions']);
            $defaultvalues['timespent'] = $conditions->timespent;
            $defaultvalues['completed'] = $conditions->completed;
            $defaultvalues['gradebetterthan'] = $conditions->gradebetterthan;
        }

        // Set up the completion checkbox which is not part of standard data.
        $defaultvalues['completiontimespentenabled'] =
            !empty($defaultvalues['completiontimespent']) ? 1 : 0;

        if ($this->current->instance) {
            // Editing existing instance - copy existing files into draft area.
            $draftitemid = file_get_submitted_draft_itemid('mediafile');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_escape', 'mediafile', 0, array('subdirs'=>0, 'maxbytes' => $this->course->maxbytes, 'maxfiles' => 1));
            $defaultvalues['mediafile'] = $draftitemid;
        }
    }

    /**
     * Enforce validation rules here
     *
     * @param object $data Post data to validate
     * @return array
     **/
    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['available'] != 0 && $data['deadline'] != 0 &&
                $data['deadline'] < $data['available']) {
            $errors['deadline'] = get_string('closebeforeopen', 'escape');
        }

        if (!empty($data['usepassword']) && empty($data['password'])) {
            $errors['password'] = get_string('emptypassword', 'escape');
        }

        return $errors;
    }

    /**
     * Display module-specific activity completion rules.
     * Part of the API defined by moodleform_mod
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform = $this->_form;

        $mform->addElement('checkbox', 'completionendreached', get_string('completionendreached', 'escape'),
                get_string('completionendreached_desc', 'escape'));
        // Enable this completion rule by default.
        $mform->setDefault('completionendreached', 1);

        $group = array();
        $group[] =& $mform->createElement('checkbox', 'completiontimespentenabled', '',
                get_string('completiontimespent', 'escape'));
        $group[] =& $mform->createElement('duration', 'completiontimespent', '', array('optional' => false));
        $mform->addGroup($group, 'completiontimespentgroup', get_string('completiontimespentgroup', 'escape'), array(' '), false);
        $mform->disabledIf('completiontimespent[number]', 'completiontimespentenabled', 'notchecked');
        $mform->disabledIf('completiontimespent[timeunit]', 'completiontimespentenabled', 'notchecked');

        return array('completionendreached', 'completiontimespentgroup');
    }

    /**
     * Called during validation. Indicates whether a module-specific completion rule is selected.
     *
     * @param array $data Input data (not yet validated)
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionendreached']) || $data['completiontimespent'] > 0;
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        // Turn off completion setting if the checkbox is not ticked.
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completiontimespentenabled) || !$autocompletion) {
                $data->completiontimespent = 0;
            }
            if (empty($data->completionendreached) || !$autocompletion) {
                $data->completionendreached = 0;
            }
        }
    }
}

