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

	$vAupdate_hset  =   false;       // do not update Virtual account for HSET at Cashfree for existing accounts
    $vAupdate_llp   =   false;       // do not update Virtual account for LLP at Cashfree for existing accounts
    // declare empty array used to populate moodle user profile field with account information
    $accounts       = array();

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
	//----------------------------------- end of section 2 --------------------------------------->

    //-------------------- create new API interfaces section 3-------------------------------->
	$site_name			= " contains hset once";
    try
        {
          // creates a new API instance, autheticates using ID and secret and generates token
          // token is valid for only 5 minutes so make sure this API is done by then
          $pg_api_hset = new CfAutoCollect($site_name);    // create a new API instance
        }
    catch (Exception $e)
        {
          error_log( $e->getMessage() );
          echo nl2br("Error creating cashfree_api instance for HSET: " . $e->getMessage() . "\n");
          return;
        }

    $site_name			= " contains llp once";
    try
        {
          // creates a new API instance, autheticates using ID and secret and generates token
          // token is valid for only 5 minutes so make sure this API is done by then
          $pg_api_llp = new CfAutoCollect($site_name);    // create a new API instance
        }
    catch (Exception $e)
        {
          error_log( $e->getMessage() );
          echo nl2br("Error creating cashfree_api instance for HSEA LLP: " . $e->getMessage() . "\n");
          return;
        }
	//---------------------- end of section 2 dreating API instances ---------->

    // -----begin section 3 for each user in CSV table check if accounts exist->

	// initialize counts of accounts to be added if missing
	$count_va_hset_created	=	0;
	$count_va_llp_created	=	0;

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

	// for each of the csv users extract data from CSV table

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

            // pad moodleuserid with 0's to get vAccountId
            $vAccountId = str_pad($moodleuserid, 4, "0", STR_PAD_LEFT);

            // get details of this HSET account using user'smoodle id
			$vA =  $pg_api_hset->getvAccountGivenId($vAccountId);

            if (empty($vA))
            {	// VA for HSET does'nt exist, so create one
				$vA 	= $pg_api_hset->createVirtualAccount($vAccountId, $fullname, $phone, $email);
                if(vA)
                {   // Account created is not null and so successfull
                    $count_va_hset_created	+= 1; // increment count
                    $accounts[0] = array	(
        									"beneficiary_name"  => "Head Start Educational Trust" ,
        									"va_id"             => $vAccountId ,
        									"account_number"    => $vA->accountNumber ,
        									"va_ifsc_code"      => $vA->ifsc ,
        									);
                }
			}
            else
            {   // the account for HSET already exists, details got by function getvAccountGivenId above
                $accounts[0] = array	(
                                        "beneficiary_name"  => "Head Start Educational Trust" ,
                                        "va_id"             => $vA->vAccountId ,
                                        "account_number"    => $vA->virtualAccountNumber ,
                                        "va_ifsc_code"      => $vA->ifsc ,
                                        );
            }

            // get details of this HSEA LLP account using user'smoodle id
			$vA =  $pg_api_llp->getvAccountGivenId($vAccountId);

            if (empty($vA))
            {	// VA for HSEA LLP does'nt exist, so create one
				$vA 	= $pg_api_llp->createVirtualAccount($vAccountId, $fullname, $phone, $email);
                if(vA)
                {   // Account created is not null and so successfull
                    $count_va_llp_created	+= 1; // increment count
                    $accounts[1] = array	(
        									"beneficiary_name"  => "HSEA LLP" ,
        									"va_id"             => $vAccountId ,
        									"account_number"    => $vA->accountNumber ,
        									"va_ifsc_code"      => $vA->ifsc ,
        									);
                }
			}
            else
            {   // the account for HSEA LLP already exists, details got by function getvAccountGivenId above
                $accounts[1] = array	(
                                        "beneficiary_name"  => "HSEA LLP" ,
                                        "va_id"             => $vA->vAccountId ,
                                        "account_number"    => $vA->virtualAccountNumber ,
                                        "va_ifsc_code"      => $vA->ifsc ,
                                        );
            }
            // we have data for all accounts so print out the full row aith all data
            ?>
                    <tr>
    					<td><?php echo htmlspecialchars($fullname); ?></td>
                        <td><?php echo htmlspecialchars($employeenumber); ?></td>

                        <td><?php echo htmlspecialchars($accounts[0]["va_id"]); ?></td>
                        <td><?php echo htmlspecialchars($accounts[0]["account_number"]); ?></td>
                        <td><?php echo htmlspecialchars($accounts[0]["va_ifsc_code"]); ?></td>

                        <td><?php echo htmlspecialchars($accounts[1]["va_id"]); ?></td>
                        <td><?php echo htmlspecialchars($accounts[1]["account_number"]); ?></td>
                        <td><?php echo htmlspecialchars($accounts[1]["va_ifsc_code"]); ?></td>
                    </tr>
            <?php

			// encode the data into a JSON string for storage to user profile field
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

            // loop for next user, finished for this user
		}

	unset($csvuser);  // break reference in foreach loop on exit

    echo nl2br("Number of SriToni users from report: " . $csvcount . "\n");
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
