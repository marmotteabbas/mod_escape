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
 * Settings form for overrides in the escape module.
 *
 * @package    mod_escape
 * @copyright  2015 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/escape/mod_form.php');


/**
 * Form for editing settings overrides.
 *
 * @copyright  2015 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class escape_override_form extends moodleform {

    /** @var object course module object. */
    protected $cm;

    /** @var object the escape settings object. */
    protected $escape;

    /** @var context the escape context. */
    protected $context;

    /** @var bool editing group override (true) or user override (false). */
    protected $groupmode;

    /** @var int groupid, if provided. */
    protected $groupid;

    /** @var int userid, if provided. */
    protected $userid;

    /**
     * Constructor.
     * @param moodle_url $submiturl the form action URL.
     * @param object $cm course module object.
     * @param object $escape the escape settings object.
     * @param object $context the escape context.
     * @param bool $groupmode editing group override (true) or user override (false).
     * @param object $override the override being edited, if it already exists.
     */
    public function __construct($submiturl, $cm, $escape, $context, $groupmode, $override) {

        $this->cm = $cm;
        $this->escape = $escape;
        $this->context = $context;
        $this->groupmode = $groupmode;
        $this->groupid = empty($override->groupid) ? 0 : $override->groupid;
        $this->userid = empty($override->userid) ? 0 : $override->userid;

        parent::__construct($submiturl, null, 'post');

    }

    /**
     * Define this form - called by the parent constructor
     */
    protected function definition() {
        global $CFG, $DB;

        $cm = $this->cm;
        $mform = $this->_form;

        $mform->addElement('header', 'override', get_string('override', 'escape'));

        if ($this->groupmode) {
            // Group override.
            if ($this->groupid) {
                // There is already a groupid, so freeze the selector.
                $groupchoices = array();
                $groupchoices[$this->groupid] = groups_get_group_name($this->groupid);
                $mform->addElement('select', 'groupid',
                        get_string('overridegroup', 'escape'), $groupchoices);
                $mform->freeze('groupid');
            } else {
                // Prepare the list of groups.
                $groups = groups_get_all_groups($cm->course);
                if (empty($groups)) {
                    // Generate an error.
                    $link = new moodle_url('/mod/escape/overrides.php', array('cmid' => $cm->id));
                    print_error('groupsnone', 'escape', $link);
                }

                $groupchoices = array();
                foreach ($groups as $group) {
                    $groupchoices[$group->id] = $group->name;
                }
                unset($groups);

                if (count($groupchoices) == 0) {
                    $groupchoices[0] = get_string('none');
                }

                $mform->addElement('select', 'groupid',
                        get_string('overridegroup', 'escape'), $groupchoices);
                $mform->addRule('groupid', get_string('required'), 'required', null, 'client');
            }
        } else {
            // User override.
            if ($this->userid) {
                // There is already a userid, so freeze the selector.
                $user = $DB->get_record('user', array('id' => $this->userid));
                $userchoices = array();
                $userchoices[$this->userid] = fullname($user);
                $mform->addElement('select', 'userid',
                        get_string('overrideuser', 'escape'), $userchoices);
                $mform->freeze('userid');
            } else {
                // Prepare the list of users.
                $users = get_enrolled_users($this->context, '', 0,
                        'u.id, u.email, ' . get_all_user_name_fields(true, 'u'));

                // Filter users based on any fixed restrictions (groups, profile).
                $info = new \core_availability\info_module($cm);
                $users = $info->filter_user_list($users);

                if (empty($users)) {
                    // Generate an error.
                    $link = new moodle_url('/mod/escape/overrides.php', array('cmid' => $cm->id));
                    print_error('usersnone', 'escape', $link);
                }

                $userchoices = array();
                $canviewemail = in_array('email', get_extra_user_fields($this->context));
                foreach ($users as $id => $user) {
                    if (empty($invalidusers[$id]) || (!empty($override) &&
                            $id == $override->userid)) {
                        if ($canviewemail) {
                            $userchoices[$id] = fullname($user) . ', ' . $user->email;
                        } else {
                            $userchoices[$id] = fullname($user);
                        }
                    }
                }
                unset($users);

                if (count($userchoices) == 0) {
                    $userchoices[0] = get_string('none');
                }
                $mform->addElement('searchableselector', 'userid',
                        get_string('overrideuser', 'escape'), $userchoices);
                $mform->addRule('userid', get_string('required'), 'required', null, 'client');
            }
        }

        // Password.
        // This field has to be above the date and timelimit fields,
        // otherwise browsers will clear it when those fields are changed.
        $mform->addElement('passwordunmask', 'password', get_string('usepassword', 'escape'));
        $mform->setType('password', PARAM_TEXT);
        $mform->addHelpButton('password', 'usepassword', 'escape');
        $mform->setDefault('password', $this->escape->password);;

        // Open and close dates.
        $mform->addElement('date_time_selector', 'available', get_string('available', 'escape'), array('optional' => true));
        $mform->setDefault('available', $this->escape->available);

        $mform->addElement('date_time_selector', 'deadline', get_string('deadline', 'escape'), array('optional' => true));
        $mform->setDefault('deadline', $this->escape->deadline);

        // Escape time limit.
        $mform->addElement('duration', 'timelimit',
                get_string('timelimit', 'escape'), array('optional' => true));
        if ($this->escape->timelimit != 0) {
            $mform->setDefault('timelimit', 0);
        } else {
            $mform->setDefault('timelimit', $this->escape->timelimit);
        }

        // Try a question again.
        $mform->addElement('selectyesno', 'review', get_string('displayreview', 'escape'));
        $mform->addHelpButton('review', 'displayreview', 'escape');
        $mform->setDefault('review', $this->escape->review);

        // Number of attempts.
        $numbers = array();
        for ($i = 10; $i > 0; $i--) {
            $numbers[$i] = $i;
        }
        $mform->addElement('select', 'maxattempts', get_string('maximumnumberofattempts', 'escape'), $numbers);
        $mform->addHelpButton('maxattempts', 'maximumnumberofattempts', 'escape');
        $mform->setDefault('maxattempts', $this->escape->maxattempts);

        // Retake allowed.
        $mform->addElement('selectyesno', 'retake', get_string('retakesallowed', 'escape'));
        $mform->addHelpButton('retake', 'retakesallowed', 'escape');
        $mform->setDefault('retake', $this->escape->retake);

        // Submit buttons.
        $mform->addElement('submit', 'resetbutton',
                get_string('reverttodefaults', 'escape'));

        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'submitbutton',
                get_string('save', 'escape'));
        $buttonarray[] = $mform->createElement('submit', 'againbutton',
                get_string('saveoverrideandstay', 'escape'));
        $buttonarray[] = $mform->createElement('cancel');

        $mform->addGroup($buttonarray, 'buttonbar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonbar');

    }

    /**
     * Validate the submitted form data.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors
     */
    public function validation($data, $files) {
        global $COURSE, $DB;
        $errors = parent::validation($data, $files);

        $mform =& $this->_form;
        $escape = $this->escape;

        if ($mform->elementExists('userid')) {
            if (empty($data['userid'])) {
                $errors['userid'] = get_string('required');
            }
        }

        if ($mform->elementExists('groupid')) {
            if (empty($data['groupid'])) {
                $errors['groupid'] = get_string('required');
            }
        }

        // Ensure that the dates make sense.
        if (!empty($data['available']) && !empty($data['deadline'])) {
            if ($data['deadline'] < $data['available'] ) {
                $errors['deadline'] = get_string('closebeforeopen', 'escape');
            }
        }

        // Ensure that at least one escape setting was changed.
        $changed = false;
        $keys = array('available', 'deadline', 'review', 'timelimit', 'maxattempts', 'retake', 'password');
        foreach ($keys as $key) {
            if ($data[$key] != $escape->{$key}) {
                $changed = true;
                break;
            }
        }

        if (!$changed) {
            $errors['available'] = get_string('nooverridedata', 'escape');
        }

        return $errors;
    }
}