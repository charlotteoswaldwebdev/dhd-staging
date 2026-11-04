<?php

/**
 * Fired during plugin activation
 *
 * @link       https://wordpress-1657598-6759210.cloudwaysapps.com
 * @since      1.0.0
 *
 * @package    Heat_Loss_Calculator
 * @subpackage Heat_Loss_Calculator/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Heat_Loss_Calculator
 * @subpackage Heat_Loss_Calculator/includes
 * @author     Charlotte Oswald <web@charlotteoswald.com>
 */
class Heat_Loss_Calculator_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		self::create_table();
	}

	/**
	 * Create the rooms table.
	 *
	 * The activator was previously a no-op, so on any install where the table
	 * was never created by hand every $wpdb->insert() failed silently. dbDelta
	 * only adds what is missing, so this is safe to run against a table that
	 * already holds data.
	 *
	 * Columns and types mirror Heat_Loss_Calculator_Model::insert().
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $wpdb->prefix . 'heat_loss_calculator';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uid varchar(191) NOT NULL DEFAULT '',
			user_ref varchar(191) NOT NULL DEFAULT '',
			site_name varchar(191) NOT NULL DEFAULT '',
			room varchar(191) NOT NULL DEFAULT '',
			year_built smallint(6) NOT NULL DEFAULT 0,
			inside_temp smallint(6) NOT NULL DEFAULT 0,
			outside_temp smallint(6) NOT NULL DEFAULT 0,
			floor_area double NOT NULL DEFAULT 0,
			ceiling_height double NOT NULL DEFAULT 0,
			exposed_external_area double NOT NULL DEFAULT 0,
			exposed_ceiling_area double NOT NULL DEFAULT 0,
			exposed_floor_area double NOT NULL DEFAULT 0,
			window_and_door_area double NOT NULL DEFAULT 0,
			roof_light_area double NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY uid (uid),
			KEY user_ref (user_ref)
		) $charset_collate;";

		dbDelta( $sql );
	}

}
