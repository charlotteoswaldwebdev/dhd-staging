<?php
/**
 * Theme functions and definitions.
 *
 * For additional information on potential customization options,
 * read the developers' documentation:
 *
 * @package HelloBizChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_BIZ_CHILD_VERSION', '1.0.0' );

/**
 * Load child theme scripts & styles.
 *
 * @return void
 */
function hello_biz_child_scripts_styles() {

	wp_enqueue_style(
		'hello-biz-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'theme',
		],
		HELLO_BIZ_CHILD_VERSION
	);
}

add_action( 'wp_enqueue_scripts', 'hello_biz_child_scripts_styles', 20 );

// Add to child theme's functions.php or a custom MU plugin
add_action( 'woocommerce_after_shop_loop_item_title', function() {
    global $product;
    if ( $product ) {
        echo '<p class="product-sku">' . esc_html( $product->get_sku() ) . '</p>';
    }
}, 8 ); // Run before price (priority 10)

// The variation spec fields moved to mu-plugins/dhd-variation-specs/.
