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
 * Moodle renderer used to display special elements of the escape module
 *
 * @package mod_escape
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

class mod_escape_renderer extends plugin_renderer_base {
    /**
     * Returns the header for the escape module
     *
     * @param escape $escape a escape object.
     * @param string $currenttab current tab that is shown.
     * @param bool   $extraeditbuttons if extra edit buttons should be displayed.
     * @param int    $escapepageid id of the escape page that needs to be displayed.
     * @param string $extrapagetitle String to appent to the page title.
     * @return string
     */
    public function header($escape, $cm, $currenttab = '', $extraeditbuttons = false, $escapepageid = null, $extrapagetitle = null) {
        global $CFG;

        $activityname = format_string($escape->name, true, $escape->course);
        if (empty($extrapagetitle)) {
            $title = $this->page->course->shortname.": ".$activityname;
        } else {
            $title = $this->page->course->shortname.": ".$activityname.": ".$extrapagetitle;
        }

        // Build the buttons
        $context = context_module::instance($cm->id);

    /// Header setup
        $this->page->set_title($title);
        $this->page->set_heading($this->page->course->fullname);
        escape_add_header_buttons($cm, $context, $extraeditbuttons, $escapepageid);
        $output = $this->output->header();

        if (has_capability('mod/escape:manage', $context)) {
            $output .= $this->output->heading_with_help($activityname, 'overview', 'escape');

            if (!empty($currenttab)) {
                ob_start();
                include($CFG->dirroot.'/mod/escape/tabs.php');
                $output .= ob_get_contents();
                ob_end_clean();
            }
        } else {
            $output .= $this->output->heading($activityname);
        }

        foreach ($escape->messages as $message) {
            $output .= $this->output->notification($message[0], $message[1], $message[2]);
        }

        return $output;
    }

    /**
     * Returns the footer
     * @return string
     */
    public function footer() {
        return $this->output->footer();
    }

    /**
     * Returns HTML for a escape inaccessible message
     *
     * @param string $message
     * @return <type>
     */
    public function escape_inaccessible($message) {
        global $CFG;
        $output  =  $this->output->box_start('generalbox boxaligncenter');
        $output .=  $this->output->box_start('center');
        $output .=  $message;
        $output .=  $this->output->box('<a href="'.$CFG->wwwroot.'/course/view.php?id='. $this->page->course->id .'">'. get_string('returnto', 'escape', format_string($this->page->course->fullname, true)) .'</a>', 'escapebutton standardbutton');
        $output .=  $this->output->box_end();
        $output .=  $this->output->box_end();
        return $output;
    }

    /**
     * Returns HTML to prompt the user to log in
     * @param escape $escape
     * @param bool $failedattempt
     * @return string
     */
    public function login_prompt(escape $escape, $failedattempt = false) {
        global $CFG;
        $output  = $this->output->box_start('password-form');
        $output .= $this->output->box_start('generalbox boxaligncenter');
        $output .=  '<form id="password" method="post" action="'.$CFG->wwwroot.'/mod/escape/view.php" autocomplete="off">';
        $output .=  '<fieldset class="invisiblefieldset center">';
        $output .=  '<input type="hidden" name="id" value="'. $this->page->cm->id .'" />';
        $output .=  '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        if ($failedattempt) {
            $output .=  $this->output->notification(get_string('loginfail', 'escape'));
        }
        $output .= get_string('passwordprotectedescape', 'escape', format_string($escape->name)).'<br /><br />';
        $output .= get_string('enterpassword', 'escape')." <input type=\"password\" name=\"userpassword\" /><br /><br />";
        $output .= "<div class='escapebutton standardbutton submitbutton'><input type='submit' value='".get_string('continue', 'escape')."' /></div>";
        $output .= " <div class='escapebutton standardbutton submitbutton'><input type='submit' name='backtocourse' value='".get_string('cancel', 'escape')."' /></div>";
        $output .=  '</fieldset></form>';
        $output .=  $this->output->box_end();
        $output .=  $this->output->box_end();
        return $output;
    }

    /**
     * Returns HTML to display dependancy errors
     *
     * @param object $dependentescape
     * @param array $errors
     * @return string
     */
    public function dependancy_errors($dependentescape, $errors) {
        $output  = $this->output->box_start('generalbox boxaligncenter');
        $output .= get_string('completethefollowingconditions', 'escape', $dependentescape->name);
        $output .= $this->output->box(implode('<br />'.get_string('and', 'escape').'<br />', $errors),'center');
        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Returns HTML to display a message
     * @param string $message
     * @param single_button $button
     * @return string
     */
    public function message($message, single_button $button = null) {
        $output  = $this->output->box_start('generalbox boxaligncenter');
        $output .= $message;
        if ($button !== null) {
            $output .= $this->output->box($this->output->render($button), 'escapebutton standardbutton');
        }
        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Returns HTML to display a continue button
     * @param escape $escape
     * @param int $lastpageseen
     * @return string
     */
    public function continue_links(escape $escape, $lastpageseenid) {
        global $CFG;
        $output = $this->output->box(get_string('youhaveseen','escape'), 'generalbox boxaligncenter');
        $output .= $this->output->box_start('center');

        $yeslink = html_writer::link(new moodle_url('/mod/escape/view.php', array('id' => $this->page->cm->id,
            'pageid' => $lastpageseenid, 'startlastseen' => 'yes')), get_string('yes'), array('class' => 'btn btn-primary'));
        $output .= html_writer::tag('span', $yeslink, array('class'=>'escapebutton standardbutton'));
        $output .= '&nbsp;';

        $nolink = html_writer::link(new moodle_url('/mod/escape/view.php', array('id' => $this->page->cm->id,
            'pageid' => $escape->firstpageid, 'startlastseen' => 'no')), get_string('no'), array('class' => 'btn btn-secondary'));
        $output .= html_writer::tag('span', $nolink, array('class'=>'escapebutton standardbutton'));

        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Returns HTML to display a page to the user
     * @param escape $escape
     * @param escape_page $page
     * @param object $attempt
     * @return string
     */
    public function display_page(escape $escape, escape_page $page, $attempt) {
        // We need to buffer here as there is an mforms display call
        ob_start();
        echo $page->display($this, $attempt);
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
     * Returns HTML to display a collapsed edit form
     *
     * @param escape $escape
     * @param int $pageid
     * @return string
     */
    public function display_edit_collapsed(escape $escape, $pageid) {
        global $DB, $CFG;

        $manager = escape_page_type_manager::get($escape);
        $qtypes = $manager->get_page_type_strings();
        $npages = count($escape->load_all_pages());

        $table = new html_table();
        $table->head = array(get_string('pagetitle', 'escape'), get_string('qtype', 'escape'), get_string('jumps', 'escape'), get_string('actions', 'escape'));
        $table->align = array('left', 'left', 'left', 'center');
        $table->wrap = array('', 'nowrap', '', 'nowrap');
        $table->tablealign = 'center';
        $table->cellspacing = 0;
        $table->cellpadding = '2px';
        $table->width = '80%';
        $table->data = array();

        $canedit = has_capability('mod/escape:edit', context_module::instance($this->page->cm->id));

        while ($pageid != 0) {
            $page = $escape->load_page($pageid);
            $data = array();
            $url = new moodle_url('/mod/escape/edit.php', array(
                'id'     => $this->page->cm->id,
                'mode'   => 'single',
                'pageid' => $page->id
            ));
            $data[] = html_writer::link($url, format_string($page->title, true), array('id' => 'escape-' . $page->id));
            $data[] = $qtypes[$page->qtype];
            $data[] = implode("<br />\n", $page->jumps);
            if ($canedit) {
                $data[] = $this->page_action_links($page, $npages, true);
            } else {
                $data[] = '';
            }
            $table->data[] = $data;
            $pageid = $page->nextpageid;
        }

        return html_writer::table($table);
    }

    /**
     * Returns HTML to display the full edit page
     *
     * @param escape $escape
     * @param int $pageid
     * @param int $prevpageid
     * @param bool $single
     * @return string
     */
    public function display_edit_full(escape $escape, $pageid, $prevpageid, $single=false) {
        global $DB, $CFG;

        $manager = escape_page_type_manager::get($escape);
        $qtypes = $manager->get_page_type_strings();
        $npages = count($escape->load_all_pages());
        $canedit = has_capability('mod/escape:edit', context_module::instance($this->page->cm->id));

        $content = '';
        if ($canedit) {
            $content = $this->add_page_links($escape, $prevpageid);
        }

        $options = new stdClass;
        $options->noclean = true;

        while ($pageid != 0 && $single!=='stop') {
            $page = $escape->load_page($pageid);

            $pagetable = new html_table();
            $pagetable->align = array('right','left');
            $pagetable->width = '100%';
            $pagetable->tablealign = 'center';
            $pagetable->cellspacing = 0;
            $pagetable->cellpadding = '5px';
            $pagetable->data = array();

            $pageheading = new html_table_cell();

            $pageheading->text = html_writer::tag('a', '', array('id' => 'escape-' . $pageid)) . format_string($page->title);
            if ($canedit) {
                $pageheading->text .= ' '.$this->page_action_links($page, $npages);
            }
            $pageheading->style = 'text-align:center';
            $pageheading->colspan = 2;
            $pageheading->scope = 'col';
            $pagetable->head = array($pageheading);

            $cell = new html_table_cell();
            $cell->colspan = 2;
            $cell->style = 'text-align:left';
            $cell->text = $page->contents;
            $pagetable->data[] = new html_table_row(array($cell));

            $cell = new html_table_cell();
            $cell->colspan = 2;
            $cell->style = 'text-align:center';
            $cell->text = '<strong>'.$qtypes[$page->qtype] . $page->option_description_string().'</strong>';
            $pagetable->data[] = new html_table_row(array($cell));

            $pagetable = $page->display_answers($pagetable);

            $content .= html_writer::start_tag('div');
            $content .= html_writer::table($pagetable);
            $content .= html_writer::end_tag('div');

            if ($canedit) {
                $content .= $this->add_page_links($escape, $pageid);
            }

            // check the prev links - fix (silently) if necessary - there was a bug in
            // versions 1 and 2 when add new pages. Not serious then as the backwards
            // links were not used in those versions
            if ($page->prevpageid != $prevpageid) {
                // fix it
                $DB->set_field("escape_pages", "prevpageid", $prevpageid, array("id" => $page->id));
                debugging("<p>***prevpageid of page $page->id set to $prevpageid***");
            }

            $prevpageid = $page->id;
            $pageid = $page->nextpageid;

            if ($single === true) {
                $single = 'stop';
            }

        }

        return $this->output->box($content, 'edit_pages_box');
    }

    /**
     * Returns HTML to display the add page links
     *
     * @param escape $escape
     * @param int $prevpageid
     * @return string
     */
    public function add_page_links(escape $escape, $prevpageid=false) {
        global $CFG;

        $links = array();

        $importquestionsurl = new moodle_url('/mod/escape/import.php',array('id'=>$this->page->cm->id, 'pageid'=>$prevpageid));
        $links[] = html_writer::link($importquestionsurl, get_string('importquestions', 'escape'));

        $manager = escape_page_type_manager::get($escape);
        foreach($manager->get_add_page_type_links($prevpageid) as $link) {
            $links[] = html_writer::link($link['addurl'], $link['name']);
        }

        $addquestionurl = new moodle_url('/mod/escape/editpage.php', array('id'=>$this->page->cm->id, 'pageid'=>$prevpageid));
        $links[] = html_writer::link($addquestionurl, get_string('addaquestionpagehere', 'escape'));

        return $this->output->box(implode(" | \n", $links), 'addlinks');
    }

    /**
     * Return HTML to display add first page links
     * @param escape $escape
     * @return string
     */
    public function add_first_page_links(escape $escape) {
        global $CFG;
        $prevpageid = 0;

        $output = $this->output->heading(get_string("whatdofirst", "escape"), 3);
        $links = array();

        $importquestionsurl = new moodle_url('/mod/escape/import.php',array('id'=>$this->page->cm->id, 'pageid'=>$prevpageid));
        $links[] = html_writer::link($importquestionsurl, get_string('importquestions', 'escape'));

        $manager = escape_page_type_manager::get($escape);
        foreach ($manager->get_add_page_type_links($prevpageid) as $link) {
            $link['addurl']->param('firstpage', 1);
            $links[] = html_writer::link($link['addurl'], $link['name']);
        }

        $addquestionurl = new moodle_url('/mod/escape/editpage.php', array('id'=>$this->page->cm->id, 'pageid'=>$prevpageid, 'firstpage'=>1));
        $links[] = html_writer::link($addquestionurl, get_string('addaquestionpage', 'escape'));

        return $this->output->box($output.'<p>'.implode('</p><p>', $links).'</p>', 'generalbox firstpageoptions');
    }

    /**
     * Returns HTML to display action links for a page
     *
     * @param escape_page $page
     * @param bool $printmove
     * @param bool $printaddpage
     * @return string
     */
    public function page_action_links(escape_page $page, $printmove, $printaddpage=false) {
        global $CFG;

        $actions = array();

        if ($printmove) {
            $url = new moodle_url('/mod/escape/escape.php',
                    array('id' => $this->page->cm->id, 'action' => 'move', 'pageid' => $page->id, 'sesskey' => sesskey()));
            $label = get_string('movepagenamed', 'escape', format_string($page->title));
            $img = $this->output->pix_icon('t/move', $label);
            $actions[] = html_writer::link($url, $img, array('title' => $label));
        }
        $url = new moodle_url('/mod/escape/editpage.php', array('id' => $this->page->cm->id, 'pageid' => $page->id, 'edit' => 1));
        $label = get_string('updatepagenamed', 'escape', format_string($page->title));
        $img = $this->output->pix_icon('t/edit', $label);
        $actions[] = html_writer::link($url, $img, array('title' => $label));

        // Duplicate action.
        $url = new moodle_url('/mod/escape/escape.php', array('id' => $this->page->cm->id, 'pageid' => $page->id,
                'action' => 'duplicate', 'sesskey' => sesskey()));
        $label = get_string('duplicatepagenamed', 'escape', format_string($page->title));
        $img = $this->output->pix_icon('e/copy', $label, 'mod_escape');
        $actions[] = html_writer::link($url, $img, array('title' => $label));

        $url = new moodle_url('/mod/escape/view.php', array('id' => $this->page->cm->id, 'pageid' => $page->id));
        $label = get_string('previewpagenamed', 'escape', format_string($page->title));
        $img = $this->output->pix_icon('t/preview', $label);
        $actions[] = html_writer::link($url, $img, array('title' => $label));

        $url = new moodle_url('/mod/escape/escape.php',
                array('id' => $this->page->cm->id, 'action' => 'confirmdelete', 'pageid' => $page->id, 'sesskey' => sesskey()));
        $label = get_string('deletepagenamed', 'escape', format_string($page->title));
        $img = $this->output->pix_icon('t/delete', $label);
        $actions[] = html_writer::link($url, $img, array('title' => $label));

        if ($printaddpage) {
            $options = array();
            $manager = escape_page_type_manager::get($page->escape);
            $links = $manager->get_add_page_type_links($page->id);
            foreach ($links as $link) {
                $options[$link['type']] = $link['name'];
            }
            $options[0] = get_string('addaquestionpage', 'escape');

            $addpageurl = new moodle_url('/mod/escape/editpage.php', array('id'=>$this->page->cm->id, 'pageid'=>$page->id, 'sesskey'=>sesskey()));
            $addpageselect = new single_select($addpageurl, 'qtype', $options, null, array(''=>get_string('addanewpage', 'escape').'...'), 'addpageafter'.$page->id);
            $addpageselector = $this->output->render($addpageselect);
        }

        if (isset($addpageselector)) {
            $actions[] = $addpageselector;
        }

        return implode(' ', $actions);
    }

    /**
     * Prints the on going message to the user.
     *
     * With custom grading On, displays points
     * earned out of total points possible thus far.
     * With custom grading Off, displays number of correct
     * answers out of total attempted.
     *
     * @param object $escape The escape that the user is taking.
     * @return void
     **/

     /**
      * Prints the on going message to the user.
      *
      * With custom grading On, displays points
      * earned out of total points possible thus far.
      * With custom grading Off, displays number of correct
      * answers out of total attempted.
      *
      * @param escape $escape
      * @return string
      */
    public function ongoing_score(escape $escape) {
        return $this->output->box($escape->get_ongoing_score_message(), "ongoing center");
    }

    /**
     * Returns HTML to display a progress bar of progression through a escape
     *
     * @param escape $escape
     * @param int $progress optional, if empty it will be calculated
     * @return string
     */
    public function progress_bar(escape $escape, $progress = null) {
        $context = context_module::instance($this->page->cm->id);

        // escape setting to turn progress bar on or off
        if (!$escape->progressbar) {
            return '';
        }

        // catch teachers
        if (has_capability('mod/escape:manage', $context)) {
            return $this->output->notification(get_string('progressbarteacherwarning2', 'escape'));
        }

        if ($progress === null) {
            $progress = $escape->calculate_progress();
        }

        $content = html_writer::start_tag('div');
        $content .= html_writer::start_tag('div', array('class' => 'progress'));
        $content .= html_writer::start_tag('div', array('class' => 'progress-bar bar', 'role' => 'progressbar',
            'style' => 'width: ' . $progress .'%', 'aria-valuenow' => $progress, 'aria-valuemin' => 0, 'aria-valuemax' => 100));
        $content .= $progress . "%";
        $content .= html_writer::end_tag('div');
        $content .= html_writer::end_tag('div');
        $printprogress = html_writer::tag('div', get_string('progresscompleted', 'escape', $progress) . $content);
        return $this->output->box($printprogress, 'progress_bar');
    }

    /**
     * Returns HTML to show the start of a slideshow
     * @param escape $escape
     */
    public function slideshow_start(escape $escape) {
        $attributes = array();
        $attributes['class'] = 'slideshow';
        $attributes['style'] = 'background-color:'.$escape->properties()->bgcolor.';height:'.
                $escape->properties()->height.'px;width:'.$escape->properties()->width.'px;';
        $output = html_writer::start_tag('div', $attributes);
        return $output;
    }
    /**
     * Returns HTML to show the end of a slideshow
     */
    public function slideshow_end() {
        $output = html_writer::end_tag('div');
        return $output;
    }
    /**
     * Returns a P tag containing contents
     * @param string $contents
     * @param string $class
     */
    public function paragraph($contents, $class='') {
        $attributes = array();
        if ($class !== '') {
            $attributes['class'] = $class;
        }
        $output = html_writer::tag('p', $contents, $attributes);
        return $output;
    }

    /**
     * Returns the HTML for displaying the end of escape page.
     *
     * @param  escape $escape escape instance
     * @param  stdclass $data escape data to be rendered
     * @return string         HTML contents
     */
    public function display_eol_page(escape $escape, $data) {

        $output = '';
        $canmanage = $escape->can_manage();
        $course = $escape->courserecord;

        if ($escape->custom && !$canmanage && (($data->gradeinfo->nquestions < $escape->minquestions))) {
            $output .= $this->box_start('generalbox boxaligncenter');
        }

        if ($data->gradeescape) {
            // We are using level 3 header because the page title is a sub-heading of escape title (MDL-30911).
            $output .= $this->heading(get_string("congratulations", "escape"), 3);
            $output .= $this->box_start('generalbox boxaligncenter');
        }

        if ($data->notenoughtimespent !== false) {
            $output .= $this->paragraph(get_string("notenoughtimespent", "escape", $data->notenoughtimespent), 'center');
        }

        if ($data->numberofpagesviewed !== false) {
            $output .= $this->paragraph(get_string("numberofpagesviewed", "escape", $data->numberofpagesviewed), 'center');
        }
        if ($data->youshouldview !== false) {
            $output .= $this->paragraph(get_string("youshouldview", "escape", $data->youshouldview), 'center');
        }
        if ($data->numberofcorrectanswers !== false) {
            $output .= $this->paragraph(get_string("numberofcorrectanswers", "escape", $data->numberofcorrectanswers), 'center');
        }

        if ($data->displayscorewithessays !== false) {
            $output .= $this->box(get_string("displayscorewithessays", "escape", $data->displayscorewithessays), 'center');
        } else if ($data->displayscorewithoutessays !== false) {
            $output .= $this->box(get_string("displayscorewithoutessays", "escape", $data->displayscorewithoutessays), 'center');
        }

        if ($data->yourcurrentgradeisoutof !== false) {
            $output .= $this->paragraph(get_string("yourcurrentgradeisoutof", "escape", $data->yourcurrentgradeisoutof), 'center');
        }
        if ($data->eolstudentoutoftimenoanswers !== false) {
            $output .= $this->paragraph(get_string("eolstudentoutoftimenoanswers", "escape"));
        }
        if ($data->welldone !== false) {
            $output .= $this->paragraph(get_string("welldone", "escape"));
        }

        if ($data->progresscompleted !== false) {
            $output .= $this->progress_bar($escape, $data->progresscompleted);
        }

        if ($data->displayofgrade !== false) {
            $output .= $this->paragraph(get_string("displayofgrade", "escape"), 'center');
        }

        $output .= $this->box_end(); // End of Escape button to Continue.

        if ($data->reviewescape !== false) {
            $output .= html_writer::link($data->reviewescape, get_string('reviewescape', 'escape'), array('class' => 'centerpadded escapebutton standardbutton p-r-1'));
        }
        if ($data->modattemptsnoteacher !== false) {
            $output .= $this->paragraph(get_string("modattemptsnoteacher", "escape"), 'centerpadded');
        }

        if ($data->activitylink !== false) {
            $output .= $data->activitylink;
        }

        $url = new moodle_url('/course/view.php', array('id' => $course->id));
        $output .= html_writer::link($url, get_string('returnto', 'escape', format_string($course->fullname, true)),
                array('class' => 'centerpadded escapebutton standardbutton p-r-1'));

        if (has_capability('gradereport/user:view', context_course::instance($course->id))
                && $course->showgrades && $escape->grade != 0 && !$escape->practice) {
            $url = new moodle_url('/grade/index.php', array('id' => $course->id));
            $output .= html_writer::link($url, get_string('viewgrades', 'escape'),
                array('class' => 'centerpadded escapebutton standardbutton p-r-1'));
        }
        return $output;
    }
}
