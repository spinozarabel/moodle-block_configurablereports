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
    global $DB, $CFG;

    require_once($CFG->libdir . '/csvlib.class.php');
    require_once($CFG->dirroot."/blocks/configurable_reports/cashfree_api/cfAutoCollect.inc.php");
    require_once($CFG->dirroot."/blocks/configurable_reports/madhu_export_classes/feepayment.php");

    $simulation = true;

    $verbose    = true;

    // flag to update user profile field or not, with possible new data
	$update_profile_fees       =   get_config('block_configurable_reports', 'update_profile_fees')      ?? false;
    // Overwrite even if array exists for concerned academic year
	$overwrite_existing_fees   =   get_config('block_configurable_reports', 'overwrite_existing_fees')  ?? true;

    // Read the CSv published Google Sheet, get its URL from config settings
	$googlesheeturl  = get_config('block_configurable_reports', 'googlesheeturl');
    
	if (empty($googlesheeturl))
    {
        echo nl2br("Empty config setting for Google Published CSV file URL, please set in config: "  . "\n");
		error_log("Empty config setting for Google Published CSV file URL in plugin configurable_reports, please set in config");
        return;
    }

    // new instance of the feepayment process that defines all the functions needed for payment process
	$feepayment = new feepayment( $report, $verbose, $simulation, $overwrite_existing_fees, $update_profile_fees );


	// read file and parse to associative array. To access this in a function, make this a global there
    $fees_csv = $feepayment->csvfile_to_associative_array($googlesheeturl);

    // define table and heading
    $feepayment->print_fee_table_header();

    // get the report as an associative array without header
    $report_array = $feepayment->matrix_associative;

	// for each of the users generated from SQL filter in the report, compile
    // from user as well as from google CSV file

	foreach ($report_array as $key => $user):

        // for thiss user look up fees from sheet and formulate the new fees array to be added
        $new_fees_arr = $feepayment->get_new_fees_array( $user, $fees_csv );

        // read in the existing fees array from this user's custom field
        $updated_fees_arr = $feepayment->insert_new_fees_and_update_profile_field( $user, $new_fees_arr );

        // print out a row of the fee table for this user's fee
        $feepayment->print_fee_table_row( $user, $updated_fees_arr, $new_fees_arr );
        

    endforeach;

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
