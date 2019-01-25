<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}

/**
 * Ideal Banking plugin for Event Espresso 4
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
class EE_PMT_Ideal extends EE_PMT_Base
{

    public $tpPaymethodDisplayName = 'Ideal';

    public $tpPaymethodName = 'Ideal';

    public $tpPaymethodId = 'IDE';

    public function __construct($pm_instance = null)
    {
        if (! class_exists('TargetPayCoreEE4') || ! class_exists('TargetPayCoreEE4', true)) {
            require_once (dirname(__DIR__) . "/targetpay.class.php");
        }
        require_once ($this->file_folder() . 'EEG_Ideal.gateway.php');
        $this->_gateway = new EEG_Ideal();
        
        $this->_pretty_name = __($this->tpPaymethodDisplayName, 'event_espresso');
        $this->_template_path = $this->file_folder() . 'templates' . DS;
        
        $this->_default_description = __('After finalizing your registration, you will be transferred to the website of your bank where your payment will be securely processed.', 'event_espresso');
        
        $this->_gateway_name = $this->tpPaymethodName;
        $this->payment_method_type = 'off-site';
        parent::__construct( $pm_instance );
        $this->_default_button_url = $this->file_url() . 'lib' . DS . $this->tpPaymethodId . '_60.png';
        add_action( 'AHEE__Transactions_Admin_Page__apply_payments_or_refund__after_recording', array($this->_gateway, 'process_refund'), 10, 2 );
    }
    

    /**
     * Gets the form for all the settings related to this payment method type.
     *
     * @return EE_Payment_Method_Form
     */
    public function generate_new_settings_form()
    {
        EE_Registry::instance()->load_helper('Template');
        $form = new EE_Payment_Method_Form(
            array(
                'extra_meta_inputs' => array(
                    'rtlo' => new EE_Text_Input(
                        array(
                            'html_label_text' => __( 'TargetPay layout code', 'event_espresso' ),
                            'html_help_text'  => __('You can find this in your TargetPay account.', 'event_espresso'),
                            'required' => true
                        )
                    ),
                ),
                'exclude' => array(
                    'PMD_debug_mode'
                )
            )
        );
        return $form;
    }

    /**
     * Creates a billing form for this payment method type.
     *
     * @param \EE_Transaction $transaction
     * @return \EE_Billing_Info_Form
     */
    public function generate_new_billing_form(EE_Transaction $transaction = null)
    {
        return null;
    }
}
