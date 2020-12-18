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
* Sets up the tabs used by the escape pages for teachers.
*
* This file was adapted from the mod/quiz/tabs.php
*
 * @package mod_escape
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
*/

defined('MOODLE_INTERNAL') || die();

/// This file to be included so we can assume config.php has already been included.
global $DB;
if (empty($escape)) {
    print_error('cannotcallscript');
}
if (!isset($currenttab)) {
    $currenttab = '';
}
if (!isset($cm)) {
    $cm = get_coursemodule_from_instance('escape', $escape->id);
    $context = context_module::instance($cm->id);
}
if (!isset($course)) {
    $course = $DB->get_record('course', array('id' => $escape->course));
}

$tabs = $row = $inactive = $activated = array();

/// user attempt count for reports link hover (completed attempts - much faster)
$attemptscount = $DB->count_records('escape_grades', array('escapeid'=>$escape->id));

$row[] = new tabobject('view', "$CFG->wwwroot/mod/escape/view.php?id=$cm->id", get_string('preview', 'escape'), get_string('previewescape', 'escape', format_string($escape->name)));
$row[] = new tabobject('edit', "$CFG->wwwroot/mod/escape/edit.php?id=$cm->id", get_string('edit', 'escape'), get_string('edita', 'moodle', format_string($escape->name)));
if (has_capability('mod/escape:viewreports', $context)) {
    $row[] = new tabobject('reports', "$CFG->wwwroot/mod/escape/report.php?id=$cm->id", get_string('reports', 'escape'),
            get_string('viewreports2', 'escape', $attemptscount));
}
if (has_capability('mod/escape:grade', $context)) {
    $row[] = new tabobject('essay', "$CFG->wwwroot/mod/escape/essay.php?id=$cm->id", get_string('manualgrading', 'escape'));
}

$tabs[] = $row;


switch ($currenttab) {
    case 'reportoverview':
    case 'reportdetail':
    /// sub tabs for reports (overview and detail)
        $inactive[] = 'reports';
        $activated[] = 'reports';

        $row    = array();
        $row[]  = new tabobject('reportoverview', "$CFG->wwwroot/mod/escape/report.php?id=$cm->id&amp;action=reportoverview", get_string('overview', 'escape'));
        $row[]  = new tabobject('reportdetail', "$CFG->wwwroot/mod/escape/report.php?id=$cm->id&amp;action=reportdetail", get_string('detailedstats', 'escape'));
        $tabs[] = $row;
        break;
    case 'collapsed':
    case 'full':
    case 'single':
    /// sub tabs for edit view (collapsed and expanded aka full)
        $inactive[] = 'edit';
        $activated[] = 'edit';

        $row    = array();
        $row[]  = new tabobject('collapsed', "$CFG->wwwroot/mod/escape/edit.php?id=$cm->id&amp;mode=collapsed", get_string('collapsed', 'escape'));
        $row[]  = new tabobject('full', "$CFG->wwwroot/mod/escape/edit.php?id=$cm->id&amp;mode=full", get_string('full', 'escape'));
        $tabs[] = $row;
        break;
}

print_tabs($tabs, $currenttab, $inactive, $activated);
