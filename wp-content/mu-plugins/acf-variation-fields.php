<?php
/**
 * Plugin Name: ACF Fields on WooCommerce Variations
 * Description: Renders ACF field groups whose location is "Post Type == Product Variation" inside each variation panel, and saves the values to the variation.
 * Version:     1.0.0
 * Requires Plugins: woocommerce, advanced-custom-fields-pro
 *
 * Drop into wp-content/mu-plugins/ (or wrap as a normal plugin).
 * Works with ACF free or Pro. If you use ACF free, change "Requires Plugins"
 * to advanced-custom-fields.
 */

defined( 'ABSPATH' ) || exit;

/**
 * 1. Make "Product Variation" selectable in the field group's
 *    Location rules: Post Type → is equal to → Product Variation.
 *    (product_variation isn't a public post type, so ACF hides it by default.)
 */
add_filter( 'acf/location/rule_values/post_type', function ( $choices ) {
	$choices['product_variation'] = __( 'Product Variation', 'woocommerce' );
	return $choices;
} );

/**
 * 2. Load ACF's JS/CSS on the product edit screen, even when no "normal"
 *    product field group is present to trigger it.
 */
add_action( 'current_screen', function ( $screen ) {
	if ( function_exists( 'acf_enqueue_scripts' ) && 'product' === $screen->id ) {
		acf_enqueue_scripts();
	}
} );

/**
 * 3. Render matching field groups inside each variation panel.
 *
 *    Field names are rewritten from acf[field_x] to acf_variation[$loop][field_x]
 *    so that (a) each variation's values stay separate and (b) ACF's own
 *    acf/save_post on the parent product doesn't pick them up.
 */
add_action( 'woocommerce_product_after_variable_attributes', function ( $loop, $variation_data, $variation ) {
	if ( ! function_exists( 'acf_get_field_groups' ) ) {
		return;
	}

	// Let ACF's own location engine decide which groups apply.
	$groups = acf_get_field_groups( array(
		'post_type' => 'product_variation',
		'post_id'   => $variation->ID,
	) );

	if ( empty( $groups ) ) {
		return;
	}

	$rename = static function ( $field ) use ( $loop ) {
		// Only top-level fields start with "acf["; sub fields (repeater rows etc.)
		// inherit the already-rewritten parent name as their prefix.
		if ( 0 === strpos( $field['name'], 'acf[' ) ) {
			$field['name'] = 'acf_variation[' . (int) $loop . ']' . substr( $field['name'], 3 );
			$field['id']   = acf_idify( $field['name'] ); // unique IDs per variation
		}
		return $field;
	};

	add_filter( 'acf/prepare_field', $rename, 999 );

	foreach ( $groups as $group ) {
		echo '<div class="acf-variation-group" style="clear:both;padding-top:8px;">';
		echo '<p class="form-row form-row-full"><strong>' . esc_html( $group['title'] ) . '</strong></p>';
		echo '<div class="acf-fields -top">';
		acf_render_fields( acf_get_fields( $group ), $variation->ID, 'div', $group['instruction_placement'] );
		echo '</div></div>';
	}

	remove_filter( 'acf/prepare_field', $rename, 999 );
}, 10, 3 );

/**
 * 4. Save. WooCommerce has already verified its own "save-variations" nonce
 *    and the user's capability before this hook fires.
 *
 *    acf_update_values() runs every value through ACF's normal
 *    update_value pipeline, so repeaters, groups, relationships etc. save
 *    exactly as they would on a regular post.
 */
add_action( 'woocommerce_save_product_variation', function ( $variation_id, $i ) {
	if ( ! function_exists( 'acf_update_values' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce.
	if ( empty( $_POST['acf_variation'][ $i ] ) || ! is_array( $_POST['acf_variation'][ $i ] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field by ACF.
	$values = wp_unslash( $_POST['acf_variation'][ $i ] );

	acf_update_values( $values, $variation_id );
}, 10, 2 );

/**
 * 5. Variations are loaded over AJAX, after ACF has already initialised the
 *    page. Tell ACF about the new markup so select2, date pickers, image
 *    fields, conditional logic etc. come alive.
 */
add_action( 'admin_footer', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'product' !== $screen->id ) {
		return;
	}
	?>
	<script>
	jQuery( function ( $ ) {
		function initAcfInVariations() {
			if ( typeof acf === 'undefined' ) {
				return;
			}
			$( '#variable_product_options .woocommerce_variation' ).not( '.acf-variation-ready' ).each( function () {
				$( this ).addClass( 'acf-variation-ready' );
				acf.doAction( 'append', $( this ) );
			} );
		}

		$( '#woocommerce-product-data' ).on(
			'woocommerce_variations_loaded woocommerce_variations_added',
			initAcfInVariations
		);
	} );
	</script>
	<?php
} );
