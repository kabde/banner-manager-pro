<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Check if plugin is licensed
 */
function bmp_is_licensed() {
    // Check cached result first
    static $result = null;
    if ( $result !== null ) return $result;

    $status = get_option( 'bmp_license_status', '' );
    if ( $status === 'valid' ) {
        // Check transient for periodic revalidation
        if ( false === get_transient( 'bmp_license_valid' ) ) {
            // Schedule revalidation but don't block
            if ( ! wp_next_scheduled( 'bmp_validate_license_cron' ) ) {
                wp_schedule_single_event( time() + 10, 'bmp_validate_license_cron' );
            }
        }
        $result = true;
        return true;
    }
    $result = false;
    return false;
}

/**
 * Activate license
 */
function bmp_activate_license( $key ) {
    $key = strtoupper( sanitize_text_field( trim( $key ) ) );
    if ( ! preg_match( '/^BMP-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key ) ) {
        return [ 'success' => false, 'message' => 'Format de licence invalide.' ];
    }

    $response = wp_remote_post( BMP_API_URL . '/activate', [
        'timeout' => 15,
        'body'    => json_encode([
            'license_key' => $key,
            'domain'      => home_url(),
            'product'     => 'banner-manager-pro',
        ]),
        'headers' => [ 'Content-Type' => 'application/json' ],
    ]);

    if ( is_wp_error( $response ) ) {
        return [ 'success' => false, 'message' => 'Erreur de connexion: ' . $response->get_error_message() ];
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! empty( $body['success'] ) ) {
        update_option( 'bmp_license_key', $key );
        update_option( 'bmp_license_status', 'valid' );
        update_option( 'bmp_license_domain', home_url() );
        if ( isset( $body['expires_at'] ) ) {
            update_option( 'bmp_license_expires_at', sanitize_text_field( $body['expires_at'] ) );
        }
        set_transient( 'bmp_license_valid', 1, 72 * HOUR_IN_SECONDS );
        return [ 'success' => true, 'message' => $body['message'] ?? 'Licence activée.' ];
    }

    return [ 'success' => false, 'message' => $body['message'] ?? 'Activation échouée.' ];
}

/**
 * Deactivate license
 */
function bmp_deactivate_license() {
    $key = get_option( 'bmp_license_key', '' );
    if ( empty( $key ) ) return;

    wp_remote_post( BMP_API_URL . '/deactivate', [
        'timeout' => 15,
        'body'    => json_encode([
            'license_key' => $key,
            'domain'      => home_url(),
            'product'     => 'banner-manager-pro',
        ]),
        'headers' => [ 'Content-Type' => 'application/json' ],
    ]);

    delete_option( 'bmp_license_key' );
    delete_option( 'bmp_license_status' );
    delete_option( 'bmp_license_domain' );
    delete_option( 'bmp_license_expires_at' );
    delete_transient( 'bmp_license_valid' );
}

/**
 * Validate license (called by cron)
 */
function bmp_validate_license() {
    $key = get_option( 'bmp_license_key', '' );
    if ( empty( $key ) ) return;

    $response = wp_remote_post( BMP_API_URL . '/validate', [
        'timeout' => 15,
        'body'    => json_encode([
            'license_key' => $key,
            'domain'      => home_url(),
            'product'     => 'banner-manager-pro',
        ]),
        'headers' => [ 'Content-Type' => 'application/json' ],
    ]);

    if ( is_wp_error( $response ) ) return;

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! empty( $body['valid'] ) ) {
        update_option( 'bmp_license_status', 'valid' );
        if ( isset( $body['expires_at'] ) ) {
            update_option( 'bmp_license_expires_at', sanitize_text_field( $body['expires_at'] ) );
        }
        set_transient( 'bmp_license_valid', 1, 72 * HOUR_IN_SECONDS );
    } else {
        update_option( 'bmp_license_status', 'invalid' );
        delete_transient( 'bmp_license_valid' );
    }
}
add_action( 'bmp_validate_license_cron', 'bmp_validate_license' );

// Schedule cron
function bmp_schedule_validation() {
    if ( ! wp_next_scheduled( 'bmp_validate_license_cron' ) && bmp_is_licensed() ) {
        wp_schedule_event( time(), 'twicedaily', 'bmp_validate_license_cron' );
    }
}
add_action( 'init', 'bmp_schedule_validation' );

// Cleanup cron on deactivation
register_deactivation_hook( BMP_FILE, function() {
    wp_clear_scheduled_hook( 'bmp_validate_license_cron' );
});

/**
 * Auto-update via Worker
 */
function bmp_check_plugin_update( $transient ) {
    if ( empty( $transient ) || ! is_object( $transient ) ) return $transient;

    $response = wp_remote_get( BMP_API_URL . '/update-check?product=banner-manager-pro', [
        'timeout' => 10,
    ]);

    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        return $transient;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $data['version'] ) || ! version_compare( BMP_VERSION, $data['version'], '<' ) ) {
        return $transient;
    }

    $transient->response[ BMP_BASENAME ] = (object) [
        'slug'         => 'banner-manager-pro',
        'plugin'       => BMP_BASENAME,
        'new_version'  => $data['version'],
        'url'          => $data['url'] ?? '',
        'package'      => $data['download_url'] ?? '',
        'tested'       => '7.0',
        'requires'     => '5.0',
        'requires_php' => '7.4',
    ];

    return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'bmp_check_plugin_update' );

/* Admin menu for license removed — now handled by BMP_Settings class. */

/**
 * Admin notice when not licensed
 */
function bmp_admin_notice_no_license() {
    if ( bmp_is_licensed() ) return;
    $screen = get_current_screen();
    if ( $screen && $screen->id === 'toplevel_page_bmp-settings' ) return;

    echo '<div class="notice notice-warning"><p>';
    echo '<strong>Banner Manager Pro</strong> — ';
    echo 'Veuillez <a href="' . esc_url( admin_url( 'admin.php?page=bmp-settings' ) ) . '">activer votre licence</a> pour utiliser le plugin.';
    echo '</p></div>';
}
add_action( 'admin_notices', 'bmp_admin_notice_no_license' );

function bmp_admin_notice_expiring() {
    if ( ! bmp_is_licensed() ) return;
    $expires = get_option( 'bmp_license_expires_at', '' );
    if ( ! $expires ) return;
    $days = (int) ceil( ( strtotime( $expires ) - time() ) / 86400 );
    if ( $days > 14 ) return;

    if ( $days <= 0 ) {
        echo '<div class="notice notice-error"><p><strong>Banner Manager Pro</strong> — Votre licence a expiré. <a href="' . esc_url( admin_url( 'admin.php?page=bmp-settings' ) ) . '">Renouveler</a></p></div>';
    } else {
        echo '<div class="notice notice-warning"><p><strong>Banner Manager Pro</strong> — Votre licence expire dans ' . $days . ' jour' . ($days > 1 ? 's' : '') . '. <a href="' . esc_url( admin_url( 'admin.php?page=bmp-settings' ) ) . '">Voir</a></p></div>';
    }
}
add_action( 'admin_notices', 'bmp_admin_notice_expiring' );

/**
 * AJAX handlers
 */
function bmp_ajax_activate_license() {
    check_ajax_referer( 'bmp_license_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission refusée.' );

    $key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
    $result = bmp_activate_license( $key );

    if ( $result['success'] ) {
        wp_send_json_success( $result['message'] );
    } else {
        wp_send_json_error( $result['message'] );
    }
}
add_action( 'wp_ajax_bmp_activate_license', 'bmp_ajax_activate_license' );

function bmp_ajax_deactivate_license() {
    check_ajax_referer( 'bmp_license_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission refusée.' );

    bmp_deactivate_license();
    wp_send_json_success( 'Licence désactivée.' );
}
add_action( 'wp_ajax_bmp_deactivate_license', 'bmp_ajax_deactivate_license' );

/* Render license page removed — now handled by BMP_Settings class. */
