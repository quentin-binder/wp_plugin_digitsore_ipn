<?php

/**
 * Fired during plugin activation
 *
 * @link       qbi.at
 * @since      1.0.0
 *
 * @package    Digistore
 * @subpackage Digistore/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Digistore
 * @subpackage Digistore/includes
 * @author     Quentin Binder <q.binder@qbi.at>
 */
class Digistore_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
        self::create_db();
	}


    /**
     * Create database to store orders
     */

    public static function create_db()
    {

        global $wpdb;
        $table_name = $wpdb->prefix . 'mbqb_orders';
        $mbqb_gen_poi_db_version = get_option('mbqb_orders_db_version', '1.0');

        if ($wpdb->get_var("show tables like '{$table_name}'") != $table_name ||
            version_compare($mbqb_gen_poi_db_version, '1.0') < 0) {

            $charset_collate = $wpdb->get_charset_collate();


            $sql = "CREATE TABLE $table_name (
                    ipn_call mediumint(9) NOT NULL AUTO_INCREMENT,
                    order_id varchar(100) DEFAULT '',
                    user_id varchar(100) DEFAULT '',
                    email varchar(100) DEFAULT '',
                    ipn_event varchar(100) DEFAULT '',
                    data mediumblob DEFAULT '',
                    time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (ipn_call)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            /**
             * It seems IF NOT EXISTS isn't needed if you're using dbDelta - if the table already exists it'll
             * compare the schema and update it instead of overwriting the whole table.
             *
             * @link https://code.tutsplus.com/tutorials/custom-database-tables-maintaining-the-database--wp-28455
             */
            dbDelta($sql);

            add_option('mbqb_orders_db_version', $mbqb_gen_poi_db_version);

        }

    }


}
