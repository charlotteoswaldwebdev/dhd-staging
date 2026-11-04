<?php
/**
 * Plugin Name: DHD – UK Shipping Restrictions
 * Description: Restricts shipping to mainland UK only. Blocks Northern Ireland, Isle of Man, the Channel Islands, the Isles of Scilly, the Scottish Highlands and the Scottish Islands by outward postcode, at rate calculation and at checkout.
 * Author:      Charlotte's Web
 * Author URI:  https://charlottes-web.dev
 * Version:     1.0.0
 *
 * @package DHD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for the excluded postcode areas.
 *
 * 'areas'     – whole postcode areas that are never served.
 * 'districts' – postcode areas served only in part; each entry is a list of
 *               inclusive [from, to] district-number ranges that are NOT served.
 *
 * @return array{areas: string[], districts: array<string, array<int, array{0:int,1:int}>>}
 */
function dhd_restricted_postcode_rules() {
	return array(
		'areas'     => array(
			'BT', // Northern Ireland.
			'IM', // Isle of Man.
			'JE', // Jersey.
			'GY', // Guernsey.
			'HS', // Outer Hebrides (HS1–HS9).
			'IV', // Inverness / Highlands (IV1–IV63).
			'KW', // Kirkwall / Orkney & Caithness (KW1–KW17).
			'ZE', // Shetland (ZE1–ZE3).
		),
		'districts' => array(
			'TR' => array( array( 21, 25 ) ),                 // Isles of Scilly.
			'KA' => array( array( 27, 28 ) ),                 // Arran, Cumbrae.
			'AB' => array( array( 31, 38 ), array( 41, 56 ) ), // Aberdeenshire.
			'FK' => array( array( 17, 21 ) ),                 // Stirlingshire / Trossachs.
			'PA' => array( array( 20, 78 ) ),                 // Argyll & islands.
			'PH' => array( array( 19, 50 ) ),                 // Highland Perthshire & west.
		),
	);
}

/**
 * Split a UK postcode into its area letters and district number.
 *
 * Accepts full postcodes ("AB31 4AB"), outward codes ("AB31") and unspaced
 * input ("ab314ab"). Returns false for anything that is not UK-shaped.
 *
 * @param string $postcode Raw postcode as entered.
 * @return array{area: string, district: int}|false
 */
function dhd_parse_uk_postcode( $postcode ) {
	$postcode = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $postcode ) );

	if ( '' === $postcode ) {
		return false;
	}

	/*
	 * Strip the inward code (digit + two letters) if present, so that an
	 * unspaced "TR25AA" is read as district TR2 rather than TR25.
	 */
	if ( preg_match( '/^(.+?)[0-9][A-Z]{2}$/', $postcode, $outward ) ) {
		$postcode = $outward[1];
	}

	if ( ! preg_match( '/^([A-Z]{1,2})([0-9]{1,2})[A-Z]?$/', $postcode, $matches ) ) {
		return false;
	}

	return array(
		'area'     => $matches[1],
		'district' => (int) $matches[2],
	);
}

/**
 * Whether a destination falls outside the area the store ships to.
 *
 * Anything outside GB is restricted (the store is GB-only); inside GB the
 * postcode rules above decide. An empty postcode is treated as allowed so the
 * customer is not blocked before they have entered one.
 *
 * @param string $postcode Destination postcode.
 * @param string $country  Destination country code.
 * @return bool
 */
function dhd_is_restricted_destination( $postcode, $country ) {
	$country = strtoupper( (string) $country );

	if ( '' !== $country && 'GB' !== $country ) {
		return true;
	}

	$parsed = dhd_parse_uk_postcode( $postcode );

	if ( false === $parsed ) {
		return false;
	}

	$rules = dhd_restricted_postcode_rules();

	if ( in_array( $parsed['area'], $rules['areas'], true ) ) {
		return true;
	}

	if ( ! isset( $rules['districts'][ $parsed['area'] ] ) ) {
		return false;
	}

	foreach ( $rules['districts'][ $parsed['area'] ] as $range ) {
		if ( $parsed['district'] >= $range[0] && $parsed['district'] <= $range[1] ) {
			return true;
		}
	}

	return false;
}

/**
 * Customer-facing message used everywhere a restriction is reported.
 *
 * @return string
 */
function dhd_restricted_destination_message() {
	return __(
		'Sorry, we can only price and deliver to mainland UK addresses on the website. Please contact us for a shipping quote if you live elsewhere in UK',
		'dhd'
	);
}

/**
 * Remove every shipping rate for a restricted destination.
 *
 * @param array $rates   Available rates.
 * @param array $package Shipping package.
 * @return array
 */
function dhd_filter_package_rates( $rates, $package ) {
	$destination = isset( $package['destination'] ) ? $package['destination'] : array();
	$postcode    = isset( $destination['postcode'] ) ? $destination['postcode'] : '';
	$country     = isset( $destination['country'] ) ? $destination['country'] : '';

	if ( dhd_is_restricted_destination( $postcode, $country ) ) {
		return array();
	}

	return $rates;
}
add_filter( 'woocommerce_package_rates', 'dhd_filter_package_rates', 10, 2 );

/**
 * Whether the current cart needs shipping at all.
 *
 * @return bool
 */
function dhd_cart_needs_shipping() {
	return function_exists( 'WC' ) && WC()->cart && WC()->cart->needs_shipping();
}

/**
 * Show the restriction notice on the cart page.
 */
function dhd_check_cart_destination() {
	if ( ! dhd_cart_needs_shipping() ) {
		return;
	}

	$customer = WC()->customer;

	if ( ! $customer ) {
		return;
	}

	if ( dhd_is_restricted_destination( $customer->get_shipping_postcode(), $customer->get_shipping_country() ) ) {
		wc_add_notice( dhd_restricted_destination_message(), 'error' );
	}
}
add_action( 'woocommerce_check_cart_items', 'dhd_check_cart_destination' );

/**
 * Hard block at checkout (shortcode / classic checkout).
 *
 * @param array    $data   Posted checkout data.
 * @param WP_Error $errors Error collector.
 */
function dhd_validate_checkout_destination( $data, $errors ) {
	if ( ! dhd_cart_needs_shipping() ) {
		return;
	}

	$ship_elsewhere = ! empty( $data['ship_to_different_address'] );
	$postcode       = $ship_elsewhere ? ( $data['shipping_postcode'] ?? '' ) : ( $data['billing_postcode'] ?? '' );
	$country        = $ship_elsewhere ? ( $data['shipping_country'] ?? '' ) : ( $data['billing_country'] ?? '' );

	if ( dhd_is_restricted_destination( $postcode, $country ) ) {
		$errors->add( 'dhd_restricted_destination', dhd_restricted_destination_message() );
	}
}
add_action( 'woocommerce_after_checkout_validation', 'dhd_validate_checkout_destination', 10, 2 );

/**
 * Hard block in the block-based cart and checkout (Store API).
 *
 * @param WP_Error $errors Error collector.
 */
function dhd_store_api_cart_errors( $errors ) {
	if ( ! dhd_cart_needs_shipping() || ! WC()->customer ) {
		return;
	}

	if ( dhd_is_restricted_destination( WC()->customer->get_shipping_postcode(), WC()->customer->get_shipping_country() ) ) {
		$errors->add( 'dhd_restricted_destination', dhd_restricted_destination_message() );
	}
}
add_action( 'woocommerce_store_api_cart_errors', 'dhd_store_api_cart_errors' );
