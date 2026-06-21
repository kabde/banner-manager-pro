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
            wp_die( 'You do not have sufficient permissions.' );
        }

        $licensed      = bmp_is_licensed();
        $license_key   = get_option( 'bmp_license_key', '' );
        $settings      = get_option( self::OPTION_KEY, [] );
        $defaults      = bmp_settings_defaults();
        $s             = wp_parse_args( $settings, $defaults );
        $tabs = [
            'license'  => [ 'label' => 'Licence',   'icon' => 'dashicons-lock' ],
            'general'  => [ 'label' => 'General',   'icon' => 'dashicons-admin-settings' ],
            'display'  => [ 'label' => 'Display',   'icon' => 'dashicons-visibility' ],
            'advanced' => [ 'label' => 'Advanced',  'icon' => 'dashicons-admin-generic' ],
            'docs'     => [ 'label' => 'Documentation', 'icon' => 'dashicons-book' ],
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
                            <h2>Licence</h2>
                            <div class="bmp-license-card">
                                <?php if ( $licensed ) : ?>
                                    <div style="text-align:center;margin-bottom:20px;">
                                        <span class="bmp-license-active">&#10003; Licence Active</span>
                                    </div>
                                    <table class="form-table" style="margin:0;">
                                        <tr>
                                            <th>Cl&eacute; de licence</th>
                                            <td><code style="font-size:14px;"><?php echo esc_html( $license_key ); ?></code></td>
                                        </tr>
                                        <tr>
                                            <th>Domaine</th>
                                            <td><?php echo esc_html( home_url() ); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Expiration</th>
                                            <td>
                                                <?php
                                                $expires = get_option( 'bmp_license_expires_at', '' );
                                                if ( $expires ) {
                                                    $days = (int) ceil( ( strtotime( $expires ) - time() ) / 86400 );
                                                    $date_formatted = wp_date( 'd F Y', strtotime( $expires ) );
                                                    if ( $days <= 0 ) {
                                                        echo '<span style="color:#dc2626;font-weight:600;">Expirée le ' . esc_html( $date_formatted ) . '</span>';
                                                    } elseif ( $days <= 30 ) {
                                                        echo '<span style="color:#d97706;font-weight:600;">' . esc_html( $date_formatted ) . ' (' . $days . ' jour' . ($days > 1 ? 's' : '') . ' restants)</span>';
                                                    } else {
                                                        echo '<span style="color:#16a34a;">' . esc_html( $date_formatted ) . ' (' . $days . ' jours restants)</span>';
                                                    }
                                                } else {
                                                    echo '<span style="color:#16a34a;">Lifetime</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin-top:20px;">
                                        <button type="button" id="bmp-deactivate-btn" class="button button-secondary" style="color:#d63638;">D&eacute;sactiver la licence</button>
                                    </p>
                                <?php else : ?>
                                    <h2 style="margin-top:0;">Activez votre licence</h2>
                                    <p>Entrez votre cl&eacute; de licence pour activer Banner Manager Pro.</p>
                                    <p>
                                        <input type="text" id="bmp-license-key" placeholder="BMP-XXXX-XXXX-XXXX" style="width:100%;font-size:16px;padding:8px 12px;font-family:monospace;text-transform:uppercase;" maxlength="19">
                                    </p>
                                    <p>
                                        <button type="button" id="bmp-activate-btn" class="button button-primary button-hero" style="width:100%;">Activer la licence</button>
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
                                <h2>General Settings</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Enable Banners</th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="bmp_settings[enable_banners]" value="1" <?php checked( $s['enable_banners'], 1 ); ?>>
                                                Enable frontend banner display globally
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Default Link Target</th>
                                        <td>
                                            <select name="bmp_settings[default_link_target]">
                                                <option value="_blank" <?php selected( $s['default_link_target'], '_blank' ); ?>>_blank (new tab)</option>
                                                <option value="_self" <?php selected( $s['default_link_target'], '_self' ); ?>>_self (same tab)</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Default Link Rel</th>
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
                                        <th scope="row">Lazy Loading</th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="bmp_settings[lazy_loading]" value="1" <?php checked( $s['lazy_loading'], 1 ); ?>>
                                                Add <code>loading="lazy"</code> to banner images
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="submit">
                                <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                        <!-- ═══ Display Tab ═══ -->
                        <div id="bmp-tab-display" class="bmp-tab-content">
                            <div class="bmp-admin-section">
                                <h2>Banner Positions</h2>
                                <p style="color:#6b7280;margin:0 0 16px;">Overview of available banner positions and the hooks they use.</p>
                                <table class="bmp-positions-table">
                                    <thead>
                                        <tr>
                                            <th>Position</th>
                                            <th>Hook / Method</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>Header</strong></td>
                                            <td><code>wp_body_open</code></td>
                                            <td>Displayed right after the opening &lt;body&gt; tag</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Footer</strong></td>
                                            <td><code>get_footer</code></td>
                                            <td>Displayed before the footer area</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Sidebar 1</strong></td>
                                            <td><code>get_sidebar</code></td>
                                            <td>Injected into the primary sidebar</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Sidebar 2</strong></td>
                                            <td><code>get_sidebar</code></td>
                                            <td>Injected into the secondary sidebar</td>
                                        </tr>
                                        <tr>
                                            <td><strong>In Article</strong></td>
                                            <td><code>the_content</code></td>
                                            <td>Inserted within post content at configured position</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Between Articles</strong></td>
                                            <td><code>loop_end</code></td>
                                            <td>Displayed between posts in archive/loop pages</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="bmp-admin-section">
                                <h2>Display Options</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">In-Article Position</th>
                                        <td>
                                            <select name="bmp_settings[in_article_position]">
                                                <option value="after_1" <?php selected( $s['in_article_position'], 'after_1' ); ?>>After 1st paragraph</option>
                                                <option value="after_2" <?php selected( $s['in_article_position'], 'after_2' ); ?>>After 2nd paragraph</option>
                                                <option value="after_3" <?php selected( $s['in_article_position'], 'after_3' ); ?>>After 3rd paragraph</option>
                                                <option value="end" <?php selected( $s['in_article_position'], 'end' ); ?>>End of content</option>
                                            </select>
                                            <p class="description">Where to insert in-article banners within post content.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Animation</th>
                                        <td>
                                            <select name="bmp_settings[animation]">
                                                <option value="none" <?php selected( $s['animation'], 'none' ); ?>>None</option>
                                                <option value="fade_in" <?php selected( $s['animation'], 'fade_in' ); ?>>Fade In</option>
                                                <option value="slide_up" <?php selected( $s['animation'], 'slide_up' ); ?>>Slide Up</option>
                                            </select>
                                            <p class="description">CSS animation applied when banners appear on screen.</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="submit">
                                <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                        <!-- ═══ Advanced Tab ═══ -->
                        <div id="bmp-tab-advanced" class="bmp-tab-content">
                            <div class="bmp-admin-section">
                                <h2>Custom CSS</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Custom CSS</th>
                                        <td>
                                            <textarea id="bmp_custom_css" name="bmp_settings[custom_css]" rows="12" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>
                                            <p class="description">Add custom CSS to style banners on the front-end.</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="bmp-admin-section">
                                <h2>Debug</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Debug Mode</th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="bmp_settings[debug_mode]" value="1" <?php checked( $s['debug_mode'], 1 ); ?>>
                                                Enable <code>?debug_bmp</code> query parameter for administrators
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="submit">
                                <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
                            </div>
                        </div>

                    </form>

                    <!-- Documentation tab (outside the form) -->
                    <div id="bmp-tab-docs" class="bmp-tab-content">

                        <div class="bmp-admin-section">
                            <h2>Premiers pas</h2>
                            <ol style="line-height:2;font-size:14px;color:#374151;">
                                <li>Allez dans <strong>Bannières → Ajouter une bannière</strong></li>
                                <li>Donnez un <strong>titre</strong> à votre bannière</li>
                                <li>Choisissez le <strong>type</strong> : Image ou HTML/JS</li>
                                <li>Pour une image : uploadez l'image et ajoutez un lien cible</li>
                                <li>Sélectionnez les <strong>emplacements</strong> où afficher la bannière</li>
                                <li>Choisissez le <strong>ciblage par appareil</strong> (Desktop, Mobile, ou les deux)</li>
                                <li><strong>Publiez</strong> — la bannière s'affiche immédiatement</li>
                            </ol>
                        </div>

                        <div class="bmp-admin-section">
                            <h2>Types de bannières</h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th>Type</th><th>Usage</th></tr></thead>
                                <tbody>
                                    <tr><td><strong>Image</strong></td><td>Bannière visuelle avec lien cliquable. Idéal pour les promotions, affiliations, et publicités display.</td></tr>
                                    <tr><td><strong>HTML/JS</strong></td><td>Code personnalisé (AdSense, réseaux publicitaires, widgets). Le code est inséré tel quel.</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="bmp-admin-section">
                            <h2>Emplacements disponibles</h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th>Emplacement</th><th>Position</th><th>Hook WordPress</th></tr></thead>
                                <tbody>
                                    <tr><td><strong>En-tête</strong></td><td>Haut de page, après l'ouverture du body</td><td><code>wp_body_open</code></td></tr>
                                    <tr><td><strong>Pied de page</strong></td><td>Avant le footer</td><td><code>get_footer</code></td></tr>
                                    <tr><td><strong>Sidebar 1</strong></td><td>Barre latérale principale</td><td><code>get_sidebar</code></td></tr>
                                    <tr><td><strong>Sidebar 2</strong></td><td>Barre latérale secondaire</td><td><code>get_sidebar</code></td></tr>
                                    <tr><td><strong>Dans les articles</strong></td><td>Après le 1er paragraphe d'un article</td><td><code>the_content</code></td></tr>
                                    <tr><td><strong>Entre les articles</strong></td><td>Dans les listes d'articles (blog, archives)</td><td><code>loop_end</code></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="bmp-admin-section">
                            <h2>Shortcode</h2>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th>Shortcode</th><th>Description</th></tr></thead>
                                <tbody>
                                    <tr><td><code>[bmp_banner location="header"]</code></td><td>Affiche les bannières assignées à l'emplacement "header"</td></tr>
                                    <tr><td><code>[bmp_banner location="sidebar1" limit="2"]</code></td><td>Affiche 2 bannières max de la sidebar 1</td></tr>
                                </tbody>
                            </table>
                            <p style="color:#6b7280;margin-top:8px;font-size:13px;">Emplacements disponibles : <code>header</code>, <code>footer</code>, <code>sidebar1</code>, <code>sidebar2</code>, <code>in_article</code>, <code>in_listing</code></p>
                        </div>

                        <div class="bmp-admin-section">
                            <h2>Popups</h2>
                            <p style="color:#374151;">Les popups sont gérés via un menu dédié <strong>Popups</strong> dans la barre latérale WordPress.</p>
                            <h3>Positions</h3>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th>Position</th><th>Comportement</th></tr></thead>
                                <tbody>
                                    <tr><td><strong>Centre</strong></td><td>Modal centré avec overlay sombre. Tailles : petit, moyen, grand, plein écran.</td></tr>
                                    <tr><td><strong>Droite / Gauche</strong></td><td>Panneau qui glisse depuis le côté. 380px sur desktop, 100% sur mobile.</td></tr>
                                    <tr><td><strong>Bas</strong></td><td>Barre de notification fixée en bas de l'écran.</td></tr>
                                    <tr><td><strong>Haut</strong></td><td>Barre d'annonce fixée en haut de l'écran.</td></tr>
                                </tbody>
                            </table>

                            <h3 style="margin-top:20px;">Déclencheurs</h3>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th>Déclencheur</th><th>Comportement</th></tr></thead>
                                <tbody>
                                    <tr><td><strong>Immédiat</strong></td><td>S'affiche dès le chargement de la page</td></tr>
                                    <tr><td><strong>Délai</strong></td><td>S'affiche après X secondes (configurable)</td></tr>
                                    <tr><td><strong>Scroll</strong></td><td>S'affiche quand le visiteur a scrollé X% de la page</td></tr>
                                    <tr><td><strong>Exit intent</strong></td><td>S'affiche quand la souris quitte la fenêtre (desktop uniquement)</td></tr>
                                </tbody>
                            </table>

                            <h3 style="margin-top:20px;">Fréquence d'affichage</h3>
                            <table class="widefat striped" style="max-width:700px;">
                                <thead><tr><th>Option</th><th>Comportement</th></tr></thead>
                                <tbody>
                                    <tr><td><strong>Chaque visite</strong></td><td>Le popup s'affiche à chaque chargement de page</td></tr>
                                    <tr><td><strong>Par session</strong></td><td>Une seule fois par session navigateur</td></tr>
                                    <tr><td><strong>Par jour</strong></td><td>Une fois toutes les 24 heures</td></tr>
                                    <tr><td><strong>Par semaine</strong></td><td>Une fois tous les 7 jours</td></tr>
                                    <tr><td><strong>Une seule fois</strong></td><td>Ne s'affiche plus jamais après la première fermeture</td></tr>
                                </tbody>
                            </table>

                            <h3 style="margin-top:20px;">Ciblage</h3>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><strong>Tout le site</strong> — rotation automatique entre les popups de même priorité</li>
                                <li><strong>Tous les articles</strong> ou <strong>Toutes les pages</strong></li>
                                <li><strong>Article spécifique</strong> — sélectionnez un article précis</li>
                                <li><strong>Page spécifique</strong> — sélectionnez une page précise</li>
                                <li><strong>Catégorie</strong> — s'affiche sur les articles de cette catégorie</li>
                                <li><strong>Page d'accueil</strong> uniquement</li>
                            </ul>
                        </div>

                        <div class="bmp-admin-section">
                            <h2>Formats prédéfinis</h2>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div>
                                    <h3 style="margin-top:0;">Bannières horizontales</h3>
                                    <p style="color:#6b7280;">728×90, 970×90, 970×250, 468×60, 320×50</p>
                                    <p><em>Idéal pour : en-tête, pied de page, entre les articles</em></p>
                                </div>
                                <div>
                                    <h3 style="margin-top:0;">Bannières verticales</h3>
                                    <p style="color:#6b7280;">300×250, 300×600, 160×600, 120×600, 250×250</p>
                                    <p><em>Idéal pour : sidebars</em></p>
                                </div>
                            </div>
                        </div>

                        <div class="bmp-admin-section">
                            <h2>Ciblage par appareil</h2>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><strong>Desktop</strong> — uniquement sur les ordinateurs</li>
                                <li><strong>Mobile</strong> — uniquement sur les smartphones et tablettes</li>
                                <li><strong>Les 2</strong> — sur tous les appareils</li>
                            </ul>
                            <p style="color:#6b7280;margin-top:8px;font-size:13px;">Le ciblage utilise <code>wp_is_mobile()</code> pour détecter l'appareil.</p>
                        </div>

                        <div class="bmp-admin-section">
                            <h2>Fonctionnalités avancées</h2>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li><strong>Duplication rapide</strong> — dupliquez une bannière ou un popup en un clic</li>
                                <li><strong>Filtres admin</strong> — filtrez par statut, type, appareil, emplacement</li>
                                <li><strong>Preview live</strong> — aperçu desktop/mobile dans l'éditeur</li>
                                <li><strong>Multisite</strong> — compatible WordPress Multisite (MU)</li>
                                <li><strong>Debug mode</strong> — ajoutez <code>?debug_bmp</code> à l'URL pour voir les bannières actives</li>
                            </ul>
                        </div>

                        <div class="bmp-admin-section">
                            <h2>Licence</h2>
                            <ul style="list-style:disc;padding-left:20px;color:#374151;line-height:2;">
                                <li>Le plugin nécessite une <strong>clé de licence</strong> au format <code>BMP-XXXX-XXXX-XXXX</code></li>
                                <li>La licence est validée automatiquement toutes les 72 heures</li>
                                <li>Selon votre licence : <strong>mono-domaine</strong> ou <strong>multi-domaines</strong> (illimité)</li>
                                <li>Les mises à jour sont automatiques via l'admin WordPress</li>
                            </ul>
                        </div>

                        <div class="bmp-admin-section" style="background:#fefce8;border-color:#fde68a;">
                            <h2 style="border-color:#fde68a;">Support</h2>
                            <p style="color:#374151;">Pour toute question ou problème :</p>
                            <ul style="list-style:none;padding:0;line-height:2.2;">
                                <li>Email : <a href="mailto:contact@khalid.digital">contact@khalid.digital</a></li>
                                <li>GitHub : <a href="https://github.com/kabde/banner-manager-pro" target="_blank">github.com/kabde/banner-manager-pro</a></li>
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

                btn.prop('disabled', true).text('Activation...');

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
                        btn.prop('disabled', false).text('Activer la licence');
                    }
                }).fail(function() {
                    $('#bmp-license-message').html('<div class="notice notice-error inline"><p>Erreur de connexion.</p></div>').show();
                    btn.prop('disabled', false).text('Activer la licence');
                });
            });

            $('#bmp-deactivate-btn').on('click', function() {
                if (!confirm('Désactiver la licence sur ce domaine ?')) return;
                var btn = $(this);
                btn.prop('disabled', true).text('Désactivation...');

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
