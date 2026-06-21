<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BMP_Popup_Frontend {

    public function __construct() {
        add_action( 'wp_footer', [ $this, 'render_popups' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Get all active popups matching the current page context.
     */
    private function get_matching_popups() {
        $device = wp_is_mobile() ? 'mobile' : 'desktop';

        $popups = get_posts([
            'post_type'      => 'bmp_popups',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'     => '_bmp_popup_status',
                    'value'   => 'active',
                    'compare' => '='
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_bmp_popup_device',
                        'value'   => 'both',
                        'compare' => '='
                    ],
                    [
                        'key'     => '_bmp_popup_device',
                        'value'   => $device,
                        'compare' => '='
                    ]
                ],
            ],
            'orderby'  => 'meta_value_num',
            'meta_key' => '_bmp_popup_priority',
            'order'    => 'DESC',
        ]);

        $matching = [];
        foreach ( $popups as $popup ) {
            if ( $this->matches_display_rules( $popup->ID ) ) {
                $matching[] = $popup;
            }
        }

        return $matching;
    }

    /**
     * Check if a popup matches the current page's display rules.
     */
    private function matches_display_rules( $popup_id ) {
        $display_on = get_post_meta( $popup_id, '_bmp_popup_display_on', true ) ?: 'all';

        switch ( $display_on ) {
            case 'all':
                return true;

            case 'homepage':
                return is_front_page() || is_home();

            case 'posts':
                return is_single();

            case 'pages':
                return is_page();

            case 'specific_post':
                if ( ! is_single() ) return false;
                $target = absint( get_post_meta( $popup_id, '_bmp_popup_target_post', true ) );
                return $target && get_queried_object_id() === $target;

            case 'specific_page':
                if ( ! is_page() ) return false;
                $target = absint( get_post_meta( $popup_id, '_bmp_popup_target_page', true ) );
                return $target && get_queried_object_id() === $target;

            case 'category':
                $target = absint( get_post_meta( $popup_id, '_bmp_popup_target_category', true ) );
                if ( ! $target ) return false;
                if ( is_category( $target ) ) return true;
                if ( is_single() ) return has_category( $target );
                return false;

            default:
                return false;
        }
    }

    /**
     * Select the popup to display using priority + rotation logic.
     *
     * Rules:
     * 1. Popup with highest priority wins
     * 2. If multiple popups share the same highest priority, pick one randomly (rotation)
     * 3. Only ONE popup is displayed per page load
     */
    private function select_popup( $popups ) {
        if ( empty( $popups ) ) return null;
        if ( count( $popups ) === 1 ) return $popups[0];

        // Group by priority
        $by_priority = [];
        foreach ( $popups as $popup ) {
            $prio = absint( get_post_meta( $popup->ID, '_bmp_popup_priority', true ) ?: 10 );
            $by_priority[ $prio ][] = $popup;
        }

        // Get highest priority group
        krsort( $by_priority );
        $top_group = reset( $by_priority );

        // Random rotation within same priority
        if ( count( $top_group ) === 1 ) return $top_group[0];
        return $top_group[ array_rand( $top_group ) ];
    }

    /**
     * Render the selected popup in wp_footer.
     */
    public function render_popups() {
        if ( is_admin() ) return;

        $matching = $this->get_matching_popups();
        $popup = $this->select_popup( $matching );
        if ( ! $popup ) return;

        $id        = $popup->ID;
        $type      = get_post_meta( $id, '_bmp_popup_type', true ) ?: 'image';
        $position  = get_post_meta( $id, '_bmp_popup_position', true ) ?: 'center';
        $size      = get_post_meta( $id, '_bmp_popup_size', true ) ?: 'medium';

        // Design settings
        $bg_color    = get_post_meta( $id, '_bmp_popup_bg_color', true ) ?: '#ffffff';
        $btn_color   = get_post_meta( $id, '_bmp_popup_btn_color', true ) ?: '#111827';
        $close_color = get_post_meta( $id, '_bmp_popup_close_color', true ) ?: '';
        $radius      = absint( get_post_meta( $id, '_bmp_popup_radius', true ) ?: 16 );

        // Build content HTML
        $content_html = '';
        if ( 'image' === $type ) {
            $image_id   = absint( get_post_meta( $id, '_bmp_popup_image_id', true ) );
            $image_link = get_post_meta( $id, '_bmp_popup_image_link', true );
            if ( ! $image_id ) return;
            $image_html = wp_get_attachment_image( $image_id, 'large', false, [ 'class' => 'bmp-popup-img', 'alt' => esc_attr( get_the_title( $id ) ) ] );
            if ( ! $image_html ) return;
            if ( $image_link ) {
                $content_html = '<a href="' . esc_url( $image_link ) . '" target="_blank" rel="nofollow sponsored noopener">' . $image_html . '</a>';
            } else {
                $content_html = $image_html;
            }
        } elseif ( 'post' === $type ) {
            $post_id_ref  = absint( get_post_meta( $id, '_bmp_popup_post_id', true ) );
            $btn_text     = get_post_meta( $id, '_bmp_popup_post_btn', true ) ?: 'Lire l\'article';
            $post_display = get_post_meta( $id, '_bmp_popup_post_display', true ) ?: 'card';
            if ( ! $post_id_ref ) return;
            $ref_post = get_post( $post_id_ref );
            if ( ! $ref_post || $ref_post->post_status !== 'publish' ) return;

            $thumb = get_the_post_thumbnail( $ref_post->ID, 'medium_large', [ 'style' => 'width:100%;height:auto;display:block;' ] );
            $title = esc_html( $ref_post->post_title );
            $url   = get_permalink( $ref_post->ID );

            if ( $post_display === 'full' ) {
                // Full content mode
                $content_html  = '<div class="bmp-popup-post-full">';
                if ( $thumb ) {
                    $content_html .= '<div class="bmp-popup-post-thumb">' . $thumb . '</div>';
                }
                $content_html .= '<div class="bmp-popup-post-body">';
                $content_html .= '<h2 class="bmp-popup-post-title" style="font-size:24px;margin-bottom:16px;">' . $title . '</h2>';
                $content_html .= '<div class="bmp-popup-post-content">' . apply_filters( 'the_content', $ref_post->post_content ) . '</div>';
                $content_html .= '</div></div>';
            } else {
                // Card mode (default)
                $excerpt = wp_trim_words( wp_strip_all_tags( $ref_post->post_content ), 30, '...' );
                $content_html  = '<div class="bmp-popup-post-card">';
                if ( $thumb ) {
                    $content_html .= '<div class="bmp-popup-post-thumb">' . $thumb . '</div>';
                }
                $content_html .= '<div class="bmp-popup-post-body">';
                $content_html .= '<h3 class="bmp-popup-post-title">' . $title . '</h3>';
                $content_html .= '<p class="bmp-popup-post-excerpt">' . esc_html( $excerpt ) . '</p>';
                $content_html .= '<a href="' . esc_url( $url ) . '" class="bmp-popup-post-btn" style="background:' . esc_attr( $btn_color ) . ';">' . esc_html( $btn_text ) . ' &rarr;</a>';
                $content_html .= '</div></div>';
            }
        } else {
            $html_code = get_post_meta( $id, '_bmp_popup_html', true );
            if ( empty( trim( (string) $html_code ) ) ) return;
            $content_html = '<div class="bmp-popup-html">' . $html_code . '</div>';
        }

        // Position classes
        $pos_class = 'bmp-popup-pos-' . sanitize_html_class( $position );
        $size_class = ( 'center' === $position ) ? 'bmp-popup-size-' . sanitize_html_class( $size ) : '';

        // Overlay only for center position
        $has_overlay = ( 'center' === $position );

        // Close button color class
        $close_class = '';
        if ( $close_color === 'light' ) $close_class = ' bmp-close-light';
        elseif ( $close_color === 'dark' ) $close_class = ' bmp-close-dark';

        // Inner style (bg + radius)
        $inner_style = 'background:' . esc_attr( $bg_color ) . ';border-radius:' . esc_attr( $radius ) . 'px;';
        ?>
        <?php if ( $has_overlay ) : ?>
        <div id="bmp-popup-overlay" class="bmp-popup-overlay" style="display:none;"></div>
        <?php endif; ?>
        <div id="bmp-popup-<?php echo esc_attr( $id ); ?>"
             class="bmp-popup <?php echo esc_attr( $pos_class ); ?> <?php echo esc_attr( $size_class ); ?>"
             role="dialog"
             aria-modal="true"
             aria-hidden="true"
             aria-label="<?php echo esc_attr( get_the_title( $id ) ); ?>"
             data-popup-id="<?php echo esc_attr( $id ); ?>"
             style="display:none;">
            <div class="bmp-popup-inner" style="<?php echo $inner_style; ?>">
                <button class="bmp-popup-close<?php echo $close_class; ?>" aria-label="<?php esc_attr_e( 'Fermer', 'banner-manager-pro' ); ?>">&times;</button>
                <div class="bmp-popup-content">
                    <?php echo $content_html; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue frontend CSS and JS when popups exist for current page.
     */
    public function enqueue_assets() {
        if ( is_admin() ) return;

        $matching = $this->get_matching_popups();
        $popup = $this->select_popup( $matching );
        if ( ! $popup ) return;

        $id = $popup->ID;

        // CSS
        $css_path = BMP_PATH . 'admin/css/bmp-popup-frontend.css';
        wp_enqueue_style( 'bmp-popup-frontend', BMP_URL . 'admin/css/bmp-popup-frontend.css', [], file_exists( $css_path ) ? (string) filemtime( $css_path ) : BMP_VERSION );

        // JS
        $js_path = BMP_PATH . 'admin/js/bmp-popup-frontend.js';
        wp_enqueue_script( 'bmp-popup-frontend', BMP_URL . 'admin/js/bmp-popup-frontend.js', [], file_exists( $js_path ) ? (string) filemtime( $js_path ) : BMP_VERSION, true );

        // Pass popup config to JS
        wp_add_inline_script( 'bmp-popup-frontend', 'window.bmpPopupConfig = ' . wp_json_encode([
            'id'        => $id,
            'trigger'   => get_post_meta( $id, '_bmp_popup_trigger', true ) ?: 'delay',
            'delay'     => absint( get_post_meta( $id, '_bmp_popup_delay', true ) ?: 5 ),
            'scrollPct' => absint( get_post_meta( $id, '_bmp_popup_scroll_pct', true ) ?: 50 ),
            'autoClose' => absint( get_post_meta( $id, '_bmp_popup_auto_close', true ) ?: 0 ),
            'frequency' => get_post_meta( $id, '_bmp_popup_frequency', true ) ?: 'always',
            'position'  => get_post_meta( $id, '_bmp_popup_position', true ) ?: 'center',
        ]) . ';', 'before' );
    }
}
