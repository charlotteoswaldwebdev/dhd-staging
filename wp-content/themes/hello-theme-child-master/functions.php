<?php
/**
 * Theme functions and definitions.
 *
 * For additional information on potential customization options,
 * read the developers' documentation:
 *
 * https://developers.elementor.com/docs/hello-elementor-theme/
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );

/**
 * Load child theme scripts & styles.
 *
 * @return void
 */
function hello_elementor_child_scripts_styles() {

	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		HELLO_ELEMENTOR_CHILD_VERSION
	);

}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );

function ugd_get_allowed_role_keys(): array {
    $keys = [ 'customer' ]; // always allow base customer role
    if ( function_exists( 'ugd_get_group_roles' ) ) {
        $keys = array_merge( $keys, array_keys( ugd_get_group_roles() ) ); 
    }
    return $keys;
}

add_filter( 'woocommerce_registration_errors', function( $errors, $username, $email ) {
	if ( empty( $_POST['first_name'] ) ) {
        $errors->add( 'first_name_error', 'Please enter your first name.' );
    }
    if ( empty( $_POST['last_name'] ) ) {
        $errors->add( 'last_name_error', 'Please enter your last name.' );
    }
    $chosen = isset( $_POST['user_role'] ) ? sanitize_key( $_POST['user_role'] ) : '';
    if ( empty( $chosen ) || ! in_array( $chosen, ugd_get_allowed_role_keys(), true ) ) {
        $errors->add( 'user_role_error', 'Please select a valid account type.' );
    }
    return $errors;
}, 10, 3 );

add_action( 'woocommerce_created_customer', function( $customer_id ) {
	if ( ! empty( $_POST['first_name'] ) ) {
        update_user_meta( $customer_id, 'first_name', sanitize_text_field( $_POST['first_name'] ) );
    }
    if ( ! empty( $_POST['last_name'] ) ) {
        update_user_meta( $customer_id, 'last_name', sanitize_text_field( $_POST['last_name'] ) );
    }
	if ( ! empty( $_POST['billing_phone'] ) ) {
		update_user_meta( $customer_id, 'billing_phone', sanitize_text_field( $_POST['billing_phone'] ) );
	}
    $chosen = isset( $_POST['user_role'] ) ? sanitize_key( $_POST['user_role'] ) : '';
    if ( ! empty( $chosen ) && in_array( $chosen, ugd_get_allowed_role_keys(), true ) ) {
        ( new WP_User( $customer_id ) )->set_role( $chosen );
    }
} );

add_filter( 'woocommerce_account_menu_items', function( $items ) {
    // Insert before 'logout' so it appears at the bottom of the nav
    $logout = $items['customer-logout'];
    unset( $items['customer-logout'] );

    $items['trade-discounts'] = 'My Trade Discount(s)';
    $items['customer-logout'] = $logout;

    return $items;
} );

add_action( 'init', function() {
    add_rewrite_endpoint( 'trade-discounts', EP_ROOT | EP_PAGES );
} );

add_action( 'woocommerce_account_trade-discounts_endpoint', function() {
    echo '<strong>User Role(s):</strong> ' . ugd_debug_user_role() . "<br>";
    echo '<strong>Applicable Discount:</strong> ' . ( ( ugd_get_user_discount() !== null ) ? ( ugd_get_user_discount() * 100 ) . '%' : 'No discount' );
} );

