<?php
class DigiWalletInstall
{
    /**
     * install db when active plugin
     * - create new db
     */
    public static function install_db()
    {
        global $wpdb;
        $digiwalletTbl = $wpdb->base_prefix . DIGIWALLET_TABLE_NAME;
        if(!$wpdb->get_var("SHOW TABLES LIKE '$digiwalletTbl'") == $digiwalletTbl) {
            self::create_digiwallet_db();
        }
    }

    /**
     * Create woocommerce_digiwallet table
     */
    public static function create_digiwallet_db()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . DIGIWALLET_TABLE_NAME . "` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `order_id` varchar(64) NOT NULL DEFAULT '',
              `paymethod` varchar(10) DEFAULT NULL,
              `amount` int(11) DEFAULT NULL,
              `rtlo` int(11) DEFAULT NULL,
              `token` varchar(100) DEFAULT NULL,
              `transaction_id` varchar(100) DEFAULT NULL,
              `message` varchar(500) DEFAULT NULL,
              `more` text NULL,
              `paid` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `order_id` (`order_id`)
            ) " . $charset_collate . ";";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
