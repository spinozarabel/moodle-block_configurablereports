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
        $mform->addElement('hidden', 'reportid',                  $this->_customdata['reportid']);

        $buttons = array();
        $buttons[] =& $mform->createElement('submit', 'delete', get_string('delete_json_items', 'block_configurable_reports'));
        $buttons[] =& $mform->createElement('cancel');

        $mform->addGroup($buttons, 'buttons', get_string('actions'), array(' '), false);
    }
}

$form = new \delete_json_items_form(null, ['fileids'            => implode(',', $_POST['fileids']), 
                                   'encoded_serialized_table'   => $_POST['encoded_serialized_table'],
                                   'courseid'                   => $_POST['courseid'],
                                   'reportid'                   => $_POST['reportid']]);

if ($form->is_cancelled()) 
{
    redirect(new \moodle_url('/blocks/configurable_reports/viewreport.php', ['id'       => $_POST['reportid'], 
                                                                             'courseid' => $_POST['reportid']]));
} else if ($data = $form->get_data()) 
{
    // get data of selected fileid's to be deleted from submitted form. This is turn is derived from POST cr_print_table()
    $fileids_array = explode(',', $data->fileids);
    // get data of the full table as an array from submitted form. This is turn is derived from POST cr_print_table()
    $rawtable      = unserialize(base64_decode($data->encoded_serialized_table));
    $table_array   = $rawtable["data"];
    // get heading array
    $heading_array = $rawtable["head"];
    // findout the colindex containing "fileId" in heading array
    $col_index  = array_search('fileId', $heading_array);
    // findout index conatining 'id' in heading array
    $id_index   = array_search('id', $heading_array);

    foreach ($fileids_array as $fileid) 
    {
        // get array containing all records from table with this fileid. Can be multiple records.
        $keys = searchForKeysInArray($fileid, $table_array, $col_index);

        if (empty($keys)) continue;

        // we are sure that keys array has entries at this point
        foreach ($keys as $table_index) 
        {
            // get user's id
            $moodleuserid = $table_array[$table_index][$id_index];

            // get this user's profile data
            // read in existing data in profile_field documentlinks
			$field                      = $DB->get_record('user_info_field',    array('shortname' => "documentlinks"));
			$user_profile_documentlinks = $DB->get_record('user_info_data',     array(
                                                                                        'userid'   =>  $moodleuserid,
                                                                                        'fieldid'  =>  $field->id,
                                                                                      )
												          );
            // read in the JSON encoded data or set it to blank if empty
			$documentlinks_json		= $user_profile_documentlinks->data ?? "";
			// decode json string into array. Each sub-array stands for one document record. If fails decode to empty array
			$documentlinks_arr 		= json_decode($documentlinks_json, true) ?? [];
            // find index in this array that matches with fileid of loop. 1st one if multiple values
            $doc_index = array_search($fileid, $documentlinks_arr);
            // delete this entry in the array
            unset($documentlinks_arr[$doc_index]);
            // form new json string of modified array. reindex so indices are not missing due to unset operation
            $user_profile_documentlinks->data = json_encode(array_values($documentlinks_arr));
            // write this back to the user's profile field
            $DB->update_record('user_info_data', $user_profile_documentlinks, $bulk=false);
        }
      
    }
    // After deleting json items... go back to where you came from.
    redirect(new \moodle_url('/blocks/configurable_reports/viewreport.php', ['id'       => $data->reportid, 
                                                                             'courseid' => $data->courseid]));
}

$PAGE->set_title(get_string('confirmation', 'block_configurable_reports'));
$PAGE->set_heading(format_string($COURSE->fullname));
$PAGE->navbar->add(get_string('confirm or cancel'));

echo $OUTPUT->header();

echo \html_writer::start_tag('div', ['class' => 'no-overflow']);
$form->display();
echo \html_writer::end_tag('div');

echo $OUTPUT->footer();

function searchForKeysInArray($needle, $haystack, $col_index) 
{
    $keys = null;
    foreach ($haystack as $key => $sub_array) 
    {
        if ($sub_array[$col_index] === $needle) 
        {
            $keys[] = $key;
        }
    }
    return $keys;
 }
