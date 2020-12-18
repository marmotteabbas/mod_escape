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
 * The mod_escape group override deleted event.
 *
 * @package    mod_escape
 * @copyright  2015 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_escape\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_escape group override deleted event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int escapeid: the id of the escape.
 *      - int groupid: the id of the group.
 * }
 *
 * @package    mod_escape
 * @since      Moodle 2.9
 * @copyright  2015 Jean-Michel vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_override_deleted extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'escape_overrides';
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventoverridedeleted', 'mod_escape');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' deleted the override with id '$this->objectid' for the escape with " .
            "course module id '$this->contextinstanceid' for the group with id '{$this->other['groupid']}'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/escape/overrides.php', array('cmid' => $this->contextinstanceid));
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['escapeid'])) {
            throw new \coding_exception('The \'escapeid\' value must be set in other.');
        }

        if (!isset($this->other['groupid'])) {
            throw new \coding_exception('The \'groupid\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'escape_overrides', 'restore' => 'escape_override');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['escapeid'] = array('db' => 'escape', 'restore' => 'escape');
        $othermapped['groupid'] = array('db' => 'groups', 'restore' => 'group');

        return $othermapped;
    }
}
