<?php
/* Modified by Madhu Avasarala 10/06/2019
* ver 1.2 add Moodle and WP compatibility and get settings appropriately
*         all data returned as objects instead of arrays in json_decode
*/

// if directly called die. Use standard WP and Moodle practices
if (!defined( "ABSPATH" ) && !defined( "MOODLE_INTERNAL" ) )
    {
    	die( 'No script kiddies please!' );
    }

// class definition begins
class CfAutoCollect
{
    protected $token;
    protected $baseUrl;
    protected $clientId;
    protected $clientSecret;

    const TEST_PRODUCTION  = "TEST";
    const VERBOSE          = true;

    public function __construct($site_name = null)
    {
        $this->verbose      = self::VERBOSE;

        if ( defined("ABSPATH") )
		{
			// we are in wordpress environment, don't care about $site_name since get_option is site dependendent
            // ensure key and sercret set correctly no check is made wether set or not
            // Make sure these work for Virtual Account API
			$api_key		= $this->getoption("sritoni_settings", "cashfree_key");
			$api_secret		= $this->getoption("sritoni_settings", "cashfree_secret");
		}

        if ( defined("MOODLE_INTERNAL") )
		{
			// we are in MOODLE environment
			// based on passed in $site_name change the strings for config select.
            // $site must be passed correctlt for this to work, no check is made
            // make sure these definitions are same as in configurable_reports plugin settings
			if (stripos($site_name, 'hset') !== false)
			{
				$key_string 	= 'pg_api_key_hset';
				$secret_string 	= 'pg_api_secret_hset';
			}

			if (stripos($site_name, 'llp') !== false)
			{
				$key_string 	= 'pg_api_key_llp';
				$secret_string 	= 'pg_api_secret_llp';
			}

			$api_key		= get_config('block_configurable_reports', $key_string);
			$api_secret		= get_config('block_configurable_reports', $secret_string);
		}

        // add these as properties of object
        $this->clientId		= $api_key;
		$this->clientSecret	= $api_secret;

        $stage = self::TEST_PRODUCTION;

        if ($stage == "PROD")
        {
          $this->baseUrl = "https://cac-api.cashfree.com/cac/v1";
        } else {
          $this->baseUrl = "https://cac-gamma.cashfree.com/cac/v1";
        }

        $this->token     = $this->authorizeAndGetToken();
    }       // end construct function

    /**
    *  authenticates to pg server using key and secret
    *  returns the token
    */
    protected function authorizeAndGetToken()
    {
        $token              = null;                     // initialize to null
        $clientId           = $this->clientId;
        $clientSecret       = $this->clientSecret;

        $headers =
        [
         "X-Client-Id: $clientId",
         "X-Client-Secret: $clientSecret"
        ];

        $endpoint = $this->baseUrl."/authorize";
        $curlResponse = $this->postCurl($endpoint, $headers);
        if ($curlResponse)
        {
           if ($curlResponse->status == "SUCCESS")
           {
             $token = $curlResponse->data->token;
             return $token;
             error_log($token);
           } else
           {
              throw new Exception("Authorization failed. Reason : ". $curlResponse->message);
           }
        }
    }       // end of function authorizeAndGetToken

    /**
    * @param vAccountId is moodle id padded if needed for min 4 chars
    * @param name is the user's sritoni full name
    * @param phone is the user's principal phone number
    * @param email is the SriToni email of user
    * returns an object with keys "accountNumber" and "ifsc"
    */
    public function createVirtualAccount($vAccountId, $name, $phone, $email)
    {
      $response =["status" => "FAILED", "message" => "Authorization failed"];
      if ($this->token)
      {
        $endpoint   = $this->baseUrl."/createVA";
        $authToken  = $this->token;
        $headers    = [
            "Authorization: Bearer $authToken"
            ];
        // pad moodleuserid with 0's from left for minimum length of 4
        // $vAccountId = str_pad($moodleuserid, 4, "0", STR_PAD_LEFT);
        $params     = array
                            (
                                "vAccountId" => $vAccountId,
                                "name"       => $name,
                                "phone"      => $phone,
                                "email"      => $email,
                            );
        $curlResponse = $this->postCurl($endpoint, $headers, $params);
        //error_log("curl response of accountcreate");
        //error_log(print_r($curlResponse));
        if ($curlResponse->status == "SUCCESS")
        {
            return $curlResponse->data; // returns new account object
        }
        else
        {
            if ($this->verbose)
            {
                error_log( "This is the error message while creating a new Virtual Account" . $curlResponse->message );
            }
            return null;
        }
      }
  }           // end of function createVirtualAccount

    /**
    * returns an object with all vAccounts created so far
    * The data is an array numerically indexed, of objects
    */
    function listAllVirtualAccounts()
    {
        if ($this->token)
        {
            $endpoint = $this->baseUrl."/allVA";
            $authToken = $this->token;
            $headers = [
                        "Authorization: Bearer $authToken"
                       ];
            $curlResponse   = $this->getCurl ($endpoint, $headers);
            if ($curlResponse->status == "SUCCESS")
            {
              $vAccounts = $curlResponse->data->vAccounts;
            }
            else $vAccounts = NULL;
          }
          return $vAccounts;

    }       // end of function listAllVirtualAccounts

    /**
    * @param vAccountId is the Virtual account ID
    * @param vAccounts is the array containing list of all vAs
    * returns the boolean value of vA with this ID exists or not
    */
    function vAExists($vAccountId, $vAccounts)
    {
        if (sizeof($vAccounts) == 0)
        {
            // no entries in the socket_create_listen
            return false;
        }
        // we have at least one entry in the list
        foreach ($vAccounts as $key => $vA)
        {
            if ( $vA->vAccountId == $vAccountId )
            {
                // Virtual Account exists with the given ID
                return true;
            }
        }
        // we have looped through entire list with no match
        return false;
    }

    /**
    *  Get Virtual Account Object given its ID
    * @param vAccountId is the vAccountId
    * returns null if not successfull
    * returns the fetched virtual account object if successfull
    */
    function getvAccountGivenId($vAccountId)
    {
        if (!$this->token)
            {
                return null;
            }
        $vA = null;
        // pad the moodle user id with 0's on left side, if less than 4 digits
        // $vAccountId = str_pad($moodleuserid, 4, "0", STR_PAD_LEFT);
        $endpoint = $this->baseUrl . "/va/" . $vAccountId;
        $authToken = $this->token;
        $headers = [
                    "Authorization: Bearer $authToken"
                   ];
        $curlResponse = $this->getCurl($endpoint, $headers);
        //error_log("curl response of accountcreate");
        //error_log(print_r($curlResponse));
        if ($curlResponse->status == "SUCCESS")
        {
          $vA = $curlResponse->data;    // return the account details object
        }
        return $vA;
    }

    /**
    *  @param vAccountId is self explanatory, is SriToni ID number limited to 8 chars
    *  returns all payments made to this account as an array of payment objects
    */
    public function getPaymentsForVirtualAccount($vAccountId) {
      if ($this->token) {
        // Validate , sanitize $vAccountId
        $endpoint = $this->baseUrl."/payments/".$vAccountId;
        $authToken = $this->token;
        $headers = [
             "Authorization: Bearer $authToken"
              ];
        $curlResponse = $this->getCurl($endpoint, $headers);
        if ($curlResponse->status == "SUCCESS")
        {
          $payments = $curlResponse->data->payments;
        }
        else $payments = NULL;
      }
      return $payments;
    }


    protected function postCurl ($endpoint, $headers, $params = []) {
      $postFields = json_encode($params);
      array_push($headers,
         'Content-Type: application/json',
         'Content-Length: ' . strlen($postFields));


      $endpoint = $endpoint."?";
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $endpoint);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
      curl_setopt($ch, CURLOPT_TIMEOUT, 10);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

      $returnData = curl_exec($ch);
      curl_close($ch);
      if ($returnData != "") {
        return json_decode($returnData, false);     // returns object not array
      }
      return NULL;
    }

    protected function getCurl ($endpoint, $headers)
    {
       $ch = curl_init();
       curl_setopt($ch, CURLOPT_URL, $endpoint);
       curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
       curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1); // verifies the authenticity of the peer's certificate
       curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // verify the certificate's name against host
       $returnData = curl_exec($ch);
       curl_close($ch);
       if ($returnData != "") {
        return json_decode($returnData, false);     // returns object not array
       }
       return NULL;
    }

    function __destruct()
    {
      $this->token = NULL;
    }

    /**
    *  returns the client secret of the api
    */
    public function get_clientSecret()
    {
        return $this->clientSecret;
    }
}       // class definition ends
?>
