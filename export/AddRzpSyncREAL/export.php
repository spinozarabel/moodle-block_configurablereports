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
 * 1.3 08/31/2019 Checks if bank account name or beneficiary is correct for each account created
 * 1.2 08/11/19 checks individually for account for each site and creates account if not present for any site
 * 1.1 uses class defined in sritoni_razorpay_api.php that is useable for both Moodle and Wordpress
 * 1.0 uses razorpay.lib
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
	$virtualAccounts_hset	= $razorpay_api_hset->getAllActiveVirtualAccounts();	// site sritoni.org/hset-payments
	$virtualAccounts_llp	= $razorpay_api_llp->getAllActiveVirtualAccounts();		// site sritoni.org/hsea-llp-payments

	echo nl2br("Number of Present Active Razorpay Virtual Accounts for HSET: " . count($virtualAccounts_hset) . "\n");
	echo nl2br("Number of Present Active Razorpay Virtual Accounts for LLP : " . count($virtualAccounts_llp)  . "\n");

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
			$va_hset	= $moodle_vas_obj[0];
			$va_llp 	= $moodle_vas_obj[1];

			if( $va_hset->va_id == "not assigned" )
			{
				// VA for HSET does'nt exist for this user, so create one
				$va_hset 				 = $razorpay_api_hset->createVirtualAccount($useridnumber, $username, $userid);
				$count_va_hset_created	+=1; // increment count
				// check if newly created Virtual Account has the correct beneficiary
				if ($va_hset->name != "Head Start Educational Trust")
				{
					echo nl2br("Created Virtual Account for: " . $username . " for HSET payments, has wrong beneficiary: " . $va_hset->name . "\n");
					return;
				}
			}

			if( $va_llp->va_id == "not assigned" )
			{
				// VA for HSEA-LLP does'nt exist so create one
				$va_llp 				 = $razorpay_api_llp->createVirtualAccount($useridnumber, $username, $userid);
				$count_va_llp_created	+=1; // increment count
			}

			if ($va_hset) // by now this should eist. Update Moodle profile field for old as well as newly created, just in case changed offline
			{
				$beneficiary_name	= "Head Start Educational Trust";
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
