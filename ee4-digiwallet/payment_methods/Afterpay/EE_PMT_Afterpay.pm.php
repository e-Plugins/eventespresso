<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}

/**
 * Afterpay Banking plugin for Event Espresso 4
 */
class EE_PMT_Afterpay extends EE_PMT_Base
{

    public $tpPaymethodDisplayName = 'Afterpay';

    public $tpPaymethodName = 'Afterpay';

    public $tpPaymethodId = 'AFP';

    public function __construct($pm_instance = null)
    {
        require_once ($this->file_folder() . 'EEG_Afterpay.gateway.php');
        $this->_gateway = new EEG_Afterpay();
        $this->_pretty_name = $this->tpPaymethodDisplayName;
        $this->_template_path = $this->file_folder() . 'templates' . DS;
        $this->_default_description = __('After finalizing your registration, you will be transferred to the website of your bank where your payment will be securely processed.', 'digiwallet');
        $this->_gateway_name = $this->tpPaymethodName;
        $this->_payment_method_type = self::offsite;
        
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
                            'html_label_text' => __( 'Digiwallet Outletcode', 'digiwallet' ),
                            'html_help_text'  => __('Your Digiwallet Outletcode, Go to: <a href="https://www.digiwallet.nl/en/user/dashboard" target="_blank">https://www.digiwallet.nl/en/user/dashboard</a> >> choose your Organization >> Websites & Outlets', 'digiwallet'),
                            'required' => true,
                            'default' => Digiwallet_Gateway::DEFAULT_RTLO
                        )
                    ),
                    'token' => new EE_Text_Input(
                        array(
                            'html_label_text' => __( 'Digiwallet Api Token', 'digiwallet' ),
                            'html_help_text'  => __('Obtain your Token here, go to: <a href="https://www.digiwallet.nl/en/user/dashboard" target="_blank">https://www.digiwallet.nl/en/user/dashboard</a> >> choose your Organization >> Developers', 'digiwallet'),
                            'required' => false,
                            'default' => Digiwallet_Gateway::DEFAULT_TOKEN
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
