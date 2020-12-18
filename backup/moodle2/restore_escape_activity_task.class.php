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
 * @package mod_escape
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/escape/backup/moodle2/restore_escape_stepslib.php'); // Because it exists (must)

/**
 * escape restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_escape_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // escape only has one structure step
        $this->add_step(new restore_escape_activity_structure_step('escape_structure', 'escape.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('escape', array('intro'), 'escape');
        $contents[] = new restore_decode_content('escape_pages', array('contents'), 'escape_page');
        $contents[] = new restore_decode_content('escape_answers', array('answer', 'response'), 'escape_answer');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('ESCAPEEDIT', '/mod/escape/edit.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('ESCAPEESAY', '/mod/escape/essay.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('ESCAPEREPORT', '/mod/escape/report.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('ESCAPEMEDIAFILE', '/mod/escape/mediafile.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('ESCAPEVIEWBYID', '/mod/escape/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('ESCAPEINDEX', '/mod/escape/index.php?id=$1', 'course');
        $rules[] = new restore_decode_rule('ESCAPEVIEWPAGE', '/mod/escape/view.php?id=$1&pageid=$2', array('course_module', 'escape_page'));
        $rules[] = new restore_decode_rule('ESCAPEEDITPAGE', '/mod/escape/edit.php?id=$1&pageid=$2', array('course_module', 'escape_page'));

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * escape logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('escape', 'add', 'view.php?id={course_module}', '{escape}');
        $rules[] = new restore_log_rule('escape', 'update', 'view.php?id={course_module}', '{escape}');
        $rules[] = new restore_log_rule('escape', 'view', 'view.php?id={course_module}', '{escape}');
        $rules[] = new restore_log_rule('escape', 'start', 'view.php?id={course_module}', '{escape}');
        $rules[] = new restore_log_rule('escape', 'end', 'view.php?id={course_module}', '{escape}');
        $rules[] = new restore_log_rule('escape', 'view grade', 'essay.php?id={course_module}', '[name]');
        $rules[] = new restore_log_rule('escape', 'update grade', 'essay.php?id={course_module}', '[name]');
        $rules[] = new restore_log_rule('escape', 'update email essay grade', 'essay.php?id={course_module}', '[name]');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('escape', 'view all', 'index.php?id={course}', null);

        return $rules;
    }


    /**
     * Re-map the dependency and activitylink information
     * If a depency or activitylink has no mapping in the backup data then it could either be a duplication of a
     * escape, or a backup/restore of a single escape. We have no way to determine which and whether this is the
     * same site and/or course. Therefore we try and retrieve a mapping, but fallback to the original value if one
     * was not found. We then test to see whether the value found is valid for the course being restored into.
     */
    public function after_restore() {
        global $DB;

        $escape = $DB->get_record('escape', array('id' => $this->get_activityid()), 'id, course, dependency, activitylink');
        $updaterequired = false;

        if (!empty($escape->dependency)) {
            $updaterequired = true;
            if ($newitem = restore_dbops::get_backup_ids_record($this->get_restoreid(), 'escape', $escape->dependency)) {
                $escape->dependency = $newitem->newitemid;
            }
            if (!$DB->record_exists('escape', array('id' => $escape->dependency, 'course' => $escape->course))) {
                $escape->dependency = 0;
            }
        }

        if (!empty($escape->activitylink)) {
            $updaterequired = true;
            if ($newitem = restore_dbops::get_backup_ids_record($this->get_restoreid(), 'course_module', $escape->activitylink)) {
                $escape->activitylink = $newitem->newitemid;
            }
            if (!$DB->record_exists('course_modules', array('id' => $escape->activitylink, 'course' => $escape->course))) {
                $escape->activitylink = 0;
            }
        }

        if ($updaterequired) {
            $DB->update_record('escape', $escape);
        }
    }
}
