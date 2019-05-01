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
 * @date: 04/27/2019
 * This is the Razorpay account Del sync version 1.1
 * Closes Raxorpay Virtual Account for all students who are NOT in list of generated Report
 * 
 * ver 1.1
 */

function export_report($report) 
{
    global $DB, $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');
	require_once($CFG->dirroot."/blocks/configurable_reports/sritoni_razorpay_api.php");

	
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
	$virtualAccounts_hset	= $razorpay_api_hset->getAllActiveVirtualAccounts();
	$virtualAccounts_llp	= $razorpay_api_llp->getAllActiveVirtualAccounts();
	//count the total number of active accounts available
	// assume that number is same between HSET and LLP sites
	$vacount = count($virtualAccounts_hset);
	echo nl2br("Number of Active Razorpay Virtual Accounts: " . $vacount . "\n");
	
	$va_array_hset	= (array) $virtualAccounts_hset;
	$va_array_llp	= (array) $virtualAccounts_llp;
	
	$del_count	= 0; // initialize counter for number of deleted VAs
	// for each of the active virtual accounts check to see if corresponding user exists in CSV array
	foreach ($virtualAccounts_hset->items as $key => $va) // looping through array of iems, each item is a VA
		{
			// 
			$va_id		 		= $va->id; // this is the VA ID of this account number
			$va_useridnumber	= $va->notes->idnumber;
			
			// Is there a student in the $csv array having this idnumber?
			if (in_array($va_useridnumber, array_column($csv, "uid")))
			{
				// A valid student account exists for this VA so we need to keep this account
				continue; // skip this VA and go to next VA in foreach loop
			}
			// if we are here, there is no valid user in CSV and so we need to close this account
			$va_closed			= $razorpay_api_hset->closeVirtualAccount($va_id);
			
			if ($va_closed->status == "closed" )
			{
				echo nl2br("Successfully Closed VA HSET for Student ID: " . $va_useridnumber . "VA ID: " . $va_id . "\n");
				$del_count = $del_count + 1;
			}
			
		}

	unset($va);  // break reference in foreach loop on exit
	echo nl2br("Successfully Closed " . $del_count . " HSET VA accounts" . "\n");
	
	$del_count	= 0; // initialize counter for number of deleted VAs
	// for each of the active virtual accounts check to see if corresponding user exists in CSV array
	foreach ($virtualAccounts_llp->items as $key => $va) // looping through array of iems, each item is a VA
		{
			// 
			$va_id	 			= $va->id; // this is the VA ID of this account number
			$va_useridnumber	= $va->notes->idnumber;
			
			// Is there a student in the $csv array having this idnumber?
			if (in_array($va_useridnumber, array_column($csv, "uid")))
			{
				// A valid student account exists for this VA so we need to keep this account
				continue; // skip this VA and go to next VA in foreach loop
			}
			// if we are here, there is no valid user in CSV and so we need to close this account
			$va_closed			= $razorpay_api_llp->closeVirtualAccount($va_id);
			
			if ($va_closed->status == "closed" )
			{
				echo nl2br("Successfully Closed VA HSEA LLP for Student ID: " . $va_useridnumber . "VA ID: " . $va_id . "\n");
				$del_count = $del_count + 1;
			}
			
		}

	unset($va);  // break reference in foreach loop on exit
	echo nl2br("Successfully Closed " . $del_count . " HSEA-LLP accounts" . "\n");
	

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
