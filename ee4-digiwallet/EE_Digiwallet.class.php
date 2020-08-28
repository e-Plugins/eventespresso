<?php if ( ! defined( 'EVENT_ESPRESSO_VERSION' ) ) {
	exit();
}

// define the plugin directory path and URL
define( 'EE_DIGIWALLET_BASENAME', plugin_basename( EE_DIGIWALLET_PLUGIN_FILE ) );
define( 'EE_DIGIWALLET_PATH', plugin_dir_path( __FILE__ ) );
define( 'EE_DIGIWALLET_URL', plugin_dir_url( __FILE__ ) );

/**
 * ------------------------------------------------------------------------
 *
 * Class  EE_Digiwallet
 *
 * @package Event Espresso
 * @author DigiWallet.nl
 * @ version 1.0.0
 *
 * ------------------------------------------------------------------------
 */
Class EE_Digiwallet extends EE_Addon {

	/**
	 * class constructor
	 */
	public function __construct() {
	}

	/**
	 * Registers addon gateway
	 */
	public static function register_addon() {
		// register addon via Plugin API
		EE_Register_Addon::register( 'Digiwallet', array(
		    'version'              => EE_DIGIWALLET_VERSION,
			'min_core_version'     => '4.6.0.dev.000',
			'main_file_path'       => EE_DIGIWALLET_PLUGIN_FILE,
			'admin_callback'       => 'additional_admin_hooks',
			'payment_method_paths' => array(
			    EE_DIGIWALLET_PATH . 'payment_methods' . DS . 'Afterpay',
			    EE_DIGIWALLET_PATH . 'payment_methods' . DS . 'Bancontact',
			    EE_DIGIWALLET_PATH . 'payment_methods' . DS . 'Bankwire',
			    EE_DIGIWALLET_PATH . 'payment_methods' . DS . 'Creditcard',
			    EE_DIGIWALLET_PATH . 'payment_methods' . DS . 'Ideal',
			    EE_DIGIWALLET_PATH . 'payment_methods' . DS . 'Paypal',
			    EE_DIGIWALLET_PATH . 'payment_methods' . DS . 'Paysafecard',
			    EE_DIGIWALLET_PATH . 'payment_methods' . DS . 'Sofort',
			    EE_DIGIWALLET_PATH . 'payment_methods' . DS . 'EPS',
			    EE_DIGIWALLET_PATH . 'payment_methods' . DS . 'Giropay',
			),
		) );

		add_action( 'init', __CLASS__ . '::load_i18n' );
	}

	/**
	 * Loads I18n
	 */
	public static function load_i18n() {
		load_plugin_textdomain( 'digiwallet', false, dirname( plugin_basename( EE_DIGIWALLET_PLUGIN_FILE ) ) . '/languages/' );
	}


	/**
	 *    additional_admin_hooks
	 *
	 * @access    public
	 * @return    void
	 */
	public function additional_admin_hooks() {
		// is admin and not in M-Mode ?
		if ( is_admin() && ! EE_Maintenance_Mode::instance()->level() ) {
			add_filter( 'plugin_action_links', array( $this, 'plugin_actions' ), 10, 2 );
		}
	}


	/**
	 * plugin_actions
	 *
	 * Add a settings link to the Plugins page, so people can go straight from the plugin page to the settings page.
	 *
	 * @param $links
	 * @param $file
	 *
	 * @return array
	 */
	public function plugin_actions( $links, $file ) {
		if ( $file == EE_DIGIWALLET_BASENAME ) {
			// before other links
			array_unshift( $links, '<a href="admin.php?page=espresso_payment_settings">' . __('Settings', 'event_espresso') . '</a>' );
		}

		return $links;
	}


}
