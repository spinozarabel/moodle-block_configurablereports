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

// OK form added to delete selected JSON items.
require_once('../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
global $PAGE, $USER, $DB, $COURSE;
$context = context_course::instance($COURSE->id);
$PAGE->set_context($context);

if (!has_capability('block/configurable_reports:managereports', $context) && !has_capability('block/configurable_reports:manageownreports', $context)) {
    print_error('badpermissions');
}

class delete_json_items_form extends moodleform {

    public function definition() {
        global $COURSE;

        $mform =& $this->_form;
        $context = \context_course::instance($COURSE->id);
        $editoroptions = [
            'trusttext' => true,
            'subdirs' => true,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context' => $context
        ];

        $mform->addElement('hidden', 'fileids',                  $this->_customdata['fileids']);
        $mform->addElement('hidden', 'courseid',                 $this->_customdata['courseid']);
        $mform->addElement('hidden', 'encoded_serialized_table', $this->_customdata['encoded_serialized_table']);

        $buttons = array();
        $buttons[] =& $mform->createElement('submit', 'delete', get_string('delete_json_items', 'block_configurable_reports'));
        $buttons[] =& $mform->createElement('cancel');

        $mform->addGroup($buttons, 'buttons', get_string('actions'), array(' '), false);
    }
}

$form = new \sendemail_form(null, ['fileids'                    => $_POST['fileids'], 
                                   'encoded_serialized_table'   => $_POST['encoded_serialized_table'],
                                   'courseid'                   => $_POST['courseid']]);

if ($form->is_cancelled()) 
{
    redirect(new \moodle_url('/course/view.php?id='.$data->courseid));
} else if ($data = $form->get_data()) 
{
    // get data of selected fileid's to be deleted from submitted form. This is turn is derived from POST cr_print_table()
    $fileids_array = unserialize(base64_decode($data->fileids));
    // get data of the full table as an array from submitted form. This is turn is derived from POST cr_print_table()
    $table_array   = unserialize(base64_decode($data->encoded_serialized_table));
    error_log("In program delete_json_items.php - Print out of table array:");
    error_log(print_r($table_array, true));

    foreach ($fileids_array as $fileid) 
    {
        // get array containing all records from table with this fileid
        $keys = array_keys(array_combine(array_keys($table_array), array_column($table_array, 'fileId')),$fileid);
        error_log("In loop foreach delete_json_items.php - Print out of matching keys for fileid: $fileid");
        error_log(print_r($keys, true));
    }
    // After emails were sent... go back to where you came from.
    redirect(new \moodle_url('/course/view.php?id='.$data->courseid));
}

$PAGE->set_title(get_string('confirmation', 'block_configurable_reports'));
$PAGE->set_heading(format_string($COURSE->fullname));
$PAGE->navbar->add(get_string('confirm or cancel'));

echo $OUTPUT->header();

echo \html_writer::start_tag('div', ['class' => 'no-overflow']);
$form->display();
echo \html_writer::end_tag('div');

echo $OUTPUT->footer();
