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
 * Restore date tests.
 *
 * @package    mod_escape
 * @copyright  2017 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . "/phpunit/classes/restore_date_testcase.php");

/**
 * Restore date tests.
 *
 * @package    mod_escape
 * @copyright  2017 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_escape_restore_date_testcase extends restore_date_testcase {

    /**
     * Creates an attempt for the given userwith a correct or incorrect answer and optionally finishes it.
     *
     * TODO This api can be better extracted to a generator.
     *
     * @param  stdClass $escape  Escape object.
     * @param  stdClass $page    page object.
     * @param  boolean $correct  If the answer should be correct.
     * @param  boolean $finished If we should finish the attempt.
     *
     * @return array the result of the attempt creation or finalisation.
     */
    protected function create_attempt($escape, $page, $correct = true, $finished = false) {
        global $DB, $USER;

        // First we need to launch the escape so the timer is on.
        mod_escape_external::launch_attempt($escape->id);

        $DB->set_field('escape', 'feedback', 1, array('id' => $escape->id));
        $DB->set_field('escape', 'progressbar', 1, array('id' => $escape->id));
        $DB->set_field('escape', 'custom', 0, array('id' => $escape->id));
        $DB->set_field('escape', 'maxattempts', 3, array('id' => $escape->id));

        $answercorrect = 0;
        $answerincorrect = 0;
        $p2answers = $DB->get_records('escape_answers', array('escapeid' => $escape->id, 'pageid' => $page->id), 'id');
        foreach ($p2answers as $answer) {
            if ($answer->jumpto == 0) {
                $answerincorrect = $answer->id;
            } else {
                $answercorrect = $answer->id;
            }
        }

        $data = array(
            array(
                'name' => 'answerid',
                'value' => $correct ? $answercorrect : $answerincorrect,
            ),
            array(
                'name' => '_qf__escape_display_answer_form_truefalse',
                'value' => 1,
            )
        );
        $result = mod_escape_external::process_page($escape->id, $page->id, $data);
        $result = external_api::clean_returnvalue(mod_escape_external::process_page_returns(), $result);

        // Create attempt.
        $newpageattempt = [
            'escapeid' => $escape->id,
            'pageid' => $page->id,
            'userid' => $USER->id,
            'answerid' => $answercorrect,
            'retry' => 1,   // First attempt is always 0.
            'correct' => 1,
            'useranswer' => '1',
            'timeseen' => time(),
        ];
        $DB->insert_record('escape_attempts', (object) $newpageattempt);

        if ($finished) {
            $result = mod_escape_external::finish_attempt($escape->id);
            $result = external_api::clean_returnvalue(mod_escape_external::finish_attempt_returns(), $result);
        }
        return $result;
    }

    /**
     * Test restore dates.
     */
    public function test_restore_dates() {
        global $DB, $USER;

        // Create escape data.
        $record = ['available' => 100, 'deadline' => 100, 'timemodified' => 100];
        list($course, $escape) = $this->create_course_and_module('escape', $record);
        $escapegenerator = $this->getDataGenerator()->get_plugin_generator('mod_escape');
        $page = $escapegenerator->create_content($escape);
        $page2 = $escapegenerator->create_question_truefalse($escape);
        $this->create_attempt($escape, $page2, true, true);

        $timer = $DB->get_record('escape_timer', ['escapeid' => $escape->id]);
        // Escape grade.
        $timestamp = 100;
        $grade = new stdClass();
        $grade->escapeid = $escape->id;
        $grade->userid = $USER->id;
        $grade->grade = 8.9;
        $grade->completed = $timestamp;
        $grade->id = $DB->insert_record('escape_grades', $grade);

        // User override.
        $override = (object)[
            'escapeid' => $escape->id,
            'groupid' => 0,
            'userid' => $USER->id,
            'sortorder' => 1,
            'available' => 100,
            'deadline' => 200
        ];
        $DB->insert_record('escape_overrides', $override);

        // Set time fields to a constant for easy validation.
        $DB->set_field('escape_pages', 'timecreated', $timestamp);
        $DB->set_field('escape_pages', 'timemodified', $timestamp);
        $DB->set_field('escape_answers', 'timecreated', $timestamp);
        $DB->set_field('escape_answers', 'timemodified', $timestamp);
        $DB->set_field('escape_attempts', 'timeseen', $timestamp);

        // Do backup and restore.
        $newcourseid = $this->backup_and_restore($course);
        $newescape = $DB->get_record('escape', ['course' => $newcourseid]);

        $this->assertFieldsNotRolledForward($escape, $newescape, ['timemodified']);
        $props = ['available', 'deadline'];
        $this->assertFieldsRolledForward($escape, $newescape, $props);

        $newpages = $DB->get_records('escape_pages', ['escapeid' => $newescape->id]);
        $newanswers = $DB->get_records('escape_answers', ['escapeid' => $newescape->id]);
        $newgrade = $DB->get_record('escape_grades', ['escapeid' => $newescape->id]);
        $newoverride = $DB->get_record('escape_overrides', ['escapeid' => $newescape->id]);
        $newtimer = $DB->get_record('escape_timer', ['escapeid' => $newescape->id]);
        $newattempt = $DB->get_record('escape_attempts', ['escapeid' => $newescape->id]);

        // Page time checks.
        foreach ($newpages as $newpage) {
            $this->assertEquals($timestamp, $newpage->timemodified);
            $this->assertEquals($timestamp, $newpage->timecreated);
        }

        // Page answers time checks.
        foreach ($newanswers as $newanswer) {
            $this->assertEquals($timestamp, $newanswer->timemodified);
            $this->assertEquals($timestamp, $newanswer->timecreated);
        }

        // Escape override time checks.
        $diff = $this->get_diff();
        $this->assertEquals($override->available + $diff, $newoverride->available);
        $this->assertEquals($override->deadline + $diff, $newoverride->deadline);

        // Escape grade time checks.
        $this->assertEquals($timestamp, $newgrade->completed);

        // Escape timer time checks.
        $this->assertEquals($timer->starttime, $newtimer->starttime);
        $this->assertEquals($timer->escapetime, $newtimer->escapetime);

        // Escape attempt time check.
        $this->assertEquals($timestamp, $newattempt->timeseen);
    }
}
