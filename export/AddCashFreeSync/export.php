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
 * @date: 09/28/2019
 * This is the Cashfree account sync_add version 1.0
 * Adds Cashfree Virtual Account for all students if not already existing
 * Adds accounts for HSET and HSEA-LLP and updates profile_field_virtualaccounts with JSON encoding
 *
 */

function export_report($report)
{
    global $DB, $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');
	require_once($CFG->dirroot."/blocks/configurable_reports/cashfree_api/cfAutoCollect.inc.php");

	$vAupdate_hset  =   false;       // do not update Virtual account for HSET
    $vAupdate_llp   =   false;       // do not update Virtual account for LLP

	//$site_name			= " contains llp once";
	//$razorpay_api_llp 	= new sritoni_razorpay_api($site_name);
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

    //-------------------- create new API interfaces section 3-------------------------------->
	$site_name			= " contains hset once";
    try
        {
          $pg_api_hset = new CfAutoCollect($site_name);    // create a new API instance
        }
    catch (Exception $e)
        {
          error_log( $e->getMessage() );
          echo nl2br("Error creating cashfree_api instance: " . $e->getMessage() . "\n");
        }
	//---------------------- end of section 2 --------------------------------------------->

    // -------begin section 3 Fetch all virtual accounts ----------------------->
	$vAccounts_hset	= $pg_api_hset->listAllVirtualAccounts();
	//$virtualAccounts_llp	= $razorpay_api_llp->getAllActiveVirtualAccounts();		// site sritoni.org/hsea-llp-payments

	//echo nl2br("Number of Present Active Razorpay Virtual Accounts for HSET: " . count($virtualAccounts_hset) . "\n");
	//echo nl2br("Number of Present Active Razorpay Virtual Accounts for LLP : " . count($virtualAccounts_llp)  . "\n");

	// initialize counts of accounts to be added if missing
	$count_va_hset_created	=	0;
	$count_va_llp_created	=	0;

	// for each of the csv users extract data to create new VA

	foreach ($csv as $key => $csvuser)
		{
			// get student id number
			$employeenumber = $csvuser["employeenumber"];	// this is the unique sritoni idnumber assigned by school
			$fullname 		= $csvuser["displayname"];		// full name in SriToni
			$moodleuserid   = $csvuser["id"];				// unique id used internally by Moodle in the user tables
            $phone          = $csvuser["phone"];            // mobile of user
            $email          = $csvuser["mail"];
            $moodleusername = $csvuser["uid"];              // sritoni username issued by school
            if (strlen($phone) !=10)
            {
                $phone  = "1234567890";     // phone dummy number
            }

			//$va_llp  = $razorpay_api_llp->getVirtualAccountGivenSritoniId( $useridnumber, $virtualAccounts_llp);
            // pad moodleuserid with 0's to get vAccountId
            $vAccountId = str_pad($moodleuserid, 4, "0", STR_PAD_LEFT);
            // check if this account ID exists in the list of accounts
			$vAccountExists =  $pg_api_hset->vAExists($vAccountId, $vAccounts_hset); // boolean value

            if (!$vAccountExists)
            {		// VA for HSET does'nt exist for this user, so create one
				$vA_hset 	= $pg_api_hset->createVirtualAccount($moodleuserid, $fullname, $phone, $email);
				$count_va_hset_created	+=1; // increment count
				echo nl2br("New VA for HSET created for: " . $moodleusername .
                    " accountNumber: " . $vA_hset->accountNumber .
                    " IFSC: " . $vA_hset->ifsc . "\n");
			}
			/*
			if(is_null($va_llp))
			{
				// VA for HSEA-LLP does'nt exist so create one
				$va_llp 				 = $razorpay_api_llp->createVirtualAccount($useridnumber, $username, $userid);
				$count_va_llp_created	+=1; // increment count
				echo nl2br("New Virtual Account created for: " . $username . " for HSEA-LLP payments, VA ID: " . $va_llp->id  . "\n");
			}
            */

			if ($va_hset) // by now this should eist. Update Moodle Profile Field with latest info of VA
			{
				$beneficiary_name	= "Head Start Educational Trust";
				$va_id				= $vAccountId;
				$account_number	    = $vA_hset->accountNumber;
				$va_ifsc_code       = $vA_hset->ifsc;
				$acct_hset = array	(
									"beneficiary_name"  => $beneficiary_name,
									"va_id"             => $va_id,
									"account_number"    => $account_number,
									"va_ifsc_code"      => $va_ifsc_code,
									);
				$accounts[0]	= $acct_hset;

			}
			/*
			if ($va_llp) // by now this should eist. Update Moodle profile field for old as well as newly created, just in case changed offline
			{
				$beneficiary_name	= "HSEA LLP";
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
			*/
			if ($accounts)
			{
				$accounts_json	= json_encode($accounts);
			}

			// Get the Moodle profile_field_virtualaccounts for this user to update
			// you may get error if this record has not been set before
			$field = $DB->get_record('user_info_field', array('shortname' => "virtualaccounts"));
			$user_profile_virtualaccounts = $DB->get_record('user_info_data', array(
																					'userid'   =>  $moodleuserid,
																					'fieldid'  =>  $field->id,
																					)
															);

			$user_profile_virtualaccounts->data = $accounts_json;
			$DB->update_record('user_info_data', $user_profile_virtualaccounts, $bulk=false);

		}

	unset($csvuser);  // break reference in foreach loop on exit

	echo nl2br("Number of new Virtual Accounts created for HSET: " . $count_va_hset_created . "\n");
	echo nl2br("Number of new Virtual Accounts created for HSEA LLP: " . $count_va_llp_created . "\n");

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
