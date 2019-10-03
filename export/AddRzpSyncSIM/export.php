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
 * @date: 04/29/2019
 * This is the Razorpay account sync_add version 1.1
 * Simulate-Adds Raxorpay Virtual Account for all students if not already existing
 * 1.2 checks individually for account for each site and creates account if not present for any site
 * 1.1 uses class defined in sritoni_razorpay_api.php that is useable for both Moodle and Wordpress
 * 1.0 uses razorpay.lib
 */

function export_report($report)
{
    global $DB, $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');
	require_once($CFG->dirroot."/blocks/configurable_reports/sritoni_razorpay_api.php"); // file contains class for razorpay API

	$site_name			= " contains hset once";
	$razorpay_api_hset 	= new sritoni_razorpay_api($site_name);


	$site_name			= " contains llp once";
	$razorpay_api_llp 	= new sritoni_razorpay_api($site_name);

    $table    = $report->table;
    $matrix   = array();
    $filename = 'report';

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
	echo nl2br("Number of SriToni users from report: " . $csvcount . "\n");



    // Fetch all virtual accounts from Razorpay as a collection
	//$virtualAccounts_hset	= $razorpay_api_hset->getAllActiveVirtualAccounts();
	//$virtualAccounts_llp	= $razorpay_api_llp->getAllActiveVirtualAccounts();

	//count the total number of active accounts available for each payment site
	/*
	$vacount_hset 	= count($virtualAccounts_hset);
	$vacount_llp	= count($virtualAccounts_llp);
	echo nl2br("Number of Active Razorpay Virtual Accounts HSET: " 		. 	$vacount_hset 	. "\n");
	echo nl2br("Number of Active Razorpay Virtual Accounts HSEA LLP: "	. 	$vacount_llp 	. "\n");
    */
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
    			<th>Student Name</th>
    			<th>employeenumber</th>
    			<th>HSET VA ID</th>
                <th>HSET Account No</th>
                <th>HSET IFSC</th>
    			<th>HSEA LLP VA ID</th>
                <th>HSEA LLP Account No</th>
                <th>HSEA LLP IFSC</th>
    		</tr>
    <?php

	// initialize counts of accounts to be added if missing
	$count_va_hset_created	=	0;
	$count_va_llp_created	=	0;
	// for each of the csv users check to see if they have an associated account.
	// if they do unset them from the csv data. All remaining csv users need new virtual accounts.
	foreach ($csv as $key => $csvuser)
		{
			// get student id number
			$useridnumber 	= $csvuser["employeenumber"];	// this is the unique sritoni idnumber assigned by school
			$username 		= $csvuser["uid"];				// unique username assigned by school
			$userid   		= $csvuser["id"];				// unique id used internally by Moodle in the user tables
            $moodle_vadata  = $csvuser["vadata"];           // JSON encoded razorpay account data
            $user_display_name = $csvuser["displayname"];
            // read in user field containing razorpay accounts stored as array read in by SQL
            $moodle_vas_obj = json_decode($moodle_vadata, false); // decoded as object
			// check from student profile if va_id for HSET account is "not assigned" meaning not created yet

			$va_hset	= $moodle_vas_obj[0];
            error_log(print_r($va_hset, true));
            // check from student profile if va_id for HSET account is "not assigned" meaning not created yet
			$va_llp 	= $moodle_vas_obj[1];
			//echo nl2br("Student ID: " . $useridnumber . "VA ID: " . $va->id . "\n");
			// if this is not assigned we need to create an account for this user
			if( $va_hset->va_id == "not assigned" )
			{	// create a new virtual account for this user for this site, if not already present
				$count_va_hset_created	=	$count_va_hset_created + 1; // increment count of accounts created
                ?>
                    <tr>
    					<td><?php echo htmlspecialchars($user_display_name); ?></td>
                        <td><?php echo htmlspecialchars($useridnumber); ?></td>
                        <td><?php echo htmlspecialchars($va_hset->va_id); ?></td>
                        <td><?php echo htmlspecialchars($va_hset->account_number); ?></td>
                        <td><?php echo htmlspecialchars($va_hset->va_ifsc_code); ?></td>
                <?php
			}
            else
            {
                ?>
                    <tr>
    					<td><?php echo htmlspecialchars($user_display_name); ?></td>
                        <td><?php echo htmlspecialchars($useridnumber); ?></td>
                        <td><?php echo htmlspecialchars($va_hset->va_id); ?></td>
                        <td><?php echo htmlspecialchars($va_hset->account_number); ?></td>
                        <td><?php echo htmlspecialchars($va_hset->va_ifsc_code); ?></td>
                <?php
            }
			if( $va_llp->va_id == "not assigned" )
			{	// create a new virtual account for this user for this site, if not already present
				$count_va_llp_created	=	$count_va_llp_created + 1; // increment count of accounts created
                ?>
                    <td><?php echo htmlspecialchars($va_llp->va_id); ?></td>
                    <td><?php echo htmlspecialchars($va_llp->account_number); ?></td>
                    <td><?php echo htmlspecialchars($va_llp->va_ifsc_code); ?></td>
                </tr>
                <?php
			}
            else
            {
                ?>
                    <td><?php echo htmlspecialchars($va_llp->va_id); ?></td>
                    <td><?php echo htmlspecialchars($va_llp->account_number); ?></td>
                    <td><?php echo htmlspecialchars($va_llp->va_ifsc_code); ?></td>
                </tr>
                <?php
            }

		}
        ?></table><?php
	unset($csvuser);  // break reference in foreach loop on exit
	// print total number of new accounts added
	echo nl2br("Number of new Razorpay Virtual Accounts simulation created for HSET: " 		. 	$count_va_hset_created 	. "\n");
	echo nl2br("Number of new Razorpay Virtual Accounts simulation created for HSEA LLP: "	. 	$count_va_llp_created 	. "\n");

	exit;
}

/** function cleanUpEntry
*  This function takes an entry downloaded from LDAP and cleans it up
* and makes it an associative array
*/

function cleanUpEntry( $entry ) {
  $retEntry = array();
  for ( $i = 0; $i < $entry['count']; $i++ ) {
    if (is_array($entry[$i])) {
      $subtree = $entry[$i];
      //This condition should be superfluous so just take the recursive call
      //adapted to your situation in order to increase perf.
      if ( ! empty($subtree['dn']) and ! isset($retEntry[$subtree['dn']])) {
        $retEntry[$subtree['dn']] = cleanUpEntry($subtree);
      }
      else {
        $retEntry[] = cleanUpEntry($subtree);
      }
    }
    else {
      $attribute = $entry[$i];
      if ( $entry[$attribute]['count'] == 1 ) {
        $retEntry[$attribute] = $entry[$attribute][0];
      } else {
        for ( $j = 0; $j < $entry[$attribute]['count']; $j++ ) {
          $retEntry[$attribute][] = $entry[$attribute][$j];
        }
      }
    }
  }
  return $retEntry;
}
