<?php
/**
 * Plugin Name: [CW] Variation Specs
 * Description: Shows per-variation ACF specs (voltage, amps, flow rate) on the single product page, swapping as the shopper changes variation.
 * Author: Charlotte's Web
 * Version: 1.0.0
 * Author URI: https://charlottes-web.dev
 *
 * The ACF fields themselves are registered and saved by acf-variation-fields.php.
 * This plugin is the read side: it exposes the values to the front end and
 * renders them.
 *
 * WordPress only auto-loads *.php sitting directly in mu-plugins/, never files
 * in subdirectories, so the folder's contents are required from here.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/dhd-variation-specs/variation-data.php';
require_once __DIR__ . '/dhd-variation-specs/display.php';

/**
 * The specs shown, in display order.
 *
 * Keys are the ACF field names on the variation. `unit` is appended after the
 * value, so leave it empty where the ACF value already carries its own unit.
 *
 * @return array<string, array{label: string, unit: string}>
 */
function dhd_vspecs_fields(): array {
	return array(
		'voltage'   => array( 'label' => 'Voltage',   'unit' => '' ),
		'amps'      => array( 'label' => 'Current',   'unit' => '' ),
		'flow_rate' => array( 'label' => 'Flow rate', 'unit' => '' ),
	);
}
