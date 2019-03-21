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

function export_report($report) 
{
    global $DB, $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');
	
	$api_key 	= "api key here";
	$api_secret = "api secret here";

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
	echo nl2br("Number of SriToni entries found: " . $csvcount . "\n");
	
    // create virtual accounts. For each account create the data needed in an array
    $va_constructor = array(
							'receivers' => array('types' => array(
																	'bank_account'
																 )
												), 
							'description' 	 => 'Virtual Account for Sritoni2 Moodle2', 
							'notes' 		 => array(
														'idnumber' => '00_00-02'
													 )
							);
	$post = json_encode($va_constructor);
	$url = "https://api.razorpay.com/v1/virtual_accounts";
	
	$virtualAccount  = create_virtualaccount($url, $post);
	$result_array = json_decode($virtualAccount);
	
	//
	print_r($result_array);
	exit;
}

/*
*
*
*
*/
function create_virtualaccount( $post, $url, $api_key, $api_secret )
{
	$headers    = array();
    $headers[]  = "Content-Type: application/json";
    $options = array(
	    CURLOPT_POST		   => true,
		CURLOPT_POSTFIELDS	   => $post,
		CURLOPT_URL			   => $url,
		CURLOPT_USERPWD		   => $api_key . ":" . $api_secret,
        CURLOPT_RETURNTRANSFER => true,     // return web page
		CURLOPT_HTTPHEADER	   => $headers,
//      CURLOPT_HEADER         => false,    // don't return headers
//      CURLOPT_FOLLOWLOCATION => true,     // follow redirects
//      CURLOPT_ENCODING       => "",       // handle all encodings
//      CURLOPT_USERAGENT      => "spider", // who am i
//      CURLOPT_AUTOREFERER    => true,     // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 20,      // timeout on connect
        CURLOPT_TIMEOUT        => 120,      // timeout on response
        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
        CURLOPT_SSL_VERIFYPEER => true,     // enable SSL Cert checks
		CURLOPT_SSL_VERIFYHOST => 2,
		CURLOPT_SSLVERSION	   => CURL_SSLVERSION_TLSv1_2
    );
	
    $ch      = curl_init( $url );
    curl_setopt_array( $ch, $options );
    $result = curl_exec( $ch );
	if (curl_errno($ch)) 
	  {
		echo 'Error:' . curl_error($ch);
      }
    curl_close( $ch );
    return $result;
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
