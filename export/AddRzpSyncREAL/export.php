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
 * This is the Razorpay account sync_add version 1.0
 * Adds Raxorpay Virtual Account for all students if not already existing
 * Adds accounts for HSET and HSEA-LLP and updates profile_field_virtualaccounts with JSON encoding
 * ver 1.1
 */

function export_report($report) 
{
    global $DB, $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');
	require_once($CFG->dirroot."/blocks/configurable_reports/razorpaylib.php");
	require_once($CFG->dirroot."/blocks/configurable_reports/ignore_key.php");
	
	$site_name	= " contains hset once";
	$api_key_hset 	= getRazorpayApiKey($site_name);
	$api_secret_hset = getRazorpayApiSecret($site_name);
	
	$site_name	= " contains llp once";
	$api_key_llp 	= getRazorpayApiKey($site_name);
	$api_secret_llp = getRazorpayApiSecret($site_name);

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
	$virtualAccounts_hset	= getAllActiveVirtualAccounts($api_key_hset, $api_secret_hset);	
	$virtualAccounts_llp  	= getAllActiveVirtualAccounts($api_key_llp, $api_secret_llp); // uncomment once LLP account created razorpay
	//count the total number of active accounts available
	// assume that number is same between HSET and LLP sites
	$vacount = count($virtualAccounts_hset);
	echo nl2br("Number of Active Razorpay Virtual Accounts: " . $vacount . "\n");
	
	
	// for each of the csv users check to see if they have an associated account.
	// if they do unset them from the csv data. All remaining csv users need new virtual accounts.
	foreach ($csv as $key => $csvuser) 
		{
			// get student id number
			$useridnumber = $csvuser["employeenumber"];
			// get virtual account corespondin to this student ID. We check only HSET since it is true for LLP also by design
			$va_hset = getVirtualAccountGivenSritoniId($useridnumber, $virtualAccounts_hset);
			$va_llp  = getVirtualAccountGivenSritoniId($useridnumber, $virtualAccounts_llp);
			
			
			//echo nl2br("Student ID: " . $useridnumber . "VA ID: " . $va->id . "\n");
			// if this is not null then unset this item since we want to create accounts for those who don't have them yet
			
			if(is_null($va_hset)) 	// VA doesn't exist, need to create so keep this entry, go to next item
				{
					continue;       // keep this element, go to next user in for each loop
				}
			unset($csv[$key]);	    // the account exists and so no need to create one, remove this entry from the array
		}
	// TO DO before removing the entry lets update the user profile field for good measure?
	unset($csvuser);  // break reference in foreach loop on exit
	
	// Now all remaining members of $csv do not have matching virtual accounts so create them for both HSET and LLP
	$count_va_created = 0; //initialize count
	
	foreach ($csv as $key => $csvuser) 
		{
			// get student id number and user name from CSV array
			$useridnumber 	= $csvuser["employeenumber"];  // this is the unique sritoni idnumber assigned by school
			$username 		= $csvuser["uid"];             // uniques username assigned by school
			$userid   		= $csvuser["id"];              // unique id used internally by Moodle in the user tables
			
			// create a new virtual account for this user
			$va_hset 		= createVirtualAccount($api_key_hset, $api_secret_hset, $useridnumber, $username, $userid);
			
			
			// prepare the array of account information to be JSON encoded
			if ($va_hset->id)
			{
				$acct_hset = array	(
									"beneficiary_name"  => $va_hset->receivers[0]->name,
									"va_id"             => $va_hset->id,
									"account_number"    => $va_hset->receivers[0]->account_number,
									"va_ifsc_code"      => $va_hset->receivers[0]->ifsc,
									);
				$accounts[0]	= $acct_hset;
			}
			
			// create a new VA for this user at HSEA-LLP
			$va_llp			= createVirtualAccount($api_key_llp, $api_secret_llp, $useridnumber, $username, $userid);
			
			// prepare the array of account information to be JSON encoded
			if ($va_llp->id)
			{
				$acct_hseallp = array	(
									"beneficiary_name"  => $va_hseallp->receivers[0]->name,
									"va_id"             => $va_hseallp->id,
									"account_number"    => $va_hseallp->receivers[0]->account_number,
									"va_ifsc_code"      => $va_hseallp->receivers[0]->ifsc,
									);
				$accounts[1]	= $acct_hseallp;
			}
			
			// JSON encode array if array is valid
			if ($accounts) 
			{
				$accounts_json	= json_encode($accounts);
			}
			
			// Get the Moodle profile_field_virtualaccounts for this user to update
			$field = $DB->get_record('user_info_field', array('shortname' => "virtualaccounts"));
			$user_profile_virtualaccounts = $DB->get_record('user_info_data', array(
																					'userid'   =>  $userid,
																					'fieldid'  =>  $field->id,
																					)
															);  // Get fieldid based on shortname "virtualaccounts"
			// this data will be empty since we have not yet created VAs for this user
			
			$user_profile_virtualaccounts->data = $accounts_json;
			$DB->update_record('user_info_data', $user_profile_virtualaccounts, $bulk=false);
			
			
			$count_va_created += 1;  // increment count
			echo nl2br("New Virtual Account created for: " . $username . " for HSET payments, VA ID: " . $va_hset->id . "\n");
			echo nl2br("New Virtual Account created for: " . $username . " for HSEA-LLP payments, VA ID: " . $va_llp->id . "\n");
		}
		unset($csvuser); // break foreach reference
	
	echo nl2br("New Virtual Accounts created at each of HSET and HSEA-LLP sites: " . $count_va_created . "\n");
	

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
