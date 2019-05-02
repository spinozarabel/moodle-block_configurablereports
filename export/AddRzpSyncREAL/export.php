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
 * This is the Razorpay account sync_add version 1.2
 * Adds Raxorpay Virtual Account for all students if not already existing
 * Adds accounts for HSET and HSEA-LLP and updates profile_field_virtualaccounts with JSON encoding
 * ver 1.2
 */

function export_report($report) 
{
    global $DB, $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');
	require_once($CFG->dirroot."/blocks/configurable_reports/sritoni_razorpay_api.php");

	//-------------------- create new API interfaces section 1-------------------------------->
	$site_name			= " contains hset once";
	$razorpay_api_hset 	= new sritoni_razorpay_api($site_name);

	
	$site_name			= " contains llp once";
	$razorpay_api_llp 	= new sritoni_razorpay_api($site_name);
	//--------------------- end of section 1 -----------------------------------------------------

	//--------------------- create the report table and the csv users matrix section 2--------------------->
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
	//----------------------------------- end of section 2 --------------------------------------->
	
	
	
    // Fetch all virtual accounts from Razorpay as a collection
	$virtualAccounts_hset	= $razorpay_api_hset->getAllActiveVirtualAccounts();
	$virtualAccounts_llp	= $razorpay_api_llp->getAllActiveVirtualAccounts();

	echo nl2br("Number of Present Active Razorpay Virtual Accounts for HSET: " . count($virtualAccounts_hset) . "\n");
	echo nl2br("Number of Present Active Razorpay Virtual Accounts for LLP : " . count($virtualAccounts_llp)  . "\n");
	
	
	// for each of the csv users check to see if they have an associated account.
	// if they do unset them from the csv data. All remaining csv users need new virtual accounts.
	
	foreach ($csv as $key => $csvuser) 
		{
			// get student id number
			$useridnumber = $csvuser["employeenumber"];
			
			// get virtual account corespondin to this student ID for both sites
			$va_hset = $razorpay_api_hset->getVirtualAccountGivenSritoniId($useridnumber, $virtualAccounts_hset);
			$va_llp  = $razorpay_api_llp->getVirtualAccountGivenSritoniId( $useridnumber, $virtualAccounts_llp);
			
			// initialzie counts
			$count_va_hset 	= 0; //initialize count
			$count_va_llp 	= 0; //initialize count
			
			// keep data ready needed for creating VAs for this user. It maynot be used if accounts exist
			$useridnumber 	= $csvuser["employeenumber"];  // this is the unique sritoni idnumber assigned by school
			$username 		= $csvuser["uid"];             // uniques username assigned by school
			$userid   		= $csvuser["id"]; 
			
			if(is_null($va_hset))
			{
				// VA for HSET does'nt exist so create one
				$va_hset 		= $razorpay_api_hset->createVirtualAccount($useridnumber, $username, $userid);
				$count_va_hset	+=1; // increment count	
				echo nl2br("New Virtual Account created for: " . $username . " for HSET payments,     VA ID: " . $va_hset->id . "\n");
			}
			
			if(is_null($va_llp))
			{
				// VA for HSEA-LLP does'nt exist so create one
				$va_llp 		= $razorpay_api_llp->createVirtualAccount($useridnumber, $username, $userid);
				$count_va_llp	+=1; // increment count	
				echo nl2br("New Virtual Account created for: " . $username . " for HSEA-LLP payments, VA ID: " . $va_llp->id  . "\n");
			}
			
			if ($va_hset) // by now this should eist, but just in case the creation didn't work due to some reason
			{
				$beneficiary_name	= $va_hset->receivers[0]->name;
				$va_id				= $va_hset->id;
				$account_number	    = $va_hset->receivers[0]->account_number;
				$va_ifsc_code       = $va_hset->receivers[0]->ifsc;
				$acct_hset = array	(
									"beneficiary_name"  => $beneficiary_name,
									"va_id"             => $va_id,
									"account_number"    => $account_number,
									"va_ifsc_code"      => $va_ifsc_code,
									);
				$accounts[0]	= $acct_hset;
				
			}
			
			if ($va_llp) // by now this should eist, but just in case the creation didn't work due to some reason
			{
				$beneficiary_name	= $va_llp->receivers[0]->name;
				$va_id				= $va_llp->id;
				$account_number	    = $va_llp->receivers[0]->account_number;
				$va_ifsc_code       = $va_llp->receivers[0]->ifsc;
				$acct_hseallp = array	(
									"beneficiary_name"  => $beneficiary_name,
									"va_id"             => $va_id,
									"account_number"    => $account_number,
									"va_ifsc_code"      => $va_ifsc_code,
									);
				$accounts[1]	= $acct_hseallp;
				
			}
			
			if ($accounts) 
			{
				$accounts_json	= json_encode($accounts);
			}
			
			// Get the Moodle profile_field_virtualaccounts for this user to update
			// you may get error if this record has not been set before
			$field = $DB->get_record('user_info_field', array('shortname' => "virtualaccounts"));
			$user_profile_virtualaccounts = $DB->get_record('user_info_data', array(
																					'userid'   =>  $userid,
																					'fieldid'  =>  $field->id,
																					)
															);

			$user_profile_virtualaccounts->data = $accounts_json;
			$DB->update_record('user_info_data', $user_profile_virtualaccounts, $bulk=false);

		}

	unset($csvuser);  // break reference in foreach loop on exit
	

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
