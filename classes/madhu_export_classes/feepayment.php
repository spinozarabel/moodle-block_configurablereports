<?php
/* by Madhu Avasarala 13/08/2021
* ver 1.0
*/

namespace block_configurable_reports\madhu_export_classes;

// if directly called die. Use standard WP and Moodle practices
defined('MOODLE_INTERNAL') || die('direct access to this file is not permitted');

// class definition begins
class feepayment
{

    public function __construct($report,   
                                $verbose                    = true, 
                                $simulation                 = false 
                                )
    {
        $this->verbose                  = $verbose;

        $this->simulation               = $simulation;

        $this->report = $report;

        // read in configuration settings and set them as properties to this
        $this->get_config();

        // matrix is record rows as an array. It is not yet associative
        $matrix  = $this->get_report_matrix();

        // get working copy before manipulation
        $matrix_associative = $matrix;

        // transform copy into associative
        array_walk($matrix_associative, function(&$a) use ($matrix_associative)
		{
			$a = array_combine($matrix_associative[0], $a);
		});
	    array_shift($matrix_associative); # remove column header

        // write back associative matrix as property to this object
        $this->matrix_associative = $matrix_associative;

        // get the fees_csv associative array read in from the google sheet
        $this->fees_csv = $this->csvfile_to_associative_array($this->googlesheeturl);
    }

    private function get_config()
    {
        // flag to update user profile field or not, with possible new data
	    $this->update_profile_fees      = get_config('block_configurable_reports', 'update_profile_fees')      ?? false;

        // Overwrite even if array exists for concerned academic year
	    $this->overwrite_existing_fees  = get_config('block_configurable_reports', 'overwrite_existing_fees')  ?? true;

        // Read the CSv published Google Sheet, get its URL from config settings
	    $this->googlesheeturl           = get_config('block_configurable_reports', 'googlesheeturl');

        if (empty($this->googlesheeturl))
        {
            echo nl2br("Empty config setting for Google Published CSV file URL, please set in config: "  . "\n");
            error_log("Empty config setting for Google Published CSV file URL in plugin configurable_reports, please set in config");
        }

         
    }


    public function get_report_matrix ()
    {
        $table      = $this->report->table;
        $matrix     = array();
        $filename   = 'report';
        $accounts   = array();

        if (!empty($table->head))
        {
            $countcols = count($table->head);
            $keys      = array_keys($table->head);
            $lastkey   = end($keys);

            foreach ($table->head as $key => $heading)
            {
                $matrix[0][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($heading))));
            }
        }

        if (!empty($table->data))
        {
            foreach ($table->data as $rkey => $row)
            {
                foreach ($row as $key => $item)
                {
                    $matrix[$rkey + 1][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($item))));
                }
            }
        }

        return $matrix;
    }

    /**
     * 
     */
    public function new_fees($simulation = true)
    {
        // print the table header
        $this->print_fee_table_header();

        foreach ($this->matrix_associative as $key => $user):
            // for thiss user look up fees from sheet and formulate the new fees array to be added
            $new_fees_arr = $this->get_new_fees_array( $user, $this->fees_csv );

            // echo nl2br("New fees Array looked up in fees_csv array");
            // echo "<pre>" . print_r($new_fees_arr, true) ."</pre>";

            // read in the existing fees array from this user's custom field
            $updated_fees_arr = $this->insert_new_fees_and_update_profile_field( $user, $new_fees_arr, $simulation );

            // print out a row of the fee table for this user's fee
            $this->print_fee_table_row( $user, $updated_fees_arr, $new_fees_arr );

        endforeach;

        $this->print_footer();
    }



    /**
     * 
     */
    public function insert_new_fees_and_update_profile_field( $user, $new_fees_arr, $simulation = true )
    {
        global $DB;

        // unique id used internally by Moodle in the user tables
        $moodleuserid   = $user["id"];

        // read in existing data in profile_field_fees
        $field = $DB->get_record('user_info_field', array('shortname' => "fees"));
        $user_profile_fees = $DB->get_record('user_info_data', array(
                                                                        'userid'   =>  $moodleuserid,
                                                                        'fieldid'  =>  $field->id,
                                                                    )
                                            );
        // read in the JSON encoded data or set it to blank if empty or has phrases such as paid etc.
        $fees_json = (empty($fees_json)  || !is_array($user_profile_fees->data)) ?  "[]" : $user_profile_fees->data;

 
        // decode json string into array. Each sub-array stands for one fee record. If fails decode to empty array
        $existing_fees_arr 		= json_decode($fees_json, true);

        if ( json_last_error() !== JSON_ERROR_NONE )
        {
            $this->verbose ? error_log( "problem with JSON decoding of fees profiel field for user:" . $user('idnumber') ): false;
            $existing_fees_arr = [];
        }

        // if the array of fees is empty add our fee array as the first sub-array
        if (empty($existing_fees_arr))
        {
            // add it as first element
            $existing_fees_arr[0] = $new_fees_arr;
        }
        else
        {
            // check to see if data exists for this already, based on equality of key elements
            $key = is_fees_payment_exists($existing_fees_arr, $new_fees_arr);
            // a value of -1 impleies no, otherwise it already exists
            if ((-1) !== $key)
            {
                // this already exists, we can rewrite this or ignore based on flag
                $this->verbose ? error_log("This payment already exists in user's fee profile, user being:" . $user["username"]): false;
                if ( $this->overwrite_existing_fees)
                {
                    $existing_fees_arr[$key] = $new_fees_arr;
                }
                // if overwrite flag is not set do not overwrite
            }
            else
            {
                // we don't have fees data for desired academic year so we will add our new fees due array
                // add it as 1st element, so that latest payment due comes always 1st in the array
                array_unshift($existing_fees_arr, $new_fees_arr);
            }
        }
        
        if ($this->update_profile_fees && !$simulation)
        {
            // convert the array to JSON and write it back to the user profile field
            $user_profile_fees->data = json_encode($existing_fees_arr);
            // update the database record for this user for this field
            $DB->update_record('user_info_data', $user_profile_fees, $bulk=false);
        }

        return $existing_fees_arr;
    }

    



    /**
     * @return arr:$new_fees_arr holding fees information for the upcoming payment cycle
     * @param arr:$user associative array holding record for a single student
     * @param arr:$fees_csv the associative array of the fees spreadsheet from the google CSV sheet
     */
    public function get_new_fees_array ($user, $fees_csv)
    {
        // extract data from the user generated by the report
        // this is the unique sritoni idnumber assigned by school
        $idnumber 		= $user["idnumber"];
        // unique id used internally by Moodle in the user tables
        $moodleuserid   = $user["id"];
        // sritoni username issued by school
        $moodleusername = $user["username"];
        // present grade of child around Feb just before paying fees
        $present_grade	= $user["present_grade"];
        // what is the student category?
        $studentcat     = strtolower($user["studentcat"]) ?? "general";


        //setup a search index based on present grade and studentcat
        $present_grade_studentcat = $present_grade . "|" . $studentcat;

        // check if this combination of present_gared and studentcat exists as a column in the CSF array
        if (empty($fees_csv[0][$present_grade_studentcat]) || empty($fees_csv[1][$present_grade_studentcat]) ||
            empty($fees_csv[2][$present_grade_studentcat]) || empty($fees_csv[3][$present_grade_studentcat]))
        {
            // data required for this student's present_grade and studentcat is not available in the sheet, go to next student
            // print out the full row aith all data
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($idnumber); ?></td>
                    <td><?php echo htmlspecialchars($moodleuserid); ?></td>
                    <td><?php echo htmlspecialchars($moodleusername); ?></td>
                    <td><?php echo htmlspecialchars($present_grade); ?></td>
                    <td><?php echo htmlspecialchars("Not found"); ?></td>
                    <td><?php echo htmlspecialchars("Not found"); ?></td>
                    <td><?php echo htmlspecialchars("Not found"); ?></td>
                    <td><?php echo htmlspecialchars("Not found"); ?></td>
                </tr>
            <?php

            return null;
        }
        else
        {
            // data for the combination of present_grade | Studentcat exists in the spreadsheet
            // extract from googlecsv file data the grade to pay for based on present grade
            $fees_for_grade = $fees_csv[0][$present_grade_studentcat];
            // extract from google CSV file the amount of fees to be paid to hset
            $amount_hset	= $fees_csv[1][$present_grade_studentcat];
            // extract from google CSV file data the academic year for which the fees are to be paid
            $ay				= $fees_csv[2][$present_grade_studentcat];
            // extract payee name from google CSV file data
            $payee          = $fees_csv[3][$present_grade_studentcat];

            // make a convenient fee array for insertion into user profile field fees
            $new_fees_arr	= array(
                                    "set_during" 	    => $present_grade,
                                    "fees_for"	        => $fees_for_grade,
                                    "amount"			=> $amount_hset,
                                    "ay"				=> $ay,
                                    "status"            => "not paid",
                                    "payee"             => $payee,
                                    );
            return $new_fees_arr;
         }
    }


    /**
     * 
     */
    public function print_fee_table_row( $user, $updated_fees_arr, $new_fees_arr )
    {

        // print out the full row aith all data
        ?>
                <tr>
                    <td><?php echo htmlspecialchars( $user["idnumber"] ); ?></td>
                    <td><?php echo htmlspecialchars( $user["id"] ); ?></td>
                    <td><?php echo htmlspecialchars( $user["username"] ); ?></td>
                    <td><?php echo htmlspecialchars( $user["present_grade"] ); ?></td>
                    <td><?php echo htmlspecialchars( $new_fees_arr['fees_for'] ); ?></td>
                    <td><?php echo htmlspecialchars( $new_fees_arr['amount'] ); ?></td>
                    <td><?php echo htmlspecialchars( $new_fees_arr['ay']) ; ?></td>
                    <td><?php echo htmlspecialchars(json_encode( $updated_fees_arr )); ?></td>
                    <td><?php echo htmlspecialchars( $new_fees_arr['payee'] ); ?></td>
                </tr>
        <?php
    }


    /**
     * 
     */
    public function print_fee_table_header() 
    {
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
                    <th>idnumber</th>
                    <th>Moodle ID</th>
                    <th>username</th>
                    <th>Set During</th>
                    <th>Pay Fees for</th>
                    <th>Amount</th>
                    <th>For Academic Year</th>
                    <th>New JSON data for fees field</th>
                    <th>Payee</th>
                </tr>
        <?php
    }

    /**
     * 
     */
    public function print_footer()
    {
        // close that HTML table tag
        ?>
                </table>
        <?php
    }

    /**
     * 
     */
    public function update_create_virtual_accounts_hset()
    {
        //
    }

    /**
     * 
     */
    public function generate_remote_payment_orders_hset_payments()
    {
        //
    }
    


    /**
    *  @param old is the array of fees payments arrays read in from json decoded fees field
    *  @param new_fees is the new fee payment array built from the report
    *  @return key index of old which matches new. If no match set key to -1
    *
    */
    public function is_fees_payment_exists(array $old, array $new_fees):int
    {
        // check to see if new fees payment already exists in the existing fees payments array
        // it already exists if desired elements match. If exists then return true, if not false.
        // present grade is d/c and amount also d/c all else should be same.
        foreach ($old AS $key => $fees)
        {
            if ($fees['ay']             == $new_fees['ay']              &&
                $fees['fees_for']       == $new_fees['fees_for']        &&
            //  $fees['amount']         == $new_fees['amount']          &&
            //  $fees['present_grade']  == $new_fees['present_grade']   &&
                $fees['payee']          == $new_fees['payee']           &&
                $fees['status']         == 'not paid'
                )
            {
                // fee record exists, just return the key
                return $key;
            }
            // no match, loop to next fee record
        }
        // no equality after looping throuh all fee elements so return false
        return -1;
    }



    /**
     * This routine is attributed to https://github.com/rap2hpoutre/csv-to-associative-array
     *
    * The items in the 1st line (column headers) become the fields of the array
    * each line of the CSV file is parsed into a sub-array using these fields
    * The 1st index of the array is an integer pointing to these sub arrays
    * The 1st row of the CSV file is ignored and index 0 points to 2nd line of CSV file
    * This is the example data:
    *
    * grade1,grade2,grade3
    * grade2,grade3,grade4
    * 10000,20000,30000
    *
    * This is the associative array
    * Array
    *(
    *  [0] => Array
    *		(
    *		   [grade1] => grade2
    *          [grade2] => grade3
    *		   [grade3] => grade4
    *		)
    *  [1] => Array
    *      (
    *          [grade1] => 10000
    *          [grade2] => 20000
    *          [grade3] => 30000
    *      )
    * )
    */
    public function  csvfile_to_associative_array ( $file, $delimiter = ',', $enclosure = '"' )
    {
        if (($handle = fopen($file, "r")) !== false)
        {
            $headers = fgetcsv($handle, 0, $delimiter, $enclosure);
            $lines = [];
            while (($data = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false)
            {
                $current = [];
                $i = 0;
                foreach ($headers as $header)
                {
                    $current[$header] = $data[$i++];
                }
                $lines[] = $current;
            }
            fclose($handle);
            return $lines;
        }
    }
}