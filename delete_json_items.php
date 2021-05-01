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

$PAGE->set_pagelayout('incourse');
$PAGE->set_title('Delete selected JSON data from User profile');
$PAGE->set_heading(format_string($COURSE->fullname));
$PAGE->set_url('/blocks/configurable_reports/delete_json_items.php', ['id'       => $_POST['reportid'], 
                                                                      'courseid' => $_POST['courseid']]);
$PAGE->navbar->add('Delete selected JSON data from User profile');

echo $OUTPUT->header();

// this POST variable is an array of JSON encoded row which in then base64 encoded.
$encoded_rows = $_POST['rowsserialized'];

echo "The following items will be deleted from the corresponding users' profile field";

// we now display a table of such rows to be deleted and ask user permission to delete them or to cancel the operation.
// define table and heading
?>
    <style>
    table {
        border-collapse: collapse;
    }
    th, td {
        border: 1px solid orange;
        padding: 10px;
        text-align: left;
    }
    </style>
    <table style="width:100%">
        <tr>
            <th>username</th>
            <th>fullname</th>
            <th>id</th>
            <th>idnumber</th>
            <th>documentName</th>
            <th>documentlink</th>
        </tr>
<?php

$row_for_form = [];

foreach ($encoded_rows as $encoded_row)
{
    // each item has to base64 decoded to get the JSON encoded array.
    $json_row = base64_decode($encoded_row);

    // This now has to be json decoded into the final associative row array containing associative row data to be deleted
    $row          = json_decode($json_row, true);
    $row_for_form[] = $row;

    $docid        = $row["fileId"];
    $documentName = format_string($row["fileId"]);
    $docurl       = 'https://drive.google.com/open?id=' . $docid;
    $attrs        = ['alt' => $documentName];
    
    ?>
        <tr>
            <td><?php echo htmlspecialchars($row['username']); ?></td>
            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
            <td><?php echo htmlspecialchars($row['id']); ?></td>
            <td><?php echo htmlspecialchars($row['idnumber']); ?></td>
            <td><?php echo htmlspecialchars($row['documentName']); ?></td>
            <td><?php echo \html_writer::link($docurl, $documentName, $attrs); ?></td>
        </tr>
    <?php
}
?>
    </table>
<?php
echo "Confirm or Cancel:";

// encode row_for_form to be transmitted to form
$serialize_array = serialize($row_for_form);

class delete_json_items_form extends moodleform 
{

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

        $mform->addElement('hidden', 'serialize_array',           $this->_customdata['serialize_array']);
        $mform->addElement('hidden', 'courseid',                 $this->_customdata['courseid']);
        $mform->addElement('hidden', 'encoded_serialized_table', $this->_customdata['encoded_serialized_table']);
        $mform->addElement('hidden', 'reportid',                 $this->_customdata['reportid']);

        $buttons = array();
        $buttons[] =& $mform->createElement('submit', 'delete', 'Delete above items');
        $buttons[] =& $mform->createElement('cancel');

        $mform->addGroup($buttons, 'buttons', get_string('actions'), array(' '), false);
    }
}

$form = new \delete_json_items_form(null, [
                                            'serialize_array'              => $serialize_array, 
                                            'encoded_serialized_table'     => $_POST['encoded_serialized_table'],
                                            'courseid'                     => $_POST['courseid'],
                                            'reportid'                     => $_POST['reportid'],
                                          ]
                                    );

if ($form->is_cancelled()) 
{
    redirect(new \moodle_url('/blocks/configurable_reports/viewreport.php', ['id'       => $_POST['reportid'], 
                                                                             'courseid' => $_POST['reportid']]));
} 
else if ($formdata = $form->get_data()) 
{
    $data = $formdata->serialize_array;

    // unserialize to get the array of deletable rows
    $rows = unserialize($data);

    foreach ($rows as $row_array)
    {
        $doc_index = null;

        // get user's id
        $moodleuserid = $row_array["id"];
        $fileId       = $row_array["fileId"];

        // get this user's profile data
        // read in existing data in profile_field documentlinks
        $field                      = $DB->get_record('user_info_field',    array('shortname' => "documentlinks"));
        $user_profile_documentlinks = $DB->get_record('user_info_data',     array(
                                                                                    'userid'   =>  $moodleuserid,
                                                                                    'fieldid'  =>  $field->id,
                                                                                    )
                                                        );
        // read in the JSON encoded data or set it to blank if empty. Strip tags
        $documentlinks_json		= strip_tags(html_entity_decode($user_profile_documentlinks->data));
        // decode json string into array. Each sub-array stands for one document record. If fails decode to empty array
        $documentlinks_arr 		= json_decode($documentlinks_json, true) ?? [];

        if (empty($documentlinks_arr)) continue;

        // find index in this array that matches with fileid of loop. returns 1stmatch if multiple
        $doc_index = array_search($fileId, array_column($documentlinks_arr, 'fileId'));

        //error_log("This is the json array index to be deleted: $doc_index");
        //error_log("This is the corresponding fileID to be deleted:" . $documentlinks_arr[$doc_index]['fileId']);

        if ($a !== false)
        {
            // delete this entry in the array only as search was successfull.
            unset($documentlinks_arr[$doc_index]);

            // form new json string of modified array. reindex so indices are not missing due to unset operation
            $user_profile_documentlinks->data = json_encode(array_values($documentlinks_arr));

            // write this back to the user's profile field
            $DB->update_record('user_info_data', $user_profile_documentlinks, $bulk=false);

            // finished with this loop iteration, move on to next one
            continue;
        }
        else 
        {
            // did not find a match to delete, log and continue with next item.
            error_log("Did mot find a match to delete for user id: $moodleuserid and fileId: $fileId");
        }   
    }
    // After deleting json items... go back to where you came from.
    redirect(new \moodle_url('/blocks/configurable_reports/viewreport.php', ['id'       => $formdata->reportid, 
                                                                             'courseid' => $formdata->courseid]));
}

echo \html_writer::start_tag('div', ['class' => 'no-overflow']);
$form->display();
echo \html_writer::end_tag('div');



echo $OUTPUT->footer();
