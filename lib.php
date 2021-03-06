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
 * Standard library of functions and constants for escape
 *
 * @package mod_escape
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

// Event types.
define('ESCAPE_EVENT_TYPE_OPEN', 'open');
define('ESCAPE_EVENT_TYPE_CLOSE', 'close');

/* Do not include any libraries here! */

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @global object
 * @param object $escape Escape post data from the form
 * @return int
 **/
function escape_add_instance($data, $mform) {
    global $DB;

    $cmid = $data->coursemodule;
    $draftitemid = $data->mediafile;
    $context = context_module::instance($cmid);

    escape_process_pre_save($data);

    unset($data->mediafile);
    $escapeid = $DB->insert_record("escape", $data);
    $data->id = $escapeid;

    escape_update_media_file($escapeid, $context, $draftitemid);

    escape_process_post_save($data);

    escape_grade_item_update($data);

    return $escapeid;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $escape Escape post data from the form
 * @return boolean
 **/
function escape_update_instance($data, $mform) {
    global $DB;

    $data->id = $data->instance;
    $cmid = $data->coursemodule;
    $draftitemid = $data->mediafile;
    $context = context_module::instance($cmid);

    escape_process_pre_save($data);

    unset($data->mediafile);
    $DB->update_record("escape", $data);

    escape_update_media_file($data->id, $context, $draftitemid);

    escape_process_post_save($data);

    // update grade item definition
    escape_grade_item_update($data);

    // update grades - TODO: do it only when grading style changes
    escape_update_grades($data, 0, false);

    return true;
}

/**
 * This function updates the events associated to the escape.
 * If $override is non-zero, then it updates only the events
 * associated with the specified override.
 *
 * @uses ESCAPE_MAX_EVENT_LENGTH
 * @param object $escape the escape object.
 * @param object $override (optional) limit to a specific override
 */
function escape_update_events($escape, $override = null) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/escape/locallib.php');
    require_once($CFG->dirroot . '/calendar/lib.php');

    // Load the old events relating to this escape.
    $conds = array('modulename' => 'escape',
                   'instance' => $escape->id);
    if (!empty($override)) {
        // Only load events for this override.
        if (isset($override->userid)) {
            $conds['userid'] = $override->userid;
        } else {
            $conds['groupid'] = $override->groupid;
        }
    }
    $oldevents = $DB->get_records('event', $conds, 'id ASC');

    // Now make a to-do list of all that needs to be updated.
    if (empty($override)) {
        // We are updating the primary settings for the escape, so we need to add all the overrides.
        $overrides = $DB->get_records('escape_overrides', array('escapeid' => $escape->id), 'id ASC');
        // It is necessary to add an empty stdClass to the beginning of the array as the $oldevents
        // list contains the original (non-override) event for the module. If this is not included
        // the logic below will end up updating the wrong row when we try to reconcile this $overrides
        // list against the $oldevents list.
        array_unshift($overrides, new stdClass());
    } else {
        // Just do the one override.
        $overrides = array($override);
    }

    // Get group override priorities.
    $grouppriorities = escape_get_group_override_priorities($escape->id);

    foreach ($overrides as $current) {
        $groupid   = isset($current->groupid) ? $current->groupid : 0;
        $userid    = isset($current->userid) ? $current->userid : 0;
        $available  = isset($current->available) ? $current->available : $escape->available;
        $deadline = isset($current->deadline) ? $current->deadline : $escape->deadline;

        // Only add open/close events for an override if they differ from the escape default.
        $addopen  = empty($current->id) || !empty($current->available);
        $addclose = empty($current->id) || !empty($current->deadline);

        if (!empty($escape->coursemodule)) {
            $cmid = $escape->coursemodule;
        } else {
            $cmid = get_coursemodule_from_instance('escape', $escape->id, $escape->course)->id;
        }

        $event = new stdClass();
        $event->type = !$deadline ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
        $event->description = format_module_intro('escape', $escape, $cmid);
        // Events module won't show user events when the courseid is nonzero.
        $event->courseid    = ($userid) ? 0 : $escape->course;
        $event->groupid     = $groupid;
        $event->userid      = $userid;
        $event->modulename  = 'escape';
        $event->instance    = $escape->id;
        $event->timestart   = $available;
        $event->timeduration = max($deadline - $available, 0);
        $event->timesort    = $available;
        $event->visible     = instance_is_visible('escape', $escape);
        $event->eventtype   = ESCAPE_EVENT_TYPE_OPEN;
        $event->priority    = null;

        // Determine the event name and priority.
        if ($groupid) {
            // Group override event.
            $params = new stdClass();
            $params->escape = $escape->name;
            $params->group = groups_get_group_name($groupid);
            if ($params->group === false) {
                // Group doesn't exist, just skip it.
                continue;
            }
            $eventname = get_string('overridegroupeventname', 'escape', $params);
            // Set group override priority.
            if ($grouppriorities !== null) {
                $openpriorities = $grouppriorities['open'];
                if (isset($openpriorities[$available])) {
                    $event->priority = $openpriorities[$available];
                }
            }
        } else if ($userid) {
            // User override event.
            $params = new stdClass();
            $params->escape = $escape->name;
            $eventname = get_string('overrideusereventname', 'escape', $params);
            // Set user override priority.
            $event->priority = CALENDAR_EVENT_USER_OVERRIDE_PRIORITY;
        } else {
            // The parent event.
            $eventname = $escape->name;
        }

        if ($addopen or $addclose) {
            // Separate start and end events.
            $event->timeduration  = 0;
            if ($available && $addopen) {
                if ($oldevent = array_shift($oldevents)) {
                    $event->id = $oldevent->id;
                } else {
                    unset($event->id);
                }
                $event->name = get_string('escapeeventopens', 'escape', $eventname);
                // The method calendar_event::create will reuse a db record if the id field is set.
                calendar_event::create($event, false);
            }
            if ($deadline && $addclose) {
                if ($oldevent = array_shift($oldevents)) {
                    $event->id = $oldevent->id;
                } else {
                    unset($event->id);
                }
                $event->type      = CALENDAR_EVENT_TYPE_ACTION;
                $event->name      = get_string('escapeeventcloses', 'escape', $eventname);
                $event->timestart = $deadline;
                $event->timesort  = $deadline;
                $event->eventtype = ESCAPE_EVENT_TYPE_CLOSE;
                if ($groupid && $grouppriorities !== null) {
                    $closepriorities = $grouppriorities['close'];
                    if (isset($closepriorities[$deadline])) {
                        $event->priority = $closepriorities[$deadline];
                    }
                }
                calendar_event::create($event, false);
            }
        }
    }

    // Delete any leftover events.
    foreach ($oldevents as $badevent) {
        $badevent = calendar_event::load($badevent);
        $badevent->delete();
    }
}

/**
 * Calculates the priorities of timeopen and timeclose values for group overrides for a escape.
 *
 * @param int $escapeid The escape ID.
 * @return array|null Array of group override priorities for open and close times. Null if there are no group overrides.
 */
function escape_get_group_override_priorities($escapeid) {
    global $DB;

    // Fetch group overrides.
    $where = 'escapeid = :escapeid AND groupid IS NOT NULL';
    $params = ['escapeid' => $escapeid];
    $overrides = $DB->get_records_select('escape_overrides', $where, $params, '', 'id, groupid, available, deadline');
    if (!$overrides) {
        return null;
    }

    $grouptimeopen = [];
    $grouptimeclose = [];
    foreach ($overrides as $override) {
        if ($override->available !== null && !in_array($override->available, $grouptimeopen)) {
            $grouptimeopen[] = $override->available;
        }
        if ($override->deadline !== null && !in_array($override->deadline, $grouptimeclose)) {
            $grouptimeclose[] = $override->deadline;
        }
    }

    // Sort open times in ascending manner. The earlier open time gets higher priority.
    sort($grouptimeopen);
    // Set priorities.
    $opengrouppriorities = [];
    $openpriority = 1;
    foreach ($grouptimeopen as $timeopen) {
        $opengrouppriorities[$timeopen] = $openpriority++;
    }

    // Sort close times in descending manner. The later close time gets higher priority.
    rsort($grouptimeclose);
    // Set priorities.
    $closegrouppriorities = [];
    $closepriority = 1;
    foreach ($grouptimeclose as $timeclose) {
        $closegrouppriorities[$timeclose] = $closepriority++;
    }

    return [
        'open' => $opengrouppriorities,
        'close' => $closegrouppriorities
    ];
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every escape event in the site is checked, else
 * only escape events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param int $courseid
 * @param int|stdClass $instance Escape module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function escape_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB;

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('escape', array('id' => $instance), '*', MUST_EXIST);
        }
        escape_update_events($instance);
        return true;
    }

    if ($courseid == 0) {
        if (!$escapes = $DB->get_records('escape')) {
            return true;
        }
    } else {
        if (!$escapes = $DB->get_records('escape', array('course' => $courseid))) {
            return true;
        }
    }

    foreach ($escapes as $escape) {
        escape_update_events($escape);
    }

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id
 * @return bool
 */
function escape_delete_instance($id) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/escape/locallib.php');

    $escape = $DB->get_record("escape", array("id"=>$id), '*', MUST_EXIST);
    $escape = new escape($escape);
    return $escape->delete();
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $escape
 * @return object
 */
function escape_user_outline($course, $user, $mod, $escape) {
    global $CFG, $DB;

    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'escape', $escape->id, $user->id);
    $return = new stdClass();

    if (empty($grades->items[0]->grades)) {
        $return->info = get_string("noescapeattempts", "escape");
    } else {
        $grade = reset($grades->items[0]->grades);
        if (empty($grade->grade)) {

            // Check to see if it an ungraded / incomplete attempt.
            $sql = "SELECT *
                      FROM {escape_timer}
                     WHERE escapeid = :escapeid
                       AND userid = :userid
                  ORDER BY starttime DESC";
            $params = array('escapeid' => $escape->id, 'userid' => $user->id);

            if ($attempts = $DB->get_records_sql($sql, $params, 0, 1)) {
                $attempt = reset($attempts);
                if ($attempt->completed) {
                    $return->info = get_string("completed", "escape");
                } else {
                    $return->info = get_string("notyetcompleted", "escape");
                }
                $return->time = $attempt->escapetime;
            } else {
                $return->info = get_string("noescapeattempts", "escape");
            }
        } else {
            if (!$grade->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
                $return->info = get_string('grade') . ': ' . $grade->str_long_grade;
            } else {
                $return->info = get_string('grade') . ': ' . get_string('hidden', 'grades');
            }

            // Datesubmitted == time created. dategraded == time modified or time overridden.
            // If grade was last modified by the user themselves use date graded. Otherwise use date submitted.
            // TODO: move this copied & pasted code somewhere in the grades API. See MDL-26704.
            if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
                $return->time = $grade->dategraded;
            } else {
                $return->time = $grade->datesubmitted;
            }
        }
    }
    return $return;
}

/**
 * Print a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $escape
 * @return bool
 */
function escape_user_complete($course, $user, $mod, $escape) {
    global $DB, $OUTPUT, $CFG;

    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'escape', $escape->id, $user->id);

    // Display the grade and feedback.
    if (empty($grades->items[0]->grades)) {
        echo $OUTPUT->container(get_string("noescapeattempts", "escape"));
    } else {
        $grade = reset($grades->items[0]->grades);
        if (empty($grade->grade)) {
            // Check to see if it an ungraded / incomplete attempt.
            $sql = "SELECT *
                      FROM {escape_timer}
                     WHERE escapeid = :escapeid
                       AND userid = :userid
                     ORDER by starttime desc";
            $params = array('escapeid' => $escape->id, 'userid' => $user->id);

            if ($attempt = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE)) {
                if ($attempt->completed) {
                    $status = get_string("completed", "escape");
                } else {
                    $status = get_string("notyetcompleted", "escape");
                }
            } else {
                $status = get_string("noescapeattempts", "escape");
            }
        } else {
            if (!$grade->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
                $status = get_string("grade") . ': ' . $grade->str_long_grade;
            } else {
                $status = get_string('grade') . ': ' . get_string('hidden', 'grades');
            }
        }

        // Display the grade or escape status if there isn't one.
        echo $OUTPUT->container($status);

        if ($grade->str_feedback &&
            (!$grade->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id)))) {
            echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
        }
    }

    // Display the escape progress.
    // Attempt, pages viewed, questions answered, correct answers, time.
    $params = array ("escapeid" => $escape->id, "userid" => $user->id);
    $attempts = $DB->get_records_select("escape_attempts", "escapeid = :escapeid AND userid = :userid", $params, "retry, timeseen");
    $branches = $DB->get_records_select("escape_branch", "escapeid = :escapeid AND userid = :userid", $params, "retry, timeseen");
    if (!empty($attempts) or !empty($branches)) {
        echo $OUTPUT->box_start();
        $table = new html_table();
        // Table Headings.
        $table->head = array (get_string("attemptheader", "escape"),
            get_string("totalpagesviewedheader", "escape"),
            get_string("numberofpagesviewedheader", "escape"),
            get_string("numberofcorrectanswersheader", "escape"),
            get_string("time"));
        $table->width = "100%";
        $table->align = array ("center", "center", "center", "center", "center");
        $table->size = array ("*", "*", "*", "*", "*");
        $table->cellpadding = 2;
        $table->cellspacing = 0;

        $retry = 0;
        $nquestions = 0;
        $npages = 0;
        $ncorrect = 0;

        // Filter question pages (from escape_attempts).
        foreach ($attempts as $attempt) {
            if ($attempt->retry == $retry) {
                $npages++;
                $nquestions++;
                if ($attempt->correct) {
                    $ncorrect++;
                }
                $timeseen = $attempt->timeseen;
            } else {
                $table->data[] = array($retry + 1, $npages, $nquestions, $ncorrect, userdate($timeseen));
                $retry++;
                $nquestions = 1;
                $npages = 1;
                if ($attempt->correct) {
                    $ncorrect = 1;
                } else {
                    $ncorrect = 0;
                }
            }
        }

        // Filter content pages (from escape_branch).
        foreach ($branches as $branch) {
            if ($branch->retry == $retry) {
                $npages++;

                $timeseen = $branch->timeseen;
            } else {
                $table->data[] = array($retry + 1, $npages, $nquestions, $ncorrect, userdate($timeseen));
                $retry++;
                $npages = 1;
            }
        }
        if ($npages > 0) {
                $table->data[] = array($retry + 1, $npages, $nquestions, $ncorrect, userdate($timeseen));
        }
        echo html_writer::table($table);
        echo $OUTPUT->box_end();
    }

    return true;
}

/**
 * Prints escape summaries on MyMoodle Page
 *
 * Prints escape name, due date and attempt information on
 * escapes that have a deadline that has not already passed
 * and it is available for taking.
 *
 * @deprecated since 3.3
 * @todo The final deprecation of this function will take place in Moodle 3.7 - see MDL-57487.
 * @global object
 * @global stdClass
 * @global object
 * @uses CONTEXT_MODULE
 * @param array $courses An array of course objects to get escape instances from
 * @param array $htmlarray Store overview output array( course ID => 'escape' => HTML output )
 * @return void
 */
function escape_print_overview($courses, &$htmlarray) {
    global $USER, $CFG, $DB, $OUTPUT;

    debugging('The function escape_print_overview() is now deprecated.', DEBUG_DEVELOPER);

    if (!$escapes = get_all_instances_in_courses('escape', $courses)) {
        return;
    }

    // Get all of the current users attempts on all escapes.
    $params = array($USER->id);
    $sql = 'SELECT escapeid, userid, count(userid) as attempts
              FROM {escape_grades}
             WHERE userid = ?
          GROUP BY escapeid, userid';
    $allattempts = $DB->get_records_sql($sql, $params);
    $completedattempts = array();
    foreach ($allattempts as $myattempt) {
        $completedattempts[$myattempt->escapeid] = $myattempt->attempts;
    }

    // Get the current course ID.
    $listofescapes = array();
    foreach ($escapes as $escape) {
        $listofescapes[] = $escape->id;
    }
    // Get the last page viewed by the current user for every escape in this course.
    list($insql, $inparams) = $DB->get_in_or_equal($listofescapes, SQL_PARAMS_NAMED);
    $dbparams = array_merge($inparams, array('userid' => $USER->id));

    // Get the escape attempts for the user that have the maximum 'timeseen' value.
    $select = "SELECT l.id, l.timeseen, l.escapeid, l.userid, l.retry, l.pageid, l.answerid as nextpageid, p.qtype ";
    $from = "FROM {escape_attempts} l
             JOIN (
                   SELECT idselect.escapeid, idselect.userid, MAX(idselect.id) AS id
                     FROM {escape_attempts} idselect
                     JOIN (
                           SELECT escapeid, userid, MAX(timeseen) AS timeseen
                             FROM {escape_attempts}
                            WHERE userid = :userid
                              AND escapeid $insql
                         GROUP BY userid, escapeid
                           ) timeselect
                       ON timeselect.timeseen = idselect.timeseen
                      AND timeselect.userid = idselect.userid
                      AND timeselect.escapeid = idselect.escapeid
                 GROUP BY idselect.userid, idselect.escapeid
                   ) aid
               ON l.id = aid.id
             JOIN {escape_pages} p
               ON l.pageid = p.id ";
    $lastattempts = $DB->get_records_sql($select . $from, $dbparams);

    // Now, get the escape branches for the user that have the maximum 'timeseen' value.
    $select = "SELECT l.id, l.timeseen, l.escapeid, l.userid, l.retry, l.pageid, l.nextpageid, p.qtype ";
    $from = str_replace('{escape_attempts}', '{escape_branch}', $from);
    $lastbranches = $DB->get_records_sql($select . $from, $dbparams);

    $lastviewed = array();
    foreach ($lastattempts as $lastattempt) {
        $lastviewed[$lastattempt->escapeid] = $lastattempt;
    }

    // Go through the branch times and record the 'timeseen' value if it doesn't exist
    // for the escape, or replace it if it exceeds the current recorded time.
    foreach ($lastbranches as $lastbranch) {
        if (!isset($lastviewed[$lastbranch->escapeid])) {
            $lastviewed[$lastbranch->escapeid] = $lastbranch;
        } else if ($lastviewed[$lastbranch->escapeid]->timeseen < $lastbranch->timeseen) {
            $lastviewed[$lastbranch->escapeid] = $lastbranch;
        }
    }

    // Since we have escapes in this course, now include the constants we need.
    require_once($CFG->dirroot . '/mod/escape/locallib.php');

    $now = time();
    foreach ($escapes as $escape) {
        if ($escape->deadline != 0                                         // The escape has a deadline
            and $escape->deadline >= $now                                  // And it is before the deadline has been met
            and ($escape->available == 0 or $escape->available <= $now)) { // And the escape is available

            // Visibility.
            $class = (!$escape->visible) ? 'dimmed' : '';

            // Context.
            $context = context_module::instance($escape->coursemodule);

            // Link to activity.
            $url = new moodle_url('/mod/escape/view.php', array('id' => $escape->coursemodule));
            $url = html_writer::link($url, format_string($escape->name, true, array('context' => $context)), array('class' => $class));
            $str = $OUTPUT->box(get_string('escapename', 'escape', $url), 'name');

            // Deadline.
            $str .= $OUTPUT->box(get_string('escapecloseson', 'escape', userdate($escape->deadline)), 'info');

            // Attempt information.
            if (has_capability('mod/escape:manage', $context)) {
                // This is a teacher, Get the Number of user attempts.
                $attempts = $DB->count_records('escape_grades', array('escapeid' => $escape->id));
                $str     .= $OUTPUT->box(get_string('xattempts', 'escape', $attempts), 'info');
                $str      = $OUTPUT->box($str, 'escape overview');
            } else {
                // This is a student, See if the user has at least started the escape.
                if (isset($lastviewed[$escape->id]->timeseen)) {
                    // See if the user has finished this attempt.
                    if (isset($completedattempts[$escape->id]) &&
                             ($completedattempts[$escape->id] == ($lastviewed[$escape->id]->retry + 1))) {
                        // Are additional attempts allowed?
                        if ($escape->retake) {
                            // User can retake the escape.
                            $str .= $OUTPUT->box(get_string('additionalattemptsremaining', 'escape'), 'info');
                            $str = $OUTPUT->box($str, 'escape overview');
                        } else {
                            // User has completed the escape and no retakes are allowed.
                            $str = '';
                        }

                    } else {
                        // The last attempt was not finished or the escape does not contain questions.
                        // See if the last page viewed was a branchtable.
                        require_once($CFG->dirroot . '/mod/escape/pagetypes/branchtable.php');
                        if ($lastviewed[$escape->id]->qtype == ESCAPE_PAGE_BRANCHTABLE) {
                            // See if the next pageid is the end of escape.
                            if ($lastviewed[$escape->id]->nextpageid == ESCAPE_EOL) {
                                // The last page viewed was the End of Escape.
                                if ($escape->retake) {
                                    // User can retake the escape.
                                    $str .= $OUTPUT->box(get_string('additionalattemptsremaining', 'escape'), 'info');
                                    $str = $OUTPUT->box($str, 'escape overview');
                                } else {
                                    // User has completed the escape and no retakes are allowed.
                                    $str = '';
                                }

                            } else {
                                // The last page viewed was NOT the end of escape.
                                $str .= $OUTPUT->box(get_string('notyetcompleted', 'escape'), 'info');
                                $str = $OUTPUT->box($str, 'escape overview');
                            }

                        } else {
                            // Last page was a question page, so the attempt is not completed yet.
                            $str .= $OUTPUT->box(get_string('notyetcompleted', 'escape'), 'info');
                            $str = $OUTPUT->box($str, 'escape overview');
                        }
                    }

                } else {
                    // User has not yet started this escape.
                    $str .= $OUTPUT->box(get_string('noescapeattempts', 'escape'), 'info');
                    $str = $OUTPUT->box($str, 'escape overview');
                }
            }
            if (!empty($str)) {
                if (empty($htmlarray[$escape->course]['escape'])) {
                    $htmlarray[$escape->course]['escape'] = $str;
                } else {
                    $htmlarray[$escape->course]['escape'] .= $str;
                }
            }
        }
    }
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 * @global stdClass
 * @return bool true
 */
function escape_cron () {
    global $CFG;

    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @global stdClass
 * @global object
 * @param int $escapeid id of escape
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function escape_get_user_grades($escape, $userid=0) {
    global $CFG, $DB;

    $params = array("escapeid" => $escape->id,"escapeid2" => $escape->id);

    if (!empty($userid)) {
        $params["userid"] = $userid;
        $params["userid2"] = $userid;
        $user = "AND u.id = :userid";
        $fuser = "AND uu.id = :userid2";
    }
    else {
        $user="";
        $fuser="";
    }

    if ($escape->retake) {
        if ($escape->usemaxgrade) {
            $sql = "SELECT u.id, u.id AS userid, MAX(g.grade) AS rawgrade
                      FROM {user} u, {escape_grades} g
                     WHERE u.id = g.userid AND g.escapeid = :escapeid
                           $user
                  GROUP BY u.id";
        } else {
            $sql = "SELECT u.id, u.id AS userid, AVG(g.grade) AS rawgrade
                      FROM {user} u, {escape_grades} g
                     WHERE u.id = g.userid AND g.escapeid = :escapeid
                           $user
                  GROUP BY u.id";
        }
        unset($params['escapeid2']);
        unset($params['userid2']);
    } else {
        // use only first attempts (with lowest id in escape_grades table)
        $firstonly = "SELECT uu.id AS userid, MIN(gg.id) AS firstcompleted
                        FROM {user} uu, {escape_grades} gg
                       WHERE uu.id = gg.userid AND gg.escapeid = :escapeid2
                             $fuser
                       GROUP BY uu.id";

        $sql = "SELECT u.id, u.id AS userid, g.grade AS rawgrade
                  FROM {user} u, {escape_grades} g, ($firstonly) f
                 WHERE u.id = g.userid AND g.escapeid = :escapeid
                       AND g.id = f.firstcompleted AND g.userid=f.userid
                       $user";
    }

    return $DB->get_records_sql($sql, $params);
}

/**
 * Update grades in central gradebook
 *
 * @category grade
 * @param object $escape
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone
 */
function escape_update_grades($escape, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if ($escape->grade == 0 || $escape->practice) {
        escape_grade_item_update($escape);

    } else if ($grades = escape_get_user_grades($escape, $userid)) {
        escape_grade_item_update($escape, $grades);

    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = null;
        escape_grade_item_update($escape, $grade);

    } else {
        escape_grade_item_update($escape);
    }
}

/**
 * Create grade item for given escape
 *
 * @category grade
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_NONE
 * @param object $escape object with extra cmidnumber
 * @param array|object $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function escape_grade_item_update($escape, $grades=null) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (array_key_exists('cmidnumber', $escape)) { //it may not be always present
        $params = array('itemname'=>$escape->name, 'idnumber'=>$escape->cmidnumber);
    } else {
        $params = array('itemname'=>$escape->name);
    }

    if (!$escape->practice and $escape->grade > 0) {
        $params['gradetype']  = GRADE_TYPE_VALUE;
        $params['grademax']   = $escape->grade;
        $params['grademin']   = 0;
    } else if (!$escape->practice and $escape->grade < 0) {
        $params['gradetype']  = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$escape->grade;

        // Make sure current grade fetched correctly from $grades
        $currentgrade = null;
        if (!empty($grades)) {
            if (is_array($grades)) {
                $currentgrade = reset($grades);
            } else {
                $currentgrade = $grades;
            }
        }

        // When converting a score to a scale, use scale's grade maximum to calculate it.
        if (!empty($currentgrade) && $currentgrade->rawgrade !== null) {
            $grade = grade_get_grades($escape->course, 'mod', 'escape', $escape->id, $currentgrade->userid);
            $params['grademax']   = reset($grade->items)->grademax;
        }
    } else {
        $params['gradetype']  = GRADE_TYPE_NONE;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    } else if (!empty($grades)) {
        // Need to calculate raw grade (Note: $grades has many forms)
        if (is_object($grades)) {
            $grades = array($grades->userid => $grades);
        } else if (array_key_exists('userid', $grades)) {
            $grades = array($grades['userid'] => $grades);
        }
        foreach ($grades as $key => $grade) {
            if (!is_array($grade)) {
                $grades[$key] = $grade = (array) $grade;
            }
            //check raw grade isnt null otherwise we erroneously insert a grade of 0
            if ($grade['rawgrade'] !== null) {
                $grades[$key]['rawgrade'] = ($grade['rawgrade'] * $params['grademax'] / 100);
            } else {
                //setting rawgrade to null just in case user is deleting a grade
                $grades[$key]['rawgrade'] = null;
            }
        }
    }

    return grade_update('mod/escape', $escape->course, 'mod', 'escape', $escape->id, 0, $grades, $params);
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function escape_get_view_actions() {
    return array('view','view all');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function escape_get_post_actions() {
    return array('end','start');
}

/**
 * Runs any processes that must run before
 * a escape insert/update
 *
 * @global object
 * @param object $escape Escape form data
 * @return void
 **/
function escape_process_pre_save(&$escape) {
    global $DB;

    $escape->timemodified = time();

    if (empty($escape->timelimit)) {
        $escape->timelimit = 0;
    }
    if (empty($escape->timespent) or !is_numeric($escape->timespent) or $escape->timespent < 0) {
        $escape->timespent = 0;
    }
    if (!isset($escape->completed)) {
        $escape->completed = 0;
    }
    if (empty($escape->gradebetterthan) or !is_numeric($escape->gradebetterthan) or $escape->gradebetterthan < 0) {
        $escape->gradebetterthan = 0;
    } else if ($escape->gradebetterthan > 100) {
        $escape->gradebetterthan = 100;
    }

    if (empty($escape->width)) {
        $escape->width = 640;
    }
    if (empty($escape->height)) {
        $escape->height = 480;
    }
    if (empty($escape->bgcolor)) {
        $escape->bgcolor = '#FFFFFF';
    }

    // Conditions for dependency
    $conditions = new stdClass;
    $conditions->timespent = $escape->timespent;
    $conditions->completed = $escape->completed;
    $conditions->gradebetterthan = $escape->gradebetterthan;
    $escape->conditions = serialize($conditions);
    unset($escape->timespent);
    unset($escape->completed);
    unset($escape->gradebetterthan);

    if (empty($escape->password)) {
        unset($escape->password);
    }
}

/**
 * Runs any processes that must be run
 * after a escape insert/update
 *
 * @global object
 * @param object $escape Escape form data
 * @return void
 **/
function escape_process_post_save(&$escape) {
    // Update the events relating to this escape.
    escape_update_events($escape);
    $completionexpected = (!empty($escape->completionexpected)) ? $escape->completionexpected : null;
    \core_completion\api::update_completion_date_event($escape->coursemodule, 'escape', $escape, $completionexpected);
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the escape.
 *
 * @param $mform form passed by reference
 */
function escape_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'escapeheader', get_string('modulenameplural', 'escape'));
    $mform->addElement('advcheckbox', 'reset_escape', get_string('deleteallattempts','escape'));
    $mform->addElement('advcheckbox', 'reset_escape_user_overrides',
            get_string('removealluseroverrides', 'escape'));
    $mform->addElement('advcheckbox', 'reset_escape_group_overrides',
            get_string('removeallgroupoverrides', 'escape'));
}

/**
 * Course reset form defaults.
 * @param object $course
 * @return array
 */
function escape_reset_course_form_defaults($course) {
    return array('reset_escape' => 1,
            'reset_escape_group_overrides' => 1,
            'reset_escape_user_overrides' => 1);
}

/**
 * Removes all grades from gradebook
 *
 * @global stdClass
 * @global object
 * @param int $courseid
 * @param string optional type
 */
function escape_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $sql = "SELECT l.*, cm.idnumber as cmidnumber, l.course as courseid
              FROM {escape} l, {course_modules} cm, {modules} m
             WHERE m.name='escape' AND m.id=cm.module AND cm.instance=l.id AND l.course=:course";
    $params = array ("course" => $courseid);
    if ($escapes = $DB->get_records_sql($sql,$params)) {
        foreach ($escapes as $escape) {
            escape_grade_item_update($escape, 'reset');
        }
    }
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * escape attempts for course $data->courseid.
 *
 * @global stdClass
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function escape_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'escape');
    $status = array();

    if (!empty($data->reset_escape)) {
        $escapessql = "SELECT l.id
                         FROM {escape} l
                        WHERE l.course=:course";

        $params = array ("course" => $data->courseid);
        $escapes = $DB->get_records_sql($escapessql, $params);

        // Get rid of attempts files.
        $fs = get_file_storage();
        if ($escapes) {
            foreach ($escapes as $escapeid => $unused) {
                if (!$cm = get_coursemodule_from_instance('escape', $escapeid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);
                $fs->delete_area_files($context->id, 'mod_escape', 'essay_responses');
            }
        }

        $DB->delete_records_select('escape_timer', "escapeid IN ($escapessql)", $params);
        $DB->delete_records_select('escape_grades', "escapeid IN ($escapessql)", $params);
        $DB->delete_records_select('escape_attempts', "escapeid IN ($escapessql)", $params);
        $DB->delete_records_select('escape_branch', "escapeid IN ($escapessql)", $params);

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            escape_reset_gradebook($data->courseid);
        }

        $status[] = array('component'=>$componentstr, 'item'=>get_string('deleteallattempts', 'escape'), 'error'=>false);
    }

    // Remove user overrides.
    if (!empty($data->reset_escape_user_overrides)) {
        $DB->delete_records_select('escape_overrides',
                'escapeid IN (SELECT id FROM {escape} WHERE course = ?) AND userid IS NOT NULL', array($data->courseid));
        $status[] = array(
        'component' => $componentstr,
        'item' => get_string('useroverridesdeleted', 'escape'),
        'error' => false);
    }
    // Remove group overrides.
    if (!empty($data->reset_escape_group_overrides)) {
        $DB->delete_records_select('escape_overrides',
        'escapeid IN (SELECT id FROM {escape} WHERE course = ?) AND groupid IS NOT NULL', array($data->courseid));
        $status[] = array(
        'component' => $componentstr,
        'item' => get_string('groupoverridesdeleted', 'escape'),
        'error' => false);
    }
    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        $DB->execute("UPDATE {escape_overrides}
                         SET available = available + ?
                       WHERE escapeid IN (SELECT id FROM {escape} WHERE course = ?)
                         AND available <> 0", array($data->timeshift, $data->courseid));
        $DB->execute("UPDATE {escape_overrides}
                         SET deadline = deadline + ?
                       WHERE escapeid IN (SELECT id FROM {escape} WHERE course = ?)
                         AND deadline <> 0", array($data->timeshift, $data->courseid));

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        shift_course_mod_dates('escape', array('available', 'deadline'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function escape_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * Obtains the automatic completion state for this escape based on any conditions
 * in escape settings.
 *
 * @param object $course Course
 * @param object $cm course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function escape_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;

    // Get escape details.
    $escape = $DB->get_record('escape', array('id' => $cm->instance), '*',
            MUST_EXIST);

    $result = $type; // Default return value.
    // If completion option is enabled, evaluate it and return true/false.
    if ($escape->completionendreached) {
        $value = $DB->record_exists('escape_timer', array(
                'escapeid' => $escape->id, 'userid' => $userid, 'completed' => 1));
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($escape->completiontimespent != 0) {
        $duration = $DB->get_field_sql(
                        "SELECT SUM(escapetime - starttime)
                               FROM {escape_timer}
                              WHERE escapeid = :escapeid
                                AND userid = :userid",
                        array('userid' => $userid, 'escapeid' => $escape->id));
        if (!$duration) {
            $duration = 0;
        }
        if ($type == COMPLETION_AND) {
            $result = $result && ($escape->completiontimespent < $duration);
        } else {
            $result = $result || ($escape->completiontimespent < $duration);
        }
    }
    return $result;
}
/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called
 *
 * @param settings_navigation $settings
 * @param navigation_node $escapenode
 */
function escape_extend_settings_navigation($settings, $escapenode) {
    global $PAGE, $DB;

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $escapenode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_capability('mod/escape:manageoverrides', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/escape/overrides.php', array('cmid' => $PAGE->cm->id));
        $node = navigation_node::create(get_string('groupoverrides', 'escape'),
                new moodle_url($url, array('mode' => 'group')),
                navigation_node::TYPE_SETTING, null, 'mod_escape_groupoverrides');
        $escapenode->add_node($node, $beforekey);

        $node = navigation_node::create(get_string('useroverrides', 'escape'),
                new moodle_url($url, array('mode' => 'user')),
                navigation_node::TYPE_SETTING, null, 'mod_escape_useroverrides');
        $escapenode->add_node($node, $beforekey);
    }

    if (has_capability('mod/escape:edit', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/escape/view.php', array('id' => $PAGE->cm->id));
        $escapenode->add(get_string('preview', 'escape'), $url);
        $editnode = $escapenode->add(get_string('edit', 'escape'));
        $url = new moodle_url('/mod/escape/edit.php', array('id' => $PAGE->cm->id, 'mode' => 'collapsed'));
        $editnode->add(get_string('collapsed', 'escape'), $url);
        $url = new moodle_url('/mod/escape/edit.php', array('id' => $PAGE->cm->id, 'mode' => 'full'));
        $editnode->add(get_string('full', 'escape'), $url);
    }

    if (has_capability('mod/escape:viewreports', $PAGE->cm->context)) {
        $reportsnode = $escapenode->add(get_string('reports', 'escape'));
        $url = new moodle_url('/mod/escape/report.php', array('id'=>$PAGE->cm->id, 'action'=>'reportoverview'));
        $reportsnode->add(get_string('overview', 'escape'), $url);
        $url = new moodle_url('/mod/escape/report.php', array('id'=>$PAGE->cm->id, 'action'=>'reportdetail'));
        $reportsnode->add(get_string('detailedstats', 'escape'), $url);
    }

    if (has_capability('mod/escape:grade', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/escape/essay.php', array('id'=>$PAGE->cm->id));
        $escapenode->add(get_string('manualgrading', 'escape'), $url);
    }

}

/**
 * Get list of available import or export formats
 *
 * Copied and modified from lib/questionlib.php
 *
 * @param string $type 'import' if import list, otherwise export list assumed
 * @return array sorted list of import/export formats available
 */
function escape_get_import_export_formats($type) {
    global $CFG;
    $fileformats = core_component::get_plugin_list("qformat");

    $fileformatname=array();
    foreach ($fileformats as $fileformat=>$fdir) {
        $format_file = "$fdir/format.php";
        if (file_exists($format_file) ) {
            require_once($format_file);
        } else {
            continue;
        }
        $classname = "qformat_$fileformat";
        $format_class = new $classname();
        if ($type=='import') {
            $provided = $format_class->provide_import();
        } else {
            $provided = $format_class->provide_export();
        }
        if ($provided) {
            $fileformatnames[$fileformat] = get_string('pluginname', 'qformat_'.$fileformat);
        }
    }
    natcasesort($fileformatnames);

    return $fileformatnames;
}

/**
 * Serves the escape attachments. Implements needed access control ;-)
 *
 * @package mod_escape
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function escape_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    $fileareas = escape_get_file_areas();
    if (!array_key_exists($filearea, $fileareas)) {
        return false;
    }

    if (!$escape = $DB->get_record('escape', array('id'=>$cm->instance))) {
        return false;
    }

    require_course_login($course, true, $cm);

    if ($filearea === 'page_contents') {
        $pageid = (int)array_shift($args);
        if (!$page = $DB->get_record('escape_pages', array('id'=>$pageid))) {
            return false;
        }
        $fullpath = "/$context->id/mod_escape/$filearea/$pageid/".implode('/', $args);

    } else if ($filearea === 'page_answers' || $filearea === 'page_responses') {
        $itemid = (int)array_shift($args);
        if (!$pageanswers = $DB->get_record('escape_answers', array('id' => $itemid))) {
            return false;
        }
        $fullpath = "/$context->id/mod_escape/$filearea/$itemid/".implode('/', $args);

    } else if ($filearea === 'essay_responses') {
        $itemid = (int)array_shift($args);
        if (!$attempt = $DB->get_record('escape_attempts', array('id' => $itemid))) {
            return false;
        }
        $fullpath = "/$context->id/mod_escape/$filearea/$itemid/".implode('/', $args);

    } else if ($filearea === 'mediafile') {
        if (count($args) > 1) {
            // Remove the itemid when it appears to be part of the arguments. If there is only one argument
            // then it is surely the file name. The itemid is sometimes used to prevent browser caching.
            array_shift($args);
        }
        $fullpath = "/$context->id/mod_escape/$filearea/0/".implode('/', $args);

    } else {
        return false;
    }

    $fs = get_file_storage();
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, $forcedownload, $options); // download MUST be forced - security!
}

/**
 * Returns an array of file areas
 *
 * @package  mod_escape
 * @category files
 * @return array a list of available file areas
 */
function escape_get_file_areas() {
    $areas = array();
    $areas['page_contents'] = get_string('pagecontents', 'mod_escape');
    $areas['mediafile'] = get_string('mediafile', 'mod_escape');
    $areas['page_answers'] = get_string('pageanswers', 'mod_escape');
    $areas['page_responses'] = get_string('pageresponses', 'mod_escape');
    $areas['essay_responses'] = get_string('essayresponses', 'mod_escape');
    return $areas;
}

/**
 * Returns a file_info_stored object for the file being requested here
 *
 * @package  mod_escape
 * @category files
 * @global stdClass $CFG
 * @param file_browse $browser file browser instance
 * @param array $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info_stored
 */
function escape_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB;

    if (!has_capability('moodle/course:managefiles', $context)) {
        // No peaking here for students!
        return null;
    }

    // Mediafile area does not have sub directories, so let's select the default itemid to prevent
    // the user from selecting a directory to access the mediafile content.
    if ($filearea == 'mediafile' && is_null($itemid)) {
        $itemid = 0;
    }

    if (is_null($itemid)) {
        return new mod_escape_file_info($browser, $course, $cm, $context, $areas, $filearea);
    }

    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!$storedfile = $fs->get_file($context->id, 'mod_escape', $filearea, $itemid, $filepath, $filename)) {
        return null;
    }

    $itemname = $filearea;
    if ($filearea == 'page_contents') {
        $itemname = $DB->get_field('escape_pages', 'title', array('escapeid' => $cm->instance, 'id' => $itemid));
        $itemname = format_string($itemname, true, array('context' => $context));
    } else {
        $areas = escape_get_file_areas();
        if (isset($areas[$filearea])) {
            $itemname = $areas[$filearea];
        }
    }

    $urlbase = $CFG->wwwroot . '/pluginfile.php';
    return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemname, $itemid, true, true, false);
}


/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function escape_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array(
        'mod-escape-*'=>get_string('page-mod-escape-x', 'escape'),
        'mod-escape-view'=>get_string('page-mod-escape-view', 'escape'),
        'mod-escape-edit'=>get_string('page-mod-escape-edit', 'escape'));
    return $module_pagetype;
}

/**
 * Update the escape activity to include any file
 * that was uploaded, or if there is none, set the
 * mediafile field to blank.
 *
 * @param int $escapeid the escape id
 * @param stdClass $context the context
 * @param int $draftitemid the draft item
 */
function escape_update_media_file($escapeid, $context, $draftitemid) {
    global $DB;

    // Set the filestorage object.
    $fs = get_file_storage();
    // Save the file if it exists that is currently in the draft area.
    file_save_draft_area_files($draftitemid, $context->id, 'mod_escape', 'mediafile', 0);
    // Get the file if it exists.
    $files = $fs->get_area_files($context->id, 'mod_escape', 'mediafile', 0, 'itemid, filepath, filename', false);
    // Check that there is a file to process.
    if (count($files) == 1) {
        // Get the first (and only) file.
        $file = reset($files);
        // Set the mediafile column in the escapes table.
        $DB->set_field('escape', 'mediafile', '/' . $file->get_filename(), array('id' => $escapeid));
    } else {
        // Set the mediafile column in the escapes table.
        $DB->set_field('escape', 'mediafile', '', array('id' => $escapeid));
    }
}

/**
 * Get icon mapping for font-awesome.
 */
function mod_escape_get_fontawesome_icon_map() {
    return [
        'mod_escape:e/copy' => 'fa-clone',
    ];
}

/*
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.3
 */
function escape_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB, $USER;

    $updates = course_check_module_updates_since($cm, $from, array(), $filter);

    // Check if there are new pages or answers in the escape.
    $updates->pages = (object) array('updated' => false);
    $updates->answers = (object) array('updated' => false);
    $select = 'escapeid = ? AND (timecreated > ? OR timemodified > ?)';
    $params = array($cm->instance, $from, $from);

    $pages = $DB->get_records_select('escape_pages', $select, $params, '', 'id');
    if (!empty($pages)) {
        $updates->pages->updated = true;
        $updates->pages->itemids = array_keys($pages);
    }
    $answers = $DB->get_records_select('escape_answers', $select, $params, '', 'id');
    if (!empty($answers)) {
        $updates->answers->updated = true;
        $updates->answers->itemids = array_keys($answers);
    }

    // Check for new question attempts, grades, pages viewed and timers.
    $updates->questionattempts = (object) array('updated' => false);
    $updates->grades = (object) array('updated' => false);
    $updates->pagesviewed = (object) array('updated' => false);
    $updates->timers = (object) array('updated' => false);

    $select = 'escapeid = ? AND userid = ? AND timeseen > ?';
    $params = array($cm->instance, $USER->id, $from);

    $questionattempts = $DB->get_records_select('escape_attempts', $select, $params, '', 'id');
    if (!empty($questionattempts)) {
        $updates->questionattempts->updated = true;
        $updates->questionattempts->itemids = array_keys($questionattempts);
    }
    $pagesviewed = $DB->get_records_select('escape_branch', $select, $params, '', 'id');
    if (!empty($pagesviewed)) {
        $updates->pagesviewed->updated = true;
        $updates->pagesviewed->itemids = array_keys($pagesviewed);
    }

    $select = 'escapeid = ? AND userid = ? AND completed > ?';
    $grades = $DB->get_records_select('escape_grades', $select, $params, '', 'id');
    if (!empty($grades)) {
        $updates->grades->updated = true;
        $updates->grades->itemids = array_keys($grades);
    }

    $select = 'escapeid = ? AND userid = ? AND (starttime > ? OR escapetime > ? OR timemodifiedoffline > ?)';
    $params = array($cm->instance, $USER->id, $from, $from, $from);
    $timers = $DB->get_records_select('escape_timer', $select, $params, '', 'id');
    if (!empty($timers)) {
        $updates->timers->updated = true;
        $updates->timers->itemids = array_keys($timers);
    }

    // Now, teachers should see other students updates.
    if (has_capability('mod/escape:viewreports', $cm->context)) {
        $select = 'escapeid = ? AND timeseen > ?';
        $params = array($cm->instance, $from);

        $insql = '';
        $inparams = [];
        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            $groupusers = array_keys(groups_get_activity_shared_group_members($cm));
            if (empty($groupusers)) {
                return $updates;
            }
            list($insql, $inparams) = $DB->get_in_or_equal($groupusers);
            $select .= ' AND userid ' . $insql;
            $params = array_merge($params, $inparams);
        }

        $updates->userquestionattempts = (object) array('updated' => false);
        $updates->usergrades = (object) array('updated' => false);
        $updates->userpagesviewed = (object) array('updated' => false);
        $updates->usertimers = (object) array('updated' => false);

        $questionattempts = $DB->get_records_select('escape_attempts', $select, $params, '', 'id');
        if (!empty($questionattempts)) {
            $updates->userquestionattempts->updated = true;
            $updates->userquestionattempts->itemids = array_keys($questionattempts);
        }
        $pagesviewed = $DB->get_records_select('escape_branch', $select, $params, '', 'id');
        if (!empty($pagesviewed)) {
            $updates->userpagesviewed->updated = true;
            $updates->userpagesviewed->itemids = array_keys($pagesviewed);
        }

        $select = 'escapeid = ? AND completed > ?';
        if (!empty($insql)) {
            $select .= ' AND userid ' . $insql;
        }
        $grades = $DB->get_records_select('escape_grades', $select, $params, '', 'id');
        if (!empty($grades)) {
            $updates->usergrades->updated = true;
            $updates->usergrades->itemids = array_keys($grades);
        }

        $select = 'escapeid = ? AND (starttime > ? OR escapetime > ? OR timemodifiedoffline > ?)';
        $params = array($cm->instance, $from, $from, $from);
        if (!empty($insql)) {
            $select .= ' AND userid ' . $insql;
            $params = array_merge($params, $inparams);
        }
        $timers = $DB->get_records_select('escape_timer', $select, $params, '', 'id');
        if (!empty($timers)) {
            $updates->usertimers->updated = true;
            $updates->usertimers->itemids = array_keys($timers);
        }
    }
    return $updates;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_escape_core_calendar_provide_event_action(calendar_event $event,
                                                       \core_calendar\action_factory $factory,
                                                       int $userid = 0) {
    global $DB, $CFG, $USER;
    require_once($CFG->dirroot . '/mod/escape/locallib.php');

    if (!$userid) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['escape'][$event->instance];

    if (!$cm->uservisible) {
        // The module is not visible to the user for any reason.
        return null;
    }

    $escape = new escape($DB->get_record('escape', array('id' => $cm->instance), '*', MUST_EXIST));

    if ($escape->count_user_retries($userid)) {
        // If the user has attempted the escape then there is no further action for the user.
        return null;
    }

    // Apply overrides.
    $escape->update_effective_access($userid);

    if (!$escape->is_participant($userid)) {
        // If the user is not a participant then they have
        // no action to take. This will filter out the events for teachers.
        return null;
    }

    return $factory->create_instance(
        get_string('startescape', 'escape'),
        new \moodle_url('/mod/escape/view.php', ['id' => $cm->id]),
        1,
        $escape->is_accessible()
    );
}

/**
 * Add a get_coursemodule_info function in case any escape type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function escape_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionendreached, completiontimespent';
    if (!$escape = $DB->get_record('escape', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $escape->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('escape', $escape, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionendreached'] = $escape->completionendreached;
        $result->customdata['customcompletionrules']['completiontimespent'] = $escape->completiontimespent;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_escape_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionendreached':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionendreached_desc', 'escape', $val);
                }
                break;
            case 'completiontimespent':
                if (!empty($val)) {
                    $descriptions[] = get_string('completiontimespentdesc', 'escape', format_time($val));
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

/**
 * This function calculates the minimum and maximum cutoff values for the timestart of
 * the given event.
 *
 * It will return an array with two values, the first being the minimum cutoff value and
 * the second being the maximum cutoff value. Either or both values can be null, which
 * indicates there is no minimum or maximum, respectively.
 *
 * If a cutoff is required then the function must return an array containing the cutoff
 * timestamp and error string to display to the user if the cutoff value is violated.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The due date must be after the start date'],
 *     [1506741172, 'The due date must be before the cutoff date']
 * ]
 *
 * @param calendar_event $event The calendar event to get the time range for
 * @param stdClass $instance The module instance to get the range from
 * @return array
 */
function mod_escape_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $instance) {
    $mindate = null;
    $maxdate = null;

    if ($event->eventtype == ESCAPE_EVENT_TYPE_OPEN) {
        // The start time of the open event can't be equal to or after the
        // close time of the escape activity.
        if (!empty($instance->deadline)) {
            $maxdate = [
                $instance->deadline,
                get_string('openafterclose', 'escape')
            ];
        }
    } else if ($event->eventtype == ESCAPE_EVENT_TYPE_CLOSE) {
        // The start time of the close event can't be equal to or earlier than the
        // open time of the escape activity.
        if (!empty($instance->available)) {
            $mindate = [
                $instance->available,
                get_string('closebeforeopen', 'escape')
            ];
        }
    }

    return [$mindate, $maxdate];
}

/**
 * This function will update the escape module according to the
 * event that has been modified.
 *
 * It will set the available or deadline value of the escape instance
 * according to the type of event provided.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event
 * @param stdClass $escape The module instance to get the range from
 */
function mod_escape_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $escape) {
    global $DB;

    if (empty($event->instance) || $event->modulename != 'escape') {
        return;
    }

    if ($event->instance != $escape->id) {
        return;
    }

    if (!in_array($event->eventtype, [ESCAPE_EVENT_TYPE_OPEN, ESCAPE_EVENT_TYPE_CLOSE])) {
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;

    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if ($event->eventtype == ESCAPE_EVENT_TYPE_OPEN) {
        // If the event is for the escape activity opening then we should
        // set the start time of the escape activity to be the new start
        // time of the event.
        if ($escape->available != $event->timestart) {
            $escape->available = $event->timestart;
            $escape->timemodified = time();
            $modified = true;
        }
    } else if ($event->eventtype == ESCAPE_EVENT_TYPE_CLOSE) {
        // If the event is for the escape activity closing then we should
        // set the end time of the escape activity to be the new start
        // time of the event.
        if ($escape->deadline != $event->timestart) {
            $escape->deadline = $event->timestart;
            $modified = true;
        }
    }

    if ($modified) {
        $escape->timemodified = time();
        $DB->update_record('escape', $escape);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}
