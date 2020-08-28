<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}
require_once (EE_DIGIWALLET_PLUGIN_PATH . '/includes/digiwallet.class.gateway.php');
require_once (EE_DIGIWALLET_PLUGIN_PATH . '/payment_methods/EPS/EEG_EPS.gateway.php');
/**
 * EEG_Giropay
 *
 * Giropay Banking plugin for Event Espresso 4
 */
class EEG_Giropay extends EEG_EPS
{
    public $tpPaymethodName = 'Giropay';

    public $tpPaymethodId = 'GIP';
}
