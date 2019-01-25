<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}

require_once (dirname(__DIR__) . "/targetpay.class.gateway.php");

/**
 * EEG_Bankwire
 *
 * Bancontact Banking plugin for Event Espresso 4
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
class EEG_Bankwire extends Targetpay_Gateway
{
    public $tpPaymethodName = 'Bankwire';

    public $tpPaymethodId = 'BW';
    
    private $salt = 'e381277';
    
    protected $forceIPN = true;
    /**
     * Event handler to attach additional parameters.
     *
     * @param $payment
     * @param TargetPayCore $targetPay
     */
    public function additionalParameters($payment, $targetPay)
    {
        $attendee = $payment->get_primary_attendee();
        $targetPay->bindParam('salt', $this->salt);
        $targetPay->bindParam('email', $attendee->email());
        $targetPay->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
    }
    
    public function setRedirect($payment, $url, $targetPay, $return_url) {
        $_SESSION['bwData_'.$payment->transaction()->ID()] = $targetPay->getMoreInformation();
        $payment->set_redirect_url($return_url);
        $payment->set_redirect_args(array('transactionID' => $targetPay->getTransactionId()));
        return true;
    }
    
    protected function getAdditionParametersReport($extOrder)
    {
        $param = [];
        $checksum = md5($extOrder->targetpay_txid . $this->_rtlo . $this->salt);
        $param['checksum'] = $checksum;
        return $param;
    }
    
    public function handle_IPN_in_this_request( $request_data, $separate_IPN_request ) {
        if( $separate_IPN_request ) {
            // payment data being sent in a request separate from the user
            // it is this other request that will update the TXN and payment info
            return $this->_uses_separate_IPN_request;
        } else {
            // it's a request where the user returned from an offsite gateway WITH the payment data
            return ! $this->_uses_separate_IPN_request;
        }
    }
}
