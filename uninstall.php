<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

function bmp_uninstall_blog() {
    // Delete banners
    $posts = get_posts([
        'post_type'   => 'bmp_banners',
        'numberposts' => -1,
        'post_status' => 'any',
    ]);
    foreach ( $posts as $post ) {
        wp_delete_post( $post->ID, true );
    }

    // Delete popups
    $popups = get_posts([
        'post_type'   => 'bmp_popups',
        'numberposts' => -1,
        'post_status' => 'any',
    ]);
    foreach ( $popups as $popup ) {
        wp_delete_post( $popup->ID, true );
    }

    delete_option( 'bmp_settings' );
    delete_option( 'bmp_popup_options' );
    delete_option( 'bmp_license_key' );
    delete_option( 'bmp_license_status' );
    delete_option( 'bmp_license_domain' );
    delete_transient( 'bmp_license_valid' );

    $role = get_role( 'administrator' );
    if ( $role ) {
        $role->remove_cap( 'manage_bmp' );
    }
}

if ( is_multisite() ) {
    $site_ids = get_sites([ 'fields' => 'ids', 'number' => 0 ]);
    foreach ( $site_ids as $site_id ) {
        switch_to_blog( $site_id );
        bmp_uninstall_blog();
        restore_current_blog();
    }
} else {
    bmp_uninstall_blog();
}
