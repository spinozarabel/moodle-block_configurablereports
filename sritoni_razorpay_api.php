<?php

if (!defined( "ABSPATH" ) && !defined( "MOODLE_INTERNAL" ) )
{
	die( 'No script kiddies please!' );
}

/**
 * sritoni razorpay api class
 * version 1.1
 * @author Madhu <madhu.avasarala@gmail.com>
 * This class is valid for both Moodle and Wordpress
 * In Wordpress since it is used in a multi-site, we have separate sites for HSET and HSEA-LLP and get_option() information depends on site
 * In Moodle, we need to pass the $site_name to enable it to load correct config values
 */
class sritoni_razorpay_api 
{	
	
	public function __construct( $site_name = null ) 
	{
		if (defined("ABSPATH")
		{
			// we are in wordpress environment, don't care about argument since get_option is site dependendent
			$api_key		= $this->getoption("sritoni_settings", "razorpay_key");
			$api_secret		= $this->getoption("sritoni_settings", "razorpay_secret");
		}
		
		if (defined("MOODLE_INTERNAL")
		{
			// we are in MOODLE environment
			// based on passed in $site_name change the strings for config select. $site must be passed correctlt for this to work
			if (stripos($site_name, 'hset') !== false)
			{
				$key_string 	= 'razorpay_api_key_hset';
				$secret_string 	= 'razorpay_api_secret_hset';
			}
			
			if (stripos($site_name, 'llp') !== false)
			{
				$key_string 	= 'razorpay_api_key_llp';
				$secret_string 	= 'razorpay_api_secret_llp';
			}
			
			$api_key		= get_config('block_configurable_reports', $key_string);
			$api_secret		= get_config('block_configurable_reports', $secret_string);
		}
		$this->password	= $api_key . ":" . $api_secret;
	}
	
	public function getoption($optionGroup, $optionField)
	{
		return get_option( $optionGroup)[$optionField];
	}
	
	/** getAllActiveVirtualAccounts($api_key, $api_secret)
	*   
	*   returns all active Virtual Accounts in Razorpay 
	*   
	*/
	function getAllActiveVirtualAccounts()
	{
		//$api_key		= $this->razorpay_key;
		//$api_secret		= $this->razorpay_secret;
		//first Fetch all virtual accounts from Razorpay as a collection
		$virtualAccounts = $this->getAllVirtualAccounts()->items;		
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
	
	/** getAllVirtualAccounts()
	*   gets all virtual accounts as a collection
	*  
	*
	*/
	function getAllVirtualAccounts()
	{
		
		$rel_url = "virtual_accounts";
		
		$virtualAccounts = $this->getDatafromServerUsingCurl( $rel_url );
		
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
	
	/** getPayments($vaid)
	*   @param $vaid is the id of the virtual account for which we need to get the payments of
	*   gets all payments associated with a given virtual account id
	*   returns a collection, see https://razorpay.com/docs/smart-collect/api/#fetch-all
	*
	*/
	function getPayments($vaid)
	{
		$rel_url = "virtual_accounts/" . $vaid . "/payments";
		return $this->getDatafromServerUsingCurl( $rel_url );
	} 
	
	/** getPaymentDetails($payment_id)
	* 
	*   
	*
	*/
	function getPaymentDetails($payment_id)
	{
		$rel_url = "payments/" . $payment_id . "/bank_transfer";
		return $this->getDatafromServerUsingCurl( $rel_url );
	}
	
	/** getLastPayment($vaid)
	*   gets Last payment associated with a given virtual account id
	*   returns a valid payment object or null if no payments found for VA with this id
	*
	*/
	function getLastPayment($vaid)
	{
		//$last_payment = null; // initialze to null
		
		$payments_collection = $this->getPayments($vaid);  // get all payments as collection for this VAid
		
		if ($payments_collection->count)
		{
			$last_payment = $payments_collection->items[0]; // assumes latest payment is 1st item in payment collection	
		}
		return $last_payment;
	}
	
	/** createVirtualAccount() creates a new razorpay virtual account
	*
	*
	*
	*/
	function createVirtualAccount($useridnumber, $username, $userid)
	{
		$rel_url = "virtual_accounts";
		$post = array(
						'receivers' => array('types' => array(
																'bank_account'
															 )
											), 
						'description' 	 => 'Virtual Account for ' . $username, 
						'notes' 		 => array(
													'idnumber' => $useridnumber,
													'id'	   => $userid,
												  )
					 );
		$virtualAccount = $this->postDataToServerUsingCurl( $post, $rel_url );
		return $virtualAccount;
	}
	
	/** getDatafromServerUsingCurl( $url, $api_key, $api_secret )
	* This function uses curl using POST method to send data to server
	* It returns the result of the trasaction as a stdclass object
	* @param $post is an associative array containing the parameters as required by Razorpay
	* @param $rel_url is the relative URL of razorpay
	*/
	function getDatafromServerUsingCurl( $rel_url )
	{
		$url	 = "https://api.razorpay.com/v1/" . $rel_url;
		$options = array(
			CURLOPT_URL			   => $url,
			CURLOPT_USERPWD		   => $this->password,
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
	function postDataToServerUsingCurl( $post, $rel_url )
	{
		$url		= "https://api.razorpay.com/v1/" . $rel_url;
		$post_json  = json_encode($post);
		$headers    = array();
		$headers[]  = "Content-Type: application/json";
		$options = array(
			CURLOPT_POST		   => true,
			CURLOPT_POSTFIELDS	   => $post_json,
			CURLOPT_URL			   => $url,
			CURLOPT_USERPWD		   => $this->password,
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

	
}