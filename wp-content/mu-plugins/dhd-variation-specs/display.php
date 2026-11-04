<?php
/**
 * [dhd_variation_specs] — a spec table that follows the chosen variation.
 *
 * Elementor's ACF dynamic tags resolve once, server-side, against the post
 * being viewed (the parent product), so they cannot follow a variation the
 * shopper picks in the browser. This renders the rows empty and lets JS fill
 * them from WooCommerce's own `found_variation` event instead.
 *
 * Drop it into the single-product Elementor template with a Shortcode widget.
 * It can sit anywhere on the page, inside the add-to-cart form or not.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'dhd_variation_specs', 'dhd_vspecs_render' );

/**
 * The product in context, falling back to the queried post for Elementor
 * templates, where $product is not always primed.
 */
function dhd_vspecs_get_product(): ?WC_Product {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return null;
	}

	global $product;
	if ( $product instanceof WC_Product ) {
		return $product;
	}

	$id = get_the_ID();
	if ( ! $id ) {
		return null;
	}

	$resolved = wc_get_product( $id );

	return $resolved instanceof WC_Product ? $resolved : null;
}

/**
 * Whether any variation of this product has any of the spec fields filled in.
 *
 * Reads post meta directly rather than going through get_field(), because this
 * only has to answer "is there anything worth rendering" and runs once per
 * page load.
 */
function dhd_vspecs_has_values( WC_Product $product ): bool {
	return array() !== dhd_vspecs_first_values( $product );
}

/**
 * The spec values of the first variation that has any, keyed by field name.
 * Used for the gate above and to pre-fill the Elementor editor preview.
 *
 * @return array<string, string>
 */
function dhd_vspecs_first_values( WC_Product $product ): array {
	$keys = array_keys( dhd_vspecs_fields() );

	foreach ( $product->get_children() as $variation_id ) {
		$found = array();

		foreach ( $keys as $key ) {
			$value = get_post_meta( $variation_id, $key, true );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$found[ $key ] = trim( (string) $value );
			}
		}

		if ( array() !== $found ) {
			return $found;
		}
	}

	return array();
}

/**
 * True inside Elementor's editor preview, where no variation is ever chosen.
 */
function dhd_vspecs_is_editor_preview(): bool {
	return class_exists( '\Elementor\Plugin' )
		&& isset( \Elementor\Plugin::$instance->preview )
		&& \Elementor\Plugin::$instance->preview->is_preview_mode();
}

/**
 * Render the block, or an empty string when it does not apply.
 *
 * @param array<string, string>|string $atts
 */
function dhd_vspecs_render( $atts = array() ): string {
	$atts = shortcode_atts(
		array( 'title' => 'Specifications' ),
		is_array( $atts ) ? $atts : array(),
		'dhd_variation_specs'
	);

	$product = dhd_vspecs_get_product();

	// The "dynamic visibility" gate: variable products that actually have specs.
	if ( ! $product || ! $product->is_type( 'variable' ) || ! dhd_vspecs_has_values( $product ) ) {
		return '';
	}

	// In the editor nothing can be selected, so show sample values to style against.
	$preview = dhd_vspecs_is_editor_preview();
	$values  = $preview ? dhd_vspecs_first_values( $product ) : array();

	dhd_vspecs_enqueue_assets();

	$html = sprintf(
		'<div class="dhd-vspecs"%s>',
		$preview ? '' : ' hidden'
	);

	if ( '' !== trim( (string) $atts['title'] ) ) {
		$html .= '<h3 class="dhd-vspecs__title">' . esc_html( $atts['title'] ) . '</h3>';
	}

	$html .= '<dl class="dhd-vspecs__list">';

	foreach ( dhd_vspecs_fields() as $key => $field ) {
		$value = $values[ $key ] ?? '';
		$unit  = (string) ( $field['unit'] ?? '' );
		$shown = '' !== $value ? trim( $value . ' ' . $unit ) : '';

		$html .= sprintf(
			'<div class="dhd-vspecs__row" data-spec="%1$s" data-unit="%2$s"%3$s>'
				. '<dt class="dhd-vspecs__label">%4$s</dt>'
				. '<dd class="dhd-vspecs__value">%5$s</dd>'
				. '</div>',
			esc_attr( $key ),
			esc_attr( $unit ),
			'' !== $shown ? '' : ' hidden',
			esc_html( (string) $field['label'] ),
			esc_html( $shown )
		);
	}

	$html .= '</dl></div>';

	return $html;
}

/**
 * Inline CSS/JS, registered against src-less handles so they only load on
 * pages that actually render the shortcode. Same pattern as the hot water
 * finder. Colours fall back to the Elementor kit globals.
 */
function dhd_vspecs_enqueue_assets(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	wp_register_style( 'dhd-vspecs', false, array(), '1.0.0' );
	wp_enqueue_style( 'dhd-vspecs' );
	wp_add_inline_style(
		'dhd-vspecs',
		'
.dhd-vspecs[hidden],.dhd-vspecs [hidden]{display:none!important}
.dhd-vspecs{margin:1.5rem 0}
.dhd-vspecs__title{margin:0 0 .5rem;font-size:1.05em;color:var(--e-global-color-primary,inherit)}
.dhd-vspecs__list{margin:0;padding:0}
.dhd-vspecs__row{display:flex;justify-content:space-between;align-items:baseline;gap:1rem;margin:0;padding:.55rem 0;border-bottom:1px solid rgba(0,0,0,.1)}
.dhd-vspecs__row:last-child{border-bottom:0}
.dhd-vspecs__label{margin:0;font-weight:600}
.dhd-vspecs__value{margin:0;text-align:right}
'
	);

	wp_register_script( 'dhd-vspecs', false, array( 'jquery' ), '1.0.0', true );
	wp_enqueue_script( 'dhd-vspecs' );
	wp_add_inline_script(
		'dhd-vspecs',
		'
jQuery( function ( $ ) {
	var $blocks = $( ".dhd-vspecs" );
	if ( ! $blocks.length ) {
		return;
	}

	// found_variation is triggered on form.variations_form and bubbles, so one
	// delegated handler covers a block sitting anywhere on the page.
	$( document.body ).on( "found_variation", "form.variations_form", function ( event, variation ) {
		$blocks.each( function () {
			var $block = $( this ), any = false;

			$block.find( ".dhd-vspecs__row" ).each( function () {
				var $row  = $( this ),
				    key   = $row.attr( "data-spec" ),
				    unit  = $row.attr( "data-unit" ) || "",
				    raw   = variation && variation[ key ] != null ? String( variation[ key ] ).trim() : "",
				    shown = raw ? ( unit ? raw + " " + unit : raw ) : "";

				$row.find( ".dhd-vspecs__value" ).text( shown );
				$row.prop( "hidden", "" === shown );

				if ( shown ) {
					any = true;
				}
			} );

			$block.prop( "hidden", ! any );
		} );
	} );

	// Selection cleared, or an unavailable combination chosen.
	$( document.body ).on( "reset_data hide_variation", "form.variations_form", function () {
		$blocks.prop( "hidden", true );
	} );
} );
'
	);
}
