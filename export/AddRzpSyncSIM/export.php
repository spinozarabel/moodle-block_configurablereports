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
 * @date: 03/21/2019
 * This is the Razorpay account sync_add version 1.0
 * Adds Raxorpay Virtual Account for all students if not already existing
 */

function export_report($report) 
{
    global $DB, $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');
	require_once($CFG->dirroot."/blocks/configurable_reports/razorpaylib.php");
	require_once($CFG->dirroot."/blocks/configurable_reports/ignore_key.php");
	
	
	$api_key 	= getRazorpayApiKey();
	$api_secret = getRazorpayApiSecret();

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
	$virtualAccounts  = getAllActiveVirtualAccounts($api_key, $api_secret);	
	//count the total number of active accounts available
	$vacount = count($virtualAccounts);
	echo nl2br("Number of Active Razorpay Virtual Accounts: " . $vacount . "\n");
	
	
	// for each of the csv users check to see if they have an associated account.
	// if they do unset them from the csv data. All remaining csv users need new virtual accounts.
	foreach ($csv as $key => $csvuser) 
		{
			// get student id number
			$useridnumber = $csvuser["employeenumber"];
			// get virtual account corespondin to this student ID
			$va = getVirtualAccountGivenSritoniId($useridnumber, $virtualAccounts);
			//echo nl2br("Student ID: " . $useridnumber . "VA ID: " . $va->id . "\n");
			// if this is not null then unset this item since we want to create accounts for those who don't have them yet
			
			if(is_null($va)) 	// VA doesn't exist, need to create so keep this entry, go to next item
				{
					break;
				}
			unset($csv[$key]);	// the account exists and so no ned to create one, remove this entry from the array
		}
	unset($csvuser);  // break reference in foreach loop on exit
	
	// Now all remaining members of $csv do not have matching virtual accounts so create them
	$count_va_created = 0; //initialize count
	
	foreach ($csv as $key => $csvuser) 
		{
			// get student id number and user name from CSV array
			$useridnumber 	= $csvuser["employeenumber"];  // this is the unique sritoni idnumber assigned by school
			$username 		= $csvuser["uid"];             // uniques username assigned by school
			$userid   		= $csvuser["id"];              // unique id used internally by Moodle in the user tables
			
			// create a new virtual account for this user
			//$va = createVirtualAccount($api_key, $api_secret, $useridnumber, $username, $userid);
			$count_va_created += 1;  // increment count
			echo nl2br("New Virtual Account created for: " . $username . " with VA ID: " . "not created yet" . "\n");
		}
		unset($csvuser); // break foreach reference
	
	echo nl2br("New Virtual Accounts created: " . $count_va_created . "\n");
	

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