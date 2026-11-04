<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://wordpress-1657598-6759210.cloudwaysapps.com
 * @since             1.0.0
 * @package           Heat_Loss_Calculator
 *
 * @wordpress-plugin
 * Plugin Name:       Discount Heating Direct - Heat Loss Calculator
 * Plugin URI:        https://wordpress-1657598-6759210.cloudwaysapps.com
 * Description:       Heat Loss Calculator.
 * Version:           1.0.1
 * Author:            Charlotte Oswald (Charlotte's Web)
 * Author URI:        https://charlotteoswald.com
 * License:           Proprietary
 * License URI:       Proprietary
 * Text Domain:       heat-loss-calculator
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access allowed' );
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'HEAT_LOSS_CALCULATOR_VERSION', '1.0.1' );

/**
 * Company details.
 *
 * These appear on the public calculator pages and on the downloadable PDF
 * report. They are defined here so they can be changed in one place, and can
 * be overridden from wp-config.php if ever needed.
 */
if ( ! defined( 'HLC_COMPANY_NAME' ) ) {
	define( 'HLC_COMPANY_NAME', 'Discount Heating Direct' );
}
if ( ! defined( 'HLC_COMPANY_LEGAL_NAME' ) ) {
	define( 'HLC_COMPANY_LEGAL_NAME', 'Discount Heating Direct (Company number SC887239)' );
}
if ( ! defined( 'HLC_COMPANY_ADDRESS' ) ) {
	define( 'HLC_COMPANY_ADDRESS', 'Block 5, Unit 38, Third Road, Blantyre Industrial Estate, Blantyre, G72 0UP' );
}
if ( ! defined( 'HLC_COMPANY_PHONE' ) ) {
	define( 'HLC_COMPANY_PHONE', '0113 322 7719' );
}
if ( ! defined( 'HLC_COMPANY_EMAIL' ) ) {
	define( 'HLC_COMPANY_EMAIL', 'enquiries@discountheatingdirect.co.uk' );
}
if ( ! defined( 'HLC_COMPANY_WEBSITE' ) ) {
	// Displayed on the PDF cover. Update when the site moves to its live domain.
	define( 'HLC_COMPANY_WEBSITE', 'wordpress-1657598-6759210.cloudwaysapps.com' );
}

/**
 * Debug logging switch.
 *
 * Set `define( 'HLC_DEBUG', true );` in wp-config.php to trace the submit flow
 * in the PHP error log. Defaults to WP_DEBUG so it is off on production.
 */
if ( ! defined( 'HLC_DEBUG' ) ) {
	define( 'HLC_DEBUG', defined( 'WP_DEBUG' ) && WP_DEBUG );
}

/**
 * Log a heat loss calculator diagnostic message.
 *
 * Writes nothing unless HLC_DEBUG is on, so these calls are safe to leave in
 * place once the current fault is closed out.
 *
 * @param string $message Message to log.
 *
 * @return void
 */
function hlc_log( $message ) {
	if ( ! HLC_DEBUG ) {
		return;
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( '[HLC] ' . $message );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-heat-loss-calculator-activator.php
 */
function activate_plugin_name() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-heat-loss-calculator-activator.php';
	Heat_Loss_Calculator_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-heat-loss-calculator-deactivator.php
 */
function deactivate_plugin_name() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-heat-loss-calculator-deactivator.php';
	Heat_Loss_Calculator_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_plugin_name' );
register_deactivation_hook( __FILE__, 'deactivate_plugin_name' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-heat-loss-calculator.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_plugin_name() {

	$plugin = new Heat_Loss_Calculator();
	$plugin->run();

}
run_plugin_name();

/**
 * Print with pre tags
 *
 * @param array $var Array
 * @param boolean $return Return
 *
 * @return string|void
 */
function print_r_pre($var, $return = false)
{
	if ( $return ) {
		return '<pre>' . print_r($var, true) . '</pre>';
	}

	if ( ! $return ) {
		print('<pre>');
		print_r($var);
		print('</pre>');
	}
}

/**
 * Var dump with pre tags
 *
 * @param mixed $var String
 *
 * @return string Outputs HTML
 */
function var_dump_pre($var)
{
	print('<pre>');
	var_dump($var);
	print('</pre>');
}

/**
 * Array to object
 *
 * Convert an array to an object
 *
 * @param array $array Array
 *
 * @return object
 */
function array_to_object($array)
{
	return (object) $array;
}

/**
 * Object to array
 *
 * Convert an object to an array
 *
 * @param object $object Object
 *
 * @return array
 */
function object_to_array($object)
{
	return (array) $object;
}

/**
 * Is selected
 *
 * @param string $key Key.
 * @param mixed  $val Value.
 *
 * @return string
 */
function is_selected( $key, $val ) {
	if ( strval($key) === strval($val) || null === $val || '' === strval($val) ) {
		return ' selected="selected"';
	}
}
