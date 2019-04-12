<?php

defined('MOODLE_INTERNAL') || die();
//defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// lib version 1.0
//     version 1.1 Added systemid to craete account function

/** getAllActiveVirtualAccounts($api_key, $api_secret)
*   
*   returns all active Virtual Accounts in Razorpay 
*   
*/
function getAllActiveVirtualAccounts($api_key, $api_secret)
{
    //first Fetch all virtual accounts from Razorpay as a collection
	$virtualAccounts = getAllVirtualAccounts($api_key, $api_secret)->items;
	
	
	// remove all closed accounts from the returned object $virtualAccounts
	foreach ($virtualAccounts as $key => $va) 
		{
			if($va->status == "closed") 
				{
				unset($virtualAccounts[$key]);
				}
		}
		unset($va); // break reference of previous foreach
		
		
	return $virtualAccounts;
}

/** getVirtualAccountGivenSritoniId($useridnumber, $virtualAccounts)
*   given a student useridnumber, and the array of active virtual accounts,
*   returns the corresponding virtual account. 
*   if not found, returns null
*   ver 1.1
*/
function getVirtualAccountGivenSritoniId($useridnumber, $virtualAccounts)
{
	$item = null;
	foreach($virtualAccounts as $va) 
	{
		if ($useridnumber == $va->notes->idnumber) 
		{
			$item = $va;
			return $item;
		}
	}
	unset ($va); // break for each reference
}

/** getLastPayment($vaid, $api_key, $api_secret)
*   gets Last payment associated with a given virtual account id
*   returns a valid payment object or null if no payments found for VA with this id
*
*/
function getLastPayment($vaid, $api_key, $api_secret)
{
	//$last_payment = null; // initialze to null
	
	$payments_collection = getPayments($vaid, $api_key, $api_secret);  // get all payments as collection for this VAid
	
	if ($payments_collection->count)
	{
		$last_payment = $payments_collection->items[0]; // assumes latest payment is 1st item in payment collection	
	}
	return $last_payment;
}

/** getPayments($vaid, $api_key, $api_secret)
*   gets all payments associated with a given virtual account id
*   returns a collection, see https://razorpay.com/docs/smart-collect/api/#fetch-all
*
*/
function getPayments($vaid, $api_key, $api_secret)
{
	$url = "https://api.razorpay.com/v1/virtual_accounts/" . $vaid . "/payments";
	$collection = getDatafromServerUsingCurl( $url, $api_key, $api_secret );
	return $collection;
}

/** getAllVirtualAccounts()
*   gets all virtual accounts as a collection
*  
*
*/
function getAllVirtualAccounts($api_key, $api_secret)
{
	$url = "https://api.razorpay.com/v1/virtual_accounts";
	$virtualAccounts = getDatafromServerUsingCurl( $url, $api_key, $api_secret );
	return $virtualAccounts;
}

/** createVirtualAccount() creates a new razorpay virtual account
*	$idnumber is the unique sritoni idnumber given to all students and staff by school
*   $username is the unique user name, also the login
*   $id is the unique system id that is used in the tables for user internally by Moodle
*/
function createVirtualAccount($api_key, $api_secret, $idnumber, $username, $id)
{
	$url = "https://api.razorpay.com/v1/virtual_accounts";
	$post = array(
					'receivers' => array('types' => array(
															'bank_account'
														 )
										), 
					'description' 	 => 'Virtual Account for ' . $username, 
					'notes' 		 => array(
												'idnumber' => $idnumber,
												'id'	   => $id,
											  )
				 );
	$virtualAccount = postDataToServerUsingCurl( $post, $url, $api_key, $api_secret );
	return $virtualAccount;
}

/** getDatafromServerUsingCurl( $url, $api_key, $api_secret )
* This function uses curl using POST method to send data to server
* It returns the result of the trasaction as a stdclass object
* @param $post is an associative array containing the parameters as required by Razorpay
* @param $url is the URL of razorpay
* @param $api_key is the key ID sepcified by Razorpay for account being used
* @param $api_secret is the corresponding secret issued by Razorpay
*/
function getDatafromServerUsingCurl( $url, $api_key, $api_secret )
{
    $options = array(
		CURLOPT_URL			   => $url,
		CURLOPT_USERPWD		   => $api_key . ":" . $api_secret,
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_CONNECTTIMEOUT => 20,      // timeout on connect
        CURLOPT_TIMEOUT        => 120,      // timeout on response
        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
        CURLOPT_SSL_VERIFYPEER => true,     // enable SSL Cert checks
		CURLOPT_SSL_VERIFYHOST => 2,
		CURLOPT_SSLVERSION	   => CURL_SSLVERSION_TLSv1_2
    );
	
    $ch     = curl_init( $url );
    curl_setopt_array( $ch, $options );
    $result = curl_exec( $ch );
	if (curl_errno($ch)) 
	  {
		echo 'Error:' . curl_error($ch);
      }
    curl_close( $ch );
    return json_decode($result);
}


/** postDataToServerUsingCurl( $post, $url, $api_key, $api_secret )
* This function uses curl using POST method to send data to server
* It returns the result of the trasaction as a stdclass object
* @param $post is an associative array containing the parameters as required by Razorpay
* @param $url is the URL of razorpay
* @param $api_key is the key ID sepcified by Razorpay for account being used
* @param $api_secret is the corresponding secret issued by Razorpay
*/
function postDataToServerUsingCurl( $post, $url, $api_key, $api_secret )
{
	$post_json  = json_encode($post);
	$headers    = array();
    $headers[]  = "Content-Type: application/json";
    $options = array(
	    CURLOPT_POST		   => true,
		CURLOPT_POSTFIELDS	   => $post_json,
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
	
    $ch     = curl_init( $url );
    curl_setopt_array( $ch, $options );
    $result = curl_exec( $ch );
	if (curl_errno($ch)) 
	  {
		echo 'Error:' . curl_error($ch);
      }
    curl_close( $ch );
    return json_decode($result);
}

