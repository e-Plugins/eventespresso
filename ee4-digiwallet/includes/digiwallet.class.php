<?php

/**
 * @file     Provides support for Digiwallet iDEAL, Bancontact and Sofort
 * @author   Digiwallet.
 * @url      https://www.digiwallet.nl
 * @release  01-08-2019
 * @ver      1.0
 */

/**
 * @class   Digiwallet Core class
 */

class DigiwalletCoreEE4
{
    // Constants
    const APP_ID = 'dw_eventEspresso4.10_1.0.3';
    
    const MIN_AMOUNT            = 84;
    
    const ERR_NO_AMOUNT         = "Geen bedrag meegegeven | No amount given";
    const ERR_NO_DESCRIPTION    = "Geen omschrijving meegegeven | No description given";
    const ERR_NO_RTLO           = "Geen DigiWallet Outletcode bekend; controleer de module instellingen | No Digiwallet Outletcode filled in, check the module settings";
    const ERR_NO_TXID           = "Er is een onjuist transactie ID opgegeven | An incorrect transaction ID was given";
    const ERR_NO_RETURN_URL     = "Geen of ongeldige return URL | No or invalid return URL";
    const ERR_NO_REPORT_URL     = "Geen of ongeldige report URL | No or invalid report URL";
    const ERR_IDEAL_NO_BANK     = "Geen bank geselecteerd voor iDEAL | No bank selected for iDEAL";
    const ERR_SOFORT_NO_COUNTRY = "Geen land geselecteerd voor Sofort | No country selected for Sofort";
    const ERR_PAYBYINVOICE      = "Fout bij achteraf betalen|Error with paybyinvoice";
    
    // Constant array's
    
    protected $paymentOptions   = array("IDE", "MRC", "DEB", "WAL", "CC", "PYP", "BW", "AFP");
    
    protected $checkAPIs = [
        "IDE" => "https://transaction.digiwallet.nl/ideal/check",
        "MRC" => "https://transaction.digiwallet.nl/mrcash/check",
        "DEB" => "https://transaction.digiwallet.nl/directebanking/check",
        "WAL" => "https://transaction.digiwallet.nl/paysafecard/check",
        "CC"  => "https://transaction.digiwallet.nl/creditcard/check",
        "PYP" => "https://transaction.digiwallet.nl/paypal/check",
        "AFP" => "https://transaction.digiwallet.nl/afterpay/check",
        "BW"  => "https://transaction.digiwallet.nl/bankwire/check"
    ];
    
    protected $startAPIs = [
        "IDE" => "https://transaction.digiwallet.nl/ideal/start",
        "MRC" => "https://transaction.digiwallet.nl/mrcash/start",
        "DEB" => "https://transaction.digiwallet.nl/directebanking/start",
        "WAL" => "https://transaction.digiwallet.nl/paysafecard/start",
        "CC" => "https://transaction.digiwallet.nl/creditcard/start",
        "PYP" => "https://transaction.digiwallet.nl/paypal/start",
        "AFP" => "https://transaction.digiwallet.nl/afterpay/start",
        "BW" => "https://transaction.digiwallet.nl/bankwire/start"
    ];
    
    // Variables
    
    protected $rtlo             = null;
    protected $testMode         = false;
    
    protected $language         = "nl";
    protected $payMethod        = null;
    
    protected $bankId           = null;
    protected $countryId        = null;
    protected $amount           = 0;
    protected $description      = null;
    protected $returnUrl        = null; // When using the AUTO-setting; %payMethod% will be replaced by the actual payment method just before starting the payment
    protected $cancelUrl        = null; // When using the AUTO-setting; %payMethod% will be replaced by the actual payment method just before starting the payment
    protected $reportUrl        = null; // When using the AUTO-setting; %payMethod% will be replaced by the actual payment method just before starting the payment
    
    protected $bankUrl          = null;
    
    protected $transactionId    = null;
    protected $paidStatus       = false;
    
    protected $errorMessage     = null;
    
    protected $parameters       = array();    // Additional parameters
    
    protected $moreInformation = null;
    
    protected $consumerInfo = [];
    /**
     *  Constructor
     *  @param int $rtlo Layoutcode
     */
    
    public function __construct($payMethod, $rtlo = false, $language = "nl", $testMode = false)
    {
        $payMethod = strtoupper($payMethod);
        if (in_array($payMethod, $this->paymentOptions)) {
            $this->payMethod = $payMethod;
        } else {
            return false;
        }
        $this->rtlo = (int) $rtlo;
        $this->testMode = ($testMode) ? '1' : '0';
        $this->language = strtolower(substr($language, 0, 2));
    }
    
    /**
     * Get list with banks based on PayMethod setting (AUTO, IDE, ...
     * etc.)
     */
    public function getBankList()
    {
        $url = "https://transaction.digiwallet.nl/ideal/getissuers?ver=4&format=xml";
        
        $xml = $this->httpRequest($url);
        $banks_array = array();
        if (! $xml) {
            $banks_array["IDE0001"] = "Bankenlijst kon niet opgehaald worden bij Digiwallet, controleer of curl werkt!";
            $banks_array["IDE0002"] = "  ";
        } else {
            $p = xml_parser_create();
            xml_parse_into_struct($p, $xml, $banks_object, $index);
            xml_parser_free($p);
            foreach ($banks_object as $bank) {
                if(empty($bank['attributes']['ID']))
                    continue;
                    $banks_array[$bank['attributes']['ID']] = $bank['value'];
            }
        }
        return array_map( 'esc_attr', $banks_array);
    }
    
    public function getCountryList()
    {
        return array(
            'AT' => 'Austria',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'DE' => 'Germany',
            'IT' => 'Italy',
            'NL' => 'Netherlands'
        );
    }
    
    /**
     *  Start transaction with Digiwallet
     *
     *  Set at least: amount, description, returnUrl, reportUrl (optional: cancelUrl)
     *  In case of iDEAL: bankId
     *  In case of Sofort: countryId
     *
     *  After starting, it will return a link to the bank if successfull :
     *  - Link can also be fetched with getBankUrl()
     *  - Get the transaction id via getTransactionId()
     *  - Read the errors with getErrorMessage()
     *  - Get the actual started payment method, in case of auto-setting, using getPayMethod()
     */
    
    public function startPayment()
    {
        if (!$this->rtlo) {
            $this->errorMessage = self::ERR_NO_RTLO;
            return false;
        }
        
        if (!$this->amount) {
            $this->errorMessage = self::ERR_NO_AMOUNT;
            return false;
        }
        
        if (!$this->description) {
            $this->errorMessage = self::ERR_NO_DESCRIPTION;
            return false;
        }
        
        if (!$this->returnUrl) {
            $this->errorMessage = self::ERR_NO_RETURN_URL;
            return false;
        }
        
        if (!$this->reportUrl) {
            $this->errorMessage = self::ERR_NO_REPORT_URL;
            return false;
        }
        
        if (($this->payMethod=="IDE") && (!$this->bankId)) {
            $this->errorMessage = self::ERR_IDEAL_NO_BANK;
            return false;
        }
        
        if (($this->payMethod=="DEB") && (!$this->countryId)) {
            $this->errorMessage = self::ERR_SOFORT_NO_COUNTRY;
            return false;
        }
        
        $this->returnUrl = str_replace("%payMethod%", $this->payMethod, $this->returnUrl);
        $this->cancelUrl = str_replace("%payMethod%", $this->payMethod, $this->cancelUrl);
        $this->reportUrl = str_replace("%payMethod%", $this->payMethod, $this->reportUrl);
        
        $url = $this->startAPIs[$this->payMethod];
        switch ($this->payMethod) {
            case 'IDE':
                $url .= '?ver=4' . '&bank=' . urlencode($this->bankId);
                break;
            case 'MRC':
                $url .= '?ver=2' . '&lang=' . urlencode($this->getLanguage(array("NL", "FR", "EN"), "NL"));
                break;
            case 'DEB':
                $url .= '?ver=2&type=1' . '&country='.urlencode($this->countryId). '&lang=' . urlencode($this->getLanguage(array("NL", "EN", "DE"), "DE"));
                break;
            case 'CC':
                $url .= '?ver=3';
                break;
            case 'WAL':
                $url .= '?ver=2';
                break;
            case 'PYP':
            case 'BW':
            case 'AFP':
                $url .= '?ver=1';
                break;
        }
        
        $url .= "&rtlo=".urlencode($this->rtlo) .
        "&amount=".urlencode($this->amount).
        "&description=".urlencode($this->description).
        "&test=".$this->testMode.
        "&userip=".urlencode($_SERVER["REMOTE_ADDR"]).
        "&domain=".urlencode($_SERVER["HTTP_HOST"]).
        "&app_id=".urlencode(self::APP_ID).
        "&returnurl=".urlencode($this->returnUrl).
        ((!empty($this->cancelUrl)) ? "&cancelurl=".urlencode($this->cancelUrl) : "").
        "&reporturl=".urlencode($this->reportUrl);
        
        if (is_array($this->parameters)) {
            foreach ($this->parameters as $k => $v) {
                $url .= "&" . $k . "=" . urlencode($v);
            }
        }
        
        if(WP_DEBUG) {
            error_log("\n-------------------------------------------------\n");
            error_log($url);
        }
        
        $result = sanitize_text_field($this->httpRequest($url));
        $result_code = substr($result, 0, 6);
        if (($result_code == "000000") || ($result_code == "000001" && $this->payMethod == "CC")) {
            $result = substr($result, 7);
            if ($this->payMethod == 'AFP') {
                list($this->transactionId, $status, $this->bankUrl) = explode("|", $result);
            } else {
                list($this->transactionId, $this->bankUrl) = explode("|", $result);
            }
            
            if ($this->payMethod == 'BW') {
                $this->moreInformation = $result;
                return true;
            }
            return $this->bankUrl;
        } else {
            $this->errorMessage = "Digiwallet antwoordde: ".$result." | Digiwallet responded with: ".$result;
            return false;
        }
    }
    
    /**
     *  Check transaction with Digiwallet
     *  @param string  $payMethodId Payment method's see above
     *  @param string  $transactionId Transaction ID to check
     *
     *  Returns true if payment successfull (or testmode) and false if not
     *
     */
    
    public function checkPayment($transactionId, $params)
    {
        if (! $this->rtlo) {
            $this->errorMessage = self::ERR_NO_RTLO;
            return false;
        }
        if (! $transactionId) {
            $this->errorMessage = self::ERR_NO_TXID;
            return false;
        }
        $url = $this->checkAPIs[$this->payMethod] . "?" .
            "rtlo=" . urlencode($this->rtlo) . "&" .
            "trxid=" . urlencode($transactionId) . "&" .
            "test=" . (($this->testMode) ? "1" : "0") .
            "&once=0";
        
        if (! empty($params)) {
            foreach ($params as $k => $v) {
                $url .= "&" . $k . "=" . urlencode($v);
            }
        }
        
        return $this->parseCheckApi($this->httpRequest($url));
    }
    /*
     * Bankwire: 000000 OK|750|795
     *
     * After pay:
     * 000000 invoiceKey|invoicePaymentReference|status
     * 000000 invoiceKey|invoicePaymentReference|status|enrichmentURL
     * 000000 invoiceKey|invoicePaymentReference|status|rejectionReason|rejectionMessages
     *
     * sofort, creditcard, ideal, mistercash, paypal, paysafecard: 000000 OK
     */
    public function parseCheckApi($strResult)
    {
        $_result = explode("|", $strResult);
        $consumerBank = "";
        $consumerName = "";
        $consumerCity = "NOT PROVIDED";
        
        if (count($_result) == 4) {
            list ($resultCode, $consumerBank, $consumerName, $consumerCity) = $_result;
        } elseif(count($_result) == 3){
            // For BankWire
            list ($resultCode, $due_amount, $paid_amount) = $_result;
            $this->consumerInfo["bw_due_amount"] = $due_amount;
            $this->consumerInfo["bw_paid_amount"] = $paid_amount;
        }else{
            list ($resultCode) = $_result;
        }
        
        $this->consumerInfo["bankaccount"] = "bank";
        $this->consumerInfo["name"] = "customername";
        $this->consumerInfo["city"] = "city";
        
        if (($resultCode == "000000 OK")  || ($resultCode == "000001 OK" && $this->payMethod == "CC") || (substr(trim($resultCode), 0, 6) == "000000" && trim($status) == 'Captured')) {
            $this->consumerInfo["bankaccount"] = $consumerBank;
            $this->consumerInfo["name"] = $consumerName;
            $this->consumerInfo["city"] = ($consumerCity != "NOT PROVIDED") ? $consumerCity : "";
            $this->paidStatus = true;
            return true;
        } else {
            $this->paidStatus = false;
            $this->errorMessage = $strResult;
            return false;
        }
    }
    
    /**
     *  [DEPRECATED] checkReportValidity
     *  Will removed in future versions
     *  This function used to act as a redundant check on the validity of reports by checking IP addresses
     *  Because this is bad practice and not necessary it is now removed
     */
    
    public function checkReportValidity($post, $server)
    {
        return true;
    }
    
    /**
     *  PRIVATE FUNCTIONS
     */
    
    protected function httpRequest($url)
    {
        $request = new WP_Http;
        $result = $request->request( $url );
        if (isset($result->errors)) {
            $this->errorMessage = 'Something went wrong with Digiwallet API';
            return false;
        }
        return $result['body'];
    }
    
    /**
     *  GETTERS & SETTERS
     */
    
    public function setAmount($amount)
    {
        $this->amount = round($amount);
        return true;
    }
    
    /**
     * Bind additional parameter to start request. Safe for chaining.
     */
    
    public function bindParam($name, $value)
    {
        $this->parameters[$name] = $value;
        return $this;
    }
    
    public function getAmount()
    {
        return $this->amount;
    }
    
    public function setBankId($bankId)
    {
        $this->bankId = $bankId;
        return true;
    }
    
    public function getTax($rate = null)
    {
        if(empty($rate)) return 4; // No tax
        else if($rate>= 21) return 1;
        else if($rate>= 6) return 2;
        else return 3;
    }
    
    public function getBankId()
    {
        return $this->bankId;
    }
    
    public function getBankUrl()
    {
        return $this->bankUrl;
    }
    
    public function getMoreInformation()
    {
        return $this->moreInformation;
    }
    
    public function setCountryId($countryId)
    {
        $this->countryId = strtolower(substr($countryId, 0, 2));
        return true;
    }
    
    public function getCountryId()
    {
        return $this->countryId;
    }
    
    public function setDescription($description)
    {
        $this->description = substr($description, 0, 32);
        return true;
    }
    
    public function getDescription()
    {
        return $this->description;
    }
    
    public function getErrorMessage()
    {
        $returnVal = '';
        if (! empty($this->errorMessage)) {
            if ($this->language == "nl" && strpos($this->errorMessage, " | ") !== false) {
                list ($returnVal) = explode(" | ", $this->errorMessage, 2);
            } elseif ($this->language == "en" && strpos($this->errorMessage, " | ") !== false) {
                list ($discard, $returnVal) = explode(" | ", $this->errorMessage, 2);
            } else {
                $returnVal = $this->errorMessage;
            }
        }
        return $returnVal;
    }
    
    public function getLanguage($allowList = false, $defaultLanguage = false)
    {
        if (!$allowList) {
            return $this->language;
        } else {
            if (in_array(strtoupper($this->language), $allowList)) {
                return strtoupper($this->language);
            } else {
                return $this->defaultLanguage;
            }
        }
    }
    
    public function getPaidStatus()
    {
        return $this->paidStatus;
    }
    
    public function getPayMethod()
    {
        return $this->payMethod;
    }
    
    public function setReportUrl($reportUrl)
    {
        if (preg_match('|(\w+)://([^/:]+)(:\d+)?(.*)|', $reportUrl)) {
            $this->reportUrl = $reportUrl;
            return true;
        } else {
            return false;
        }
    }
    
    public function getReportUrl()
    {
        return $this->reportUrl;
    }
    
    public function setReturnUrl($returnUrl)
    {
        if (preg_match('|(\w+)://([^/:]+)(:\d+)?(.*)|', $returnUrl)) {
            $this->returnUrl = $returnUrl;
            return true;
        } else {
            return false;
        }
    }
    
    public function getReturnUrl()
    {
        return $this->returnUrl;
    }
    
    public function setCancelUrl($cancelUrl)
    {
        if (preg_match('|(\w+)://([^/:]+)(:\d+)?(.*)|', $cancelUrl)) {
            $this->cancelUrl = $cancelUrl;
            return true;
        } else {
            return false;
        }
    }
    
    public function getCancelUrl()
    {
        return $this->cancelUrl;
    }
    
    public function setTransactionId($transactionId)
    {
        $this->transactionId = substr($transactionId, 0, 32);
        return true;
    }
    
    public function getTransactionId()
    {
        return $this->transactionId;
    }
    
    /**
     *
     * @param unknown $token
     * @param unknown $dataRefund
     * @return string|boolean
     */
    public function refund($token, $dataRefund)
    {
        $url = 'https://api.digiwallet.nl/refund';
        $request = new WP_Http();
        $response_full = $request->request($url, array('method' => 'POST', 'body' => $data, 'headers' => array(
            "authorization: Bearer " . $token,
            "cache-control: no-cache"
        )));
        if (isset($response_full->errors)) {
            $this->errorMessage = 'Something went wrong with Digiwallet API';
            return false;
        }
        
        $response = json_decode($response_full['body']);
        if(!empty($response->refundID) && $response->refundID > 0) {
            return true;
        }
        else {
            $this->errorMessage = (!empty($response->status) ? 'Error status: ' . $response->status . ' - ' : '') . $response->message;
            
            if (! empty($response->errors)) {
                $arrError = [];
                foreach ($response->errors as $errors) {
                    foreach ($errors as $error) {
                        $arrError[] = '- ' . $error;
                    }
                }
                $errorMsg = implode("\n", $arrError);
                $this->errorMessage .= ":\n" . $errorMsg;
            }
            
            return false;
        }
        
        return false;
    }
    
    /**
     *
     * @param unknown $token
     * @param unknown $method
     * @param unknown $trxid
     * @return boolean
     */
    public function deleteRefund($token, $method, $trxid)
    {
        $url = "https://api.digiwallet.nl/refund/$method/$trxid";
        $request = new WP_Http();
        $response_full = $request->request($url, array('method' => 'DELETE', 'headers' => array(
            "authorization: Bearer " . $token,
            "cache-control: no-cache"
        )));
        
        if (isset($response_full->errors)) {
            $this->errorMessage = 'Something went wrong with Digiwallet API';
            return false;
        }
        
        $response = json_decode($response_full['body']);
        if(!empty($response->status)) {
            $this->errorMessage =  'Error status: ' . $response->status . ' - ' . $response->message;
        }
        
        if (!empty($response->errors)) {
            $arrError = [];
            foreach ($response->errors as $errors) {
                foreach ($errors as $error) {
                    $arrError[] = '- ' . $error;
                }
            }
            $errorMsg = implode("\n", $arrError);
            $this->errorMessage .= ":\n" . $errorMsg;
        }
        
        if($this->errorMessage) {
            return false;
        }
        else {
            return true;
        }
    }
    
    /**
     * Retrieve customer information
     *
     * @return string
     */
    public function getConsumerInfo()
    {
        return $this->consumerInfo;
    }
}
