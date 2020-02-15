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
 * @date: 01/08/2020
 * This is the Cashfree account sync_add version 2.0
 * Adds Cashfree Virtual Account for all students if not already existing
 * Adds accounts for a max of 2 payment sites and updates profile_field_virtualaccounts with JSON encoding
 *
 */

function export_report($report)
{
    global $DB, $CFG;

    require_once($CFG->libdir . '/csvlib.class.php');
	//require_once($CFG->dirroot."/blocks/configurable_reports/cashfree_api/cfAutoCollect.inc.php");

    // flag to update user profile field or not, with possible new data
	$update_profile =   false;
    // Simulation only, do not change any user data absolutely!!!
	$sim			=   true;

	//--------------------- end of section 1 Decalarations------------------------------------------------->

	//--------------------- create the report table and the csv users matrix section 2--------------------->
    $table    = $report->table;
    $matrix   = array();
    $filename = 'report';
    $accounts = array();

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

    $csv   =  $matrix;  # instead of downloading and parsing, we are reusing



    array_walk($csv, function(&$a) use ($csv)
		{
			$a = array_combine($csv[0], $a);
		});
	array_shift($csv); # remove column header
	// find number of entries extracted from CSV into array
    $csvcount = count($csv);
	//----------------------------------- end of section 2 --------------------------------------->
	
	// Read the CSv published Google Sheet, get its URL from config settings
	$googlesheeturl  = get_config('block_configurable_reports', 'googlesheeturl');
	if (empty($googlesheeturl))
    {
        echo nl2br("Empty config setting for Google Published CSV file URL, please set in config: "  . "\n");
		error_log("Empty config setting for Google Published CSV file URL in plugin configurable_reports, please set in config");
        return;
    }
	// read file and parse to associative array. To access this in a function, make this a global there
    $fees_csv = csv_to_associative_array($googlesheeturl);
	error_log(print_r($fees_csv, true));
	
	

	// initialize counts of accounts to be added if missing
	$count_field_processed	=	0;


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
                <th>Present Grade</th>
                <th>Pay Fees for</th>
    			<th>Amount</th>
				<th>For Academic Year</th>
    		</tr>
    <?php

	// for each of the csv users extract data from CSV table

	foreach ($csv as $key => $csvuser)
		{
			$idnumber 		= $csvuser["idnumber"];			// this is the unique sritoni idnumber assigned by school
			$moodleuserid   = $csvuser["id"];				// unique id used internally by Moodle in the user tables
            $moodleusername = $csvuser["username"]; 		// sritoni username issued by school
			$present_grade	= $csvuser["present_grade"];	// present grade of child around Feb just before paying fees
			$fees_for_grade = $fees_csv[0][$present_grade];	// extract from table the grade to pay for based on present grade
			$amount			= $fees_csv[1][$present_grade];	// extract from table the amount based on present grade
			$ay				= $fees_csv[2][$present_grade];

            // we have data for all accounts so print out the full row aith all data
            ?>
                    <tr>
                        <td><?php echo htmlspecialchars($idnumber); ?></td>
                        <td><?php echo htmlspecialchars($moodleuserid); ?></td>
						<td><?php echo htmlspecialchars($moodleusername); ?></td>
                        <td><?php echo htmlspecialchars($present_grade); ?></td>
                        <td><?php echo htmlspecialchars($fees_for_grade); ?></td>
                        <td><?php echo htmlspecialchars($amount); ?></td>
						<td><?php echo htmlspecialchars($ay); ?></td>
                    </tr>
            <?php
			
		}

	exit;
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
