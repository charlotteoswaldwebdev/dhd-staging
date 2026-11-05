<?php
/**
 * Renders the spec rows inside WooCommerce's "Additional information" tab,
 * below the attributes table.
 *
 * Elementor Pro's Product Data Tabs widget calls woocommerce_output_product_data_tabs(),
 * so the standard tab templates and their hooks apply. The template
 * single-product/tabs/additional-information.php fires:
 *
 *     do_action( 'woocommerce_product_additional_information', $product );
 *
 * with wc_display_product_attributes() attached at priority 10, so priority 20
 * puts our rows underneath it.
 *
 * Caveat worth knowing: WooCommerce only adds that tab at all when
 *
 *     $product->has_attributes() || apply_filters(
 *         'wc_product_enable_dimensions_display',
 *         $product->has_weight() || $product->has_dimensions()
 *     )
 *
 * and has_attributes() requires at least one attribute marked *visible* on the
 * product page. A variable product whose only attribute is hidden and which has
 * no weight or dimensions would therefore have no tab to render into.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_product_additional_information', 'dhd_vspecs_render_in_tab', 20 );

/**
 * The $product passed by the action is the same one dhd_vspecs_render() resolves
 * from the global, and the shortcode's own gate still applies: nothing is output
 * for simple products or where no variation has a spec filled in.
 */
function dhd_vspecs_render_in_tab( $product = null ): void {
	// phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped per value in dhd_vspecs_render().
	echo dhd_vspecs_render( array( 'title' => 'Specifications' ) );
}
