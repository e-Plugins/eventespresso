<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}
require_once (EE_DIGIWALLET_PLUGIN_PATH . '/includes/digiwallet.class.gateway.php');

/**
 * Sofort Banking plugin for Event Espresso 4
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
class EEG_Sofort extends Digiwallet_Gateway
{
    public $tpPaymethodName = 'Sofort';

    public $tpPaymethodId = 'DEB';

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
        $countryId = sanitize_text_field($_POST[strtolower($this->tpPaymethodName) . "_select_box"]);
        $digiwallet->setCountryId($countryId);
    }
}
