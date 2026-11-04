<?php

/**
 * Define your discount percentages here.
 * role_key => percentage as decimal (0.15 = 15%)
 */
function ugd_get_discounts(): array {
    return [
        'b2b_trade_customer'         => 0.03615,
        'b2b_trade_plus_customer'    => 0.03615,
        'b2b_ahc'                    => 0.10843,
    ];
}

add_action( 'woocommerce_cart_calculate_fees', function( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    if ( ! is_user_logged_in() ) return;

    $user      = wp_get_current_user();
    $discounts = ugd_get_discounts();

    foreach ( $discounts as $role => $percentage ) {
        if ( in_array( $role, (array) $user->roles ) ) {
            $discount_amount = $cart->get_subtotal() * $percentage * -1;
            $label = get_role( $role )->name . ' Discount';
            $cart->add_fee( $label, $discount_amount, false ); // false = not taxable
            break; // first matching role wins — remove if you want stacking
        }
    }
});