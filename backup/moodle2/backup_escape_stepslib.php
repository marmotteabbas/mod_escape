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
 * This file contains the backup structure for the escape module
 *
 * This is the "graphical" structure of the escape module:
 *
 *         escape ---------->-------------|------------>---------|----------->----------|
 *      (CL,pk->id)                       |                      |                      |
 *            |                           |                      |                      |
 *            |                     escape_grades           escape_timer           escape_overrides
 *            |            (UL, pk->id,fk->escapeid)  (UL, pk->id,fk->escapeid) (UL, pk->id,fk->escapeid)
 *            |                           |
 *            |                           |
 *            |                           |
 *            |                           |
 *      escape_pages-------->-------escape_branch
 *   (CL,pk->id,fk->escapeid)     (UL, pk->id,fk->pageid)
 *            |
 *            |
 *            |
 *      escape_answers
 *   (CL,pk->id,fk->pageid)
 *            |
 *            |
 *            |
 *      escape_attempts
 *  (UL,pk->id,fk->answerid)
 *
 * Meaning: pk->primary key field of the table
 *          fk->foreign key to link with parent
 *          nt->nested field (recursive data)
 *          CL->course level info
 *          UL->user level info
 *          files->table may have files)
 *
 * @package mod_escape
 * @copyright  2010 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step class that informs a backup task how to backup the escape module.
 *
 * @copyright  2010 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_escape_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // The escape table
        // This table contains all of the goodness for the escape module, quite
        // alot goes into it but nothing relational other than course when will
        // need to be corrected upon restore.
        $escape = new backup_nested_element('escape', array('id'), array(
            'course', 'name', 'intro', 'introformat', 'practice', 'modattempts',
            'usepassword', 'password',
            'dependency', 'conditions', 'grade', 'custom', 'ongoing', 'usemaxgrade',
            'maxanswers', 'maxattempts', 'review', 'nextpagedefault', 'feedback',
            'minquestions', 'maxpages', 'timelimit', 'retake', 'activitylink',
            'mediafile', 'mediaheight', 'mediawidth', 'mediaclose', 'slideshow',
            'width', 'height', 'bgcolor', 'displayleft', 'displayleftif', 'progressbar',
            'available', 'deadline', 'timemodified',
            'completionendreached', 'completiontimespent', 'allowofflineattempts'
        ));

        // The escape_pages table
        // Grouped within a `pages` element, important to note that page is relational
        // to the escape, and also to the previous/next page in the series.
        // Upon restore prevpageid and nextpageid will need to be corrected.
        $pages = new backup_nested_element('pages');
        $page = new backup_nested_element('page', array('id'), array(
            'prevpageid','nextpageid','qtype','qoption','layout',
            'display','timecreated','timemodified','title','contents',
            'contentsformat'
        ));

        // The escape_answers table
        // Grouped within an answers `element`, the escape_answers table relates
        // to the page and escape with `pageid` and `escapeid` that will both need
        // to be corrected during restore.
        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', array('id'), array(
            'jumpto','grade','score','flags','timecreated','timemodified','answer_text',
            'response', 'answerformat', 'responseformat'
        ));
        // Tell the answer element about the answer_text elements mapping to the answer
        // database field.
        $answer->set_source_alias('answer', 'answer_text');

        // The escape_attempts table
        // Grouped by an `attempts` element this is relational to the page, escape,
        // and user.
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', array('id'), array(
            'userid','retry','correct','useranswer','timeseen'
        ));

        // The escape_branch table
        // Grouped by a `branch` element this is relational to the page, escape,
        // and user.
        $branches = new backup_nested_element('branches');
        $branch = new backup_nested_element('branch', array('id'), array(
             'userid', 'retry', 'flag', 'timeseen', 'nextpageid'
        ));

        // The escape_grades table
        // Grouped by a grades element this is relational to the escape and user.
        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element('grade', array('id'), array(
            'userid','grade','late','completed'
        ));

        // The escape_timer table
        // Grouped by a `timers` element this is relational to the escape and user.
        $timers = new backup_nested_element('timers');
        $timer = new backup_nested_element('timer', array('id'), array(
            'userid', 'starttime', 'escapetime', 'completed', 'timemodifiedoffline'
        ));

        $overrides = new backup_nested_element('overrides');
        $override = new backup_nested_element('override', array('id'), array(
            'groupid', 'userid', 'available', 'deadline', 'timelimit',
            'review', 'maxattempts', 'retake', 'password'));

        // Now that we have all of the elements created we've got to put them
        // together correctly.
        $escape->add_child($pages);
        $pages->add_child($page);
        $page->add_child($answers);
        $answers->add_child($answer);
        $answer->add_child($attempts);
        $attempts->add_child($attempt);
        $page->add_child($branches);
        $branches->add_child($branch);
        $escape->add_child($grades);
        $grades->add_child($grade);
        $escape->add_child($timers);
        $timers->add_child($timer);
        $escape->add_child($overrides);
        $overrides->add_child($override);

        // Set the source table for the elements that aren't reliant on the user
        // at this point (escape, escape_pages, escape_answers)
        $escape->set_source_table('escape', array('id' => backup::VAR_ACTIVITYID));
        //we use SQL here as it must be ordered by prevpageid so that restore gets the pages in the right order.
        $page->set_source_table('escape_pages', array('escapeid' => backup::VAR_PARENTID), 'prevpageid ASC');

        // We use SQL here as answers must be ordered by id so that the restore gets them in the right order
        $answer->set_source_table('escape_answers', array('pageid' => backup::VAR_PARENTID), 'id ASC');

        // Escape overrides to backup are different depending of user info.
        $overrideparams = array('escapeid' => backup::VAR_PARENTID);

        // Check if we are also backing up user information
        if ($this->get_setting_value('userinfo')) {
            // Set the source table for elements that are reliant on the user
            // escape_attempts, escape_branch, escape_grades, escape_timer.
            $attempt->set_source_table('escape_attempts', array('answerid' => backup::VAR_PARENTID));
            $branch->set_source_table('escape_branch', array('pageid' => backup::VAR_PARENTID));
            $grade->set_source_table('escape_grades', array('escapeid'=>backup::VAR_PARENTID));
            $timer->set_source_table('escape_timer', array('escapeid' => backup::VAR_PARENTID));
        } else {
            $overrideparams['userid'] = backup_helper::is_sqlparam(null); //  Without userinfo, skip user overrides.
        }

        $override->set_source_table('escape_overrides', $overrideparams);

        // Annotate the user id's where required.
        $attempt->annotate_ids('user', 'userid');
        $branch->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'userid');
        $timer->annotate_ids('user', 'userid');
        $override->annotate_ids('user', 'userid');
        $override->annotate_ids('group', 'groupid');

        // Annotate the file areas in user by the escape module.
        $escape->annotate_files('mod_escape', 'intro', null);
        $escape->annotate_files('mod_escape', 'mediafile', null);
        $page->annotate_files('mod_escape', 'page_contents', 'id');
        $answer->annotate_files('mod_escape', 'page_answers', 'id');
        $answer->annotate_files('mod_escape', 'page_responses', 'id');
        $attempt->annotate_files('mod_escape', 'essay_responses', 'id');

        // Prepare and return the structure we have just created for the escape module.
        return $this->prepare_activity_structure($escape);
    }
}
