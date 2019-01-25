<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}

require_once (dirname(__DIR__) . "/targetpay.class.gateway.php");

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
class EEG_Ideal extends Targetpay_Gateway
{
    public $tpPaymethodDisplayName = 'Ideal';
    
    public $tpPaymethodName = 'Ideal';
    
    public $tpPaymethodId = 'IDE';

    public function set_settings($settings_array)
    {
        $this->setPaymentMethodOptions($this->tpPaymethodName);
        parent::set_settings($settings_array);
    }

    /**
     * Event handler to attach additional parameters.
     * 
     * @param $payment
     * @param TargetPayCore $targetPay
     */
    public function additionalParameters($payment, $targetPay)
    {
        $bankId = $_POST[strtolower($this->tpPaymethodName) . "_select_box"];
        $targetPay->setBankId($bankId);
    }
}
