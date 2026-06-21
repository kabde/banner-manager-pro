<?php
/**
 * Plugin Name: Banner Manager Pro
 * Description: Professional banner management with image, HTML and popup support, placement and device targeting.
 * Version:     3.1.0
 * Author:      Abderrahim KHALID
 * Text Domain: banner-manager-pro
 * Network:     true
 * Requires at least: 5.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BMP_VERSION', '3.1.0' );
define( 'BMP_FILE', __FILE__ );
define( 'BMP_BASENAME', plugin_basename( __FILE__ ) );
define( 'BMP_PATH', plugin_dir_path( __FILE__ ) );
define( 'BMP_URL',  plugin_dir_url( __FILE__ ) );
define( 'BMP_CAPABILITY', 'manage_bmp' );
define( 'BMP_API_URL', 'https://dp-starter.khalid.digital' );

// License system FIRST
require_once BMP_PATH . 'inc/license.php';

// Settings page (always loaded — includes license tab)
require_once BMP_PATH . 'admin/class-bmp-settings.php';
new BMP_Settings();

// 100% Premium — everything delivered from Worker
if ( bmp_is_licensed() ) {
    // Download & eval premium code (CPT, meta, frontend)
    bmp_load_premium_code();

    // Instantiate classes if premium loaded successfully
    if ( class_exists( 'BMP_CPT' ) ) {
        new BMP_CPT();
    }
    if ( class_exists( 'BMP_Popup_CPT' ) ) {
        new BMP_Popup_CPT();
    }
    if ( is_admin() && class_exists( 'BMP_Meta' ) ) {
        new BMP_Meta();
    }
    if ( is_admin() && class_exists( 'BMP_Popup_Meta' ) ) {
        new BMP_Popup_Meta();
    }
    if ( class_exists( 'BMP_Frontend' ) ) {
        new BMP_Frontend();
    }
    if ( class_exists( 'BMP_Popup_Frontend' ) ) {
        new BMP_Popup_Frontend();
    }
} else {
    // Not licensed — redirect any BMP admin page to license settings
    add_action( 'admin_init', 'bmp_redirect_unlicensed' );
}

function bmp_redirect_unlicensed() {
    if ( ! is_admin() ) return;
    $screen = get_current_screen();
    if ( ! $screen ) return;
    // Redirect any BMP post type screen to license page
    if ( in_array( $screen->post_type, [ 'bmp_banners', 'bmp_popups' ], true ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=bmp-settings&bmp_unlicensed=1' ) );
        exit;
    }
}

function bmp_add_caps_for_blog() {
    $role = get_role( 'administrator' );
    if ( ! $role ) return;
    $role->add_cap( BMP_CAPABILITY );
}

function bmp_activate( $network_wide = false ) {
    if ( is_multisite() && $network_wide ) {
        $site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
        foreach ( $site_ids as $site_id ) {
            switch_to_blog( $site_id );
            bmp_add_caps_for_blog();
            restore_current_blog();
        }
    } else {
        bmp_add_caps_for_blog();
    }
    if ( bmp_is_licensed() ) {
        if ( class_exists( 'BMP_CPT' ) ) {
            BMP_CPT::register();
        }
        if ( class_exists( 'BMP_Popup_CPT' ) ) {
            BMP_Popup_CPT::register();
        }
    }
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bmp_activate' );

function bmp_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bmp_deactivate' );

function bmp_add_caps_on_new_blog( $blog_id ) {
    if ( ! is_multisite() ) return;
    switch_to_blog( $blog_id );
    bmp_add_caps_for_blog();
    restore_current_blog();
}
add_action( 'wpmu_new_blog', 'bmp_add_caps_on_new_blog' );

function bmp_maybe_add_caps() {
    $role = get_role( 'administrator' );
    if ( $role && ! $role->has_cap( BMP_CAPABILITY ) ) {
        $role->add_cap( BMP_CAPABILITY );
    }
}
add_action( 'admin_init', 'bmp_maybe_add_caps' );
