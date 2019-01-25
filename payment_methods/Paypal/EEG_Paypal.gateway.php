<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}

require_once (dirname(__DIR__) . "/targetpay.class.gateway.php");

/**
 * EEG_Paypal
 *
 * Paypal Banking plugin for Event Espresso 4
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
class EEG_Paypal extends Targetpay_Gateway
{
    public $tpPaymethodName = 'Paypal';

    public $tpPaymethodId = 'PYP';
}
