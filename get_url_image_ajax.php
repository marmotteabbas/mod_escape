<?php
require_once(dirname(__FILE__) . '/../../config.php');

global $CFG, $DB;
$rec_db_file = $DB->get_record_sql('SELECT * FROM {files} WHERE itemid = '.$_POST["themid"].' && filename !="."');

$url_to_return = $CFG->wwwroot."/draftfile.php/".$rec_db_file->contextid."/".$rec_db_file->component."/".$rec_db_file->filearea."/".$rec_db_file->itemid."/".$rec_db_file->filename;

header("Content-type: text/html; charset=utf-8");
echo $url_to_return;