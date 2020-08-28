<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}
require_once (EE_DIGIWALLET_PLUGIN_PATH . '/includes/digiwallet.class.gateway.php');

/**
 * EEG_Bancontact
 *
 * Bancontact Banking plugin for Event Espresso 4
 */
class EEG_Bancontact extends Digiwallet_Gateway
{
    public $tpPaymethodName = 'Bancontact';

    public $tpPaymethodId = 'MRC';
}
