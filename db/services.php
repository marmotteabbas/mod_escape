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
 * Escape external functions and service definitions.
 *
 * @package    mod_escape
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */

defined('MOODLE_INTERNAL') || die;

$functions = array(
    'mod_escape_get_escapes_by_courses' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_escapes_by_courses',
        'description'   => 'Returns a list of escapes in a provided list of courses,
                            if no list is provided all escapes that the user can view will be returned.',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'mod_escape_get_escape_access_information' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_escape_access_information',
        'description'   => 'Return access information for a given escape.',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:view',
        'services'    => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'mod_escape_view_escape' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'view_escape',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_get_questions_attempts' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_questions_attempts',
        'description'   => 'Return the list of questions attempts in a given escape.',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_get_user_grade' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_user_grade',
        'description'   => 'Return the final grade in the escape for the given user.',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_get_user_attempt_grade' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_user_attempt_grade',
        'description'   => 'Return grade information in the attempt for a given user.',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_get_content_pages_viewed' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_content_pages_viewed',
        'description'   => 'Return the list of content pages viewed by a user during a escape attempt.',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_get_user_timers' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_user_timers',
        'description'   => 'Return the timers in the current escape for the given user.',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_get_pages' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_pages',
        'description'   => 'Return the list of pages in a escape (based on the user permissions).',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_launch_attempt' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'launch_attempt',
        'description'   => 'Starts a new attempt or continues an existing one.',
        'type'          => 'write',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_get_page_data' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_page_data',
        'description'   => 'Return information of a given page, including its contents.',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_process_page' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'process_page',
        'description'   => 'Processes page responses.',
        'type'          => 'write',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_finish_attempt' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'finish_attempt',
        'description'   => 'Finishes the current attempt.',
        'type'          => 'write',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_get_attempts_overview' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_attempts_overview',
        'description'   => 'Get a list of all the attempts made by users in a escape.',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:viewreports',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_get_user_attempt' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_user_attempt',
        'description'   => 'Return information about the given user attempt (including answers).',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:viewreports',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_get_pages_possible_jumps' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_pages_possible_jumps',
        'description'   => 'Return all the possible jumps for the pages in a given escape.',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_get_escape' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_escape',
        'description'   => 'Return information of a given escape.',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),'mod_escape_answer_question' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'answer_question',
        'description'   => 'Answer a question page',
        'type'          => 'write',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_get_possible_answers_for_a_page' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_possible_answers_for_a_page',
        'description'   => 'Give the possible answer for a page',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_escape_get_all_escapes' => array(
        'classname'     => 'mod_escape_external',
        'methodname'    => 'get_all_escapes',
        'description'   => 'Give all possible escapes for a user',
        'type'          => 'read',
        'capabilities'  => 'mod/escape:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);
