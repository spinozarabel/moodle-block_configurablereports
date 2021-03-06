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
	require_once($CFG->dirroot."/blocks/configurable_reports/cashfree_api/cfAutoCollect.inc.php");

	$vAupdate_profile =   true;       // flag to update user profile filed or not, with possible new data

	$sim			  =   false;	   // Not a simulation, the real thing!

	//--------------------- end of section 1 -----------------------------------------------------

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

    //-------------------- create new API interfaces section 3-------------------------------->
    // read in comma separated list of site names from config_settings of plugin
    $site_names_config  = get_config('block_configurable_reports', 'site_names') ?? "";
    // if empty exit with error message
    if (empty($site_names_config))
    {
        echo nl2br("Empty config setting for site names, please set in config: "  . "\n");
        return;
    }
    // read in the comma separated site names into an array.
    $site_names_arr     = explode( "," , $site_names_config ) ?? [];

    $num_sites          = count($site_names_arr) ?? 0;
    // return if num_sites is 0
    if (0 == $num_sites)
    {
        echo nl2br("number of sites evaluates to 0, please set correctly in config settings site names: "  . "\n");
        return;
    }

    // read in beneficiary names from config settings into an array
    $account_names_config = get_config('block_configurable_reports', 'account_names') ?? "";
    // if empty exit with error message
    if (empty($account_names_config))
    {
        echo nl2br("Empty config setting for account names, please set in config: "  . "\n");
        return;
    }
    // read in comma separated account names into an array
    $account_nammes_arr = explode( "," , $account_names_config );

    // get the 1st payment site, we know it exists, because if we are here, $num_sites being not 0
    $site_name			= $site_names_arr[0];

    // setup gateway api instance only if sutename is not blank
    if (!empty($site_name))
    {
        try
            {
              // creates a new API instance, autheticates using ID and secret and generates token
              // token is valid for only 5 minutes so make sure this API is done by then
              $pg_api_site1 = new CfAutoCollect($site_name);    // create a new API instance
            }
        catch (Exception $e)
            {
              error_log( $e->getMessage() );
              echo nl2br("Error creating cashfree_api instance for: " . $site_name . " " . $e->getMessage() . "\n");
              return;
            }
    }

    // do the 2nd site only if it exists
    if ($num_sites > 1)
    {
        $site_name	= $site_names_arr[1] ?? "";
        if (!empty($site_name))
        {
            try
                {
                  // creates a new API instance, autheticates using ID and secret and generates token
                  // token is valid for only 5 minutes so make sure this API is done by then
                  $pg_api_site2 = new CfAutoCollect($site_name);    // create a new API instance
                }
            catch (Exception $e)
                {
                  error_log( $e->getMessage() );
                  echo nl2br("Error creating cashfree_api instance for: " . $site_name . " " . $e->getMessage() . "\n");
                  return;
                }
        }
    }
	//---------------------- end of section 2 dreating API instances ---------->

    // -----begin section 3 for each user in CSV table check if accounts exist->

	// initialize counts of accounts to be added if missing
	$count_va_site1_created	=	0;
	$count_va_site2_created	=	0;

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
    			<th><?php echo htmlspecialchars($site_names_arr[0]); ?> VA ID</th>
                <th><?php echo htmlspecialchars($site_names_arr[0]); ?> Account No</th>
                <th><?php echo htmlspecialchars($site_names_arr[0]); ?> IFSC</th>
    			<th><?php echo htmlspecialchars($site_names_arr[1]); ?> VA ID</th>
                <th><?php echo htmlspecialchars($site_names_arr[1]); ?> Account No</th>
                <th><?php echo htmlspecialchars($site_names_arr[1]); ?> IFSC</th>
    		</tr>
    <?php

	// for each of the csv users extract data from CSV table

	foreach ($csv as $key => $csvuser)
		{
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

            // get details of this Site1 account using user'smoodle id padded to 4 digits
			$vA =  $pg_api_site1->getvAccountGivenId($vAccountId);

			switch (true)
			{

				case (empty($vA) && !$sim):
					// VA doesn't exist, this is not a Sim, so let's create a new VA
					$vA 	= $pg_api_site1->createVirtualAccount($vAccountId, $fullname, $phone, $email);
					// increment count
					$count_va_site1_created	+= 1;
                    $account_number         = $vA->accountNumber ?? "Error in creation?";
                    $ifsc                   = $vA->ifsc ?? "Error in creation?";

                    $accounts_site1 = array	(
        									"beneficiary_name"  => $account_nammes_arr[0] ,
        									"va_id"             => $vAccountId ,
        									"account_number"    => $account_number ,
        									"va_ifsc_code"      => $ifsc ,
        									);
                    $accounts[$site_names_arr[0]]    = $accounts_site1;
				break;

				case (empty($vA) && $sim):
					// Account doesn't exist so only created in simulation
					// This is a Sim so new account only simulation created
					$count_va_site1_created	+= 1; // increment count


                    $accounts_site1 = array	(
        									"beneficiary_name"  => $account_nammes_arr[0] ,
        									"va_id"             => "To be created" ,
        									"account_number"    => "To be created" ,
        									"va_ifsc_code"      => "To be created" ,
        									);
                    $accounts[$site_names_arr[0]]    = $accounts_site1;
				break;

				case ($vA):
					// the account for Site1 already exists, details got by function getvAccountGivenId above
					// this can also refresh details pushed to Moodle profile field
					$account_number         = $vA->virtualAccountNumber ?? "Error in reading?";
					$ifsc                   = $vA->ifsc ?? "Error in reading?";

					$accounts_site1 = array	(
											"beneficiary_name"  => $account_nammes_arr[0] ,
											"va_id"             => $vA->vAccountId ,
											"account_number"    => $account_number ,
											"va_ifsc_code"      => $ifsc ,
											);
					$accounts[$site_names_arr[0]]    = $accounts_site1;
				break;
			}

			// get details of Site2 account for user only if pg_api_site2 instance exists
			// this automatically implies that num_sites = 2
            if (!empty($pg_api_site2))
            {
    			$vA =  $pg_api_site2->getvAccountGivenId($vAccountId);
            }

			switch (true)
			{
				case ($num_sites < 2):
					// 2nd site is not specified so make all data NA for this site just for dummy display
					$accounts_site2 = array	(
                                        "beneficiary_name"  => 'NA' ,
                                        "va_id"             => "NA" ,
                                        "account_number"    => "NA" ,
                                        "va_ifsc_code"      => "NA" ,
                                        );
				break;

				case (empty($vA) && !$sim):
					// VA doesn't exist, this is not a Sim, so let's create a new VA
					$vA 	= $pg_api_site2->createVirtualAccount($vAccountId, $fullname, $phone, $email);
					// increment count
					$count_va_site2_created	+= 1;
                    $account_number         = $vA->accountNumber ?? "Error in creation?";
                    $ifsc                   = $vA->ifsc ?? "Error in creation?";

                    $accounts_site2 = array	(
        									"beneficiary_name"  => $account_nammes_arr[1] ,
        									"va_id"             => $vAccountId ,
        									"account_number"    => $account_number ,
        									"va_ifsc_code"      => $ifsc ,
        									);
                    $accounts[$site_names_arr[1]]    = $accounts_site2;
				break;

				case (empty($vA) && $sim):
					// Account doesn't exist so only created in simulation
					// This is a Sim so new account only simulation created
					$count_va_site2_created	+= 1; // increment count


                    $accounts_site2 = array	(
        									"beneficiary_name"  => $account_nammes_arr[1] ,
        									"va_id"             => "To be created" ,
        									"account_number"    => "To be created" ,
        									"va_ifsc_code"      => "To be created" ,
        									);
                    $accounts[$site_names_arr[1]]    = $accounts_site2;
				break;

				case ($vA):
					// the account for Site2 already exists, details got by function getvAccountGivenId above
					// this can also refresh details pushed to Moodle profile field
					$account_number         = $vA->virtualAccountNumber ?? "Error in reading?";
					$ifsc                   = $vA->ifsc ?? "Error in reading?";

					$accounts_site2 = array	(
											"beneficiary_name"  => $account_nammes_arr[1] ,
											"va_id"             => $vA->vAccountId ,
											"account_number"    => $account_number ,
											"va_ifsc_code"      => $ifsc ,
											);
					$accounts[$site_names_arr[1]]    = $accounts_site2;
				break;
			}

            // we have data for all accounts so print out the full row aith all data
            ?>
                    <tr>
    					<td><?php echo htmlspecialchars($fullname); ?></td>
                        <td><?php echo htmlspecialchars($employeenumber); ?></td>

                        <td><?php echo htmlspecialchars($accounts[$site_names_arr[0]]["va_id"]); ?></td>
                        <td><?php echo htmlspecialchars($accounts[$site_names_arr[0]]["account_number"]); ?></td>
                        <td><?php echo htmlspecialchars($accounts[$site_names_arr[0]]["va_ifsc_code"]); ?></td>

                        <td><?php echo htmlspecialchars($accounts[$site_names_arr[1]]["va_id"]); ?></td>
                        <td><?php echo htmlspecialchars($accounts[$site_names_arr[1]]["account_number"]); ?></td>
                        <td><?php echo htmlspecialchars($accounts[$site_names_arr[1]]["va_ifsc_code"]); ?></td>
                    </tr>
            <?php

			// encode the data into a JSON string for storage to user profile field
			if ($accounts)
			{
				$accounts_json	= json_encode($accounts);
			}

            // only update user profile field with new data if not a simulation and update flag is TRUE
            if ($vAupdate_profile)
            {
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

            // loop for next user, finished for this user
		}

	unset($csvuser);  // break reference in foreach loop on exit

    echo nl2br("Number of SriToni users from report: " . $csvcount . "\n");
	echo nl2br("Number of new Virtual Accounts created for Site1: " . $count_va_site1_created . "\n");
	echo nl2br("Number of new Virtual Accounts created for Site2: " . $count_va_site2_created . "\n");

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
