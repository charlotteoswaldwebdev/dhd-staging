<?php
/**
 * Plugin Name: [CW] User Group Discounts
 * Description: Manages custom user group roles and applies role-based WooCommerce discounts. 
 * Author: Charlotte's Web
 * Version: 1.0.0
 * Author URI: https://charlottes-web.dev
 */

require_once __DIR__ . '/user-group-discounts/roles.php';
require_once __DIR__ . '/user-group-discounts/discounts.php';

// Return's a comma-separated list of the current user's role labels
function ugd_debug_user_role() {
    $user = wp_get_current_user();

    if ( ! $user->ID ) return 'Not logged in';

    $all_roles = wp_roles()->roles;

    $labels = array_map( function( $role_key ) use ( $all_roles ) {
        return $all_roles[ $role_key ]['name'] ?? $role_key;
    }, $user->roles );

    return implode( ', ', $labels );
}

// Returns the discount percentage (e.g. 0.20 for 20%) for the current user based on their role, or null if no discount applies
function ugd_get_user_discount() {
    $user = wp_get_current_user();

    if ( ! $user->ID ) return null;

    $discounts = ugd_get_discounts();

    foreach ( $user->roles as $role ) {
        if ( isset( $discounts[ $role ] ) ) {
            return $discounts[ $role ]; // returns e.g. 0.20
        }
    }

    return null; // no discount role found
}