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

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();
//
/**
 * Configurable Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: Madhu Avasarala
 * @date: 13/08/2021
 * This is the fees ver 3.0
 * Reads fees due data from Google CSV published file and adds fee data to user_profile_field fees
 * Creates new VA if needed. Creates remote Payment Shop Orders based on fees amount due
 */

function export_report($report)
{
    global $DB, $CFG, $COURSE;

    require_once($CFG->libdir . '/csvlib.class.php');
    require_once($CFG->dirroot."/blocks/configurable_reports/cashfree_api/cfAutoCollect.inc.php");
    // require_once($CFG->dirroot."/blocks/configurable_reports/madhu_export_classes/feepayment.php");

    $simulation = true;

    $verbose    = true;

    // new instance of the feepayment process that defines all the functions needed for payment process
    // it also generates the associative array from the report's table
	$feepayment = new \block_configurable_reports\madhu_export_classes\feepayment( $report, $verbose, $simulation );

    // echo nl2br("fees CSV array as read from Google published file");
    // echo "<pre>" . print_r($fees_csv, true) ."</pre>";


    ?>
            <h4> Choose functionality and Click on button</h4>
            <form action="" method="post" id="form1">
                <input type="submit" name="button" 	value="Back to Report"/>
                <input type="submit" name="button" 	value="Simulate fees"/>
                <input type="submit" name="button" 	value="Write fees to user data"/>
                <input type="submit" name="button" 	value="update or create CF Accounts"/>
                <input type="submit" name="button" 	value="Generate POs"/>
                <input type="hidden" name="courseid" value="' . $COURSE->id .'">';
                <input type="hidden" name="reportid" value="' . $report->table->reportid .'">';
            </form>

        <?php

        // TODO sanitize the _POST variable
        $button = filter_var($_POST['button'], FILTER_SANITIZE_STRING) ;

        switch ($button):
            case "Back to Report":
                // bredirect using new moodle_url
                redirect(new \moodle_url('/blocks/configurable_reports/viewreport.php', ['id'       => $report->table->reportid, 
                                                                                         'courseid' => $COURSE->id
                                                                                        ]
                                        ));
                break;

                case "Simulate fees":
                    $feepayment->new_fees($report, true);
                    $feepayment->print_footer();
                    break;
            
                case "Write fees to user data":
                    // bredirect using new moodle_url
                    $feepayment->new_fees($report, false);
                    $feepayment->print_footer();
                    break;

                case "update or create CF Accounts":
                    //
                    $feepayment->update_create_virtual_accounts_hset();
                    break;

                case "Generate POs":
                    // Create new orders on hset-payments site for each user for this fee amount
                    $feepayment->generate_remote_payment_orders_hset_payments();
                    break;
        endswitch;

	exit;
}

/**
*  @param old is the array of fees payments arrays read in from json decoded fees field
*  @param new_fees is the new fee payment array built from the report
*  @return key index of old which matches new. If no match set key to -1
*
*/
function fees_payment_exists(array $old, array $new_fees):int
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
 *			[grade1] => grade2
 *          [grade2] => grade3
 *			[grade3] => grade4
 *		)
 *  [1] => Array
 *      (
 *          [grade1] => 10000
 *          [grade2] => 20000
 *          [grade3] => 30000
 *      )
 * )
 */
function csv_to_associative_array($file, $delimiter = ',', $enclosure = '"')
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
