<?php
/*
 * Event Espresso - Digiwallet (EE 4.x+) payment module
 * 
 * @author DigiWallet.nl <techsupport@targetmedia.nl>
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @copyright Copyright (C) 2019 e-plugins.nl
 * 
 * Plugin Name: Event Espresso - Digiwallet (EE 4.x+)
 * Plugin URI: https://www.digiwallet.nl
 * Description: Activates iDEAL, Bancontact, Sofort Banking, Visa / Mastercard Credit cards, PaysafeCard, AfterPay, BankWire, PayPal and Refunds in Event Espresso.
 * Version: 1.0.2
 * Author: DigiWallet.nl
 * Author URI: https://www.digiwallet.nl
 * Text Domain: digiwallet
 */

define( 'EE_DIGIWALLET_VERSION', '1.0.3' );
define( 'EE_DIGIWALLET_PLUGIN_FILE', __FILE__ );
define( 'EE_DIGIWALLET_PLUGIN_PATH', realpath(dirname(__FILE__)));
define( 'DIGIWALLET_TABLE_NAME', 'ee4_digiwallet' );
require(__DIR__ . '/vendor/autoload.php');
if (! class_exists('DigiWalletInstall')) {
    require_once (realpath(dirname(__FILE__)) . '/includes/install.php');
}
// create db when active plugin
register_activation_hook(__FILE__, array(
    'DigiWalletInstall',
    'install_db')
    );

// update db when plugin update complete
add_action( 'upgrader_process_complete', 'digiwallet_upgrade',10, 2);
// check db when load plugin
add_action( 'plugins_loaded', 'digiwallet_check_db');
add_action('AHEE__EE_System__core_loaded_and_ready', 'digiwallet_init_class', 10);
add_action( 'AHEE__EE_System__load_espresso_addons', 'load_espresso_digiwallet_payment_method' );

function digiwallet_upgrade ( $upgrader_object, $options ) {
    $current_plugin_path_name = plugin_basename( __FILE__ );
    if ($options['action'] == 'update' && $options['type'] == 'plugin' ){
        foreach($options['plugins'] as $each_plugin){
            if ($each_plugin == $current_plugin_path_name) {
                (new DigiWalletInstall)->install_db();
            }
        }
    }
}

function digiwallet_check_db() {
    (new DigiWalletInstall)->install_db();
}

function load_espresso_digiwallet_payment_method() {
    if ( class_exists( 'EE_Addon' ) ) {
        // new_payment_method version
        require_once( plugin_dir_path( __FILE__ ) . 'EE_Digiwallet.class.php' );
        EE_Digiwallet::register_addon();
    }
}

function digiwallet_init_class()
{
    if (! class_exists('DigiwalletCoreEE4')) {
        require_once (EE_DIGIWALLET_PLUGIN_PATH . '/includes/digiwallet.class.php');
    }
}