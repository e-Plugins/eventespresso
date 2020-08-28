<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}
require_once (EE_DIGIWALLET_PLUGIN_PATH . '/includes/digiwallet.class.gateway.php');

/**
 * EEG_Bankwire
 *
 * Bancontact Banking plugin for Event Espresso 4
 */
class EEG_Bankwire extends Digiwallet_Gateway
{
    public $tpPaymethodName = 'Bankwire';

    public $tpPaymethodId = 'BW';
    
    private $salt = 'e381277';
    
    protected $forceIPN = true;
    /**
     * Event handler to attach additional parameters.
     *
     * @param $payment
     * @param DigiwalletCore $digiwallet
     */
    public function additionalParameters($payment, $digiwallet)
    {
        $attendee = $payment->get_primary_attendee();
        $digiwallet->bindParam('salt', $this->salt);
        $digiwallet->bindParam('email', $attendee->email());
        $digiwallet->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
    }
    
    public function setRedirect($payment, $url, $digiwallet=null, $return_url=null) {
        $_SESSION['bwData_'.$payment->transaction()->ID()] = $digiwallet->getMoreInformation();
        $payment->set_redirect_url($return_url);
        $payment->set_redirect_args(array('transactionID' => $digiwallet->getTransactionId()));
        return true;
    }
    
    protected function getAdditionParametersReport($extOrder)
    {
        $param = [];
        $checksum = md5($extOrder->transaction_id . $this->_rtlo . $this->salt);
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
