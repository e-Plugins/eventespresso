<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}
require_once (EE_DIGIWALLET_PLUGIN_PATH . '/includes/digiwallet.class.gateway.php');

/**
 * EEG_Ideal
 * 
 * Ideal banking plugin for Event Espresso 4
 */
class EEG_Ideal extends Digiwallet_Gateway
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
     * @param DigiwalletCore $digiwallet
     */
    public function additionalParameters($payment, $digiwallet)
    {
        $bankId = sanitize_text_field($_POST[strtolower($this->tpPaymethodName) . "_select_box"]);
        $digiwallet->setBankId($bankId);
    }
}
