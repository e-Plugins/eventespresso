<?php
use Digiwallet\Packages\Transaction\Client\Client;
use Digiwallet\Packages\Transaction\Client\Request\CreateTransaction;
use Digiwallet\Packages\Transaction\Client\Request\CheckTransaction;

if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}
require_once (EE_DIGIWALLET_PLUGIN_PATH . '/includes/digiwallet.class.gateway.php');

/**
 * EEG_EPS
 *
 * EPS Banking plugin for Event Espresso 4
 */
class EEG_EPS extends Digiwallet_Gateway
{
    public $tpPaymethodName = 'EPS';

    public $tpPaymethodId = 'EPS';
    
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
        
        $amount = $transaction->remaining();
        
        $digiwalletApi = new Client(self::DIGIWALLET_API);
        
        $formParams = [
            'outletId' => $this->_rtlo,
            'currencyCode' => 'EUR',
            'consumerEmail' => $this->getConsumerEmail($payment),
            'description' => ('Transaction: ' . $transaction->ID()),
            'returnUrl' => str_replace('#checkout', '', $return_url),
            'reportUrl' => $notify_url,
            'cancelUrl' => $cancel_url,
            'consumerIp' => $this->getCustomerIP(),
            'suggestedLanguage' => 'NLD',
            'amountChangeable' => false,
            'inputAmount' => $amount * 100,
            'paymentMethods' => [
                $this->tpPaymethodId
            ],
            'app_id' => DigiwalletCoreEE4::APP_ID
        ];
        
        $request = new CreateTransaction($digiwalletApi, $formParams);
        $request->withBearer($this->_token);
        /** @var \Digiwallet\Packages\Transaction\Client\Response\CreateTransaction $apiResult */
        $apiResult = $request->send();
        if ($apiResult->status() !== 0) {
            error_log($apiResult->message());
            EE_Error::add_error(
                $apiResult->message(),
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
            'transaction_id' => esc_sql($apiResult->transactionId()),
            'more' => esc_sql(json_encode($apiResult->response()))
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
        
        $this->setRedirect($payment, $apiResult->launchUrl());
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
            $trxid = sanitize_text_field(@$update_info['transactionID']);
            if (empty($trxid)) {
                return $this->handleIPN($this->tpPaymethodName . ": missing trxid", $is_post, $payment, $payModel->failed_status());
            }
            
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
            
            $digiwalletApi = new Client(self::DIGIWALLET_API);
            $request = new CheckTransaction($digiwalletApi);
            $request->withBearer($tpPayment->token);
            $request->withOutlet($tpPayment->rtlo);
            $request->withTransactionId($tpPayment->transaction_id);
            /** @var \Digiwallet\Packages\Transaction\Client\Response\CheckTransaction $apiResult */
            $apiResult = $request->send();
            
//             if ($apiResult->getStatus() == 0 && $apiResult->getTransactionStatus() == 'Completed') {
//                 $order->update_status($this->orderStatus, "Method {$order->get_payment_method_title()}(Transaction ID $extOrder->transaction_id): ");
//                 $order->set_transaction_id($extOrder->transaction_id);
//                 $order->save();
//                 $this->updateDigiWalletTable($order, array('message' => null));
//                 do_action( 'woocommerce_payment_complete', $order->get_id());
//             } else {
//                 $this->updateDigiWalletTable($order, array('message' => esc_sql($apiResult->getMessage())));
//                 $order->update_status(self::WOO_ORDER_STATUS_FAILED, "Method {$order->get_payment_method_title()}(Transaction ID $extOrder->transaction_id): ");
//             }
            
            /* Verify payment */
            $gateway_response = null;
            if ($apiResult->getStatus() == 0 && $apiResult->getTransactionStatus() == 'Completed') {
                /* Update temptable */
                $sql = "UPDATE `" . $wpdb->base_prefix . DIGIWALLET_TABLE_NAME . "` SET `paid` = now() WHERE `id`=%s";
                $wpdb->get_results($wpdb->prepare($sql, $tpPayment->id));
                $gateway_response = __('Your payment is approved.', 'digiwallet');
                $payment->set_status($payModel->approved_status());
                $primary_registrant = $transaction->primary_registration();
                $primary_registration_code = ! empty($primary_registrant) ? $primary_registrant->reg_code() : '';
                $payment->set_amount($tpPayment->amount);
                $payment->set_details($_POST);
                $payment->set_txn_id_chq_nmbr($trxid);
                $payment->set_extra_accntng($primary_registration_code);
            } else {
                /* Update temptable */
                $gateway_response = __('Digiwallet Error message: ', 'digiwallet') . $apiResult->getMessage();
                $sql = "UPDATE `" . $wpdb->base_prefix . DIGIWALLET_TABLE_NAME . "` SET `message` = '" . $gateway_response . "' WHERE `id`=%s";
                $wpdb->get_results($wpdb->prepare($sql, $tpPayment->id));
                $payment->set_status( $payModel->failed_status() );
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
}
