<?php

/**
 * Define your groups here.
 * 'role_key' => [ 'display_name', capabilities ]
 * 
 * We clone capabilities from 'customer' so the role
 * behaves correctly in WooCommerce.
 */
function ugd_get_group_roles(): array {
    return [
        'b2b_trade_customer'            => 'B2B Trade Customer',
        'b2b_trade_plus_customer'       => 'B2B Trade Plus Customer',
        'b2b_ahc'                       => 'B2B AHC (Accredited Heating Contractor)',
    ];
}

/**
 * Register roles on init. add_role() silently does nothing
 * if the role already exists, so this is safe to run every request.
 */
add_action( 'init', function() {
    $customer_role = get_role( 'customer' );
    $caps = $customer_role ? $customer_role->capabilities : [ 'read' => true ];

    foreach ( ugd_get_group_roles() as $key => $label ) {
        add_role( $key, $label, $caps );
    }
});