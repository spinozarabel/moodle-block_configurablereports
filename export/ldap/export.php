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

/**
 * Configurable Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @date: 2009
 * This is the sync version with HTML output
 */

function export_report($report) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');

    $table    = $report->table;
    $matrix   = array();
    $filename = 'report';

    if (!empty($table->head)) {
        $countcols = count($table->head);
        $keys      = array_keys($table->head);
        $lastkey   = end($keys);
        foreach ($table->head as $key => $heading) {
            $matrix[0][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($heading))));
        }
    }

    if (!empty($table->data)) {
        foreach ($table->data as $rkey => $row) {
            foreach ($row as $key => $item) {
                $matrix[$rkey + 1][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($item))));
            }
        }
    }
    // Here is where we start the code for LDAP sync
    // ver 1.2 assumes LDAP has 2 organization units, ou=student and ou=employee
// added capability to modify replace LDAP attributes using CSV data
// config
// the following 3 flags control simulation or actual operation
$flag_add_simulate 		=	  false;
$flag_del_simulate		=	  false;
// the following flags control actual (not simulated) operations
$flag_mod_users			  = 	true;			# this allows users's LDAP attributes to be updated to that in the CSV file
$flag_add_users 		  = 	true;			# this allows the code to add users that don't exist yet in LDAP directory
$flag_delete_users 		= 	true ;			# This allows the code to delete LDAP users that don't exist in the CSV file
//
$ldapserver 			= 	'ldaps://rahilmahmood.com';
$ldapuser   			= 	'cn=admin,dc=headstart,dc=edu,dc=in';
$ldappass   			= 	'password';
$ldaptree   			= 	"dc=headstart,dc=edu,dc=in";
$ldapfilter 			= 	"(objectClass=inetOrgPerson)";	# tailor this to your need
//
// connect
$ldapconn = ldap_connect($ldapserver) or die("Could not connect to LDAP server.");
//
if($ldapconn) {
    // binding to ldap server
	// but first set protocol version
	ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
	//
  $ldapbind = ldap_bind($ldapconn, $ldapuser, $ldappass) or die ("Error trying to bind: ".ldap_error($ldapconn));
  // verify binding and if good search and download entries based on filter set below
  if ($ldapbind) {
    echo nl2br("LDAP Connection and Authenticated bind successful...\n");
    // $ldapsearch contains the search, $data contains all the entries
    //
    $result = ldap_search($ldapconn,$ldaptree, $ldapfilter) or die ("Error in search query: ".ldap_error($ldapconn));
    $data = ldap_get_entries($ldapconn, $result);
    //
    // print number of entries found
		$ldapcount = ldap_count_entries($ldapconn, $result);
    echo nl2br("Number of entries found in LDAP directory: " . $ldapcount . "\n");
  }
	else {
    echo "LDAP bind failed...";
  }
  // convert entries to associative type using cleanup function as given on php manual
	$ldapentries = cleanUpEntry( $data );
	// print sample entry to check format
	//$dn = "uid=sritoni1,ou=student,dc=headstart,dc=edu,dc=in";
	//echo "sample dn from ldap entries " . $dn . "\n";
	//print_r($ldapentries[$dn]);
	//
  // Read in the CSV file generated from Configurable reports fo accounts on SriToni
		//
    //$csv = array_map('str_getcsv', file($csvfile));
    $csv   =  $matrix;  # instead of downloading and parsing, we are reusing
		//
    array_walk($csv, function(&$a) use ($csv) {
      $a = array_combine($csv[0], $a);
    	});
		array_shift($csv); # remove column header
	 	//
		// find number of entries extracted from CSV into array
    $csvcount = count($csv);
		//
    //print_r($csv[0]);
		echo nl2br("Number of CSV entries found: " . $csvcount . "\n");
	//
  //
  // Now start to synchonize LDAP with SriToni CSV data
	// lets see if each entry in CSV is present in LDAP data
	// This is to add users into LDAP
	//
	$addcount 			= 0;	# keeps track of number of users added to LDAP
	$notaddcount 		= 0; # keeps track of users not added due to problems
	//$sim_add_count 	= 0; # keeps track of simulated user additions not needed now
	//$sim_del_count 	= 0; # keeps track of simulated user deletions not needed now
	//
	if ($flag_add_users) {
		// if this flag is set we can go ahead and add any missing users into LDAP
		// provided the simulation flag is true
    for ( $i = 0; $i < $csvcount; $i++ ) {
			$csvuid = $csv[$i]["uid"];
			if (strpos($csv[$i]["ou"] , "Teaching") !== false) { # does ou contain "Teaching"?
				$ou = "employee";  # if so add to organization unit ou = employee
			}
			if (strpos($csv[$i]["ou"] , "Student") !== false) { # does ou contain "Student"?
				$ou = "student";  # if so add to organization unit ou = student
			}
			$csvdn = "uid=" . $csvuid . ",ou=" . $ou . ",dc=headstart,dc=edu,dc=in";
			//echo $csvdn . "\n";
			if (!array_key_exists($csvdn, $ldapentries)) {
				//echo "User not in LDAP, need to add user with uid: " . $csv[$i]["uid"] . "\n" ;
				// csvuser is not in LDAP and needs to be added to LDAP
				$entry = $csv[$i];
			  //
				if ($flag_add_simulate == false) {
					$add = ldap_add($ldapconn,$csvdn,$entry);
					if ($add) {
						$addcount = $addcount + 1;
						//echo "user with dn: " . $csvdn . "successfully added to LDAP server" . "\n";
					} else {
						echo "user with dn: " . $csvdn . " could not be added to LDAP server, check for blank fields" . "\n";
						$notaddcount = $notaddcount + 1;
					}
				} else {
						$sim_add_count	=	$sim_add_count + 1;  # increment simulated user addition
				}
			}  # if end array_key_exists
		}  # for end $i loop
	}  # if end $flag_add_users
	if ($flag_add_simulate == false) {
		echo nl2br("A total of " . $addcount . " Sritoni users were added to the LDAP server directory" . "\n");
		if ($notaddcount > 0) {
      echo nl2br("A total of " . $notaddcount . " Sritoni users could'nt be added LDAP, check for blank fields" . "\n");
    }
	} else {
			echo nl2br(" A total of " . $sim_add_count . " Sritoni users were simulation added to the LDAP server directory" . "\n");
	}
	//
	// This is to remove users from LDAP not present in the sritoni CSV file
	// For this we begin by checking to see if LDAP user is present in sritoni user data
	//
	$delcount = 0;	# keeps track of number of users deleted from LDAP
	$notdelcount = 0; # keeps track of users not deletable due to unforseen problems
	//
	if ($flag_delete_users) {
		// get the keys of $ldapentries into an array
		$keys_ldapentries = array_keys($ldapentries);
		//
		for ( $i = 0; $i < $ldapcount; $i++ ) {
			$ldapuid = $ldapentries[$keys_ldapentries[$i]]["uid"];  # this is the uid of the indexed user in ldap array
			// print_r($ldapuid);
			// lets check if this uid is present in the sritoni user csv data
			if (!in_array($ldapuid, array_column($csv,"uid"))) {
				// delete this user if delete flag is set otherwise simulate deletion
				if ($flag_del_simulate == false) {
					$ldapuserdn = $keys_ldapentries[$i];
					// the key of the ldapentry carries the dn of that entry so very easy to get dn
					$del = ldap_delete($ldapconn,$ldapuserdn);
					//
					if ($del) {
						$delcount = $delcount + 1;
						//echo "user with dn: " . $csvdn . "successfully added to LDAP server" . "\n";
					} else {
						echo nl2br("user with dn: " . $ldapuser . " could not be deleted from LDAP server, check for problems" . "\n");
						$notdelcount = $notdelcount + 1;
					}
				}  else {  # end if flagdel simulate check
						$sim_del_count	=	$sim_del_count	+	1;
					}
			}  # end if in array check
		}  # end for loop
	}   # end if del flag checking
	if ($flag_del_simulate == false) {
		echo nl2br(" A total of " . $delcount . " LDAP users were deleted from the LDAP server directory" . "\n");
		if ($notdelcount > 0) {
      echo nl2br(" A total of " . $notdelcount . " LDAP users could not be deleted from the LDAP directory..." . "\n");
    }
	} else {
			echo nl2br(" A total of " . $sim_del_count . " LDAP users were simulation deleted from the LDAP server directory" . "\n");
	}
	//
	// now check to see if attributes need to be updated
	$modcount = 0;	# keeps track of number of LDAP users modified
	$notmodcount = 0; # keeps track of LDAP users not modifiable due to unforseen problems
	//
  if ($flag_mod_users) {
		//
		for ( $i = 0; $i < $csvcount; $i++ ) {
			$csvuid = $csv[$i]["uid"];
			if (strpos($csv[$i]["ou"] , "Teaching") !== false) {
				$ou = "employee";
			}
			if (strpos($csv[$i]["ou"] , "Student") !== false) {
				$ou = "student";
			}
			$csvdn = "uid=" . $csvuid . ",ou=" . $ou . ",dc=headstart,dc=edu,dc=in";
			//echo $csvdn . "\n";
			if (array_key_exists($csvdn, $ldapentries)) {
				// this means that a CSV user is present in LDAP directory
				// We can sync the attributes from CSV to LDAP if flag is set
				$entry = $csv[$i];
				//
				$mod = ldap_mod_replace($ldapconn,$csvdn,$entry);
				if ($mod) {
					$modcount = $modcount + 1;
					//echo "user with dn: " . $csvdn . "successfully modified in LDAP server" . "\n";
				} else {
					echo nl2br("user with dn: " . $csvdn . " could not be modified in LDAP server, reason unknown" . "\n");
					$notmodcount = $notmodcount + 1;
				}
			}  # end if array_exists
		}  # end for loop
	}  # end if flag check
	echo nl2br(" A total of " . $modcount . " LDAP users' attributes were sync'd from the CSV data" . "\n");
	echo nl2br(" A total of " . $notaddcount . " LDAP users' attributes were NOT sync'd from the CSV data, check for problems" . "\n");
}  # if end $ldapconn
// all done? clean up
ldap_close($ldapconn);
    // Here is where we end the LDAP sync code
    exit;
}
//
// Theis function takes an entry downloaded from LDAP and cleans it up
// to make it an associative array.
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
