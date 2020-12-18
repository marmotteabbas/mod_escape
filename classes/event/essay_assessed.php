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
 * The mod_escape essay assessed event.
 *
 * @package    mod_escape
 * @copyright  2014 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_escape\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_escape essay assessed event class.
 *
 * @property-read array $other {
 *     Extra information about the event.
 *
 *     - int escapeid: The ID of the escape.
 *     - int attemptid: The ID for the attempt.
 * }
 *
 * @package    mod_escape
 * @since      Moodle 2.7
 * @copyright  2014 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class essay_assessed extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'escape_grades';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has marked the essay with id '{$this->other['attemptid']}' and " .
            "recorded a mark '$this->objectid' in the escape with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        $escape = $this->get_record_snapshot('escape', $this->other['escapeid']);
        return array($this->courseid, 'escape', 'update grade', 'essay.php?id=' .
                $this->contextinstanceid, $escape->name, $this->contextinstanceid);
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventessayassessed', 'mod_escape');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/escape/essay.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        if (!isset($this->other['escapeid'])) {
            throw new \coding_exception('The \'escapeid\' value must be set in other.');
        }
        if (!isset($this->other['attemptid'])) {
            throw new \coding_exception('The \'attemptid\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'escape_grades', 'restore' => 'escape_grade');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['escapeid'] = array('db' => 'escape', 'restore' => 'escape');
        $othermapped['attemptid'] = array('db' => 'escape_attempts', 'restore' => 'escape_attept');

        return $othermapped;
    }
}
