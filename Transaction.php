<?php
/*
 * Pay.nl Transaction Class 
 * 
 * @author      Kelvin Huizing
 * @copyright   2013 - Pay.nl
 * @version     1.0
 * @link        http://www.pay.nl/
 */
class Transaction
{
    /**
     * class constants
     */
    const API_BASE_COMM_METHOD = 'https://';
    const API_BASE_URL = 'rest-api.pay.nl/';
    const CLASS_VERSION = '1.0';
    const PAYMENT_METHOD_ID = 4;

    /**
     * @var int containing the program id
     */
    private $iProgramId;

    /**
     * @var int containing the website id
     */
    private $iWebsiteId;

    /**
     * @var int containing the website location id
     */
    private $iWebsiteLocationId;

    /**
     * @var int containing the Pay.nl account id
     */
    private $iAccountId;

    /**
     * @var int containing the token string
     */
    private $strToken = '';

    /**
     * @var string handshake for the purpose of authorization
     */
    private $strHandshake = '';

    /**
     * @var array with the emailaddress of the client 
     */
    private $arrEmailAddress = array();

    /**
     * @var string url to exchange data with Pay.nl 
     */
    private $strExchangeUrl = '';

    /**
     * @var string url to send the visitor to after a payment
     */
    private $strReturnUrl = '';

    /**
     * @var string containing an optional client's order id
     */
    private $strExternalOrder = '';

    /**
     * @var int bank id in case of ideal 
     */
    private $iBankId;

    /**
     * @var int amount of money in cents to transfer
     */
    private $iAmount;

    /**
     * @var int the payment profile id to be used
     */
    private $iPaymentProfileId;

    /**
     * @var int the payment session id of the order
     */
    private $iPaymentSessionId;

    /**
     * @var string customers ip address 
     */
    private $strIpAddress;

    /**
     * @var bool containing the test mode 
     */
    private $boolTestMode = false;

    /**
     * @var bool containing the debug mode
     */
    private $boolDebugMode = false;

    /**
     * @var bool to log errors to webserver's error file
     */
    private $boolWriteErrorLog = true;

    /**
     * @var bool to determine if cURL is available 
     */
    private $boolUseCurl = false;

    /**
     * @var object cURL handle
     */
    private $objCurl = null;

    /**
     * constructor
     * 
     * @param int $iProgramId
     * @param int $iWebsiteId
     * @param int $iWebsiteLocationId
     * @param array $arrEmailAddress
     * @param int $iAccountId
     * @param string $strToken 
     */
    public function __construct($iProgramId, $iWebsiteId, $iWebsiteLocationId, $arrEmailAddress, $iAccountId, $strToken, $boolDebugMode = false)
    {
        // init the vars
        $this->init($iProgramId, $iWebsiteId, $iWebsiteLocationId, $arrEmailAddress, $iAccountId, $strToken, $boolDebugMode);

        // check if cURL exists
        $this->useCurl();

        // login
        $this->login();
    }

    /**
     * desctructor
     * 
     * check for an handshake and / or a cURL connection and close it
     */
    public function __destruct()
    {
        // check for handshake to be closed
        if(strlen($this->getHandshake()) > 0)
        {
            // close Pay.nl connection
            $this->logOut();
        }

        // check for cURL to be closed
        if(!is_null($this->curl))
        {
            curl_close($this->curl);
        }
    }

    /**
     * init the vars
     * 
     * @param int $iProgramId
     * @param int $iWebsiteId
     * @param int $iWebsiteLocationId
     * @param array $arrEmailAddress
     * @param int $iAccountId
     * @param string $strToken 
     */
    private function init($iProgramId, $iWebsiteId, $iWebsiteLocationId, $arrEmailAddress, $iAccountId, $strToken, $boolDebugMode)
    {
        // set the required variables
        $this->setProgramId($iProgramId);
        $this->setWebsiteId($iWebsiteId);
        $this->setWebsiteLocationId($iWebsiteLocationId);
        $this->setEmailAddress($arrEmailAddress);
        $this->setAccountId($iAccountId);
        $this->setToken($strToken);
        $this->setDebugMode($boolDebugMode);
    }

    /**
     * login and request for an handshake
     */
    private function login()
    {
        // set namespace / function
        $strFunction = 'Authentication/loginByToken';

        // create array with required API login vars
        $arrArguments = array();
        $arrArguments['accountId'] = $this->getAccountId();
        $arrArguments['token'] = $this->generateLoginToken();

        // request handshake
        $arrResult = $this->doRequest($strFunction, $arrArguments, 5, 'v2');

        // unserialize the array
        $arrResult = @unserialize($arrResult);

        // check if an error occured
        if(!is_array($arrResult) || !isset($arrResult['result']))
        {
            // log error
            $this->doError('Unexpected response from Pay.nl @ ' . $strFunction);

            // throw exception
            throw new Exception('Unexpected response from Pay.nl @ ' . $strFunction);
        }

        // set debug info
        $this->doDebug('API request: ' . $strFunction . ' -  Result: ' . $arrResult['result']);

        // set handshake
        $this->setHandshake($arrResult['result']);
    }

    /**
     * Logoff from Pay.nl API
     */
    private function logOut()
    {
        // set namespace / function
        $strFunction = 'Authentication/logout';

        try
        {
            $this->doRequest($strFunction, array(), 5, 'v2');
        }
        catch(Exception $ex)
        {
            
        }
    }

    /**
     * get the program id
     * 
     * @return int
     */
    private function getProgramId()
    {
        return $this->iProgramId;
    }

    /**
     * set the program id
     * 
     * @param int $iProgramId 
     */
    private function setProgramId($iProgramId)
    {
        if($this->validateProgramId($iProgramId))
        {
            $this->iProgramId = intval($iProgramId);
        }
    }

    /**
     * validate the program id
     * 
     * @param int $iProgramId
     * @return bool
     */
    private function validateProgramId($iProgramId)
    {
        if(!isset($iProgramId) || !is_numeric($iProgramId))
        {
            // write errorlog
            $this->doError('Invalid program id');

            // throw exception
            throw new Exception('Invalid program id');
        }

        return true;
    }

    /**
     * get the website id
     * 
     * @return int
     */
    private function getWebsiteId()
    {
        return $this->iWebsiteId;
    }

    /**
     * set the website id
     * 
     * @param int $iWebsiteId 
     */
    private function setWebsiteId($iWebsiteId)
    {
        if($this->validateWebsiteId($iWebsiteId))
        {
            $this->iWebsiteId = intval($iWebsiteId);
        }
    }

    /**
     * validate the website id
     * 
     * @param int $iWebsiteId
     * @return bool
     */
    private function validateWebsiteId($iWebsiteId)
    {
        if(!isset($iWebsiteId) || !is_numeric($iWebsiteId))
        {
            // write errorlog
            $this->doError('Invalid website id');

            // throw exception
            throw new Exception('Invalid website id');
        }

        return true;
    }

    /**
     * get the website locationid
     * 
     * @return int
     */
    private function getWebsiteLocationId()
    {
        return $this->iWebsiteLocationId;
    }

    /**
     * set the website location id
     * 
     * @param int $iWebsiteLocationId 
     */
    private function setWebsiteLocationId($iWebsiteLocationId)
    {
        if($this->validateWebsiteLocationId($iWebsiteLocationId))
        {
            $this->iWebsiteLocationId = intval($iWebsiteLocationId);
        }
    }

    /**
     * validate the website location id
     * 
     * @param int $iWebsiteLocationId
     * @return bool
     */
    private function validateWebsiteLocationId($iWebsiteLocationId)
    {
        if(!isset($iWebsiteLocationId) || !is_numeric($iWebsiteLocationId))
        {
            // write errorlog
            $this->doError('Invalid website location id');

            // throw exception
            throw new Exception('Invalid website location id');
        }

        return true;
    }

    /**
     * get the account id
     * 
     * @return int
     */
    private function getAccountId()
    {
        return $this->iAccountId;
    }

    /**
     * set the account id
     * 
     * @param int $iAccountId 
     */
    private function setAccountId($iAccountId)
    {
        if($this->validateAccountId($iAccountId))
        {
            $this->iAccountId = intval($iAccountId);
        }
    }

    /**
     * validate the account id
     * 
     * @param int $iAccountId
     * @return bool 
     */
    private function validateAccountId($iAccountId)
    {
        if(!isset($iAccountId) || !is_numeric($iAccountId))
        {
            // write errorlog
            $this->doError('Invalid account id');

            // throw exception
            throw new Exception('Invalid account id');
        }

        return true;
    }

    /**
     * get the amount of money in cents
     * 
     * @return int
     */
    private function getAmount()
    {
        return $this->iAmount;
    }

    /**
     * set the amount of money in cents
     * 
     * @param int $iAmount 
     */
    private function setAmount($iAmount)
    {
        if($this->validateAmount($iAmount))
        {
            $this->iAmount = intval($iAmount);
        }
    }

    /**
     * validate the amount of money in cents
     * 
     * @param int $iAmount
     * @return bool
     */
    private function validateAmount($iAmount)
    {
        if(!isset($iAmount) || !is_numeric($iAmount))
        {
            // write errorlog
            $this->doError('Invalid amount in cents');

            // throw exception
            throw new Exception('Invalid amounts in cents');
        }
        else if($iAmount <= 0)
        {
            // write errorlog
            $this->doError('Amount in cents cannot be negative or 0');

            // throw exception
            throw new Exception('Amount in cents cannot be negative or 0');
        }
        else if(is_float($iAmount))
        {
            // write errorlog
            $this->doError('Amount in cents cannot cannot be decimal');

            // throw exception
            throw new Exception('Amount in cents cannot be decimal');
        }

        return true;
    }

    /**
     * get the handshake to authorize API call
     * 
     * @return string  
     */
    private function getHandshake()
    {
        return $this->strHandshake;
    }

    /**
     * set the handshake to authorize API call
     * 
     * @param type $strHandshake 
     */
    private function setHandshake($strHandshake)
    {
        if($this->validateHandshake($strHandshake))
        {
            $this->strHandshake = $strHandshake;
        }
    }

    /**
     * validate the handshake to authorize API call
     * 
     * @param type $strHandshake
     * @return bool
     */
    private function validateHandshake($strHandshake)
    {
        if(!isset($strHandshake) || strlen($strHandshake) < 10)
        {
            // write errorlog
            $this->doError('Invalid handshake');

            // throw exception
            throw new Exception('Invalid handshake');
        }

        return true;
    }

    /**
     * get the client's API token
     * 
     * @return string  
     */
    private function getToken()
    {
        return $this->strToken;
    }

    /**
     * set the client's API token
     * 
     * @param type $strToken 
     */
    private function setToken($strToken)
    {
        if($this->validateToken($strToken))
        {
            $this->strToken = $strToken;
        }
    }

    /**
     * validatethe client's API token
     * 
     * @param type $strToken
     * @return bool
     */
    private function validateToken($strToken)
    {
        if(!isset($strToken) || strlen($strToken) < 10)
        {
            // write errorlog
            $this->doError('Invalid token');

            // throw exception
            throw new Exception('Invalid token');
        }

        return true;
    }

    /**
     * get the client's emailaddress
     * 
     * @return array  
     */
    protected function getEmailAddress()
    {
        return $this->arrEmailAddress;
    }

    /**
     * set the client's emailaddress
     * 
     * @param array $arrEmailAddress 
     */
    private function setEmailAddress($arrEmailAddress)
    {
        if($this->validateEmailAddress($arrEmailAddress))
        {
            $this->arrEmailAddress = $arrEmailAddress;
        }
    }

    /**
     * validate the client's emailaddress
     * 
     * @param type $arrHandshake
     * @return bool
     */
    private function validateEmailAddress($arrEmailAddress)
    {
        if(isset($arrEmailAddress) && is_array($arrEmailAddress) && count($arrEmailAddress) > 0)
        {
            foreach($arrEmailAddress as $strEmailAddress)
            {
                // check email address
                if(!preg_match("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$^", $strEmailAddress))
                {
                    // write errorlog
                    $this->doError('Invalid emailaddress');

                    // throw exception
                    throw new Exception('Invalid emailaddress');
                }
            }

            return true;
        }

        // write errorlog
        $this->doError('Invalid emailaddress');

        // throw exception
        throw new Exception('Invalid emailaddress');
    }

    /**
     * get the exchange url
     * 
     * @return string 
     */
    private function getExchangeUrl()
    {
        return $this->strExchangeUrl;
    }

    /**
     * set the exchange url
     * 
     * @param string $strExchangeUrl 
     */
    public function setExchangeUrl($strExchangeUrl)
    {
        if($this->validateExchangeUrl($strExchangeUrl))
        {
            $this->strExchangeUrl = $strExchangeUrl;
        }
    }

    /**
     * validate the exchange url
     * 
     * @param string $strExchangeUrl
     * @return bool 
     */
    private function validateExchangeUrl($strExchangeUrl)
    {
        if(!preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $strExchangeUrl))
        {
            // write errorlog
            $this->doError('Invalid exchange url');

            // throw exception
            throw new Exception('Invalid exchange url');
        }

        return true;
    }

    /**
     * get the return url
     * 
     * @return string 
     */
    private function getReturnUrl()
    {
        return $this->strReturnUrl;
    }

    /**
     * set the return url
     * 
     * @param string $strReturnUrl 
     */
    public function setReturnUrl($strReturnUrl)
    {
        if($this->validateReturnUrl($strReturnUrl))
        {
            $this->strReturnUrl = $strReturnUrl;
        }
    }

    /**
     * validate the return url
     * 
     * @param string $strReturnUrl
     * @return bool 
     */
    private function validateReturnUrl($strReturnUrl)
    {
        if(!preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $strReturnUrl))
        {
            // write errorlog
            $this->doError('Invalid return url');

            // throw exception
            throw new Exception('Invalid return url');
        }

        return true;
    }

    /**
     * get an optional client's external order
     * 
     * @return string 
     */
    protected function getExternalOrder()
    {
        return $this->strExternalOrder;
    }

    /**
     * set an optional client's external order
     * the string can contain max 25 characters
     * 
     * @param string $strExternalOrder 
     */
    public function setExternalOrder($strExternalOrder)
    {
        if($this->validateExternalOrder($strExternalOrder))
        {
            $this->strExternalOrder = $strExternalOrder;
        }
    }

    /**
     * validate an optional client's external order
     * 
     * @param string $strExternalOrder
     * @return bool 
     */
    private function validateExternalOrder($strExternalOrder)
    {
        if(isset($strExternalOrder) && strlen($strExternalOrder) < 25)
        {
            // valid external order
            return true;
        }
        elseif(isset($strExternalOrder) && strlen($strExternalOrder) > 25)
        {
            // write errorlog
            $this->doError('Invalid external order');

            // throw exception
            throw new Exception('Invalid external order');
        }

        // return false cause there's no external order available
        // and we don't want the external order to be set
        // we don't throw an exception though
        return false;
    }

    /**
     * get the bank id required for ideal / giropay
     * 
     * @return int
     */
    private function getBankId()
    {
        return $this->iBankId;
    }

    /**
     * set the bank id required for ideal / giropay
     * 
     * @param int $iBankId 
     */
    public function setBankId($iBankId)
    {
        if($this->validateBankId($iBankId))
        {
            $this->iBankId = $iBankId;
        }
    }

    /**
     * validate the bank id required for ideal / giropay
     * 
     * @param int $iBankId
     * @return bool 
     */
    private function validateBankId($iBankId)
    {
        if(!isset($iBankId) || !is_numeric($iBankId))
        {
            // write errorlog
            $this->doError('Invalid bank id');

            // throw exception
            throw new Exception('Invalid bank id');
        }

        return true;
    }

    /**
     * get the payment profile id
     * 
     * @return int
     */
    private function getPaymentProfileId()
    {
        return $this->iPaymentProfileId;
    }

    /**
     * set the payment profile id
     * 
     * @param int $iPaymentProfileId 
     */
    public function setPaymentProfileId($iPaymentProfileId)
    {
        if($this->validatePaymentProfileId(intval($iPaymentProfileId)))
        {
            $this->iPaymentProfileId = intval($iPaymentProfileId);
        }
    }

    /**
     * validate the payment profile id
     * 
     * @param int $iPaymentProfileId
     * @return bool 
     */
    private function validatePaymentProfileId($iPaymentProfileId)
    {
        if(!isset($iPaymentProfileId) || $iPaymentProfileId <= 0 || !is_numeric($iPaymentProfileId))
        {
            // write errorlog
            $this->doError('Invalid payment profile id');

            // throw exception
            throw new Exception('Invalid payment profile id');
        }
        
        return true;
    }

    /**
     * get the payment session id of an order
     * 
     * @return int
     */
    protected function getPaymentSessionId()
    {
        return $this->iPaymentSessionId;
    }

    /**
     * set the payment session id of an order
     * we will receive this id from pay.nl after a transaction
     * 
     * @param int $iPaymentProfileId 
     */
    protected function setPaymentSessionId($iPaymentSessionId)
    {
        if($this->validatePaymentSessionId(intval($iPaymentSessionId)))
        {
            $this->iPaymentSessionId = intval($iPaymentSessionId);
        }
    }

    /**
     * validate the payment session id of an order
     * 
     * @param int $iPaymentProfileId
     * @return bool 
     */
    private function validatePaymentSessionId($iPaymentSessionId)
    {
        if(!isset($iPaymentSessionId) || strlen($iPaymentSessionId) < 5)
        {
            // write errorlog
            $this->doError('Invalid payment session id');

            // throw exception
            throw new Exception('Invalid payment session id');
        }

        return true;
    }

    /**
     * get the boolean value of the test mode
     * 
     * @return bool 
     */
    private function getTestMode()
    {
        return $this->boolTestMode;
    }

    /**
     * set the boolean value of the test mode
     * 
     * @param type $boolTestMode 
     */
    public function setTestMode($boolTestMode)
    {
        if($this->validateTestMode($boolTestMode))
        {
            $this->boolTestMode = $boolTestMode;
        }
    }

    /**
     * validate the boolean value of the test mode
     * 
     * @param type $boolTestMode
     * @return bool 
     */
    private function validateTestMode($boolTestMode)
    {
        if(!isset($boolTestMode) || !is_bool($boolTestMode))
        {
            // write errorlog
            $this->doError('Invalid test mode value');

            // throw exception
            throw new Exception('Invalid test mode value');
        }

        return true;
    }

    /**
     * get the boolean value of the debug mode
     * 
     * @return bool 
     */
    public function getDebugMode()
    {
        return $this->boolDebugMode;
    }

    /**
     * set the boolean value of the debug mode
     * 
     * @param type $boolTestMode 
     */
    public function setDebugMode($boolDebugMode)
    {
        if($this->validateDebugMode($boolDebugMode))
        {
            $this->boolDebugMode = $boolDebugMode;
        }
    }

    /**
     * validate the boolean value of the debug mode
     * 
     * @param type $boolTestMode
     * @return bool 
     */
    private function validateDebugMode($boolDebugMode)
    {
        if(!isset($boolDebugMode) || !is_bool($boolDebugMode))
        {
            // write errorlog
            $this->doError('Invalid debug mode value');

            // throw exception
            throw new Exception('Invalid debug mode value');
        }

        return true;
    }

    /**
     * get the setting to write errors to server's logfile
     * 
     * @return string  
     */
    private function getWriteErrorLog()
    {
        return $this->boolWriteErrorLog;
    }

    /**
     * generate a login token based on the settings token
     * we need to create this token to authorize login
     * 
     * @return string login token 
     */
    private function generateLoginToken()
    {
        return sha1($this->getToken() . time());
    }

    /**
     * check if cURL is available
     */
    private function useCurl()
    {
        if(function_exists('curl_init'))
        {
            // set debug info
            $this->doDebug('cURL is available - Session initialized');

            $this->boolUseCurl = true;
        }
    }

    /**
     * retrieve list of allowed/enabled payment profiles for
     * this program/website/location.
     *
     * @return array
     */
    public function getActivePaymentProfiles()
    {
        // set namespace / function
        $strFunction = 'WebsiteLocation/getActivePaymentProfiles';

        // generate array with the correct parameters
        $arrArguments = array();
        $arrArguments['programId'] = $this->getProgramId();
        $arrArguments['websiteId'] = $this->getWebsiteId();
        $arrArguments['websiteLocationId'] = $this->getWebsiteLocationId();
        $arrArguments['paymentMethodId'] = self::PAYMENT_METHOD_ID;

        // request for the payment profiles
        $arrResult = $this->doRequest($strFunction, $arrArguments);

        // unserialize the array
        $arrResult = @unserialize($arrResult);

        if(!is_array($arrResult))
        {
            // write errorlog
            $this->doError('Unexpected response from Pay.nl @ ' . $strFunction);

            // throw exception
            throw new Exception('Unexpected response from Pay.nl @ ' . $strFunction);
        }

        // set debug info
        $this->doDebug('API request: ' . $strFunction . ' Result: ' . print_r($arrResult, true));

        return $arrResult;
    }

    /**
     * Get status of payment with paymentSessionId
     *
     * @param integer $iPaymentSessionId
     * @return array
     */
    public function getPaymentStatus($iPaymentSessionId)
    {
        // set namespace / function
        $strFunction = 'Transaction/getStatusByPaymentSessionId';

        // set array arguments
        $arrArguments = array();
        $arrArguments['paymentSessionId'] = $iPaymentSessionId;

        // request for the payment session id
        $result = $this->doRequest($strFunction, $arrArguments);

        // unserialize array
        $result = @unserialize($result);

        // check if an error occured
        if(!is_array($result) || !isset($result['result']))
        {
            $this->doError('Unexpected response from Pay.nl @ ' . $strFunction);
            throw new Exception('Unexpected response from Pay.nl @ ' . $strFunction);
        }

        // opbouwen array met resultaten
        $arrResult = array();
        $arrResult['status'] = $result['statusAction'];
        $arrResult['amount'] = $result['amount'];
        $arrResult['statsAdded'] = $result['statsAdded'];

        if(isset($result['customer']))
        {
            $arrResult = array_merge($arrResult, $result['customer']);
        }

        // set debug info
        $this->doDebug('API request: ' . $strFunction . ' Result: ' . print_r($arrResult, true));

        return $arrResult;
    }

    /**
     * get current list of iDeal banks
     *
     * @return array
     */
    public function getIdealBanks()
    {
        // set namespace / function
        $strFunction = 'Transaction/getBanks';

        // request list with ideal banks
        $arrResult = $this->doRequest($strFunction);

        // unserialize the array
        $arrResult = @unserialize($arrResult);

        if(!is_array($arrResult))
        {
            // write errorlog
            $this->doError('Unexpected response from Pay.nl @ ' . $strFunction);

            // throw exception
            throw new Exception('Unexpected response from Pay.nl @ ' . $strFunction);
        }

        // set debug info
        $this->doDebug('API request: ' . $strFunction . ' Result: ' . print_r($arrResult, true));

        return $arrResult;
    }

    /**
     * create new transaction
     *
     * @param integer $iAmount (in cents)
     * @param integer $iPaymentProfileId
     * @param array $arrSettings (optional settings)
     * @return array
     */
    public function createTransaction($iAmount, $iPaymentProfileId, array $arrSettings = array())
    {
        // set namespace / function
        $strFunction = 'Transaction/create';

        // set the amount to pay
        $this->setAmount($iAmount);

        // set the selected payment profile id
        $this->setPaymentProfileId($iPaymentProfileId);

        // create arguments for the request
        $arrArguments = $arrSettings;
        $arrArguments['amount'] = $this->getAmount();
        $arrArguments['programId'] = $this->getProgramId();
        $arrArguments['websiteId'] = $this->getWebsiteId();
        $arrArguments['websiteLocationId'] = $this->getWebsiteLocationId();
        $arrArguments['paymentProfileId'] = $this->getPaymentProfileId();
        $arrArguments['ipAddress'] = $this->getIpAddress();
        $arrArguments['orderReturnUrl'] = $this->getReturnUrl();

        // check for testmode; only for live websites
        if($this->getTestMode() == true)
        {
            // set debug info
            $this->doDebug('Testmode has been started');

            $arrArguments['testMode'] = 1;
        }

        // check if a bank id is available (in case of iDeal / giropay)
        if(!is_null($this->getBankId()))
        {
            $arrArguments['bankId'] = $this->getBankId();
        }

        // check for a client's order id
        if(!is_null($this->getExternalOrder()))
        {
            $arrArguments['object'] = $this->getExternalOrder();
            if(!isset($arrArguments['orderDesc']))
            {
                $arrArguments['orderDesc'] = 'Order ' . $this->getExternalOrder();
            }
        }

        // check if an exchange url is set
        // if not we use the url entered at admin.pay.nl
        if(!is_null($this->getExchangeUrl()) && strlen($this->getExchangeUrl()) > 5)
        {
            $arrArguments['orderExchangeUrl'] = $this->getExchangeUrl();
        }

        // request for a create transaction
        $arrResult = $this->doRequest($strFunction, $arrArguments);

        // unserialize array
        $arrResult = @unserialize($arrResult);

        // check if an arror occured
        if(!is_array($arrResult) || !isset($arrResult['result']))
        {
            // write errorlog
            $this->doError('Unexpected response from Pay.nl @ ' . $strFunction);

            // throw exception
            throw new Exception('Unexpected response from Pay.nl @ ' . $strFunction);
        }

        // check result
        if($arrResult['result'] == 'FALSE')
        {
            // write errorlog
            $this->doError('Unable to create new session due to error');

            // throw exception
            throw new Exception('Unable to create new session due to error');
        }

        // unset vars
        unset($arrResult['entranceCode']);
        unset($arrResult['result']);

        // set debug info
        $this->doDebug('API request: ' . $strFunction . ' Result: ' . print_r($arrResult, true));

        return $arrResult;
    }

    /**
     * call a Pay.nl API
     * 
     * @param string $strFunctionName
     * @param array $arrArguments
     * @param int $iRetryCount
     * @param string $strVersion
     * @return type boolean / mixed
     */
    final private function doRequest($strFunctionName, array $arrArguments = array(), $iRetryCount = 5, $strVersion = 'v1')
    {
        // a request can be tried for 5 times max
        if($iRetryCount < 0)
        {
            return false;
        }

        $iRetryCount--;

        // check if cURL is available otherwise we have to try file_get_contents
        if($this->boolUseCurl)
        {
            // construct url
            $strUrl = self::API_BASE_COMM_METHOD . self::API_BASE_URL . $strVersion . '/' . $strFunctionName . '/array_serialize/';

            // prepare url with proper get vars
            $strUrl = $this->prepareHttpGet($strUrl, $arrArguments);

            // set debug info
            if($strFunctionName != 'Authentication/logout') $this->doDebug('Using cURL to call: ' . $strUrl);

            // check if cURL has already been init
            // if not init a new cURL
            if($this->curl == null)
            {
                $this->curl = curl_init();

                // set cURL options
                curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($this->curl, CURLOPT_USERAGENT, 'PPT Class version ' . self::CLASS_VERSION . ' (for program ' . $this->getProgramId() . ')');
                curl_setopt($this->curl, CURLOPT_TIMEOUT, 10);
                curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
            }

            // set handshake if value is known
            if(strlen($this->getHandshake()) > 0)
            {
                curl_setopt($this->curl, CURLOPT_USERPWD, 'pptCHandshake' . ":" . $this->getHandshake());
            }

            // set url
            curl_setopt($this->curl, CURLOPT_URL, $strUrl);

            // execute cURL
            $arrCurlData = curl_exec($this->curl);

            // get info
            $arrCurlInfo = curl_getinfo($this->curl);

            // get possible errornumber
            $iCurlErrorNumber = curl_errno($this->curl);

            // check if an error occured
            // in this case retry the request
            if($iCurlErrorNumber > 0)
            {
                return $this->doRequest($strFunctionName, $arrArguments, $iRetryCount, $strVersion);
            }
            // check for unknow communication errors
            if($arrCurlInfo['http_code'] > 400)
            {
                $error = 'Unknown communications error';
                $uData = @unserialize($arrCurlData);
                if(is_array($uData) && count($uData) > 0)
                {
                    if(isset($uData['error']))
                    {
                        $error = $uData['error'];
                    }
                    else
                    {
                        $error.= ' - Unable to retrieve error';
                    }
                }

                $this->doError($error);
                throw new Exception('Error: ' . $error, $arrCurlInfo['http_code']);
            }

            return $arrCurlData;
        }
        else
        {
            // cUrl is not availabe so we try to do a request
            // by file_get_contents   
             
            // set debug info
            if($strFunctionName != 'Authentication/logout') $this->doDebug('Using file_get_content to call: ' . $strUrl);

            // construct url
            // check if handshake is set
            if(strlen($this->getHandshake()) > 0)
            {
                // request url with authorization
                $strUrl = self::API_BASE_COMM_METHOD . "pptHandshake:" . $this->getHandshake() . "@" . self::API_BASE_URL . $strVersion . '/' . $strFunctionName . '/array_serialize/';
            }
            else
            {
                // request url without authorization
                $strUrl = self::API_BASE_COMM_METHOD . self::API_BASE_URL . $strVersion . '/' . $strFunctionName . '/array_serialize/';
            }

            // set proper get vars
            $strUrl = $this->prepareHttpGet($strUrl, $arrArguments);

            // file_get_contents settings
            $context = stream_context_create(
                array(
                  'http' => array(
                    'timeout' => 10      // Timeout in seconds
                  )
                ));

            // get the data by file_get_contents
            $arrData = file_get_contents($strUrl, 0, $context);

            // check if an error occured
            if($arrData === false)
            {
                // retry the request
                return $this->doRequest($strFunctionName, $arrArguments, $iRetryCount, $strVersion);
            }

            return $arrData;
        }

        return false;
    }

    /**
     * check if a list of banks needs to be shown
     * 
     * @param type $arrPaymentProfiles
     * @return type 
     */
    public function isBankList($arrPaymentProfiles)
    {
        if(is_array($arrPaymentProfiles))
        {
            // we're checking for payment method 10 (iDEAL)
            if($this->in_array_r("10", $arrPaymentProfiles)) return true;
        }

        // a list of banks doesn't need to be shown
        return false;
    }

    /**
     * determine the customers ip address
     * 
     * @return string
     */
    public function getIpAddress()
    {
        // determine ipAddress
        $this->strIpAddress = '127.0.0.1';

        if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && strlen($_SERVER['HTTP_X_FORWARDED_FOR']) > 0)
        {
            $this->strIpAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        elseif(isset($_SERVER['REMOTE_ADDR']))
        {
            $this->strIpAddress = $_SERVER['REMOTE_ADDR'];
        }

        return $this->strIpAddress;
    }

    /**
     * show debug information
     * 
     * @param string $message 
     */
    public function doDebug($message)
    {
        if($this->getDebugMode())
        {
            //echo "<div class='paynl_debug'><pre>" . $message . "</pre></div>";
        }
    }

    /**
     * error handler writes errors to webserver logs
     * 
     * @param string $message
     */
    public function doError($message)
    {
        // write error to webserver logs
        if($this->getWriteErrorLog()) error_log("Pay.nl [critical error]: " . $message);
        
        // Inform Pay.nl client about the error by mail
        foreach($this->getEmailAddress() as $mail)
        {
            mail($mail, 'Pay.nl Critical error', $message . "\n\nOccured at:\n" . $this->generateBacktrace());
        }
    }

    /**
     * generate backtrace in case of error.
     *
     * @return string
     */
    final private function generateBacktrace()
    {
        $rawtrace = debug_backtrace();
        array_shift($rawtrace);

        $output = '';

        // generate proper output
        foreach($rawtrace as $entry)
        {
            if(isset($entry['file']))
            {
                $output.="\nFile: " . $entry['file'] . " (Line: " . $entry['line'] . ")\n";
            }
            else
            {
                $output.="\nClass: " . $entry['class'] . "\n";
            }

            $output.="Function: " . $entry['function'] . "\n";
        }

        return $output;
    }

    /**
     * function to modify array of params into REST API parameters.
     *
     * @param string $strUrl
     * @param array $arrParams
     * @return string
     */
    final private function prepareHttpGet($strUrl, array $arrParams)
    {
        $first = 1;

        // prepare query string
        foreach($arrParams as $key => $value)
        {
            if($first != 1)
            {
                $strUrl = $strUrl . "&";
            }
            else
            {
                $strUrl = $strUrl . "?";
                $first = 0;
            }

            if(is_array($value))
            {
                $count = count($value);
                foreach($value as $k => $v)
                {
                    $count--;
                    $strUrl = $strUrl . $key . "[" . $k . "]=" . urlencode($v);
                    if($count > 0) $strUrl.="&";
                }
                continue;
            }

            // add item to string
            $strUrl = $strUrl . $key . "=" . urlencode($value);
        }

        return $strUrl;
    }

    /**
     * checking for a value in a multidimensional array
     * 
     * @param mixed $needle
     * @param mixed $haystack
     * @param bool $strict
     * @return bool 
     */
    private function in_array_r($needle, $haystack, $strict = false)
    {
        foreach($haystack as $item)
        {
            if(($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_r($needle, $item, $strict)))
            {
                return true;
            }
        }

        return false;
    }
}

/** eof **/
