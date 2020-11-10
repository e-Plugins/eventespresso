<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}

/**
 * Digiwallet gateway for Event Espresso 4
 */
class Digiwallet_Gateway extends EE_Offsite_Gateway
{
    const DIGIWALLET_API = "https://api.digiwallet.nl/";
    
    const DEFAULT_RTLO = 93929;
    
    const METHOD_SOFORT = "sofort";
    
    const METHOD_IDEAL = "ideal";
    
    public $tpPaymethodName = null;

    public $tpPaymethodId = null;
    
    protected $forceIPN = false;
    
    /**
     * Digiwallet Outlet Identifier
     *
     * @var string
     */
    protected $_rtlo = null;
    protected $_token = null;

    /**
     * All the currencies supported by this gateway.
     *
     * @var array
     */
    protected $_currencies_supported = array('EUR');
    
    private $digiwalletCore = null;
    
    public function __construct()
    {
        /**
         * Whether or not this gateway can support SENDING a refund request (ie, initiated by
         * admin in EE's wp-admin page)
         *
         * @var boolean
         */
        $this->_supports_sending_refunds = true;
        
        if (! class_exists('DigiwalletCoreEE4') || ! class_exists('DigiwalletCoreEE4', true)) {
            require_once ("digiwallet.class.php");
        }
        
        $this->digiwalletCore = new DigiwalletCoreEE4($this->tpPaymethodId);
        $this->_rtlo = self::DEFAULT_RTLO;
        
        $is_post = $_SERVER['REQUEST_METHOD'] === 'POST' ? true : false;
        $e_reg_url_link = EE_Registry::instance()->REQ->get('e_reg_url_link', '');
        if (($is_post && $e_reg_url_link) || $this->forceIPN) {
            $this->set_uses_separate_IPN_request( true );
        }
        parent::__construct();
    }
    
    public function set_settings($settings_array)
    {
        parent::set_settings($settings_array);
    }

    public function EE_finalize($previousReturnValue,EE_Base_Class $object, $argsArray){return null;}
    
    /**
     *
     * {@inheritdoc}
     *
     * @see EE_Offsite_Gateway::set_redirection_info()
     */
    public function set_redirection_info($payment, $billing_info = array(), $return_url = null, $notify_url = null, $cancel_url = null)
    {
        global $wpdb;
        add_filter('FHEE__EE_Registration__finalize', array('this', 'EE_finalize'), 10, 3);
        
        $transaction = $payment->transaction();
        /**
         * @type EE_Transaction $primary_registrant
         *      to finalize the transaction follow ee4 flow.
         */
        // transaction calculation/finalize for method
        // commented out as request 17/0/2017. Beware this is diff from other payment methods from EE4.
        $primary_registrant = $transaction->primary_registration();
        $primary_registrant->finalize();
        
        $amount =$transaction->remaining();
        
        // set required info for API
        $digiwallet = new DigiwalletCoreEE4($this->tpPaymethodId, $this->_rtlo, "nl");
        
        $digiwallet->setAmount(round($amount * 100));
        $digiwallet->setDescription('Transaction: ' . $transaction->ID());
        // set return & report & cancel url, replace #checkout for cc method
        $digiwallet->setReturnUrl(str_replace('#checkout', '', $return_url));
        $digiwallet->setReportUrl($notify_url);
        $digiwallet->setCancelUrl($cancel_url);
        //Add consumer email
        $consumerEmail = $this->getConsumerEmail($payment);
        if ($consumerEmail) {
            $digiwallet->bindParam('email', $consumerEmail);
        }
        // Add additional parameters
        $this->additionalParameters($payment, $digiwallet);
        
        $url = $digiwallet->startPayment();
        
        if (!$url) {
            error_log($digiwallet->getErrorMessage());
            EE_Error::add_error(
                $digiwallet->getErrorMessage(),
                __FILE__,
                __FUNCTION__,
                __LINE__
                );
            return $payment;
        }
        
        // init db record for tx
        $insert = $wpdb->insert($wpdb->base_prefix . DIGIWALLET_TABLE_NAME, array(
            'order_id' => esc_sql($transaction->ID()),
            'paymethod' => esc_sql($this->tpPaymethodId),
            'amount' => esc_sql($amount),
            'rtlo' => esc_sql($this->_rtlo),
            'token' => esc_sql($this->_token),
            'transaction_id' => esc_sql($digiwallet->getTransactionId()),
            'more' => esc_sql($digiwallet->getMoreInformation())
        ), array(
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
            '%s'
        ));
        if (! $insert) {
            $message = "Payment could not be started: can not insert into digiwallet table";
            throw new EE_Error($message);
        }
        $this->setRedirect($payment, $url, $digiwallet, $return_url);
        return $payment;
    }
    
    /**
     * Handles an IPN, verifies we haven't already processed this IPN, creates a payment if succesful
     * and updates the provided transaction, and saves to DB
     *
     * {@inheritdoc}
     *
     * @see EE_Offsite_Gateway::handle_payment_update()
     */
    public function handle_payment_update($update_info, $transaction)
    {
        global $wpdb;
        $is_post = $_SERVER['REQUEST_METHOD'] === 'POST' ? true : false;
        $trxid = null;
        $payModel = $this->_pay_model;
        $payment = $payModel->get_payment_by_txn_id_chq_nmbr( $transaction->ID());
        if (! $payment instanceof EEI_Payment) {
            $payment = $transaction->last_payment();
        }
        if ($payment instanceof EEI_Payment) {
            switch ($this->tpPaymethodId) {
                case 'AFP':
                    $trxid = sanitize_text_field(@$update_info['invoiceID']);
                    break;
                case 'PYP':
                    if ($is_post) {
                        $trxid = sanitize_text_field(@$update_info['acquirerID']);
                    } else {
                        $trxid = sanitize_text_field(@$update_info['paypalid']);
                    }
                    break;
                default:
                    $trxid = sanitize_text_field(@$update_info['trxid']);
            }
            
            if (empty($trxid)) {
                return $this->handleIPN($this->tpPaymethodName . ": missing trxid", $is_post, $payment, $payModel->failed_status());
            }
            
            $digiwallet = new DigiwalletCoreEE4($this->tpPaymethodId, $this->_rtlo, "nl");
            
            /* Get transaction data from database */
            $sql = "SELECT * FROM `" . $wpdb->base_prefix . DIGIWALLET_TABLE_NAME . "` WHERE `order_id`=%s AND `transaction_id`=%s";
            $tpPayment = $wpdb->get_row($wpdb->prepare($sql, $transaction->ID(), $trxid));
            
            if (! $tpPayment) {
                return $this->handleIPN($this->tpPaymethodName . ": transaction id=" . $transaction->ID() . " trxid=" . $trxid. " not found...", $is_post, $payment, $payModel->failed_status());
            }
            // DUPLICATED callback: ensure that no duplicated request from api could be processed further. Stop here.
            if ($payment->status() === $payModel->approved_status()) {
                return $this->handleIPN($this->tpPaymethodName . ": Transaction id=" . $transaction->ID() . " had been done", $is_post);
            }
            
            /* Verify payment */
            $payResult = $digiwallet->checkPayment($trxid, $this->getAdditionParametersReport($tpPayment));
            $gateway_response = null;
            if (! $payResult) {
                /* Update temptable */
                $gateway_response = $digiwallet->getErrorMessage() ? __('Digiwallet Error message: ', 'digiwallet') . $digiwallet->getErrorMessage() : __('Your payment is failed.', 'digiwallet');
                $sql = "UPDATE `" . $wpdb->base_prefix . DIGIWALLET_TABLE_NAME . "` SET `message` = '" . $gateway_response . "' WHERE `id`=%s";
                $wpdb->get_results($wpdb->prepare($sql, $tpPayment->id));
                $payment->set_status( $payModel->failed_status() );
            } else {
                /* Update temptable */
                $sql = "UPDATE `" . $wpdb->base_prefix . DIGIWALLET_TABLE_NAME . "` SET `paid` = now() WHERE `id`=%s";
                $wpdb->get_results($wpdb->prepare($sql, $tpPayment->id));
                $amountPaid = $tpPayment->amount;
                $gateway_response = __('Your payment is approved.', 'digiwallet');
                if($tpPayment->paymethod == 'BW') {
                    $paymentIsPartial = false;
                    $consumer_info = $digiwallet->getConsumerInfo();
                    if (!empty($consumer_info) && $consumer_info['bw_paid_amount'] > 0) {
                        $amountPaid = number_format($consumer_info['bw_paid_amount'] / 100, 2);
                        if ($consumer_info['bw_paid_amount'] < $consumer_info['bw_due_amount']) {
                            $paymentIsPartial = true;
                        }
                    }
                    if ($paymentIsPartial) {
                        $gateway_response = __('Your payment is paid partial.', 'digiwallet');
                    }
                }
                $payment->set_status($payModel->approved_status());
                $primary_registrant = $transaction->primary_registration();
                $primary_registration_code = ! empty($primary_registrant) ? $primary_registrant->reg_code() : '';
                $payment->set_amount($amountPaid);
                $payment->set_details($_POST);
                $payment->set_txn_id_chq_nmbr($trxid);
                $payment->set_extra_accntng($primary_registration_code);
            }
            $payment->set_gateway_response($gateway_response);
            if ($is_post) {
                $payment->save();
                $payment_processor = EE_Registry::instance()->load_core( 'Payment_Processor' );
                $payment_processor->update_txn_based_on_payment($transaction, $payment, true, true);
                echo esc_html($gateway_response) . PHP_EOL;
                die('Done (EventEspresso 4, '.date('Y-m-d H:i:s').')');
            }
        } else {
            $payment->set_gateway_response(
                esc_html__(
                    'Error occurred while trying to process the payment.',
                    'digiwallet'
                    )
                );
            $payment->set_status($payModel->failed_status());
        }
        return $payment;
        
    }
    
    /**
     * @param EE_Transaction $transaction
     * @param EE_Payment     $payment
     */
    public function process_refund( $transaction, $payment ) {
        if ( strtolower($this->tpPaymethodName) == $payment->payment_method()->slug() ) {
            $current_user = wp_get_current_user();
            $username = $current_user->user_login;
            $transactionId = $transaction->ID();
            $dataRefund = array('paymethodID' => $this->tpPaymethodId, 
                                'transactionID' => $payment->txn_id_chq_nmbr(), 
                                'amount' => absint( $payment->amount() * 100), 
                                'description' => "Refund transaction $transactionId",
                                'internalNote' => "Refunded by $username"
            );
            $digiwallet = new DigiwalletCoreEE4($this->tpPaymethodId, $this->_rtlo, "nl");
            $digiwallet->refund($this->_token, $dataRefund);
        }
    }
    
    /*
     * SUPORTING METHODS
     */
    
    /**
     * Get consumer email
     *
     * @param $payment
     * @return string
     */
    public function getConsumerEmail($payment)
    {
        $attendee = $payment->get_primary_attendee();
        return $attendee->email();
    }
    
    /**
     * Event handler to attach additional parameters.
     * 
     * @param $payment
     * @param DigiwalletCore $digiwallet
     */
    public function additionalParameters($payment, $digiwallet)
    {
    }
    /**
     * addition params for report
     * @return array
     */
    protected function getAdditionParametersReport($tpPayment)
    {
        return [];
    }
    
    public function setRedirect($payment, $url, $digiwallet=null, $return_url=null) {
        $payment->set_redirect_url($url);
        return true;
    }
    /**
     * @author Peter
     * Hacking tweak hook to ee4 core to include country/bank list for sofort and ideal.
     * The method name in each payment method should not be changed otherwise we need to double check the hook and re-test.
     *
     * @param unknown $methodName
     * @return true
     */
    public function setPaymentMethodOptions($methodName)
    {
        $methodName = strtolower($methodName);
        
        $text = array(
            self::METHOD_SOFORT => 'your country',
            self::METHOD_IDEAL => 'your bank'
        );
        // apply the filter
        $result = add_filter(
            "AFEE__Form_Section_Layout__spco_payment_method_info_{$methodName}__html",
            function ($html, $form_section) use ($methodName, $text) {
                $htmlDom = new DOMDocument();
                $htmlDom->loadHTML($html);
                $helpText = $htmlDom->createTextNode(__("Please select {$text[$methodName]}: ", 'digiwallet'));
                
                $parentNode = $htmlDom->getElementsByTagName('div')->item(0);
                if ($methodName == self::METHOD_IDEAL) {
                    $optionList = $this->buildSelectBankList($methodName);
                } else if ($methodName == self::METHOD_SOFORT) {
                    $optionList = $this->buildSelectCountryList($methodName);
                }
                
                $template = $htmlDom->createDocumentFragment();
                $template->appendXML($optionList);
                $parentNode->appendChild($helpText);
                $parentNode->appendChild($template);
                
                return $htmlDom->saveHTML();
            }, 10, 2);
        
        return $result;
    }
    
    public function buildSelectBankList($methodName)
    {
        $banks = $this->digiwalletCore->getBankList();
        $selectBox = '<select class="method-select" name="' . esc_attr($methodName) . '_select_box">';
        foreach ($banks as $key => $value) {
            $selectBox .= '<option value="' . esc_attr($key) . '">' . esc_html($value) . '</option>';
        }
        $selectBox .= '</select>' ;
        
        return $selectBox;
    }
    
    public function buildSelectCountryList($methodName)
    {
        $banks = $this->digiwalletCore->getCountryList();
        $selectBox = '<select class="method-select" name="' . esc_attr($methodName) . '_select_box">';
        foreach ($banks as $key => $value) {
            $selectBox .= '<option value="' . esc_attr($key) . '">' . esc_html($value) . '</option>';
        }
        $selectBox .= '</select>' ;
        
        return $selectBox;
    }
    
    public function handleIPN($message, $is_post = false, $payment = null, $status = null) {
        error_log($message);
        if ($is_post) {
            die($message);
        } else {
            if ($payment) {
                $payment->set_gateway_response($message);
                $payment->set_status($status);
                return $payment;
            } else {
                return false;
            }
        }
    }
    
    /***
     * Get user's ip address
     * @return mixed
     */
    public function getCustomerIP()
    {
        if(!empty($_SERVER['HTTP_CLIENT_IP'])){
            //ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }else{
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
}
