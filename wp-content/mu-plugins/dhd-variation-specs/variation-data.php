<?php
/**
 * Exposes the variation ACF values to the front end.
 *
 * WooCommerce serialises every available variation into the
 * data-product_variations JSON on form.variations_form (and returns the same
 * shape from its AJAX handler when a product has more variations than
 * woocommerce_ajax_variation_threshold). Anything added here is therefore
 * readable in JS from the variation object passed to `found_variation`.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'woocommerce_available_variation',
	function ( $data, $product, $variation ) {
		if ( ! function_exists( 'get_field' ) ) {
			return $data;
		}

		foreach ( array_keys( dhd_vspecs_fields() ) as $key ) {
			$value = get_field( $key, $variation->get_id() );

			// get_field() returns null/false when a field cannot be resolved and
			// '' when it is simply empty; all three mean "nothing to show".
			$data[ $key ] = is_scalar( $value ) ? trim( (string) $value ) : '';
		}

		return $data;
	},
	10,
	3
);
