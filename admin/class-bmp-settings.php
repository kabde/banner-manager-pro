<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BMP_Settings {

    const OPTION_KEY = 'bmp_settings';

    /** @var string Settings page hook suffix */
    private $hook = '';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /* ─── Menu ─────────────────────────────────────────────── */

    public function add_menu() {
        // If licensed AND CPT is registered, add as submenu under Bannières
        if ( bmp_is_licensed() && post_type_exists( 'bmp_banners' ) ) {
            $this->hook = add_submenu_page(
                'edit.php?post_type=bmp_banners',
                'Settings',
                'Settings',
                'manage_options',
                'bmp-settings',
                [ $this, 'render' ]
            );
        } else {
            // Not licensed OR premium not loaded yet — top-level menu
            $this->hook = add_menu_page(
                'Banner Manager Pro',
                'Banner Manager Pro',
                BMP_CAPABILITY,
                'bmp-settings',
                [ $this, 'render' ],
                'dashicons-format-image',
                20
            );
        }
    }

    /* ─── Register ─────────────────────────────────────────── */

    public function register_settings() {
        register_setting( 'bmp_settings_group', self::OPTION_KEY, [
            'sanitize_callback' => [ $this, 'sanitize' ],
        ] );

        add_filter( 'allowed_options', function ( $allowed ) {
            $allowed['bmp_settings_group'] = [ 'bmp_settings' ];
            return $allowed;
        } );
    }

    /* ─── Sanitize ─────────────────────────────────────────── */

    public function sanitize( $input ) {
        $input = is_array( $input ) ? $input : [];
        $clean = [];

        // General
        $clean['enable_banners']      = empty( $input['enable_banners'] ) ? 0 : 1;
        $clean['default_link_target'] = in_array( $input['default_link_target'] ?? '', [ '_blank', '_self' ], true ) ? $input['default_link_target'] : '_blank';
        $clean['rel_nofollow']        = empty( $input['rel_nofollow'] ) ? 0 : 1;
        $clean['rel_sponsored']       = empty( $input['rel_sponsored'] ) ? 0 : 1;
        $clean['rel_noopener']        = empty( $input['rel_noopener'] ) ? 0 : 1;
        $clean['lazy_loading']        = empty( $input['lazy_loading'] ) ? 0 : 1;

        // Display
        $clean['in_article_position'] = in_array( $input['in_article_position'] ?? '', [ 'after_1', 'after_2', 'after_3', 'end' ], true ) ? $input['in_article_position'] : 'after_2';
        $clean['animation']           = in_array( $input['animation'] ?? '', [ 'none', 'fade_in', 'slide_up' ], true ) ? $input['animation'] : 'none';

        // Advanced
        $clean['custom_css']  = wp_strip_all_tags( $input['custom_css'] ?? '' );
        $clean['debug_mode']  = empty( $input['debug_mode'] ) ? 0 : 1;

        return $clean;
    }

    /* ─── Assets ───────────────────────────────────────────── */

    public function enqueue_assets( $hook ) {
        if ( $hook !== $this->hook ) {
            return;
        }
        wp_enqueue_media();

        // Code editor for custom CSS
        if ( function_exists( 'wp_enqueue_code_editor' ) ) {
            $editor = wp_enqueue_code_editor( [ 'type' => 'text/css' ] );
            if ( false !== $editor ) {
                wp_add_inline_script( 'code-editor', sprintf(
                    'jQuery(function(){if(document.getElementById("bmp_custom_css")){wp.codeEditor.initialize("bmp_custom_css",%s);}});',
                    wp_json_encode( $editor )
                ) );
            }
        }
    }

    /* ─── Render ───────────────────────────────────────────── */

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'banner-manager-pro' ) );
        }

        $licensed      = bmp_is_licensed();
        $license_key   = get_option( 'bmp_license_key', '' );
        $settings      = get_option( self::OPTION_KEY, [] );
        $defaults      = bmp_settings_defaults();
        $s             = wp_parse_args( $settings, $defaults );
        $tabs = [
            'license'  => [ 'label' => __( 'License', 'banner-manager-pro' ),       'icon' => 'dashicons-lock' ],
            'general'  => [ 'label' => __( 'General', 'banner-manager-pro' ),       'icon' => 'dashicons-admin-settings' ],
            'display'  => [ 'label' => __( 'Display', 'banner-manager-pro' ),       'icon' => 'dashicons-visibility' ],
            'advanced' => [ 'label' => __( 'Advanced', 'banner-manager-pro' ),      'icon' => 'dashicons-admin-generic' ],
            'docs'     => [ 'label' => __( 'Documentation', 'banner-manager-pro' ), 'icon' => 'dashicons-book' ],
        ];

        // Only show non-license tabs when licensed
        if ( ! $licensed ) {
            $tabs = [ 'license' => $tabs['license'] ];
        }

        $nonce = wp_create_nonce( 'bmp_license_nonce' );
        ?>
        <style>
        /* ── Layout ── */
        #bmp-settings-wrap { max-width: 1140px; margin-top: 20px; }
        .bmp-settings-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .bmp-settings-header h1 { margin: 0; font-size: 1.6rem; font-weight: 800; color: #1d2327; }
        .bmp-settings-version { background: #f0f0f1; color: #787c82; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; }
        .bmp-settings-layout { display: grid; grid-template-columns: 220px 1fr; gap: 0; min-height: 600px; border: 1px solid #c3c4c7; border-radius: 8px; overflow: hidden; background: #f6f7f7; }

        /* ── Sidebar ── */
        .bmp-settings-sidebar { background: #1d2327; padding: 12px 0; display: flex; flex-direction: column; }
        .bmp-sidebar-item { display: flex; align-items: center; gap: 10px; padding: 11px 20px; color: #bbc8d4; text-decoration: none; font-size: 13px; font-weight: 500; transition: all 120ms; border-left: 3px solid transparent; cursor: pointer; }
        .bmp-sidebar-item:hover { color: #fff; background: rgba(255,255,255,0.06); }
        .bmp-sidebar-item:focus { color: #fff; box-shadow: none; outline: none; }
        .bmp-sidebar-item.is-active { color: #fff; background: rgba(255,255,255,0.08); border-left-color: #ffc45e; }
        .bmp-sidebar-item .dashicons { font-size: 16px; width: 16px; height: 16px; opacity: 0.65; }
        .bmp-sidebar-item.is-active .dashicons { opacity: 1; color: #ffc45e; }

        /* ── Panel ── */
        .bmp-settings-panel { background: #fff; padding: 28px 32px; overflow-y: auto; }
        .bmp-tab-content { display: none; }
        .bmp-tab-content.is-active { display: block; animation: bmpFadeIn 200ms ease; }
        @keyframes bmpFadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        /* ── Sections ── */
        .bmp-admin-section { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px 28px; margin: 0 0 20px; }
        .bmp-admin-section h2 { margin: 0 0 16px; padding: 0 0 12px; border-bottom: 1px solid #e5e7eb; font-size: 1.05em; font-weight: 700; color: #1d2327; }
        .bmp-admin-section .form-table th { font-weight: 600; color: #374151; padding-top: 16px; }
        .bmp-admin-section .form-table td { padding-top: 12px; }

        /* ── Media ── */
        .bmp-media-preview { margin-bottom: 10px; }
        .bmp-media-preview img { max-width: 300px; max-height: 150px; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px; background: #fff; }

        /* ── Submit button ── */
        .bmp-settings-panel .submit { margin-top: 8px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
        .bmp-settings-panel #submit { background: #1d2327; border-color: #1d2327; color: #fff; border-radius: 6px; padding: 6px 24px; font-weight: 600; transition: background 120ms; }
        .bmp-settings-panel #submit:hover { background: #2c3338; }

        /* ── License card ── */
        .bmp-license-card { max-width: 600px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 30px; }
        .bmp-license-active { display: inline-block; background: #00a32a; color: #fff; padding: 6px 16px; border-radius: 20px; font-weight: 600; }
        .bmp-license-inactive { display: inline-block; background: #dba617; color: #fff; padding: 6px 16px; border-radius: 20px; font-weight: 600; }

        /* ── Positions table ── */
        .bmp-positions-table { width: 100%; border-collapse: collapse; }
        .bmp-positions-table th,
        .bmp-positions-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
        .bmp-positions-table th { font-weight: 600; color: #374151; background: #f3f4f6; }
        .bmp-positions-table tr:last-child td { border-bottom: none; }
        .bmp-positions-table code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-size: 12px; }

        /* ── Responsive ── */
        @media (max-width: 960px) {
            .bmp-settings-layout { grid-template-columns: 1fr; }
            .bmp-settings-sidebar { flex-direction: row; flex-wrap: wrap; padding: 8px; gap: 4px; }
            .bmp-sidebar-item { padding: 8px 12px; border-left: none; border-bottom: 2px solid transparent; font-size: 12px; }
            .bmp-sidebar-item.is-active { border-left: none; border-bottom-color: #ffc45e; }
            .bmp-sidebar-item .dashicons { display: none; }
            .bmp-settings-panel { padding: 20px 16px; }
        }
        </style>

        <div id="bmp-settings-wrap" class="wrap">

            <!-- Header -->
            <div class="bmp-settings-header">
                <h1>Banner Manager Pro</h1>
                <span class="bmp-settings-version">v<?php echo esc_html( BMP_VERSION ); ?></span>
            </div>

            <div class="bmp-settings-layout">

                <!-- Sidebar -->
                <nav class="bmp-settings-sidebar">
                    <?php foreach ( $tabs as $slug => $tab ) : ?>
                        <a href="#<?php echo esc_attr( $slug ); ?>" class="bmp-sidebar-item" data-tab="<?php echo esc_attr( $slug ); ?>">
                            <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
                            <?php echo esc_html( $tab['label'] ); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!-- Panel -->
                <div class="bmp-settings-panel">

                    <!-- ═══ License Tab ═══ -->
                    <div id="bmp-tab-license" class="bmp-tab-content">
                        <div class="bmp-admin-section">
                            <h2><?php esc_html_e( 'License', 'banner-manager-pro' ); ?></h2>
                            <div class="bmp-license-card">
                                <?php if ( $licensed ) : ?>
                                    <div style="text-align:center;margin-bottom:20px;">
                                        <span class="bmp-license-active">&#10003; <?php esc_html_e( 'License Active', 'banner-manager-pro' ); ?></span>
                                    </div>
                                    <table class="form-table" style="margin:0;">
                                        <tr>
                                            <th><?php esc_html_e( 'License Key', 'banner-manager-pro' ); ?></th>
                                            <td><code style="font-size:14px;"><?php echo esc_html( $license_key ); ?></code></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Domain', 'banner-manager-pro' ); ?></th>
                                            <td><?php echo esc_html( home_url() ); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Expiration', 'banner-manager-pro' ); ?></th>
                                            <td>
                                                <?php
                                                $expires = get_option( 'bmp_license_expires_at', '' );
                                                if ( $expires ) {
                                                    $days = (int) ceil( ( strtotime( $expires ) - time() ) / 86400 );
                                                    $date_formatted = wp_date( 'd F Y', strtotime( $expires ) );
                                                    if ( $days <= 0 ) {
                                                        /* translators: %s: formatted expiration date */
                                                        echo '<span style="color:#dc2626;font-weight:600;">' . sprintf( esc_html__( 'Expired on %s', 'banner-manager-pro' ), esc_html( $date_formatted ) ) . '</span>';
                                                    } elseif ( $days <= 30 ) {
                                                        /* translators: 1: formatted date, 2: number of days remaining */
                                                        echo '<span style="color:#d97706;font-weight:600;">' . esc_html( $date_formatted ) . ' (' . sprintf( _n( '%d day remaining', '%d days remaining', $days, 'banner-manager-pro' ), $days ) . ')</span>';
                                                    } else {
                                                        /* translators: 1: formatted date, 2: number of days remaining */
                                                        echo '<span style="color:#16a34a;">' . esc_html( $date_formatted ) . ' (' . sprintf( _n( '%d day remaining', '%d days remaining', $days, 'banner-manager-pro' ), $days ) . ')</span>';
                                                    }
                                                } else {
                                                    echo '<span style="color:#16a34a;">' . esc_html__( 'Lifetime', 'banner-manager-pro' ) . '</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin-top:20px;">
                                        <button type="button" id="bmp-deactivate-btn" class="button button-secondary" style="color:#d63638;"><?php esc_html_e( 'Deactivate License', 'banner-manager-pro' ); ?></button>
                                    </p>
                                <?php else : ?>
                                    <h2 style="margin-top:0;"><?php esc_html_e( 'Activate Your License', 'banner-manager-pro' ); ?></h2>
                                    <p><?php esc_html_e( 'Enter your license key to activate Banner Manager Pro.', 'banner-manager-pro' ); ?></p>
                                    <p>
                                        <input type="text" id="bmp-license-key" placeholder="BMP-XXXX-XXXX-XXXX" style="width:100%;font-size:16px;padding:8px 12px;font-family:monospace;text-transform:uppercase;" maxlength="19">
                                    </p>
                                    <p>
                                        <button type="button" id="bmp-activate-btn" class="button button-primary button-hero" style="width:100%;"><?php esc_html_e( 'Activate License', 'banner-manager-pro' ); ?></button>
                                    </p>
                                    <div id="bmp-license-message" style="margin-top:15px;display:none;"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ( $licensed ) : ?>

                    <!-- ═══ Form wraps General + Popup + Display + Advanced ═══ -->
                    <form method="post" action="options.php" id="bmp-settings-form">
                        <?php settings_fields( 'bmp_settings_group' ); ?>
                        <input type="hidden" id="bmp_active_tab" name="bmp_active_tab" value="">

                        <!-- ═══ General Tab ═══ -->
                        <div id="bmp-tab-general" class="bmp-tab-content">
                            <div class="bmp-admin-section">
                                <h2><?php esc_html_e( 'General Settings', 'banner-manager-pro' ); ?></h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Enable Banners', 'banner-manager-pro' ); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="bmp_settings[enable_banners]" value="1" <?php checked( $s['enable_banners'], 1 ); ?>>
                                                <?php esc_html_e( 'Enable frontend banner display globally', 'banner-manager-pro' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Default Link Target', 'banner-manager-pro' ); ?></th>
                                        <td>
                                            <select name="bmp_settings[default_link_target]">
                                                <option value="_blank" <?php selected( $s['default_link_target'], '_blank' ); ?>><?php esc_html_e( '_blank (new tab)', 'banner-manager-pro' ); ?></option>
                                                <option value="_self" <?php selected( $s['default_link_target'], '_self' ); ?>><?php esc_html_e( '_self (same tab)', 'banner-manager-pro' ); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Default Link Rel', 'banner-manager-pro' ); ?></th>
                                        <td>
                                            <label style="display:block;margin-bottom:6px;">
                                                <input type="checkbox" name="bmp_settings[rel_nofollow]" value="1" <?php checked( $s['rel_nofollow'], 1 ); ?>>
                                                nofollow
                                            </label>
                                            <label style="display:block;margin-bottom:6px;">
                                                <input type="checkbox" name="bmp_settings[rel_sponsored]" value="1" <?php checked( $s['rel_sponsored'], 1 ); ?>>
                                                sponsored
                                            </label>
                                            <label style="display:block;">
                                                <input type="checkbox" name="bmp_settings[rel_noopener]" value="1" <?php checked( $s['rel_noopener'], 1 ); ?>>
                                                noopener
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Lazy Loading', 'banner-manager-pro' ); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="bmp_settings[lazy_loading]" value="1" <?php checked( $s['lazy_loading'], 1 ); ?>>
                                                <?php /* translators: keep the HTML code tag as-is */ echo __( 'Add <code>loading="lazy"</code> to banner images', 'banner-manager-pro' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="submit">
                                <?php submit_button( __( 'Save Settings', 'banner-manager-pro' ), 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                        <!-- ═══ Display Tab ═══ -->
                        <div id="bmp-tab-display" class="bmp-tab-content">
                            <div class="bmp-admin-section">
                                <h2><?php esc_html_e( 'Banner Positions', 'banner-manager-pro' ); ?></h2>
                                <p style="color:#6b7280;margin:0 0 16px;"><?php esc_html_e( 'Overview of available banner positions and the hooks they use.', 'banner-manager-pro' ); ?></p>
                                <table class="bmp-positions-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Position', 'banner-manager-pro' ); ?></th>
                                            <th><?php esc_html_e( 'Hook / Method', 'banner-manager-pro' ); ?></th>
                                            <th><?php esc_html_e( 'Description', 'banner-manager-pro' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong><?php esc_html_e( 'Header', 'banner-manager-pro' ); ?></strong></td>
                                            <td><code>wp_body_open</code></td>
                                            <td><?php esc_html_e( 'Displayed right after the opening <body> tag', 'banner-manager-pro' ); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e( 'Footer', 'banner-manager-pro' ); ?></strong></td>
                                            <td><code>get_footer</code></td>
                                            <td><?php esc_html_e( 'Displayed before the footer area', 'banner-manager-pro' ); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e( 'Sidebar 1', 'banner-manager-pro' ); ?></strong></td>
                                            <td><code>get_sidebar</code></td>
                                            <td><?php esc_html_e( 'Injected into the primary sidebar', 'banner-manager-pro' ); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e( 'Sidebar 2', 'banner-manager-pro' ); ?></strong></td>
                                            <td><code>get_sidebar</code></td>
                                            <td><?php esc_html_e( 'Injected into the secondary sidebar', 'banner-manager-pro' ); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e( 'In Article', 'banner-manager-pro' ); ?></strong></td>
                                            <td><code>the_content</code></td>
                                            <td><?php esc_html_e( 'Inserted within post content at configured position', 'banner-manager-pro' ); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e( 'Between Articles', 'banner-manager-pro' ); ?></strong></td>
                                            <td><code>loop_end</code></td>
                                            <td><?php esc_html_e( 'Displayed between posts in archive/loop pages', 'banner-manager-pro' ); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="bmp-admin-section">
                                <h2><?php esc_html_e( 'Display Options', 'banner-manager-pro' ); ?></h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'In-Article Position', 'banner-manager-pro' ); ?></th>
                                        <td>
                                            <select name="bmp_settings[in_article_position]">
                                                <option value="after_1" <?php selected( $s['in_article_position'], 'after_1' ); ?>><?php esc_html_e( 'After 1st paragraph', 'banner-manager-pro' ); ?></option>
                                                <option value="after_2" <?php selected( $s['in_article_position'], 'after_2' ); ?>><?php esc_html_e( 'After 2nd paragraph', 'banner-manager-pro' ); ?></option>
                                                <option value="after_3" <?php selected( $s['in_article_position'], 'after_3' ); ?>><?php esc_html_e( 'After 3rd paragraph', 'banner-manager-pro' ); ?></option>
                                                <option value="end" <?php selected( $s['in_article_position'], 'end' ); ?>><?php esc_html_e( 'End of content', 'banner-manager-pro' ); ?></option>
                                            </select>
                                            <p class="description"><?php esc_html_e( 'Where to insert in-article banners within post content.', 'banner-manager-pro' ); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Animation', 'banner-manager-pro' ); ?></th>
                                        <td>
                                            <select name="bmp_settings[animation]">
                                                <option value="none" <?php selected( $s['animation'], 'none' ); ?>><?php esc_html_e( 'None', 'banner-manager-pro' ); ?></option>
                                                <option value="fade_in" <?php selected( $s['animation'], 'fade_in' ); ?>><?php esc_html_e( 'Fade In', 'banner-manager-pro' ); ?></option>
                                                <option value="slide_up" <?php selected( $s['animation'], 'slide_up' ); ?>><?php esc_html_e( 'Slide Up', 'banner-manager-pro' ); ?></option>
                                            </select>
                                            <p class="description"><?php esc_html_e( 'CSS animation applied when banners appear on screen.', 'banner-manager-pro' ); ?></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="submit">
                                <?php submit_button( __( 'Save Settings', 'banner-manager-pro' ), 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                        <!-- ═══ Advanced Tab ═══ -->
                        <div id="bmp-tab-advanced" class="bmp-tab-content">
                            <div class="bmp-admin-section">
                                <h2><?php esc_html_e( 'Custom CSS', 'banner-manager-pro' ); ?></h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Custom CSS', 'banner-manager-pro' ); ?></th>
                                        <td>
                                            <textarea id="bmp_custom_css" name="bmp_settings[custom_css]" rows="12" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>
                                            <p class="description"><?php esc_html_e( 'Add custom CSS to style banners on the front-end.', 'banner-manager-pro' ); ?></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="bmp-admin-section">
                                <h2><?php esc_html_e( 'Debug', 'banner-manager-pro' ); ?></h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Debug Mode', 'banner-manager-pro' ); ?></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="bmp_settings[debug_mode]" value="1" <?php checked( $s['debug_mode'], 1 ); ?>>
                                                <?php /* translators: keep the HTML code tag as-is */ echo __( 'Enable <code>?debug_bmp</code> query parameter for administrators', 'banner-manager-pro' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="submit">
                                <?php submit_button( __( 'Save Settings', 'banner-manager-pro' ), 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                    </form>

                    <!-- Documentation tab (outside the form) -->
                    <div id="bmp-tab-docs" class="bmp-tab-content">

                        <div class="bmp-admin-section">
                            <h2><?php esc_html_e( 'Getting Started', 'banner-manager-pro' ); ?></h2>
                            <ol style="line-height:2;font-size:14px;color:#374151;">
                                <li><?php echo __( 'Go to <strong>Banners &rarr; Add New Banner</strong>', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( 'Give your banner a <strong>title</strong>', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( 'Choose the <strong>type</strong>: Image or HTML/JS', 'banner-manager-pro' ); ?></li>
                                <li><?php esc_html_e( 'For an image: upload the image and add a destination link', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( 'Select the <strong>positions</strong> where the banner should appear', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( 'Choose the <strong>device targeting</strong> (Desktop, Mobile, or Both)', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( '<strong>Publish</strong> — the banner is displayed immediately', 'banner-manager-pro' ); ?></li>
                            </ol>
                        </div>

                        <div class="bmp-admin-section">
                            <h2><?php esc_html_e( 'Banner Types', 'banner-manager-pro' ); ?></h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th><?php esc_html_e( 'Type', 'banner-manager-pro' ); ?></th><th><?php esc_html_e( 'Usage', 'banner-manager-pro' ); ?></th></tr></thead>
                                <tbody>
                                    <tr><td><strong><?php esc_html_e( 'Image', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Visual banner with clickable link. Ideal for promotions, affiliates, and display ads.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'HTML/JS', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Custom code (AdSense, ad networks, widgets). The code is inserted as-is.', 'banner-manager-pro' ); ?></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="bmp-admin-section">
                            <h2><?php esc_html_e( 'Available Positions', 'banner-manager-pro' ); ?></h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th><?php esc_html_e( 'Position', 'banner-manager-pro' ); ?></th><th><?php esc_html_e( 'Location', 'banner-manager-pro' ); ?></th><th><?php esc_html_e( 'WordPress Hook', 'banner-manager-pro' ); ?></th></tr></thead>
                                <tbody>
                                    <tr><td><strong><?php esc_html_e( 'Header', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Top of page, after the body opening tag', 'banner-manager-pro' ); ?></td><td><code>wp_body_open</code></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Footer', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Before the footer', 'banner-manager-pro' ); ?></td><td><code>get_footer</code></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Sidebar 1', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Primary sidebar', 'banner-manager-pro' ); ?></td><td><code>get_sidebar</code></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Sidebar 2', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Secondary sidebar', 'banner-manager-pro' ); ?></td><td><code>get_sidebar</code></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'In Article', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'After the 1st paragraph of a post', 'banner-manager-pro' ); ?></td><td><code>the_content</code></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Between Articles', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'In post listings (blog, archives)', 'banner-manager-pro' ); ?></td><td><code>loop_end</code></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="bmp-admin-section">
                            <h2><?php esc_html_e( 'Shortcode', 'banner-manager-pro' ); ?></h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th><?php esc_html_e( 'Shortcode', 'banner-manager-pro' ); ?></th><th><?php esc_html_e( 'Description', 'banner-manager-pro' ); ?></th></tr></thead>
                                <tbody>
                                    <tr><td><code>[bmp_banner location="header"]</code></td><td><?php esc_html_e( 'Displays banners assigned to the "header" position', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><code>[bmp_banner location="sidebar1" limit="2"]</code></td><td><?php esc_html_e( 'Displays up to 2 banners from sidebar 1', 'banner-manager-pro' ); ?></td></tr>
                                </tbody>
                            </table>
                            <p style="color:#6b7280;margin-top:8px;font-size:13px;"><?php esc_html_e( 'Available positions:', 'banner-manager-pro' ); ?> <code>header</code>, <code>footer</code>, <code>sidebar1</code>, <code>sidebar2</code>, <code>in_article</code>, <code>in_listing</code></p>
                        </div>

                        <div class="bmp-admin-section">
                            <h2><?php esc_html_e( 'Popups', 'banner-manager-pro' ); ?></h2>
                            <p style="color:#374151;"><?php echo __( 'Popups are managed via a dedicated <strong>Popups</strong> menu in the WordPress sidebar.', 'banner-manager-pro' ); ?></p>

                            <h3><?php esc_html_e( 'Creative Types', 'banner-manager-pro' ); ?></h3>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th><?php esc_html_e( 'Type', 'banner-manager-pro' ); ?></th><th><?php esc_html_e( 'Usage', 'banner-manager-pro' ); ?></th></tr></thead>
                                <tbody>
                                    <tr><td><strong><?php esc_html_e( 'Image', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Simple image popup with optional link. Best for promotional banners.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'HTML/JS', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Custom HTML or JavaScript code. Full control over content.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Post / Page', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Displays an existing post or page as a card or full content.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Visual', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Choose from 8 professional templates and customize heading, text, image, and CTA button.', 'banner-manager-pro' ); ?></td></tr>
                                </tbody>
                            </table>

                            <h3 style="margin-top:20px;"><?php esc_html_e( 'Visual Templates', 'banner-manager-pro' ); ?></h3>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th><?php esc_html_e( 'Template', 'banner-manager-pro' ); ?></th><th><?php esc_html_e( 'Description', 'banner-manager-pro' ); ?></th></tr></thead>
                                <tbody>
                                    <tr><td><strong>Flash Sale</strong></td><td><?php esc_html_e( 'Hero image with urgency badge and CTA. Perfect for limited-time offers.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong>Lead Magnet</strong></td><td><?php esc_html_e( 'Two-column layout with image and download CTA. Ideal for ebooks and guides.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong>Newsletter</strong></td><td><?php esc_html_e( 'Clean centered layout with icon and signup CTA.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong>Video Spotlight</strong></td><td><?php esc_html_e( 'Embedded YouTube/Vimeo video with heading and CTA.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong>Coupon Code</strong></td><td><?php esc_html_e( 'Discount code with copy-to-clipboard button and CTA.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong>Announcement</strong></td><td><?php esc_html_e( 'Clean text-only popup. Strong typography, no image needed.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong>Product Showcase</strong></td><td><?php esc_html_e( 'Product image with price badge, description, and buy CTA.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong>Testimonial</strong></td><td><?php esc_html_e( 'Customer quote with avatar, name, role, and CTA.', 'banner-manager-pro' ); ?></td></tr>
                                </tbody>
                            </table>

                            <h3 style="margin-top:20px;"><?php esc_html_e( 'Positions', 'banner-manager-pro' ); ?></h3>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th><?php esc_html_e( 'Position', 'banner-manager-pro' ); ?></th><th><?php esc_html_e( 'Behavior', 'banner-manager-pro' ); ?></th></tr></thead>
                                <tbody>
                                    <tr><td><strong><?php esc_html_e( 'Center', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Centered modal with dark overlay. Sizes: small, medium, large, fullscreen.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Right / Left', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Slide-in panel from the side. 380px on desktop, 100% on mobile.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Bottom', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Notification bar fixed at the bottom of the screen.', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Top', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Announcement bar fixed at the top of the screen.', 'banner-manager-pro' ); ?></td></tr>
                                </tbody>
                            </table>

                            <h3 style="margin-top:20px;"><?php esc_html_e( 'Triggers', 'banner-manager-pro' ); ?></h3>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th><?php esc_html_e( 'Trigger', 'banner-manager-pro' ); ?></th><th><?php esc_html_e( 'Behavior', 'banner-manager-pro' ); ?></th></tr></thead>
                                <tbody>
                                    <tr><td><strong><?php esc_html_e( 'Immediate', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Displays as soon as the page loads', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Delay', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Displays after X seconds (configurable)', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Scroll', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Displays when the visitor has scrolled X% of the page', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Exit intent', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Displays when the mouse leaves the window (desktop only)', 'banner-manager-pro' ); ?></td></tr>
                                </tbody>
                            </table>

                            <h3 style="margin-top:20px;"><?php esc_html_e( 'Display Frequency', 'banner-manager-pro' ); ?></h3>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th><?php esc_html_e( 'Option', 'banner-manager-pro' ); ?></th><th><?php esc_html_e( 'Behavior', 'banner-manager-pro' ); ?></th></tr></thead>
                                <tbody>
                                    <tr><td><strong><?php esc_html_e( 'Every visit', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'The popup displays on every page load', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Per session', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Once per browser session', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Per day', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Once every 24 hours', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Per week', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Once every 7 days', 'banner-manager-pro' ); ?></td></tr>
                                    <tr><td><strong><?php esc_html_e( 'Once only', 'banner-manager-pro' ); ?></strong></td><td><?php esc_html_e( 'Never displays again after the first dismissal', 'banner-manager-pro' ); ?></td></tr>
                                </tbody>
                            </table>

                            <h3 style="margin-top:20px;"><?php esc_html_e( 'Targeting', 'banner-manager-pro' ); ?></h3>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><?php echo __( '<strong>Entire site</strong> — automatic rotation among popups with the same priority', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( '<strong>All posts</strong> or <strong>All pages</strong>', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( '<strong>Specific post</strong> — select a specific post', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( '<strong>Specific page</strong> — select a specific page', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( '<strong>Category</strong> — displays on posts in this category', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( '<strong>Homepage</strong> only', 'banner-manager-pro' ); ?></li>
                            </ul>
                        </div>

                        <div class="bmp-admin-section">
                            <h2><?php esc_html_e( 'Predefined Formats', 'banner-manager-pro' ); ?></h2>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div>
                                    <h3 style="margin-top:0;"><?php esc_html_e( 'Horizontal Banners', 'banner-manager-pro' ); ?></h3>
                                    <p style="color:#6b7280;">728×90, 970×90, 970×250, 468×60, 320×50</p>
                                    <p><em><?php esc_html_e( 'Ideal for: header, footer, between articles', 'banner-manager-pro' ); ?></em></p>
                                </div>
                                <div>
                                    <h3 style="margin-top:0;"><?php esc_html_e( 'Vertical Banners', 'banner-manager-pro' ); ?></h3>
                                    <p style="color:#6b7280;">300×250, 300×600, 160×600, 120×600, 250×250</p>
                                    <p><em><?php esc_html_e( 'Ideal for: sidebars', 'banner-manager-pro' ); ?></em></p>
                                </div>
                            </div>
                        </div>

                        <div class="bmp-admin-section">
                            <h2><?php esc_html_e( 'Device Targeting', 'banner-manager-pro' ); ?></h2>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><?php echo __( '<strong>Desktop</strong> — computers only', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( '<strong>Mobile</strong> — smartphones and tablets only', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( '<strong>Both</strong> — all devices', 'banner-manager-pro' ); ?></li>
                            </ul>
                            <p style="color:#6b7280;margin-top:8px;font-size:13px;"><?php echo __( 'Targeting uses <code>wp_is_mobile()</code> to detect the device.', 'banner-manager-pro' ); ?></p>
                        </div>

                        <div class="bmp-admin-section">
                            <h2><?php esc_html_e( 'Advanced Features', 'banner-manager-pro' ); ?></h2>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><?php esc_html_e( 'Quick Duplicate — duplicate a banner or popup in one click', 'banner-manager-pro' ); ?></li>
                                <li><?php esc_html_e( 'Admin Filters — filter by status, type, device, position', 'banner-manager-pro' ); ?></li>
                                <li><?php esc_html_e( 'Live Preview — desktop/mobile preview in the editor', 'banner-manager-pro' ); ?></li>
                                <li><?php esc_html_e( 'Multisite — compatible with WordPress Multisite (MU)', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( 'Debug mode — add <code>?debug_bmp</code> to the URL to see active banners', 'banner-manager-pro' ); ?></li>
                            </ul>
                        </div>

                        <div class="bmp-admin-section">
                            <h2><?php esc_html_e( 'License', 'banner-manager-pro' ); ?></h2>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><?php echo __( 'The plugin requires a <strong>license key</strong> in the format <code>BMP-XXXX-XXXX-XXXX</code>', 'banner-manager-pro' ); ?></li>
                                <li><?php esc_html_e( 'The license is validated automatically every 72 hours', 'banner-manager-pro' ); ?></li>
                                <li><?php echo __( 'Depending on your license: <strong>single-domain</strong> or <strong>multi-domain</strong> (unlimited)', 'banner-manager-pro' ); ?></li>
                                <li><?php esc_html_e( 'Updates are automatic via the WordPress admin', 'banner-manager-pro' ); ?></li>
                            </ul>
                        </div>

                        <div class="bmp-admin-section" style="background:#fefce8;border-color:#fde68a;">
                            <h2 style="border-color:#fde68a;"><?php esc_html_e( 'Support', 'banner-manager-pro' ); ?></h2>
                            <p style="color:#374151;"><?php esc_html_e( 'For any questions or issues:', 'banner-manager-pro' ); ?></p>
                            <ul style="list-style:none;padding:0;line-height:2.2;">
                                <li><?php esc_html_e( 'Email:', 'banner-manager-pro' ); ?> <a href="mailto:contact@khalid.digital">contact@khalid.digital</a></li>
                                <li><?php esc_html_e( 'GitHub:', 'banner-manager-pro' ); ?> <a href="https://github.com/kabde/banner-manager-pro" target="_blank">github.com/kabde/banner-manager-pro</a></li>
                            </ul>
                        </div>

                    </div>

                    <?php endif; ?>

                </div><!-- .bmp-settings-panel -->
            </div><!-- .bmp-settings-layout -->
        </div><!-- #bmp-settings-wrap -->

        <script>
        jQuery(function($) {
            /* ── Tab switching ── */
            var $items = $('.bmp-sidebar-item');
            var $tabs  = $('.bmp-tab-content');

            function activateTab(slug) {
                $items.removeClass('is-active');
                $tabs.removeClass('is-active');
                $items.filter('[data-tab="' + slug + '"]').addClass('is-active');
                $('#bmp-tab-' + slug).addClass('is-active');
                $('#bmp_active_tab').val(slug);
                if (history.replaceState) {
                    history.replaceState(null, null, '#' + slug);
                }
            }

            $items.on('click', function(e) {
                e.preventDefault();
                activateTab($(this).data('tab'));
            });

            // Determine initial tab
            var hash = window.location.hash.replace('#', '');
            var validTabs = [];
            $items.each(function() { validTabs.push($(this).data('tab')); });

            if (hash && validTabs.indexOf(hash) !== -1) {
                activateTab(hash);
            } else {
                activateTab(validTabs[0] || 'license');
            }

            /* ── License AJAX ── */
            var licenseNonce = '<?php echo esc_js( $nonce ); ?>';

            $('#bmp-activate-btn').on('click', function() {
                var btn = $(this);
                var key = $('#bmp-license-key').val().trim();
                if (!key) return;

                btn.prop('disabled', true).text('Activating...');

                $.post(ajaxurl, {
                    action: 'bmp_activate_license',
                    nonce: licenseNonce,
                    license_key: key
                }, function(response) {
                    if (response.success) {
                        $('#bmp-license-message').html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>').show();
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        $('#bmp-license-message').html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>').show();
                        btn.prop('disabled', false).text('Activate License');
                    }
                }).fail(function() {
                    $('#bmp-license-message').html('<div class="notice notice-error inline"><p>Connection error.</p></div>').show();
                    btn.prop('disabled', false).text('Activate License');
                });
            });

            $('#bmp-deactivate-btn').on('click', function() {
                if (!confirm('Deactivate the license on this domain?')) return;
                var btn = $(this);
                btn.prop('disabled', true).text('Deactivating...');

                $.post(ajaxurl, {
                    action: 'bmp_deactivate_license',
                    nonce: licenseNonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });

        });
        </script>
        <?php
    }
}

/* ─── Defaults ─────────────────────────────────────────────── */

function bmp_settings_defaults() {
    return [
        'enable_banners'      => 1,
        'default_link_target' => '_blank',
        'rel_nofollow'        => 1,
        'rel_sponsored'       => 1,
        'rel_noopener'        => 1,
        'lazy_loading'        => 1,
        'in_article_position' => 'after_2',
        'animation'           => 'none',
        'custom_css'          => '',
        'debug_mode'          => 0,
    ];
}

/* ─── Helper ───────────────────────────────────────────────── */

function bmp_get_setting( $key ) {
    static $settings = null;
    if ( $settings === null ) {
        $settings = get_option( BMP_Settings::OPTION_KEY, [] );
    }
    $defaults = bmp_settings_defaults();
    return isset( $settings[ $key ] ) && $settings[ $key ] !== '' ? $settings[ $key ] : ( $defaults[ $key ] ?? '' );
}
