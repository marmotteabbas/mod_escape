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
 * Settings used by the escape module, were moved from mod_edit
 *
 * @package mod_escape
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/escape/locallib.php');
    $yesno = array(0 => get_string('no'), 1 => get_string('yes'));

    // Introductory explanation that all the settings are defaults for the add escape form.
    $settings->add(new admin_setting_heading('mod_escape/escapeintro', '', get_string('configintro', 'escape')));

    // Appearance settings.
    $settings->add(new admin_setting_heading('mod_escape/appearance', get_string('appearance'), ''));

    // Media file popup settings.
    $setting = new admin_setting_configempty('mod_escape/mediafile', get_string('mediafile', 'escape'),
            get_string('mediafile_help', 'escape'));

    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $settings->add($setting);

    $settings->add(new admin_setting_configtext('mod_escape/mediawidth', get_string('mediawidth', 'escape'),
            get_string('configmediawidth', 'escape'), 640, PARAM_INT));

    $settings->add(new admin_setting_configtext('mod_escape/mediaheight', get_string('mediaheight', 'escape'),
            get_string('configmediaheight', 'escape'), 480, PARAM_INT));

    $settings->add(new admin_setting_configcheckbox('mod_escape/mediaclose', get_string('mediaclose', 'escape'),
            get_string('configmediaclose', 'escape'), false, PARAM_TEXT));

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/progressbar',
        get_string('progressbar', 'escape'), get_string('progressbar_help', 'escape'),
        array('value' => 0, 'adv' => false), $yesno));

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/ongoing',
        get_string('ongoing', 'escape'), get_string('ongoing_help', 'escape'),
        array('value' => 0, 'adv' => true), $yesno));

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/displayleftmenu',
        get_string('displayleftmenu', 'escape'), get_string('displayleftmenu_help', 'escape'),
        array('value' => 0, 'adv' => false), $yesno));

    $percentage = array();
    for ($i = 100; $i >= 0; $i--) {
        $percentage[$i] = $i.'%';
    }
    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/displayleftif',
        get_string('displayleftif', 'escape'), get_string('displayleftif_help', 'escape'),
        array('value' => 0, 'adv' => true), $percentage));

    // Slideshow settings.
    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/slideshow',
        get_string('slideshow', 'escape'), get_string('slideshow_help', 'escape'),
        array('value' => 0, 'adv' => true), $yesno));

    $settings->add(new admin_setting_configtext('mod_escape/slideshowwidth', get_string('slideshowwidth', 'escape'),
            get_string('configslideshowwidth', 'escape'), 640, PARAM_INT));

    $settings->add(new admin_setting_configtext('mod_escape/slideshowheight', get_string('slideshowheight', 'escape'),
            get_string('configslideshowheight', 'escape'), 480, PARAM_INT));

    $settings->add(new admin_setting_configtext('mod_escape/slideshowbgcolor', get_string('slideshowbgcolor', 'escape'),
            get_string('configslideshowbgcolor', 'escape'), '#FFFFFF', PARAM_TEXT));

    $numbers = array();
    for ($i = 20; $i > 1; $i--) {
        $numbers[$i] = $i;
    }

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/maxanswers',
        get_string('maximumnumberofanswersbranches', 'escape'), get_string('maximumnumberofanswersbranches_help', 'escape'),
        array('value' => '5', 'adv' => true), $numbers));

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/defaultfeedback',
        get_string('displaydefaultfeedback', 'escape'), get_string('displaydefaultfeedback_help', 'escape'),
        array('value' => 0, 'adv' => true), $yesno));

    $setting = new admin_setting_configempty('mod_escape/activitylink', get_string('activitylink', 'escape'),
        '');

    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $settings->add($setting);

    // Availability settings.
    $settings->add(new admin_setting_heading('mod_escape/availibility', get_string('availability'), ''));

    $settings->add(new admin_setting_configduration_with_advanced('mod_escape/timelimit',
        get_string('timelimit', 'escape'), get_string('configtimelimit_desc', 'escape'),
            array('value' => '0', 'adv' => false), 60));

    $settings->add(new admin_setting_configcheckbox_with_advanced('mod_escape/password',
        get_string('password', 'escape'), get_string('configpassword_desc', 'escape'),
        array('value' => 0, 'adv' => true)));

    // Flow Control.
    $settings->add(new admin_setting_heading('escape/flowcontrol', get_string('flowcontrol', 'escape'), ''));

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/modattempts',
        get_string('modattempts', 'escape'), get_string('modattempts_help', 'escape'),
        array('value' => 0, 'adv' => false), $yesno));

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/displayreview',
        get_string('displayreview', 'escape'), get_string('displayreview_help', 'escape'),
        array('value' => 0, 'adv' => false), $yesno));

    $attempts = array();
    for ($i = 10; $i > 0; $i--) {
        $attempts[$i] = $i;
    }

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/maximumnumberofattempts',
        get_string('maximumnumberofattempts', 'escape'), get_string('maximumnumberofattempts_help', 'escape'),
        array('value' => '1', 'adv' => false), $attempts));

    $defaultnextpages = array();
    $defaultnextpages[0] = get_string("normal", "escape");
    $defaultnextpages[ESCAPE_UNSEENPAGE] = get_string("showanunseenpage", "escape");
    $defaultnextpages[ESCAPE_UNANSWEREDPAGE] = get_string("showanunansweredpage", "escape");

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/defaultnextpage',
            get_string('actionaftercorrectanswer', 'escape'), '',
            array('value' => 0, 'adv' => true), $defaultnextpages));

    $pages = array();
    for ($i = 100; $i >= 0; $i--) {
        $pages[$i] = $i;
    }
    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/numberofpagestoshow',
        get_string('numberofpagestoshow', 'escape'), get_string('numberofpagestoshow_help', 'escape'),
        array('value' => '1', 'adv' => true), $pages));

    // Grade.
    $settings->add(new admin_setting_heading('escape/grade', get_string('grade'), ''));

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/practice',
        get_string('practice', 'escape'), get_string('practice_help', 'escape'),
        array('value' => 0, 'adv' => false), $yesno));

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/customscoring',
        get_string('customscoring', 'escape'), get_string('customscoring_help', 'escape'),
        array('value' => 1, 'adv' => true), $yesno));

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/retakesallowed',
        get_string('retakesallowed', 'escape'), get_string('retakesallowed_help', 'escape'),
        array('value' => 0, 'adv' => false), $yesno));

    $options = array();
    $options[0] = get_string('usemean', 'escape');
    $options[1] = get_string('usemaximum', 'escape');

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/handlingofretakes',
        get_string('handlingofretakes', 'escape'), get_string('handlingofretakes_help', 'escape'),
        array('value' => 0, 'adv' => true), $options));

    $settings->add(new admin_setting_configselect_with_advanced('mod_escape/minimumnumberofquestions',
        get_string('minimumnumberofquestions', 'escape'), get_string('minimumnumberofquestions_help', 'escape'),
        array('value' => 0, 'adv' => true), $pages));

}
