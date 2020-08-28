<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}

/**
 * Bankwire Banking plugin for Event Espresso 4
 */
class EE_PMT_Bankwire extends EE_PMT_Base
{

    public $tpPaymethodDisplayName = 'Bankwire';

    public $tpPaymethodName = 'Bankwire';

    public $tpPaymethodId = 'BW';

    public function __construct($pm_instance = null)
    {
        require_once ($this->file_folder() . 'EEG_Bankwire.gateway.php');
        $this->_gateway = new EEG_Bankwire();
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
                            'html_label_text' => __( 'Digiwallet Outlet Identifier', 'digiwallet' ),
                            'html_help_text'  => __('Your Digiwallet Outlet Identifier, You can find this in your organization dashboard under Websites & Outlets on <a href="https://www.digiwallet.nl" target="_blank">https://www.digiwallet.nl</a>', 'digiwallet'),
                            'required' => true
                        )
                    ),
                    'token' => new EE_Text_Input(
                        array(
                            'html_label_text' => __( 'Digiwallet token', 'digiwallet' ),
                            'html_help_text'  => __('Obtain a token from <a href="http://digiwallet.nl" target="_blank">http://digiwallet.nl</a>', 'digiwallet'),
                            'required' => false
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
    
    /**
     * For adding any html output ab ove the payment overview.
     * Many gateways won't want ot display anything, so this function just returns an empty string.
     * Other gateways may want to override this, such as offline gateways.
     * @return string
     */
    public function payment_overview_content(EE_Payment $payment){
        if (empty($_SESSION)) session_start();
        $extra_meta_for_payment_method = $this->_pm_instance->all_extra_meta_array();
        if (empty($_SESSION['bwData_'.$payment->transaction()->ID()]) || $payment->transaction()->status_ID() === EEM_Transaction::complete_status_code) {
            return null;
        }
        list($trxid, $accountNumber, $iban, $bic, $beneficiary, $bank) = explode("|", $_SESSION['bwData_'.$payment->transaction()->ID()]);
        
        $template_vars = array_merge(
            array(
                'trxid' => $trxid,
                'accountNumber' => $accountNumber,
                'iban' => $iban, 
                'bic' => $bic, 
                'beneficiary' => $beneficiary, 
                'bank' => $bank,
                'amount' => $payment->transaction()->remaining(),
                'email' => $payment->get_primary_attendee()->email()
            ),
            $extra_meta_for_payment_method);

        return EEH_Template::locate_template(
            realpath(dirname(__FILE__)) . DS . 'templates'.DS.'bankwire_payment_details_content.template.php',
            $template_vars
            );
    }
}
