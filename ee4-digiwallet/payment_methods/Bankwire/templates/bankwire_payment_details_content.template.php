<?php

if (!defined('EVENT_ESPRESSO_VERSION'))
	exit('No direct script access allowed');

?>
		<div class="event-display-boxes">
			<div class="bankwire-info">
				<h3><?php _e('Thank you for ordering!', 'digiwallet') ?></h3>
                <p>
                    <?php _e('You will receive your order as soon as we receive payment from the bank.', 'digiwallet')?> <br>
                    <?php printf( __( 'Would you be so friendly to transfer the total amount of € %1$s to the bankaccount <b style="color:red">%2$s</b> in name of %3$s* ?', 'digiwallet' ), esc_html($amount), esc_html($iban), esc_html($beneficiary));?>
                </p>
                <p>
                    <?php printf( __('State the payment feature <b>%1$s</b>, this way the payment can be automatically processed.<br>
                    As soon as this happens you shall receive a confirmation mail on %2$s.', 'digiwallet'), esc_html($trxid), esc_html($email))?>
                </p>
                <p>
                    <?php printf( __( 'If it is necessary for payments abroad, then the BIC code from the bank <span style="color:red">%1$s</span> and the name of the bank is %2$s.', 'digiwallet' ), esc_html($bic), esc_html($bank));?>
                    
                <p>
                    <i><?php _e('* Payment for our webstore is processed by TargetMedia. TargetMedia is certified as a Collecting Payment Service Provider by Currence. This means we set the highest security standards when is comes to security of payment for you as a customer and us as a webshop.', 'digiwallet') ?></i>
                </p>
            </div>
		</div>
		<?php
// End of file check_payment_details_content.template.php