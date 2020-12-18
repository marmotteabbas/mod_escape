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
 * Escape module external functions tests
 *
 * @package    mod_escape
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/escape/locallib.php');

/**
 * Silly class to access mod_escape_external internal methods.
 *
 * @package mod_escape
 * @copyright 2017 Juan Leyva <juan@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since  Moodle 3.3
 */
class testable_mod_escape_external extends mod_escape_external {

    /**
     * Validates a new attempt.
     *
     * @param  escape  $escape escape instance
     * @param  array   $params request parameters
     * @param  boolean $return whether to return the errors or throw exceptions
     * @return [array          the errors (if return set to true)
     * @since  Moodle 3.3
     */
    public static function validate_attempt(escape $escape, $params, $return = false) {
        return parent::validate_attempt($escape, $params, $return);
    }
}

/**
 * Escape module external functions tests
 *
 * @package    mod_escape
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */
class mod_escape_external_testcase extends externallib_advanced_testcase {

    /**
     * Set up for every test
     */
    public function setUp() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $this->course = $this->getDataGenerator()->create_course();
        $this->escape = $this->getDataGenerator()->create_module('escape', array('course' => $this->course->id));
        $escapegenerator = $this->getDataGenerator()->get_plugin_generator('mod_escape');
        $this->page1 = $escapegenerator->create_content($this->escape);
        $this->page2 = $escapegenerator->create_question_truefalse($this->escape);
        $this->context = context_module::instance($this->escape->cmid);
        $this->cm = get_coursemodule_from_instance('escape', $this->escape->id);

        // Create users.
        $this->student = self::getDataGenerator()->create_user();
        $this->teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $this->studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $this->teacherrole->id, 'manual');
    }


    /**
     * Test test_mod_escape_get_escapes_by_courses
     */
    public function test_mod_escape_get_escapes_by_courses() {
        global $DB;

        // Create additional course.
        $course2 = self::getDataGenerator()->create_course();

        // Second escape.
        $record = new stdClass();
        $record->course = $course2->id;
        $escape2 = self::getDataGenerator()->create_module('escape', $record);

        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $this->student->id, $this->studentrole->id);

        self::setUser($this->student);

        $returndescription = mod_escape_external::get_escapes_by_courses_returns();

        // Create what we expect to be returned when querying the two courses.
        // First for the student user.
        $expectedfields = array('id', 'coursemodule', 'course', 'name', 'intro', 'introformat', 'introfiles', 'practice',
                                'modattempts', 'usepassword', 'grade', 'custom', 'ongoing', 'usemaxgrade',
                                'maxanswers', 'maxattempts', 'review', 'nextpagedefault', 'feedback', 'minquestions',
                                'maxpages', 'timelimit', 'retake', 'mediafile', 'mediafiles', 'mediaheight', 'mediawidth',
                                'mediaclose', 'slideshow', 'width', 'height', 'bgcolor', 'displayleft', 'displayleftif',
                                'progressbar', 'allowofflineattempts');

        // Add expected coursemodule and data.
        $escape1 = $this->escape;
        $escape1->coursemodule = $escape1->cmid;
        $escape1->introformat = 1;
        $escape1->introfiles = [];
        $escape1->mediafiles = [];

        $escape2->coursemodule = $escape2->cmid;
        $escape2->introformat = 1;
        $escape2->introfiles = [];
        $escape2->mediafiles = [];

        $booltypes = array('practice', 'modattempts', 'usepassword', 'custom', 'ongoing', 'review', 'feedback', 'retake',
            'slideshow', 'displayleft', 'progressbar', 'allowofflineattempts');

        foreach ($expectedfields as $field) {
            if (in_array($field, $booltypes)) {
                $escape1->{$field} = (bool) $escape1->{$field};
                $escape2->{$field} = (bool) $escape2->{$field};
            }
            $expected1[$field] = $escape1->{$field};
            $expected2[$field] = $escape2->{$field};
        }

        $expectedescapes = array($expected2, $expected1);

        // Call the external function passing course ids.
        $result = mod_escape_external::get_escapes_by_courses(array($course2->id, $this->course->id));
        $result = external_api::clean_returnvalue($returndescription, $result);

        $this->assertEquals($expectedescapes, $result['escapes']);
        $this->assertCount(0, $result['warnings']);

        // Call the external function without passing course id.
        $result = mod_escape_external::get_escapes_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedescapes, $result['escapes']);
        $this->assertCount(0, $result['warnings']);

        // Unenrol user from second course and alter expected escapes.
        $enrol->unenrol_user($instance2, $this->student->id);
        array_shift($expectedescapes);

        // Call the external function without passing course id.
        $result = mod_escape_external::get_escapes_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedescapes, $result['escapes']);

        // Call for the second course we unenrolled the user from, expected warning.
        $result = mod_escape_external::get_escapes_by_courses(array($course2->id));
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('1', $result['warnings'][0]['warningcode']);
        $this->assertEquals($course2->id, $result['warnings'][0]['itemid']);

        // Now, try as a teacher for getting all the additional fields.
        self::setUser($this->teacher);

        $additionalfields = array('password', 'dependency', 'conditions', 'activitylink', 'available', 'deadline',
                                    'timemodified', 'completionendreached', 'completiontimespent');

        foreach ($additionalfields as $field) {
            $expectedescapes[0][$field] = $escape1->{$field};
        }

        $result = mod_escape_external::get_escapes_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedescapes, $result['escapes']);

        // Admin also should get all the information.
        self::setAdminUser();

        $result = mod_escape_external::get_escapes_by_courses(array($this->course->id));
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedescapes, $result['escapes']);

        // Now, add a restriction.
        $this->setUser($this->student);
        $DB->set_field('escape', 'usepassword', 1, array('id' => $escape1->id));
        $DB->set_field('escape', 'password', 'abc', array('id' => $escape1->id));

        $escapes = mod_escape_external::get_escapes_by_courses(array($this->course->id));
        $escapes = external_api::clean_returnvalue(mod_escape_external::get_escapes_by_courses_returns(), $escapes);
        $this->assertFalse(isset($escapes['escapes'][0]['intro']));
    }

    /**
     * Test the validate_attempt function.
     */
    public function test_validate_attempt() {
        global $DB;

        $this->setUser($this->student);
        // Test deadline.
        $oldtime = time() - DAYSECS;
        $DB->set_field('escape', 'deadline', $oldtime, array('id' => $this->escape->id));

        $escape = new escape($DB->get_record('escape', array('id' => $this->escape->id)));
        $validation = testable_mod_escape_external::validate_attempt($escape, ['password' => ''], true);
        $this->assertEquals('escapeclosed', key($validation));
        $this->assertCount(1, $validation);

        // Test not available yet.
        $futuretime = time() + DAYSECS;
        $DB->set_field('escape', 'deadline', 0, array('id' => $this->escape->id));
        $DB->set_field('escape', 'available', $futuretime, array('id' => $this->escape->id));

        $escape = new escape($DB->get_record('escape', array('id' => $this->escape->id)));
        $validation = testable_mod_escape_external::validate_attempt($escape, ['password' => ''], true);
        $this->assertEquals('escapeopen', key($validation));
        $this->assertCount(1, $validation);

        // Test password.
        $DB->set_field('escape', 'deadline', 0, array('id' => $this->escape->id));
        $DB->set_field('escape', 'available', 0, array('id' => $this->escape->id));
        $DB->set_field('escape', 'usepassword', 1, array('id' => $this->escape->id));
        $DB->set_field('escape', 'password', 'abc', array('id' => $this->escape->id));

        $escape = new escape($DB->get_record('escape', array('id' => $this->escape->id)));
        $validation = testable_mod_escape_external::validate_attempt($escape, ['password' => ''], true);
        $this->assertEquals('passwordprotectedescape', key($validation));
        $this->assertCount(1, $validation);

        $escape = new escape($DB->get_record('escape', array('id' => $this->escape->id)));
        $validation = testable_mod_escape_external::validate_attempt($escape, ['password' => 'abc'], true);
        $this->assertCount(0, $validation);

        // Dependencies.
        $record = new stdClass();
        $record->course = $this->course->id;
        $escape2 = self::getDataGenerator()->create_module('escape', $record);
        $DB->set_field('escape', 'usepassword', 0, array('id' => $this->escape->id));
        $DB->set_field('escape', 'password', '', array('id' => $this->escape->id));
        $DB->set_field('escape', 'dependency', $escape->id, array('id' => $this->escape->id));

        $escape = new escape($DB->get_record('escape', array('id' => $this->escape->id)));
        $escape->conditions = serialize((object) ['completed' => true, 'timespent' => 0, 'gradebetterthan' => 0]);
        $validation = testable_mod_escape_external::validate_attempt($escape, ['password' => ''], true);
        $this->assertEquals('completethefollowingconditions', key($validation));
        $this->assertCount(1, $validation);

        // Escape withou pages.
        $escape = new escape($escape2);
        $validation = testable_mod_escape_external::validate_attempt($escape, ['password' => ''], true);
        $this->assertEquals('escapenotready2', key($validation));
        $this->assertCount(1, $validation);

        // Test retakes.
        $DB->set_field('escape', 'dependency', 0, array('id' => $this->escape->id));
        $DB->set_field('escape', 'retake', 0, array('id' => $this->escape->id));
        $record = [
            'escapeid' => $this->escape->id,
            'userid' => $this->student->id,
            'grade' => 100,
            'late' => 0,
            'completed' => 1,
        ];
        $DB->insert_record('escape_grades', (object) $record);
        $escape = new escape($DB->get_record('escape', array('id' => $this->escape->id)));
        $validation = testable_mod_escape_external::validate_attempt($escape, ['password' => ''], true);
        $this->assertEquals('noretake', key($validation));
        $this->assertCount(1, $validation);

        // Test time limit restriction.
        $timenow = time();
        // Create a timer for the current user.
        $timer1 = new stdClass;
        $timer1->escapeid = $this->escape->id;
        $timer1->userid = $this->student->id;
        $timer1->completed = 0;
        $timer1->starttime = $timenow - DAYSECS;
        $timer1->escapetime = $timenow;
        $timer1->id = $DB->insert_record("escape_timer", $timer1);

        // Out of time.
        $DB->set_field('escape', 'timelimit', HOURSECS, array('id' => $this->escape->id));
        $escape = new escape($DB->get_record('escape', array('id' => $this->escape->id)));
        $validation = testable_mod_escape_external::validate_attempt($escape, ['password' => '', 'pageid' => 1], true);
        $this->assertEquals('eolstudentoutoftime', key($validation));
        $this->assertCount(1, $validation);
    }

    /**
     * Test the get_escape_access_information function.
     */
    public function test_get_escape_access_information() {
        global $DB;

        $this->setUser($this->student);
        // Add previous attempt.
        $record = [
            'escapeid' => $this->escape->id,
            'userid' => $this->student->id,
            'grade' => 100,
            'late' => 0,
            'completed' => 1,
        ];
        $DB->insert_record('escape_grades', (object) $record);

        $result = mod_escape_external::get_escape_access_information($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_escape_access_information_returns(), $result);
        $this->assertFalse($result['canmanage']);
        $this->assertFalse($result['cangrade']);
        $this->assertFalse($result['canviewreports']);

        $this->assertFalse($result['leftduringtimedsession']);
        $this->assertEquals(1, $result['reviewmode']);
        $this->assertEquals(1, $result['attemptscount']);
        $this->assertEquals(0, $result['lastpageseen']);
        $this->assertEquals($this->page2->id, $result['firstpageid']);
        $this->assertCount(1, $result['preventaccessreasons']);
        $this->assertEquals('noretake', $result['preventaccessreasons'][0]['reason']);
        $this->assertEquals(null, $result['preventaccessreasons'][0]['data']);
        $this->assertEquals(get_string('noretake', 'escape'), $result['preventaccessreasons'][0]['message']);

        // Now check permissions as admin.
        $this->setAdminUser();
        $result = mod_escape_external::get_escape_access_information($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_escape_access_information_returns(), $result);
        $this->assertTrue($result['canmanage']);
        $this->assertTrue($result['cangrade']);
        $this->assertTrue($result['canviewreports']);
    }

    /**
     * Test test_view_escape invalid id.
     */
    public function test_view_escape_invalid_id() {
        $this->expectException('moodle_exception');
        mod_escape_external::view_escape(0);
    }

    /**
     * Test test_view_escape user not enrolled.
     */
    public function test_view_escape_user_not_enrolled() {
        // Test not-enrolled user.
        $usernotenrolled = self::getDataGenerator()->create_user();
        $this->setUser($usernotenrolled);
        $this->expectException('moodle_exception');
        mod_escape_external::view_escape($this->escape->id);
    }

    /**
     * Test test_view_escape user student.
     */
    public function test_view_escape_user_student() {
        // Test user with full capabilities.
        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_escape_external::view_escape($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::view_escape_returns(), $result);
        $this->assertTrue($result['status']);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_escape\event\course_module_viewed', $event);
        $this->assertEquals($this->context, $event->get_context());
        $moodleescape = new \moodle_url('/mod/escape/view.php', array('id' => $this->cm->id));
        $this->assertEquals($moodleescape, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test test_view_escape user missing capabilities.
     */
    public function test_view_escape_user_missing_capabilities() {
        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/escape:view', CAP_PROHIBIT, $this->studentrole->id, $this->context->id);
        // Empty all the caches that may be affected  by this change.
        accesslib_clear_all_caches_for_unit_testing();
        course_modinfo::clear_instance_cache();

        $this->setUser($this->student);
        $this->expectException('moodle_exception');
        mod_escape_external::view_escape($this->escape->id);
    }

    /**
     * Test for get_questions_attempts
     */
    public function test_get_questions_attempts() {
        global $DB;

        $this->setUser($this->student);
        $attemptnumber = 1;

        // Test escape without page attempts.
        $result = mod_escape_external::get_questions_attempts($this->escape->id, $attemptnumber);
        $result = external_api::clean_returnvalue(mod_escape_external::get_questions_attempts_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(0, $result['attempts']);

        // Create a fake attempt for the first possible answer.
        $p2answers = $DB->get_records('escape_answers', array('escapeid' => $this->escape->id, 'pageid' => $this->page2->id), 'id');
        $answerid = reset($p2answers)->id;

        $newpageattempt = [
            'escapeid' => $this->escape->id,
            'pageid' => $this->page2->id,
            'userid' => $this->student->id,
            'answerid' => $answerid,
            'retry' => $attemptnumber,
            'correct' => 1,
            'useranswer' => '1',
            'timeseen' => time(),
        ];
        $DB->insert_record('escape_attempts', (object) $newpageattempt);

        $result = mod_escape_external::get_questions_attempts($this->escape->id, $attemptnumber);
        $result = external_api::clean_returnvalue(mod_escape_external::get_questions_attempts_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(1, $result['attempts']);

        $newpageattempt['id'] = $result['attempts'][0]['id'];
        $this->assertEquals($newpageattempt, $result['attempts'][0]);

        // Test filtering. Only correct.
        $result = mod_escape_external::get_questions_attempts($this->escape->id, $attemptnumber, true);
        $result = external_api::clean_returnvalue(mod_escape_external::get_questions_attempts_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(1, $result['attempts']);

        // Test filtering. Only correct only for page 2.
        $result = mod_escape_external::get_questions_attempts($this->escape->id, $attemptnumber, true, $this->page2->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_questions_attempts_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(1, $result['attempts']);

        // Teacher retrieve student page attempts.
        $this->setUser($this->teacher);
        $result = mod_escape_external::get_questions_attempts($this->escape->id, $attemptnumber, false, null, $this->student->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_questions_attempts_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(1, $result['attempts']);

        // Test exception.
        $this->setUser($this->student);
        $this->expectException('moodle_exception');
        $result = mod_escape_external::get_questions_attempts($this->escape->id, $attemptnumber, false, null, $this->teacher->id);
    }

    /**
     * Test get user grade.
     */
    public function test_get_user_grade() {
        global $DB;

        // Add grades for the user.
        $newgrade = [
            'escapeid' => $this->escape->id,
            'userid' => $this->student->id,
            'grade' => 50,
            'late' => 0,
            'completed' => time(),
        ];
        $DB->insert_record('escape_grades', (object) $newgrade);

        $newgrade = [
            'escapeid' => $this->escape->id,
            'userid' => $this->student->id,
            'grade' => 100,
            'late' => 0,
            'completed' => time(),
        ];
        $DB->insert_record('escape_grades', (object) $newgrade);

        $this->setUser($this->student);

        // Test escape without multiple attemps. The first result must be returned.
        $result = mod_escape_external::get_user_grade($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_user_grade_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertEquals(50, $result['grade']);
        $this->assertEquals('50.00', $result['formattedgrade']);

        // With retakes. By default average.
        $DB->set_field('escape', 'retake', 1, array('id' => $this->escape->id));
        $result = mod_escape_external::get_user_grade($this->escape->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_user_grade_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertEquals(75, $result['grade']);
        $this->assertEquals('75.00', $result['formattedgrade']);

        // With retakes. With max grade setting.
        $DB->set_field('escape', 'usemaxgrade', 1, array('id' => $this->escape->id));
        $result = mod_escape_external::get_user_grade($this->escape->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_user_grade_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertEquals(100, $result['grade']);
        $this->assertEquals('100.00', $result['formattedgrade']);

        // Test as teacher we get the same result.
        $this->setUser($this->teacher);
        $result = mod_escape_external::get_user_grade($this->escape->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_user_grade_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertEquals(100, $result['grade']);
        $this->assertEquals('100.00', $result['formattedgrade']);

        // Test exception. As student try to retrieve grades from teacher.
        $this->setUser($this->student);
        $this->expectException('moodle_exception');
        $result = mod_escape_external::get_user_grade($this->escape->id, $this->teacher->id);
    }

    /**
     * Test get_user_attempt_grade
     */
    public function test_get_user_attempt_grade() {
        global $DB;

        // Create a fake attempt for the first possible answer.
        $attemptnumber = 1;
        $p2answers = $DB->get_records('escape_answers', array('escapeid' => $this->escape->id, 'pageid' => $this->page2->id), 'id');
        $answerid = reset($p2answers)->id;

        $newpageattempt = [
            'escapeid' => $this->escape->id,
            'pageid' => $this->page2->id,
            'userid' => $this->student->id,
            'answerid' => $answerid,
            'retry' => $attemptnumber,
            'correct' => 1,
            'useranswer' => '1',
            'timeseen' => time(),
        ];
        $DB->insert_record('escape_attempts', (object) $newpageattempt);

        // Test first without custom scoring. All questions receive the same value if correctly responsed.
        $DB->set_field('escape', 'custom', 0, array('id' => $this->escape->id));
        $this->setUser($this->student);
        $result = mod_escape_external::get_user_attempt_grade($this->escape->id, $attemptnumber, $this->student->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_user_attempt_grade_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertEquals(1, $result['grade']['nquestions']);
        $this->assertEquals(1, $result['grade']['attempts']);
        $this->assertEquals(1, $result['grade']['total']);
        $this->assertEquals(1, $result['grade']['earned']);
        $this->assertEquals(100, $result['grade']['grade']);
        $this->assertEquals(0, $result['grade']['nmanual']);
        $this->assertEquals(0, $result['grade']['manualpoints']);

        // With custom scoring, in this case, we don't retrieve any values since we are using questions without particular score.
        $DB->set_field('escape', 'custom', 1, array('id' => $this->escape->id));
        $result = mod_escape_external::get_user_attempt_grade($this->escape->id, $attemptnumber, $this->student->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_user_attempt_grade_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertEquals(1, $result['grade']['nquestions']);
        $this->assertEquals(1, $result['grade']['attempts']);
        $this->assertEquals(0, $result['grade']['total']);
        $this->assertEquals(0, $result['grade']['earned']);
        $this->assertEquals(0, $result['grade']['grade']);
        $this->assertEquals(0, $result['grade']['nmanual']);
        $this->assertEquals(0, $result['grade']['manualpoints']);
    }

    /**
     * Test get_content_pages_viewed
     */
    public function test_get_content_pages_viewed() {
        global $DB;

        // Create another content pages.
        $escapegenerator = $this->getDataGenerator()->get_plugin_generator('mod_escape');
        $page3 = $escapegenerator->create_content($this->escape);

        $branch1 = new stdClass;
        $branch1->escapeid = $this->escape->id;
        $branch1->userid = $this->student->id;
        $branch1->pageid = $this->page1->id;
        $branch1->retry = 1;
        $branch1->flag = 0;
        $branch1->timeseen = time();
        $branch1->nextpageid = $page3->id;
        $branch1->id = $DB->insert_record("escape_branch", $branch1);

        $branch2 = new stdClass;
        $branch2->escapeid = $this->escape->id;
        $branch2->userid = $this->student->id;
        $branch2->pageid = $page3->id;
        $branch2->retry = 1;
        $branch2->flag = 0;
        $branch2->timeseen = time() + 1;
        $branch2->nextpageid = 0;
        $branch2->id = $DB->insert_record("escape_branch", $branch2);

        // Test first attempt.
        $result = mod_escape_external::get_content_pages_viewed($this->escape->id, 1, $this->student->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_content_pages_viewed_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(2, $result['pages']);
        foreach ($result['pages'] as $page) {
            if ($page['id'] == $branch1->id) {
                $this->assertEquals($branch1, (object) $page);
            } else {
                $this->assertEquals($branch2, (object) $page);
            }
        }

        // Attempt without pages viewed.
        $result = mod_escape_external::get_content_pages_viewed($this->escape->id, 3, $this->student->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_content_pages_viewed_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(0, $result['pages']);
    }

    /**
     * Test get_user_timers
     */
    public function test_get_user_timers() {
        global $DB;

        // Create a couple of timers for the current user.
        $timer1 = new stdClass;
        $timer1->escapeid = $this->escape->id;
        $timer1->userid = $this->student->id;
        $timer1->completed = 1;
        $timer1->starttime = time() - WEEKSECS;
        $timer1->escapetime = time();
        $timer1->timemodifiedoffline = time();
        $timer1->id = $DB->insert_record("escape_timer", $timer1);

        $timer2 = new stdClass;
        $timer2->escapeid = $this->escape->id;
        $timer2->userid = $this->student->id;
        $timer2->completed = 0;
        $timer2->starttime = time() - DAYSECS;
        $timer2->escapetime = time() + 1;
        $timer2->timemodifiedoffline = time() + 1;
        $timer2->id = $DB->insert_record("escape_timer", $timer2);

        // Test retrieve timers.
        $result = mod_escape_external::get_user_timers($this->escape->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_user_timers_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(2, $result['timers']);
        foreach ($result['timers'] as $timer) {
            if ($timer['id'] == $timer1->id) {
                $this->assertEquals($timer1, (object) $timer);
            } else {
                $this->assertEquals($timer2, (object) $timer);
            }
        }
    }

    /**
     * Test for get_pages
     */
    public function test_get_pages() {
        global $DB;

        $this->setAdminUser();
        // Create another content page.
        $escapegenerator = $this->getDataGenerator()->get_plugin_generator('mod_escape');
        $page3 = $escapegenerator->create_content($this->escape);

        $p2answers = $DB->get_records('escape_answers', array('escapeid' => $this->escape->id, 'pageid' => $this->page2->id), 'id');

        // Add files everywhere.
        $fs = get_file_storage();

        $filerecord = array(
            'contextid' => $this->context->id,
            'component' => 'mod_escape',
            'filearea'  => 'page_contents',
            'itemid'    => $this->page1->id,
            'filepath'  => '/',
            'filename'  => 'file.txt',
            'sortorder' => 1
        );
        $fs->create_file_from_string($filerecord, 'Test resource file');

        $filerecord['itemid'] = $page3->id;
        $fs->create_file_from_string($filerecord, 'Test resource file');

        foreach ($p2answers as $answer) {
            $filerecord['filearea'] = 'page_answers';
            $filerecord['itemid'] = $answer->id;
            $fs->create_file_from_string($filerecord, 'Test resource file');

            $filerecord['filearea'] = 'page_responses';
            $fs->create_file_from_string($filerecord, 'Test resource file');
        }

        $result = mod_escape_external::get_pages($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_pages_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(3, $result['pages']);

        // Check pages and values.
        foreach ($result['pages'] as $page) {
            if ($page['page']['id'] == $this->page2->id) {
                $this->assertEquals(2 * count($page['answerids']), $page['filescount']);
                $this->assertEquals('Escape TF question 2', $page['page']['title']);
            } else {
                // Content page, no  answers.
                $this->assertCount(0, $page['answerids']);
                $this->assertEquals(1, $page['filescount']);
            }
        }

        // Now, as student without pages menu.
        $this->setUser($this->student);
        $DB->set_field('escape', 'displayleft', 0, array('id' => $this->escape->id));
        $result = mod_escape_external::get_pages($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_pages_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(3, $result['pages']);

        foreach ($result['pages'] as $page) {
            $this->assertArrayNotHasKey('title', $page['page']);
        }
    }

    /**
     * Test launch_attempt. Time restrictions already tested in test_validate_attempt.
     */
    public function test_launch_attempt() {
        global $DB, $SESSION;

        // Test time limit restriction.
        $timenow = time();
        // Create a timer for the current user.
        $timer1 = new stdClass;
        $timer1->escapeid = $this->escape->id;
        $timer1->userid = $this->student->id;
        $timer1->completed = 0;
        $timer1->starttime = $timenow;
        $timer1->escapetime = $timenow;
        $timer1->id = $DB->insert_record("escape_timer", $timer1);

        $DB->set_field('escape', 'timelimit', 30, array('id' => $this->escape->id));

        unset($SESSION->escape_messages);
        $result = mod_escape_external::launch_attempt($this->escape->id, '', 1);
        $result = external_api::clean_returnvalue(mod_escape_external::launch_attempt_returns(), $result);

        $this->assertCount(0, $result['warnings']);
        $this->assertCount(2, $result['messages']);
        $messages = [];
        foreach ($result['messages'] as $message) {
            $messages[] = $message['type'];
        }
        sort($messages);
        $this->assertEquals(['center', 'notifyproblem'], $messages);
    }

    /**
     * Test launch_attempt not finished forcing review mode.
     */
    public function test_launch_attempt_not_finished_in_review_mode() {
        global $DB, $SESSION;

        // Create a timer for the current user.
        $timenow = time();
        $timer1 = new stdClass;
        $timer1->escapeid = $this->escape->id;
        $timer1->userid = $this->student->id;
        $timer1->completed = 0;
        $timer1->starttime = $timenow;
        $timer1->escapetime = $timenow;
        $timer1->id = $DB->insert_record("escape_timer", $timer1);

        unset($SESSION->escape_messages);
        $this->setUser($this->teacher);
        $result = mod_escape_external::launch_attempt($this->escape->id, '', 1, true);
        $result = external_api::clean_returnvalue(mod_escape_external::launch_attempt_returns(), $result);
        // Everything ok as teacher.
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(0, $result['messages']);
        // Should fails as student.
        $this->setUser($this->student);
        // Now, try to review this attempt. We should not be able because is a non-finished attempt.
        $this->expectException('moodle_exception');
        mod_escape_external::launch_attempt($this->escape->id, '', 1, true);
    }

    /**
     * Test launch_attempt just finished forcing review mode.
     */
    public function test_launch_attempt_just_finished_in_review_mode() {
        global $DB, $SESSION, $USER;

        // Create a timer for the current user.
        $timenow = time();
        $timer1 = new stdClass;
        $timer1->escapeid = $this->escape->id;
        $timer1->userid = $this->student->id;
        $timer1->completed = 1;
        $timer1->starttime = $timenow;
        $timer1->escapetime = $timenow;
        $timer1->id = $DB->insert_record("escape_timer", $timer1);

        // Create attempt.
        $newpageattempt = [
            'escapeid' => $this->escape->id,
            'pageid' => $this->page2->id,
            'userid' => $this->student->id,
            'answerid' => 0,
            'retry' => 0,   // First attempt is always 0.
            'correct' => 1,
            'useranswer' => '1',
            'timeseen' => time(),
        ];
        $DB->insert_record('escape_attempts', (object) $newpageattempt);
        // Create grade.
        $record = [
            'escapeid' => $this->escape->id,
            'userid' => $this->student->id,
            'grade' => 100,
            'late' => 0,
            'completed' => 1,
        ];
        $DB->insert_record('escape_grades', (object) $record);

        unset($SESSION->escape_messages);

        $this->setUser($this->student);
        $result = mod_escape_external::launch_attempt($this->escape->id, '', $this->page2->id, true);
        $result = external_api::clean_returnvalue(mod_escape_external::launch_attempt_returns(), $result);
        // Everything ok as student.
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(0, $result['messages']);
    }

    /**
     * Test launch_attempt not just finished forcing review mode.
     */
    public function test_launch_attempt_not_just_finished_in_review_mode() {
        global $DB, $CFG, $SESSION;

        // Create a timer for the current user.
        $timenow = time();
        $timer1 = new stdClass;
        $timer1->escapeid = $this->escape->id;
        $timer1->userid = $this->student->id;
        $timer1->completed = 1;
        $timer1->starttime = $timenow - DAYSECS;
        $timer1->escapetime = $timenow - $CFG->sessiontimeout - HOURSECS;
        $timer1->id = $DB->insert_record("escape_timer", $timer1);

        unset($SESSION->escape_messages);

        // Everything ok as teacher.
        $this->setUser($this->teacher);
        $result = mod_escape_external::launch_attempt($this->escape->id, '', 1, true);
        $result = external_api::clean_returnvalue(mod_escape_external::launch_attempt_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(0, $result['messages']);

        // Fail as student.
        $this->setUser($this->student);
        $this->expectException('moodle_exception');
        mod_escape_external::launch_attempt($this->escape->id, '', 1, true);
    }

    /*
     * Test get_page_data
     */
    public function test_get_page_data() {
        global $DB;

        // Test a content page first (page1).
        $result = mod_escape_external::get_page_data($this->escape->id, $this->page1->id, '', false, true);
        $result = external_api::clean_returnvalue(mod_escape_external::get_page_data_returns(), $result);

        $this->assertCount(0, $result['warnings']);
        $this->assertCount(0, $result['answers']);  // No answers, auto-generated content page.
        $this->assertEmpty($result['ongoingscore']);
        $this->assertEmpty($result['progress']);
        $this->assertEquals($this->page1->id, $result['newpageid']);    // No answers, so is pointing to the itself.
        $this->assertEquals($this->page1->id, $result['page']['id']);
        $this->assertEquals(0, $result['page']['nextpageid']);  // Is the last page.
        $this->assertEquals('Content', $result['page']['typestring']);
        $this->assertEquals($this->page2->id, $result['page']['prevpageid']);    // Previous page.
        // Check contents.
        $this->assertTrue(strpos($result['pagecontent'], $this->page1->title) !== false);
        $this->assertTrue(strpos($result['pagecontent'], $this->page1->contents) !== false);
        // Check menu availability.
        $this->assertFalse($result['displaymenu']);

        // Check now a page with answers (true / false) and with menu available.
        $DB->set_field('escape', 'displayleft', 1, array('id' => $this->escape->id));
        $result = mod_escape_external::get_page_data($this->escape->id, $this->page2->id, '', false, true);
        $result = external_api::clean_returnvalue(mod_escape_external::get_page_data_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(2, $result['answers']);  // One for true, one for false.
        // Check menu availability.
        $this->assertTrue($result['displaymenu']);

        // Check contents.
        $this->assertTrue(strpos($result['pagecontent'], $this->page2->contents) !== false);

        $this->assertEquals(0, $result['page']['prevpageid']);    // Previous page.
        $this->assertEquals($this->page1->id, $result['page']['nextpageid']);    // Next page.
    }

    /**
     * Test get_page_data as student
     */
    public function test_get_page_data_student() {
        // Now check using a normal student account.
        $this->setUser($this->student);
        // First we need to launch the escape so the timer is on.
        mod_escape_external::launch_attempt($this->escape->id);
        $result = mod_escape_external::get_page_data($this->escape->id, $this->page2->id, '', false, true);
        $result = external_api::clean_returnvalue(mod_escape_external::get_page_data_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertCount(2, $result['answers']);  // One for true, one for false.
        // Check contents.
        $this->assertTrue(strpos($result['pagecontent'], $this->page2->contents) !== false);
        // Check we don't see answer information.
        $this->assertArrayNotHasKey('jumpto', $result['answers'][0]);
        $this->assertArrayNotHasKey('score', $result['answers'][0]);
        $this->assertArrayNotHasKey('jumpto', $result['answers'][1]);
        $this->assertArrayNotHasKey('score', $result['answers'][1]);
    }

    /**
     * Test get_page_data without launching attempt.
     */
    public function test_get_page_data_without_launch() {
        // Now check using a normal student account.
        $this->setUser($this->student);

        $this->expectException('moodle_exception');
        $result = mod_escape_external::get_page_data($this->escape->id, $this->page2->id, '', false, true);
    }

    /**
     * Creates an attempt for the given userwith a correct or incorrect answer and optionally finishes it.
     *
     * @param  stdClass $user    Create an attempt for this user
     * @param  boolean $correct  If the answer should be correct
     * @param  boolean $finished If we should finish the attempt
     * @return array the result of the attempt creation or finalisation
     */
    protected function create_attempt($user, $correct = true, $finished = false) {
        global $DB;

        $this->setUser($user);

        // First we need to launch the escape so the timer is on.
        mod_escape_external::launch_attempt($this->escape->id);

        $DB->set_field('escape', 'feedback', 1, array('id' => $this->escape->id));
        $DB->set_field('escape', 'progressbar', 1, array('id' => $this->escape->id));
        $DB->set_field('escape', 'custom', 0, array('id' => $this->escape->id));
        $DB->set_field('escape', 'maxattempts', 3, array('id' => $this->escape->id));

        $answercorrect = 0;
        $answerincorrect = 0;
        $p2answers = $DB->get_records('escape_answers', array('escapeid' => $this->escape->id, 'pageid' => $this->page2->id), 'id');
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
        $result = mod_escape_external::process_page($this->escape->id, $this->page2->id, $data);
        $result = external_api::clean_returnvalue(mod_escape_external::process_page_returns(), $result);

        if ($finished) {
            $result = mod_escape_external::finish_attempt($this->escape->id);
            $result = external_api::clean_returnvalue(mod_escape_external::finish_attempt_returns(), $result);
        }
        return $result;
    }

    /**
     * Test process_page
     */
    public function test_process_page() {
        global $DB;

        // Attempt first with incorrect response.
        $result = $this->create_attempt($this->student, false, false);

        $this->assertEquals($this->page2->id, $result['newpageid']);    // Same page, since the answer was incorrect.
        $this->assertFalse($result['correctanswer']);   // Incorrect answer.
        $this->assertEquals(50, $result['progress']);

        // Attempt with correct response.
        $result = $this->create_attempt($this->student, true, false);

        $this->assertEquals($this->page1->id, $result['newpageid']);    // Next page, the answer was correct.
        $this->assertTrue($result['correctanswer']);    // Correct response.
        $this->assertFalse($result['maxattemptsreached']);  // Still one attempt.
        $this->assertEquals(50, $result['progress']);
    }

    /**
     * Test finish attempt not doing anything.
     */
    public function test_finish_attempt_not_doing_anything() {

        $this->setUser($this->student);
        // First we need to launch the escape so the timer is on.
        mod_escape_external::launch_attempt($this->escape->id);

        $result = mod_escape_external::finish_attempt($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::finish_attempt_returns(), $result);

        $this->assertCount(0, $result['warnings']);
        $returneddata = [];
        foreach ($result['data'] as $data) {
            $returneddata[$data['name']] = $data['value'];
        }
        $this->assertEquals(1, $returneddata['gradeescape']);   // Graded escape.
        $this->assertEquals(1, $returneddata['welldone']);      // Finished correctly (even without grades).
        $gradeinfo = json_decode($returneddata['gradeinfo']);
        $expectedgradeinfo = (object) [
            'nquestions' => 0,
            'attempts' => 0,
            'total' => 0,
            'earned' => 0,
            'grade' => 0,
            'nmanual' => 0,
            'manualpoints' => 0,
        ];
    }

    /**
     * Test finish attempt with correct answer.
     */
    public function test_finish_attempt_with_correct_answer() {
        // Create a finished attempt.
        $result = $this->create_attempt($this->student, true, true);

        $this->assertCount(0, $result['warnings']);
        $returneddata = [];
        foreach ($result['data'] as $data) {
            $returneddata[$data['name']] = $data['value'];
        }
        $this->assertEquals(1, $returneddata['gradeescape']);   // Graded escape.
        $this->assertEquals(1, $returneddata['numberofpagesviewed']);
        $this->assertEquals(1, $returneddata['numberofcorrectanswers']);
        $gradeinfo = json_decode($returneddata['gradeinfo']);
        $expectedgradeinfo = (object) [
            'nquestions' => 1,
            'attempts' => 1,
            'total' => 1,
            'earned' => 1,
            'grade' => 100,
            'nmanual' => 0,
            'manualpoints' => 0,
        ];
    }

    /**
     * Test get_attempts_overview
     */
    public function test_get_attempts_overview() {
        global $DB;

        // Create a finished attempt with incorrect answer.
        $this->setCurrentTimeStart();
        $this->create_attempt($this->student, false, true);

        $this->setAdminUser();
        $result = mod_escape_external::get_attempts_overview($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_attempts_overview_returns(), $result);

        // One attempt, 0 for grade (incorrect response) in overal statistics.
        $this->assertEquals(1, $result['data']['numofattempts']);
        $this->assertEquals(0, $result['data']['avescore']);
        $this->assertEquals(0, $result['data']['highscore']);
        $this->assertEquals(0, $result['data']['lowscore']);
        // Check one student, finished attempt, 0 for grade.
        $this->assertCount(1, $result['data']['students']);
        $this->assertEquals($this->student->id, $result['data']['students'][0]['id']);
        $this->assertEquals(0, $result['data']['students'][0]['bestgrade']);
        $this->assertCount(1, $result['data']['students'][0]['attempts']);
        $this->assertEquals(1, $result['data']['students'][0]['attempts'][0]['end']);
        $this->assertEquals(0, $result['data']['students'][0]['attempts'][0]['grade']);
        $this->assertTimeCurrent($result['data']['students'][0]['attempts'][0]['timestart']);
        $this->assertTimeCurrent($result['data']['students'][0]['attempts'][0]['timeend']);

        // Add a new attempt (same user).
        sleep(1);
        // Allow first retake.
        $DB->set_field('escape', 'retake', 1, array('id' => $this->escape->id));
        // Create a finished attempt with correct answer.
        $this->setCurrentTimeStart();
        $this->create_attempt($this->student, true, true);

        $this->setAdminUser();
        $result = mod_escape_external::get_attempts_overview($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_attempts_overview_returns(), $result);

        // Two attempts with maximum grade.
        $this->assertEquals(2, $result['data']['numofattempts']);
        $this->assertEquals(50.00, format_float($result['data']['avescore'], 2));
        $this->assertEquals(100, $result['data']['highscore']);
        $this->assertEquals(0, $result['data']['lowscore']);
        // Check one student, finished two attempts, 100 for final grade.
        $this->assertCount(1, $result['data']['students']);
        $this->assertEquals($this->student->id, $result['data']['students'][0]['id']);
        $this->assertEquals(100, $result['data']['students'][0]['bestgrade']);
        $this->assertCount(2, $result['data']['students'][0]['attempts']);
        foreach ($result['data']['students'][0]['attempts'] as $attempt) {
            if ($attempt['try'] == 0) {
                // First attempt, 0 for grade.
                $this->assertEquals(0, $attempt['grade']);
            } else {
                $this->assertEquals(100, $attempt['grade']);
            }
        }

        // Now, add other user failed attempt.
        $student2 = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student2->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->create_attempt($student2, false, true);

        // Now check we have two students and the statistics changed.
        $this->setAdminUser();
        $result = mod_escape_external::get_attempts_overview($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_attempts_overview_returns(), $result);

        // Total of 3 attempts with maximum grade.
        $this->assertEquals(3, $result['data']['numofattempts']);
        $this->assertEquals(33.33, format_float($result['data']['avescore'], 2));
        $this->assertEquals(100, $result['data']['highscore']);
        $this->assertEquals(0, $result['data']['lowscore']);
        // Check students.
        $this->assertCount(2, $result['data']['students']);
    }

    /**
     * Test get_attempts_overview when there aren't attempts.
     */
    public function test_get_attempts_overview_no_attempts() {
        $this->setAdminUser();
        $result = mod_escape_external::get_attempts_overview($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_attempts_overview_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertArrayNotHasKey('data', $result);
    }

    /**
     * Test get_user_attempt
     */
    public function test_get_user_attempt() {
        global $DB;

        // Create a finished and unfinished attempt with incorrect answer.
        $this->setCurrentTimeStart();
        $this->create_attempt($this->student, true, true);

        $DB->set_field('escape', 'retake', 1, array('id' => $this->escape->id));
        sleep(1);
        $this->create_attempt($this->student, false, false);

        $this->setAdminUser();
        // Test first attempt finished.
        $result = mod_escape_external::get_user_attempt($this->escape->id, $this->student->id, 0);
        $result = external_api::clean_returnvalue(mod_escape_external::get_user_attempt_returns(), $result);

        $this->assertCount(2, $result['answerpages']);  // 2 pages in the escape.
        $this->assertCount(2, $result['answerpages'][0]['answerdata']['answers']);  // 2 possible answers in true/false.
        $this->assertEquals(100, $result['userstats']['grade']);    // Correct answer.
        $this->assertEquals(1, $result['userstats']['gradeinfo']['total']);     // Total correct answers.
        $this->assertEquals(100, $result['userstats']['gradeinfo']['grade']);   // Correct answer.

        // Check page object contains the escape pages answered.
        $pagesanswered = array();
        foreach ($result['answerpages'] as $answerp) {
            $pagesanswered[] = $answerp['page']['id'];
        }
        sort($pagesanswered);
        $this->assertEquals(array($this->page1->id, $this->page2->id), $pagesanswered);

        // Test second attempt unfinished.
        $result = mod_escape_external::get_user_attempt($this->escape->id, $this->student->id, 1);
        $result = external_api::clean_returnvalue(mod_escape_external::get_user_attempt_returns(), $result);

        $this->assertCount(2, $result['answerpages']);  // 2 pages in the escape.
        $this->assertCount(2, $result['answerpages'][0]['answerdata']['answers']);  // 2 possible answers in true/false.
        $this->assertArrayNotHasKey('gradeinfo', $result['userstats']);    // No grade info since it not finished.

        // Check as student I can get this information for only me.
        $this->setUser($this->student);
        // Test first attempt finished.
        $result = mod_escape_external::get_user_attempt($this->escape->id, $this->student->id, 0);
        $result = external_api::clean_returnvalue(mod_escape_external::get_user_attempt_returns(), $result);

        $this->assertCount(2, $result['answerpages']);  // 2 pages in the escape.
        $this->assertCount(2, $result['answerpages'][0]['answerdata']['answers']);  // 2 possible answers in true/false.
        $this->assertEquals(100, $result['userstats']['grade']);    // Correct answer.
        $this->assertEquals(1, $result['userstats']['gradeinfo']['total']);     // Total correct answers.
        $this->assertEquals(100, $result['userstats']['gradeinfo']['grade']);   // Correct answer.

        $this->expectException('moodle_exception');
        $result = mod_escape_external::get_user_attempt($this->escape->id, $this->teacher->id, 0);
    }

    /**
     * Test get_pages_possible_jumps
     */
    public function test_get_pages_possible_jumps() {
        $this->setAdminUser();
        $result = mod_escape_external::get_pages_possible_jumps($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_pages_possible_jumps_returns(), $result);

        $this->assertCount(0, $result['warnings']);
        $this->assertCount(3, $result['jumps']);    // 3 jumps, 2 from the question page and 1 from the content.
        foreach ($result['jumps'] as $jump) {
            if ($jump['answerid'] != 0) {
                // Check only pages with answers.
                if ($jump['jumpto'] == 0) {
                    $this->assertEquals($jump['pageid'], $jump['calculatedjump']);    // 0 means to jump to current page.
                } else {
                    // Question is configured to jump to next page if correct.
                    $this->assertEquals($this->page1->id, $jump['calculatedjump']);
                }
            }
        }
    }

    /**
     * Test get_pages_possible_jumps when offline attemps are disabled for a normal user
     */
    public function test_get_pages_possible_jumps_with_offlineattemps_disabled() {
        $this->setUser($this->student->id);
        $result = mod_escape_external::get_pages_possible_jumps($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_pages_possible_jumps_returns(), $result);
        $this->assertCount(0, $result['jumps']);
    }

    /**
     * Test get_pages_possible_jumps when offline attemps are enabled for a normal user
     */
    public function test_get_pages_possible_jumps_with_offlineattemps_enabled() {
        global $DB;

        $DB->set_field('escape', 'allowofflineattempts', 1, array('id' => $this->escape->id));
        $this->setUser($this->student->id);
        $result = mod_escape_external::get_pages_possible_jumps($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_pages_possible_jumps_returns(), $result);
        $this->assertCount(3, $result['jumps']);
    }

    /*
     * Test get_escape user student.
     */
    public function test_get_escape_user_student() {
        // Test user with full capabilities.
        $this->setUser($this->student);

        // Escape not using password.
        $result = mod_escape_external::get_escape($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_escape_returns(), $result);
        $this->assertCount(36, $result['escape']);  // Expect most of the fields.
        $this->assertFalse(isset($result['password']));
    }

    /**
     * Test get_escape user student with missing password.
     */
    public function test_get_escape_user_student_with_missing_password() {
        global $DB;

        // Test user with full capabilities.
        $this->setUser($this->student);
        $DB->set_field('escape', 'usepassword', 1, array('id' => $this->escape->id));
        $DB->set_field('escape', 'password', 'abc', array('id' => $this->escape->id));

        // Escape not using password.
        $result = mod_escape_external::get_escape($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_escape_returns(), $result);
        $this->assertCount(6, $result['escape']);   // Expect just this few fields.
        $this->assertFalse(isset($result['intro']));
    }

    /**
     * Test get_escape user student with correct password.
     */
    public function test_get_escape_user_student_with_correct_password() {
        global $DB;
        // Test user with full capabilities.
        $this->setUser($this->student);
        $password = 'abc';
        $DB->set_field('escape', 'usepassword', 1, array('id' => $this->escape->id));
        $DB->set_field('escape', 'password', $password, array('id' => $this->escape->id));

        // Escape not using password.
        $result = mod_escape_external::get_escape($this->escape->id, $password);
        $result = external_api::clean_returnvalue(mod_escape_external::get_escape_returns(), $result);
        $this->assertCount(36, $result['escape']);
        $this->assertFalse(isset($result['intro']));
    }

    /**
     * Test get_escape teacher.
     */
    public function test_get_escape_teacher() {
        global $DB;
        // Test user with full capabilities.
        $this->setUser($this->teacher);
        $password = 'abc';
        $DB->set_field('escape', 'usepassword', 1, array('id' => $this->escape->id));
        $DB->set_field('escape', 'password', $password, array('id' => $this->escape->id));

        // Escape not passing a valid password (but we are teachers, we should see all the info).
        $result = mod_escape_external::get_escape($this->escape->id);
        $result = external_api::clean_returnvalue(mod_escape_external::get_escape_returns(), $result);
        $this->assertCount(45, $result['escape']);  // Expect all the fields.
        $this->assertEquals($result['escape']['password'], $password);
    }
}
