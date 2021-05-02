<?php
/*
* Added by Madhu 2021 36_ver3
* This lets you delete selected JSON records from user's profile field.
* OK form added to delete selected JSON items.
*/
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

// this POST variable is an array of JSON encoded rows each of which is base64 encoded.
$encoded_rows = $_POST['rowsserialized'];

// this POST variable is the shortname of user profile field corresponding to the JSON column in the SQL records
$shortname_profile_field    = $_POST['shortname_profile_field'];

// This POST variable is the array of original SQL table headings expanded with JSON  ones.
$tablehead_arr = unserialize(base64_decode($_POST['tablehead']));

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
<?php

// print the headings by looping through headings array
foreach ($tablehead_arr as $key => $value) 
{
    ?>
    <th><?php echo htmlspecialchars($value); ?></td>
    <?php
}

?>         
        </tr>
        <tr>
<?php

// print the table data rows

$row_for_form = [];

foreach ($encoded_rows as $encoded_row)
{
    // each item has to base64 decoded to get the JSON encoded array.
    $json_row = base64_decode($encoded_row);

    // This now has to be json decoded into the final associative row array
    $row          = json_decode($json_row, true);

    // prepare array for POSTing to form
    $row_for_form[] = $row;

    if ($shortname_profile_field == 'documentlinks')
    {
        $docid        = $row["fileId"];
        $documentName = format_string($row["documentName"]);
        $docurl       = 'https://drive.google.com/open?id=' . $docid;
        $attrs        = ['alt' => $documentName];
    }

    foreach ($tablehead_arr as $key => $heading) 
    {
        if ($heading == "documentName")
        {
            ?>
                <td><?php echo \html_writer::link($docurl, $documentName, $attrs); ?></td>
            <?php
        }
        else 
        {
            ?>
                <td><?php echo htmlspecialchars($row[$heading]); ?></td>
            <?php
        }
        
    }
    
    ?>
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

        $mform->addElement('hidden', 'serialize_array',          $this->_customdata['serialize_array']);
        $mform->addElement('hidden', 'courseid',                 $this->_customdata['courseid']);
        $mform->addElement('hidden', 'encoded_serialized_table', $this->_customdata['encoded_serialized_table']);
        $mform->addElement('hidden', 'reportid',                 $this->_customdata['reportid']);
        $mform->addElement('hidden', 'shortname_profile_field',  $this->_customdata['shortname_profile_field']);

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
                                            'shortname_profile_field'      => $shortname_profile_field
                                          ]
                                    );

if ($form->is_cancelled()) 
{
    redirect(new \moodle_url('/blocks/configurable_reports/viewreport.php', ['id'       => $_POST['reportid'], 
                                                                             'courseid' => $_POST['reportid']]));
} 
else if ($formdata = $form->get_data()) 
{
    $rows_serialized         = $formdata->serialize_array;

    $shortname_profile_field = $formdata->shortname_profile_field;

    // unserialize to get the array of deletable rows
    $rows = unserialize($rows_serialized);

    foreach ($rows as $row_array)
    {
        // this is the index of searched item in the array. We initialize it.
        $index = null;

        // get user's id. This must exist. If not exit
        $moodleuserid = $row_array["id"];

        // get this user's profile data
        // read in existing data in profile_field documentlinks
        $field                      = $DB->get_record('user_info_field',    array('shortname'  => $shortname_profile_field));
        $user_profile_db            = $DB->get_record('user_info_data',     array(
                                                                                    'userid'   => $moodleuserid,
                                                                                    'fieldid'  => $field->id,
                                                                                    )
                                                        );
        // read in the JSON encoded data or set it to blank if empty. Strip tags
        $userdata_json		= strip_tags(html_entity_decode($user_profile_db->data));
        // decode json string into array. Each sub-array stands for one document record. If fails decode to empty array
        $userdata_arr 		= json_decode($userdata_json, true) ?? [];

        if (empty($userdata_arr)) continue;

        // find index of sub-array in this array that matches with $row
        $index = mysubarray_search($row_array, $userdata_arr);

        //error_log("This is the json array index to be deleted: $doc_index");
        //error_log("This is the corresponding fileID to be deleted:" . $documentlinks_arr[$doc_index]['fileId']);

        if ($index !== false)
        {
            // delete this entry in the array only as search was successfull.
            // if we did not make the check for false, we might unset the wrong item
            unset($userdata_arr[$index]);

            // form new json string of modified array. reindex so indices are not missing due to unset operation
            $user_profile_db->data = json_encode(array_values($userdata_arr));

            // write this back to the user's profile field
            $DB->update_record('user_info_data', $user_profile_db, $bulk=false);

            // finished with this loop iteration, move on to next one
            continue;
        }
        else 
        {
            // did not find a match to delete, log and continue with next item.
            error_log("Did mot find a match to delete");
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

/**
 * @param array:$row_array is the json record as an associative array, that is to be deleted
 * @param array:$userdata_arr is the array of json records, each record being an associative sub-array.
 * @return mixed:$index is the matching array index of the userdata_arr containing the JSON record to be deleted
 * A boolean false is returned if no match is found.
 * We loop through each of the sub-arrays in the userdata_arr. At each loop we convert the sub-array to a JSON string.
 * We check to see if this string is a sub-set of the JSON string of the reocrd to be deleted.
 * Remember that the record to be deleted has extra user information such as name, id, etc. That is why we seek the subset
 * A final trick is that we need to get rid of the "{ start in the JSON sub-array since it won't be there in the target.
*/
function mysubarray_search($row_array, $userdata_arr)
{
    $json_row_arr = json_encode($row_array);

    foreach ($userdata_arr as $index => $sub_array) 
    {
        // remove the 1st 2 characters: "{ from the JSON string that will not have a correspondence in the target
        $json_sub_array = substr(json_encode($sub_array),2);

        // check to see if the JSON sub-array is a sub-set of the JSON record of item to be deleted.
        if (strpos($json_row_arr, $json_sub_array) !== false)
        {
            // found match, so we return this index.
            return $index;
        }
    }
    // did'nt find match
    return false;
}
