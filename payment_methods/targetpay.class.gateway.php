<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}

/**
 * Targetpay gateway for Event Espresso 4
 *
 * @package Event Espresso
 * @subpackage gateways/
 * @author Eveline van den Boom, Yellow Melon B.V.
 * @copyright (c) 2015 Yellow Melon B.V.
 * @copyright Portions (c) 2008-2011 Event Espresso All Rights Reserved.
 * @license http://eventespresso.com/support/terms-conditions/ * see Plugin Licensing *
 * @link http://www.eventespresso.com
 * @version 4.0
 *
 *          ------------------------------------------------------------------------
 */
class Targetpay_Gateway extends EE_Offsite_Gateway
{
    const MESG_VERSION_REPORT = "(EventEspresso 4, 17-01-2017)";
    
    const DEFAULT_RTLO = 93929;
    
    const TARGETPAY_TABLE = 'ee4_targetpay';
    
    const METHOD_SOFORT = "sofort";
    
    const METHOD_IDEAL = "ideal";
    
    const REFUND_TOKEN = '7479d7db51373a1eeb250919c';
    
    public $tpPaymethodName = null;

    public $tpPaymethodId = null;
    
    protected $forceIPN = false;
    
    /**
     * TargetPay layout code
     *
     * @var string
     */
    protected $_rtlo = null;

    /**
     * All the currencies supported by this gateway.
     *
     * @var array
     */
    protected $_currencies_supported = array('EUR');
    
    private $targetPayCore = null;
    
    public function __construct()
    {
        /**
         * Whether or not this gateway can support SENDING a refund request (ie, initiated by
         * admin in EE's wp-admin page)
         *
         * @var boolean
         */
        $this->_supports_sending_refunds = true;
        
        if (! class_exists('TargetPayCoreEE4') || ! class_exists('TargetPayCoreEE4', true)) {
            require_once ("targetpay.class.php");
        }
        
        $this->targetPayCore = new TargetPayCoreEE4($this->tpPaymethodId);
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
        add_filter('FHEE__EE_Registration__finalize', array('this', 'EE_finalize'), 10, 3);
        
        $bankId = $_POST[strtolower($this->tpPaymethodName) . "_select_box"];
        
        $transaction = $payment->transaction();
        /**
         * @type EE_Transaction $primary_registrant
         *      to finalize the transaction follow ee4 flow.
         */
        // transaction calculation/finalize for method
        // commented out as request 17/0/2017. Beware this is diff from other payment methods from EE4.
        $primary_registrant = $transaction->primary_registration();
        $primary_registrant->finalize();
        
        $amount = round($transaction->remaining() * 100);
        
        // set required info for API
        $targetPay = new TargetPayCoreEE4($this->tpPaymethodId, $this->_rtlo, "nl");
        
        $targetPay->setAmount($amount);
        $targetPay->setDescription('Transaction: ' . $transaction->ID());
        // set return & report & cancel url, replace #checkout for cc method
        $targetPay->setReturnUrl(str_replace('#checkout', '', $return_url));
        $targetPay->setReportUrl($notify_url);
        $targetPay->setCancelUrl($cancel_url);
        $this->additionalParameters($payment, $targetPay);
        
        $url = $targetPay->startPayment();
        
        if (!$url) {
            error_log($targetPay->getErrorMessage());
            EE_Error::add_error(
                $targetPay->getErrorMessage(),
                __FILE__,
                __FUNCTION__,
                __LINE__
                );
            return $payment;
        }
        
        // init db record for tx
        $this->initDbRecordForTx($transaction, $targetPay);
        $this->setRedirect($payment, $url, $targetPay, $return_url);
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
                    $trxid = esc_sql(@$update_info['invoiceID']);
                    break;
                case 'PYP':
                    if ($is_post) {
                        $trxid = esc_sql(@$update_info['acquirerID']);
                    } else {
                        $trxid = esc_sql(@$update_info['paypalid']);
                    }
                    break;
                default:
                    $trxid = esc_sql(@$update_info['trxid']);
            }
            
            if (empty($trxid)) {
                return $this->handleIPN($this->tpPaymethodName . ": missing trxid", $is_post, $payment, $payModel->failed_status());
            }
            
            $targetPay = new TargetPayCoreEE4($this->tpPaymethodId, $this->_rtlo, "nl");
            
            /* Get transaction data from database */
            $sql = "SELECT * FROM `" . $wpdb->base_prefix . self::TARGETPAY_TABLE . "` WHERE `order_id`=%s AND `targetpay_txid`=%s";
            $tpPayment = $wpdb->get_row($wpdb->prepare($sql, $transaction->ID(), $trxid));
            
            if (! $tpPayment) {
                return $this->handleIPN($this->tpPaymethodName . ": transaction id=" . $transaction->ID() . " trxid=" . $trxid. " not found...", $is_post, $payment, $payModel->failed_status());
            }
            // DUPLICATED callback: ensure that no duplicated request from api could be processed further. Stop here.
            if ($payment->status() === $payModel->approved_status()) {
                return $this->handleIPN($this->tpPaymethodName . ": Transaction id=" . $transaction->ID() . " had been done", $is_post);
            }
            
            /* Verify payment */
            $payResult = $targetPay->checkPayment($trxid, $this->getAdditionParametersReport($tpPayment));
            $gateway_response = null;
            if (! $payResult) {
                /* Update temptable */
                $gateway_response = $targetPay->getErrorMessage() ? __('Digiwallet Error message: ', 'event_espresso') . $targetPay->getErrorMessage() : __('Your payment is failed.', 'event_espresso');
                $sql = "UPDATE `" . $wpdb->base_prefix . self::TARGETPAY_TABLE . "` SET `targetpay_response` = '" . $gateway_response . "' WHERE `id`=%s";
                $wpdb->get_results($wpdb->prepare($sql, $tpPayment->id));
                $payment->set_status( $payModel->failed_status() );
            } else {
                /* Update temptable */
                $sql = "UPDATE `" . $wpdb->base_prefix . self::TARGETPAY_TABLE . "` SET `paid` = now() WHERE `id`=%s";
                $wpdb->get_results($wpdb->prepare($sql, $tpPayment->id));
                $amountPaid = number_format($tpPayment->amount / 100, 2);
                $gateway_response = __('Your payment is approved.', 'event_espresso');
                if($tpPayment->method == 'BW') {
                    $paymentIsPartial = false;
                    $consumber_info = $targetPay->getConsumerInfo();
                    if (!empty($consumber_info) && $consumber_info['bw_paid_amount'] > 0) {
                        $amountPaid = number_format($consumber_info['bw_paid_amount'] / 100, 2);
                        if ($consumber_info['bw_paid_amount'] < $consumber_info['bw_due_amount']) {
                            $paymentIsPartial = true;
                        }
                    }
                    if ($paymentIsPartial) {
                        $gateway_response = __('Your payment is paid partial.', 'event_espresso');
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
                echo $gateway_response;
                die('Done ' . self::MESG_VERSION_REPORT);
            }
        } else {
            $payment->set_gateway_response(
                esc_html__(
                    'Error occurred while trying to process the payment.',
                    'event_espresso'
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
            $targetPay = new TargetPayCoreEE4($this->tpPaymethodId, $this->_rtlo, "nl");
            $targetPay->refund(self::REFUND_TOKEN, $dataRefund);
        }
    }
    
    /*
     * SUPORTING METHODS
     */
    
    /**
     * Event handler to attach additional parameters.
     * 
     * @param $payment
     * @param TargetPayCore $targetPay
     */
    public function additionalParameters($payment, $targetPay)
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
    
    public function setRedirect($payment, $url, $targetPay, $return_url) {
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
                $helpText = $htmlDom->createTextNode(__("Please select {$text[$methodName]}: ", 'event_espresso'));
                
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
    
    public function createTableQuery()
    {
        global $wpdb;
        $sql = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . self::TARGETPAY_TABLE . "` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `order_id` varchar(64) NOT NULL DEFAULT '',
              `method` varchar(6) DEFAULT NULL,
              `amount` int(11) DEFAULT NULL,
              `targetpay_txid` varchar(64) DEFAULT NULL,
              `targetpay_response` varchar(128) DEFAULT NULL,
              `paid` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `order_id` (`order_id`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=latin1;";
        return $wpdb->query($sql);
    }
    
    public function buildSelectBankList($methodName)
    {
        $banks = $this->targetPayCore->getBankList();
        $selectBox = '<select class="method-select" name="' . $methodName . '_select_box">';
        foreach ($banks as $key => $value) {
            $selectBox .= '<option value="' . $key . '">' . $value . '</option>';
        }
        $selectBox .= '</select>' ;
        
        return $selectBox;
    }
    
    public function buildSelectCountryList($methodName)
    {
        $banks = $this->targetPayCore->getCountryList();
        $selectBox = '<select class="method-select" name="' . $methodName . '_select_box">';
        foreach ($banks as $key => $value) {
            $selectBox .= '<option value="' . $key . '">' . $value . '</option>';
        }
        $selectBox .= '</select>' ;
        
        return $selectBox;
    }
    
    /**
     *
     * @param unknown $wpdb
     * @param unknown $gw
     * @throws EE_Error
     */
    public function initDbRecordForTx($transaction, $targetPay)
    {
        global $wpdb;
        try {
            /* Create table if not exists */
            $this->createTableQuery();
            
            /* Save transaction data */
            $sql = "INSERT INTO `" . $wpdb->base_prefix . self::TARGETPAY_TABLE . "` SET `order_id` = %s, `method` = %s, `targetpay_txid` = %s, `amount` = %s";
            $wpdb->get_results($wpdb->prepare($sql, $transaction->ID(), $this->tpPaymethodId, $targetPay->getTransactionId(), $targetPay->getAmount()));
        } catch (\Exception $e) {
            throw new EE_Error($e->getMessage(), $e->getCode());
        }
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
    
}
