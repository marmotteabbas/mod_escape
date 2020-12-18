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
 * Data provider.
 *
 * @package    mod_escape
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_escape\privacy;
defined('MOODLE_INTERNAL') || die();

use context;
use context_helper;
use context_module;
use stdClass;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

require_once($CFG->dirroot . '/mod/escape/locallib.php');
require_once($CFG->dirroot . '/mod/escape/pagetypes/essay.php');
require_once($CFG->dirroot . '/mod/escape/pagetypes/matching.php');
require_once($CFG->dirroot . '/mod/escape/pagetypes/multichoice.php');

/**
 * Data provider class.
 *
 * @package    mod_escape
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {

    /**
     * Returns metadata.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table('escape_attempts', [
            'userid' => 'privacy:metadata:attempts:userid',
            'pageid' => 'privacy:metadata:attempts:pageid',
            'answerid' => 'privacy:metadata:attempts:answerid',
            'retry' => 'privacy:metadata:attempts:retry',
            'correct' => 'privacy:metadata:attempts:correct',
            'useranswer' => 'privacy:metadata:attempts:useranswer',
            'timeseen' => 'privacy:metadata:attempts:timeseen',
        ], 'privacy:metadata:attempts');

        $collection->add_database_table('escape_grades', [
            'userid' => 'privacy:metadata:grades:userid',
            'grade' => 'privacy:metadata:grades:grade',
            'completed' => 'privacy:metadata:grades:completed',
            // The column late is not used.
        ], 'privacy:metadata:grades');

        $collection->add_database_table('escape_timer', [
            'userid' => 'privacy:metadata:timer:userid',
            'starttime' => 'privacy:metadata:timer:starttime',
            'escapetime' => 'privacy:metadata:timer:escapetime',
            'completed' => 'privacy:metadata:timer:completed',
            'timemodifiedoffline' => 'privacy:metadata:timer:timemodifiedoffline',
        ], 'privacy:metadata:timer');

        $collection->add_database_table('escape_branch', [
            'userid' => 'privacy:metadata:branch:userid',
            'pageid' => 'privacy:metadata:branch:pageid',
            'retry' => 'privacy:metadata:branch:retry',
            'flag' => 'privacy:metadata:branch:flag',
            'timeseen' => 'privacy:metadata:branch:timeseen',
            'nextpageid' => 'privacy:metadata:branch:nextpageid',
        ], 'privacy:metadata:branch');

        $collection->add_database_table('escape_overrides', [
            'userid' => 'privacy:metadata:overrides:userid',
            'available' => 'privacy:metadata:overrides:available',
            'deadline' => 'privacy:metadata:overrides:deadline',
            'timelimit' => 'privacy:metadata:overrides:timelimit',
            'review' => 'privacy:metadata:overrides:review',
            'maxattempts' => 'privacy:metadata:overrides:maxattempts',
            'retake' => 'privacy:metadata:overrides:retake',
            'password' => 'privacy:metadata:overrides:password',
        ], 'privacy:metadata:overrides');

        $collection->add_user_preference('escape_view', 'privacy:metadata:userpref:escapeview');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();

        $sql = "
            SELECT DISTINCT ctx.id
              FROM {escape} l
              JOIN {modules} m
                ON m.name = :escape
              JOIN {course_modules} cm
                ON cm.instance = l.id
               AND cm.module = m.id
              JOIN {context} ctx
                ON ctx.instanceid = cm.id
               AND ctx.contextlevel = :modulelevel
         LEFT JOIN {escape_attempts} la
                ON la.escapeid = l.id
               AND la.userid = :userid1
         LEFT JOIN {escape_branch} lb
                ON lb.escapeid = l.id
               AND lb.userid = :userid2
         LEFT JOIN {escape_grades} lg
                ON lg.escapeid = l.id
               AND lg.userid = :userid3
         LEFT JOIN {escape_overrides} lo
                ON lo.escapeid = l.id
               AND lo.userid = :userid4
         LEFT JOIN {escape_timer} lt
                ON lt.escapeid = l.id
               AND lt.userid = :userid5
             WHERE la.id IS NOT NULL
                OR lb.id IS NOT NULL
                OR lg.id IS NOT NULL
                OR lo.id IS NOT NULL
                OR lt.id IS NOT NULL";

        $params = [
            'escape' => 'escape',
            'modulelevel' => CONTEXT_MODULE,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
            'userid5' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     *
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $params = [
            'escape' => 'escape',
            'modulelevel' => CONTEXT_MODULE,
            'contextid' => $context->id,
        ];

        // Mapping of escape tables which may contain user data.
        $joins = [
            'escape_attempts',
            'escape_branch',
            'escape_grades',
            'escape_overrides',
            'escape_timer',
        ];

        foreach ($joins as $join) {
            $sql = "
                SELECT lx.userid
                  FROM {escape} l
                  JOIN {modules} m
                    ON m.name = :escape
                  JOIN {course_modules} cm
                    ON cm.instance = l.id
                   AND cm.module = m.id
                  JOIN {context} ctx
                    ON ctx.instanceid = cm.id
                   AND ctx.contextlevel = :modulelevel
                  JOIN {{$join}} lx
                    ON lx.escapeid = l.id
                 WHERE ctx.id = :contextid";

            $userlist->add_from_sql('userid', $sql, $params);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        $userid = $user->id;
        $cmids = array_reduce($contextlist->get_contexts(), function($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[] = $context->instanceid;
            }
            return $carry;
        }, []);
        if (empty($cmids)) {
            return;
        }

        // If the context export was requested, then let's at least describe the escape.
        foreach ($cmids as $cmid) {
            $context = context_module::instance($cmid);
            $contextdata = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);
            writer::with_context($context)->export_data([], $contextdata);
        }

        // Find the escape IDs.
        $escapeidstocmids = static::get_escape_ids_to_cmids_from_cmids($cmids);

        // Prepare the common SQL fragments.
        list($inescapesql, $inescapeparams) = $DB->get_in_or_equal(array_keys($escapeidstocmids), SQL_PARAMS_NAMED);
        $sqluserescape = "userid = :userid AND escapeid $inescapesql";
        $paramsuserescape = array_merge($inescapeparams, ['userid' => $userid]);

        // Export the overrides.
        $recordset = $DB->get_recordset_select('escape_overrides', $sqluserescape, $paramsuserescape);
        static::recordset_loop_and_export($recordset, 'escapeid', null, function($carry, $record) {
            // We know that there is only one row per escape, so no need to use $carry.
            return (object) [
                'available' => $record->available !== null ? transform::datetime($record->available) : null,
                'deadline' => $record->deadline !== null ? transform::datetime($record->deadline) : null,
                'timelimit' => $record->timelimit !== null ? format_time($record->timelimit) : null,
                'review' => $record->review !== null ? transform::yesno($record->review) : null,
                'maxattempts' => $record->maxattempts,
                'retake' => $record->retake !== null ? transform::yesno($record->retake) : null,
                'password' => $record->password,
            ];
        }, function($escapeid, $data) use ($escapeidstocmids) {
            $context = context_module::instance($escapeidstocmids[$escapeid]);
            writer::with_context($context)->export_related_data([], 'overrides', $data);
        });

        // Export the grades.
        $recordset = $DB->get_recordset_select('escape_grades', $sqluserescape, $paramsuserescape, 'escapeid, completed');
        static::recordset_loop_and_export($recordset, 'escapeid', [], function($carry, $record) {
            $carry[] = (object) [
                'grade' => $record->grade,
                'completed' => transform::datetime($record->completed),
            ];
            return $carry;
        }, function($escapeid, $data) use ($escapeidstocmids) {
            $context = context_module::instance($escapeidstocmids[$escapeid]);
            writer::with_context($context)->export_related_data([], 'grades', (object) ['grades' => $data]);
        });

        // Export the timers.
        $recordset = $DB->get_recordset_select('escape_timer', $sqluserescape, $paramsuserescape, 'escapeid, starttime');
        static::recordset_loop_and_export($recordset, 'escapeid', [], function($carry, $record) {
            $carry[] = (object) [
                'starttime' => transform::datetime($record->starttime),
                'lastactivity' => transform::datetime($record->escapetime),
                'completed' => transform::yesno($record->completed),
                'timemodifiedoffline' => $record->timemodifiedoffline ? transform::datetime($record->timemodifiedoffline) : null,
            ];
            return $carry;
        }, function($escapeid, $data) use ($escapeidstocmids) {
            $context = context_module::instance($escapeidstocmids[$escapeid]);
            writer::with_context($context)->export_related_data([], 'timers', (object) ['timers' => $data]);
        });

        // Export the attempts and branches.
        $sql = "
            SELECT " . $DB->sql_concat('lp.id', "':'", 'COALESCE(la.id, 0)', "':'", 'COALESCE(lb.id, 0)') . " AS uniqid,
                   lp.escapeid,

                   lp.id AS page_id,
                   lp.qtype AS page_qtype,
                   lp.qoption AS page_qoption,
                   lp.title AS page_title,
                   lp.contents AS page_contents,
                   lp.contentsformat AS page_contentsformat,

                   la.id AS attempt_id,
                   la.retry AS attempt_retry,
                   la.correct AS attempt_correct,
                   la.useranswer AS attempt_useranswer,
                   la.timeseen AS attempt_timeseen,

                   lb.id AS branch_id,
                   lb.retry AS branch_retry,
                   lb.timeseen AS branch_timeseen,

                   lpb.id AS nextpage_id,
                   lpb.title AS nextpage_title

              FROM {escape_pages} lp
         LEFT JOIN {escape_attempts} la
                ON la.pageid = lp.id
               AND la.userid = :userid1
         LEFT JOIN {escape_branch} lb
                ON lb.pageid = lp.id
               AND lb.userid = :userid2
         LEFT JOIN {escape_pages} lpb
                ON lpb.id = lb.nextpageid
             WHERE lp.escapeid $inescapesql
               AND (la.id IS NOT NULL OR lb.id IS NOT NULL)
          ORDER BY lp.escapeid, lp.id, la.retry, lb.retry, la.id, lb.id";
        $params = array_merge($inescapeparams, ['userid1' => $userid, 'userid2' => $userid]);

        $recordset = $DB->get_recordset_sql($sql, $params);
        static::recordset_loop_and_export($recordset, 'escapeid', [], function($carry, $record) use ($escapeidstocmids) {
            $context = context_module::instance($escapeidstocmids[$record->escapeid]);
            $options = ['context' => $context];

            $take = isset($record->attempt_retry) ? $record->attempt_retry : $record->branch_retry;
            if (!isset($carry[$take])) {
                $carry[$take] = (object) [
                    'number' => $take + 1,
                    'answers' => [],
                    'jumps' => []
                ];
            }

            $pagefilespath = [get_string('privacy:path:pages', 'mod_escape'), $record->page_id];
            writer::with_context($context)->export_area_files($pagefilespath, 'mod_escape', 'page_contents', $record->page_id);
            $pagecontents = format_text(
                writer::with_context($context)->rewrite_pluginfile_urls(
                    $pagefilespath,
                    'mod_escape',
                    'page_contents',
                    $record->page_id,
                    $record->page_contents
                ),
                $record->page_contentsformat,
                $options
            );

            $pagebase = [
                'id' => $record->page_id,
                'page' => $record->page_title,
                'contents' => $pagecontents,
                'contents_files_folder' => implode('/', $pagefilespath)
            ];

            if (isset($record->attempt_id)) {
                $carry[$take]->answers[] = array_merge($pagebase, static::transform_attempt($record, $context));

            } else if (isset($record->branch_id)) {
                if (!empty($record->nextpage_id)) {
                    $wentto = $record->nextpage_title . " (id: {$record->nextpage_id})";
                } else {
                    $wentto = get_string('endofescape', 'mod_escape');
                }
                $carry[$take]->jumps[] = array_merge($pagebase, [
                    'went_to' => $wentto,
                    'timeseen' => transform::datetime($record->attempt_timeseen)
                ]);
            }

            return $carry;

        }, function($escapeid, $data) use ($escapeidstocmids) {
            $context = context_module::instance($escapeidstocmids[$escapeid]);
            writer::with_context($context)->export_related_data([], 'attempts', (object) [
                'attempts' => array_values($data)
            ]);
        });
    }

    /**
     * Export all user preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $escapeview = get_user_preferences('escape_view', null, $userid);
        if ($escapeview !== null) {
            $value = $escapeview;

            // The code seems to indicate that there also is the option 'simple', but it's not
            // described nor accessible from anywhere so we won't describe it more than being 'simple'.
            if ($escapeview == 'full') {
                $value = get_string('full', 'mod_escape');
            } else if ($escapeview == 'collapsed') {
                $value = get_string('collapsed', 'mod_escape');
            }

            writer::export_user_preference('mod_escape', 'escape_view', $escapeview,
                get_string('privacy:metadata:userpref:escapeview', 'mod_escape'));
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        if (!$escapeid = static::get_escape_id_from_context($context)) {
            return;
        }

        $DB->delete_records('escape_attempts', ['escapeid' => $escapeid]);
        $DB->delete_records('escape_branch', ['escapeid' => $escapeid]);
        $DB->delete_records('escape_grades', ['escapeid' => $escapeid]);
        $DB->delete_records('escape_timer', ['escapeid' => $escapeid]);
        $DB->delete_records_select('escape_overrides', 'escapeid = :id AND userid IS NOT NULL', ['id' => $escapeid]);

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_escape', 'essay_responses');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $cmids = array_reduce($contextlist->get_contexts(), function($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[] = $context->instanceid;
            }
            return $carry;
        }, []);
        if (empty($cmids)) {
            return;
        }

        // Find the escape IDs.
        $escapeidstocmids = static::get_escape_ids_to_cmids_from_cmids($cmids);
        $escapeids = array_keys($escapeidstocmids);
        if (empty($escapeids)) {
            return;
        }

        // Prepare the SQL we'll need below.
        list($insql, $inparams) = $DB->get_in_or_equal($escapeids, SQL_PARAMS_NAMED);
        $sql = "escapeid $insql AND userid = :userid";
        $params = array_merge($inparams, ['userid' => $userid]);

        // Delete the attempt files.
        $fs = get_file_storage();
        $recordset = $DB->get_recordset_select('escape_attempts', $sql, $params, '', 'id, escapeid');
        foreach ($recordset as $record) {
            $cmid = $escapeidstocmids[$record->escapeid];
            $context = context_module::instance($cmid);
            $fs->delete_area_files($context->id, 'mod_escape', 'essay_responses', $record->id);
        }
        $recordset->close();

        // Delete all the things.
        $DB->delete_records_select('escape_attempts', $sql, $params);
        $DB->delete_records_select('escape_branch', $sql, $params);
        $DB->delete_records_select('escape_grades', $sql, $params);
        $DB->delete_records_select('escape_timer', $sql, $params);
        $DB->delete_records_select('escape_overrides', $sql, $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist    $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $escapeid = static::get_escape_id_from_context($context);
        $userids = $userlist->get_userids();

        if (empty($escapeid)) {
            return;
        }

        // Prepare the SQL we'll need below.
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $sql = "escapeid = :escapeid AND userid {$insql}";
        $params = array_merge($inparams, ['escapeid' => $escapeid]);

        // Delete the attempt files.
        $fs = get_file_storage();
        $recordset = $DB->get_recordset_select('escape_attempts', $sql, $params, '', 'id, escapeid');
        foreach ($recordset as $record) {
            $fs->delete_area_files($context->id, 'mod_escape', 'essay_responses', $record->id);
        }
        $recordset->close();

        // Delete all the things.
        $DB->delete_records_select('escape_attempts', $sql, $params);
        $DB->delete_records_select('escape_branch', $sql, $params);
        $DB->delete_records_select('escape_grades', $sql, $params);
        $DB->delete_records_select('escape_timer', $sql, $params);
        $DB->delete_records_select('escape_overrides', $sql, $params);
    }

    /**
     * Get a survey ID from its context.
     *
     * @param context_module $context The module context.
     * @return int
     */
    protected static function get_escape_id_from_context(context_module $context) {
        $cm = get_coursemodule_from_id('escape', $context->instanceid);
        return $cm ? (int) $cm->instance : 0;
    }

    /**
     * Return a dict of escape IDs mapped to their course module ID.
     *
     * @param array $cmids The course module IDs.
     * @return array In the form of [$escapeid => $cmid].
     */
    protected static function get_escape_ids_to_cmids_from_cmids(array $cmids) {
        global $DB;
        list($insql, $inparams) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $sql = "
            SELECT l.id, cm.id AS cmid
              FROM {escape} l
              JOIN {modules} m
                ON m.name = :escape
              JOIN {course_modules} cm
                ON cm.instance = l.id
               AND cm.module = m.id
             WHERE cm.id $insql";
        $params = array_merge($inparams, ['escape' => 'escape']);
        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Loop and export from a recordset.
     *
     * @param moodle_recordset $recordset The recordset.
     * @param string $splitkey The record key to determine when to export.
     * @param mixed $initial The initial data to reduce from.
     * @param callable $reducer The function to return the dataset, receives current dataset, and the current record.
     * @param callable $export The function to export the dataset, receives the last value from $splitkey and the dataset.
     * @return void
     */
    protected static function recordset_loop_and_export(\moodle_recordset $recordset, $splitkey, $initial,
            callable $reducer, callable $export) {

        $data = $initial;
        $lastid = null;

        foreach ($recordset as $record) {
            if ($lastid && $record->{$splitkey} != $lastid) {
                $export($lastid, $data);
                $data = $initial;
            }
            $data = $reducer($data, $record);
            $lastid = $record->{$splitkey};
        }
        $recordset->close();

        if (!empty($lastid)) {
            $export($lastid, $data);
        }
    }

    /**
     * Transform an attempt.
     *
     * @param stdClass $data Data from the database, as per the exporting method.
     * @param context_module $context The module context.
     * @return array
     */
    protected static function transform_attempt(stdClass $data, context_module $context) {
        global $DB;

        $options = ['context' => $context];
        $answer = $data->attempt_useranswer;
        $response = null;
        $responsefilesfolder = null;

        if ($answer !== null) {
            if ($data->page_qtype == ESCAPE_PAGE_ESSAY) {
                // Essay questions serialise data in the answer field.
                $info = \escape_page_type_essay::extract_useranswer($answer);
                $answer = format_text($info->answer, $info->answerformat, $options);
                if ($info->response !== null) {
                    // We export the files in a subfolder to avoid conflicting files, and tell the user
                    // where those files were exported. That is because we are not using a subfolder for
                    // every single essay response.
                    $responsefilespath = [get_string('privacy:path:essayresponses', 'mod_escape'), $data->attempt_id];
                    $responsefilesfolder = implode('/', $responsefilespath);
                    $response = format_text(
                        writer::with_context($context)->rewrite_pluginfile_urls(
                            $responsefilespath,
                            'mod_escape',
                            'essay_responses',
                            $data->attempt_id,
                            $info->response
                        ),
                        $info->responseformat,
                        $options
                    );
                    writer::with_context($context)->export_area_files($responsefilespath, 'mod_escape',
                        'essay_responses', $data->page_id);
                }

            } else if ($data->page_qtype == ESCAPE_PAGE_MULTICHOICE && $data->page_qoption) {
                // Multiple choice quesitons with multiple answers encode the answers.
                list($insql, $inparams) = $DB->get_in_or_equal(explode(',', $answer), SQL_PARAMS_NAMED);
                $orderby = 'id, ' . $DB->sql_order_by_text('answer') . ', answerformat';
                $records = $DB->get_records_select('escape_answers', "id $insql", $inparams, $orderby);
                $answer = array_values(array_map(function($record) use ($options) {
                    return format_text($record->answer, $record->answerformat, $options);
                }, empty($records) ? [] : $records));

            } else if ($data->page_qtype == ESCAPE_PAGE_MATCHING) {
                // Matching questions need sorting.
                $chosen = explode(',', $answer);
                $answers = $DB->get_records_select('escape_answers', 'pageid = :pageid', ['pageid' => $data->page_id],
                    'id', 'id, answer, answerformat', 2); // The two first entries are not options.
                $i = -1;
                $answer = array_values(array_map(function($record) use (&$i, $chosen, $options) {
                    $i++;
                    return [
                        'label' => format_text($record->answer, $record->answerformat, $options),
                        'matched_with' => array_key_exists($i, $chosen) ? $chosen[$i] : null
                    ];
                }, empty($answers) ? [] : $answers));
            }
        }

        $result = [
            'answer' => $answer,
            'correct' => transform::yesno($data->attempt_correct),
            'timeseen' => transform::datetime($data->attempt_timeseen),
        ];

        if ($response !== null) {
            $result['response'] = $response;
            $result['response_files_folder'] = $responsefilesfolder;
        }

        return $result;
    }

}
