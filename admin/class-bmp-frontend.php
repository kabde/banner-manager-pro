<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe pour gérer l'affichage des bannières sur le frontend
 */
class BMP_Frontend {

	private $banner_cache = [];

	public function __construct() {
		add_action( 'wp_body_open', [ $this, 'display_header_banners' ] );
		add_action( 'wp_footer', [ $this, 'display_header_banners_fallback' ], 1 );
		add_action( 'get_footer', [ $this, 'display_footer_banners' ] );
		add_action( 'get_sidebar', [ $this, 'display_sidebar_banners' ] );
		add_action( 'the_content', [ $this, 'display_in_article_banners' ] );
		add_action( 'loop_end', [ $this, 'display_listing_banners' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_shortcode( 'bmp_banner', [ $this, 'shortcode_banner' ] );
		if ( isset( $_GET['debug_bmp'] ) ) {
			add_action( 'wp_head', [ $this, 'display_global_debug' ] );
		}
	}

	/**
	 * Afficher les bannières dans l'en-tête
	 */
	public function display_header_banners() {
		static $already_displayed = false;
		if ($already_displayed) return;
		$already_displayed = true;

		// Emplacement : header
		$banners = $this->get_banners_for_location( 'header' );
		if ( ! empty( $banners ) ) {
			echo '<div class="bmp-banner-container bmp-header-banners">';
			foreach ( $banners as $banner ) {
				echo $this->render_banner( $banner );
			}
			echo '</div>';
		}
	}

	/**
	 * Afficher les bannières dans le pied de page
	 */
	public function display_footer_banners() {
		// Emplacement : footer
		$banners = $this->get_banners_for_location( 'footer' );
		if ( ! empty( $banners ) ) {
			echo '<div class="bmp-banner-container bmp-footer-banners">';
			foreach ( $banners as $banner ) {
				echo $this->render_banner( $banner );
			}
			echo '</div>';
		}
	}

	/**
	 * Afficher les bannières dans les sidebars
	 */
	public function display_sidebar_banners() {
		// Emplacement : sidebar1
		$banners1 = $this->get_banners_for_location( 'sidebar1' );
		if ( ! empty( $banners1 ) ) {
			echo '<div class="bmp-banner-container bmp-sidebar-banners bmp-sidebar1-banners">';
			foreach ( $banners1 as $banner ) {
				echo $this->render_banner( $banner );
			}
			echo '</div>';
		}
		// Emplacement : sidebar2
		$banners2 = $this->get_banners_for_location( 'sidebar2' );
		if ( ! empty( $banners2 ) ) {
			echo '<div class="bmp-banner-container bmp-sidebar-banners bmp-sidebar2-banners">';
			foreach ( $banners2 as $banner ) {
				echo $this->render_banner( $banner );
			}
			echo '</div>';
		}
	}

	/**
	 * Afficher les bannières dans les articles
	 */
	public function display_in_article_banners( $content ) {
		// Emplacement : in_article
		if ( is_single() && in_the_loop() ) {
			$banners = $this->get_banners_for_location( 'in_article' );
			if ( ! empty( $banners ) ) {
				$banner_html = '<div class="bmp-banner-container bmp-in-article-banners">';
				foreach ( $banners as $banner ) {
					$banner_html .= $this->render_banner( $banner );
				}
				$banner_html .= '</div>';
				// Insérer après le premier paragraphe non vide
				$content = $this->insert_banner_in_content( $content, $banner_html );
			}
		}
		return $content;
	}

	/**
	 * Afficher les bannières entre les articles dans les listes
	 */
	public function display_listing_banners() {
		// Emplacement : in_listing
		if ( is_home() || is_archive() || is_search() ) {
			$banners = $this->get_banners_for_location( 'in_listing' );
			if ( ! empty( $banners ) ) {
				echo '<div class="bmp-banner-container bmp-listing-banners">';
				foreach ( $banners as $banner ) {
					echo $this->render_banner( $banner );
				}
				echo '</div>';
			}
		}
	}

	/**
	 * Récupérer les bannières pour un emplacement donné
	 */
	private function get_banners_for_location( $locations ) {
		$allowed_locations = [ 'header', 'footer', 'sidebar1', 'sidebar2', 'in_article', 'in_listing' ];
		$locations         = array_values( array_intersect( array_unique( array_map( 'sanitize_key', (array) $locations ) ), $allowed_locations ) );
		if ( empty( $locations ) ) {
			return [];
		}

		$device    = wp_is_mobile() ? 'mobile' : 'desktop';
		$cache_key = $device . ':' . implode( ',', $locations );

		if ( isset( $this->banner_cache[ $cache_key ] ) ) {
			return $this->banner_cache[ $cache_key ];
		}

		$location_query = [ 'relation' => 'OR' ];
		foreach ( $locations as $location ) {
			$location_query[] = [
				'key'     => '_bmp_positions',
				'value'   => '"' . $location . '"',
				'compare' => 'LIKE',
			];
		}

		$banners = get_posts([
			'post_type'      => BMP_CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'orderby'        => 'menu_order date',
			'order'          => 'DESC',
			'meta_query'     => [
				[
					'key'     => '_bmp_status',
					'value'   => 'active',
					'compare' => '='
				],
				[
					'relation' => 'OR',
					[
						'key'     => '_bmp_device',
						'value'   => 'both',
						'compare' => '='
					],
					[
						'key'     => '_bmp_device',
						'value'   => $device,
						'compare' => '='
					]
				],
				$location_query,
			]
		]);

		$filtered = [];
		foreach ($banners as $banner) {
			$positions = get_post_meta($banner->ID, '_bmp_positions', true);
			if (is_array($positions) && array_intersect($locations, $positions)) {
				$filtered[] = $banner;
			}
		}

		$this->banner_cache[ $cache_key ] = $filtered;
		return $filtered;
	}

	/**
	 * Rendre une bannière
	 */
	private function render_banner( $banner ) {
		$type = get_post_meta( $banner->ID, '_bmp_type', true );
		$type = in_array( $type, [ 'image', 'html' ], true ) ? $type : 'image';
		$html = '<div class="bmp-banner bmp-banner-' . esc_attr( $type ) . '" data-banner-id="' . esc_attr( $banner->ID ) . '">';
		if ( $type === 'image' ) {
			$html .= $this->render_image_banner( $banner );
		} else {
			$html .= $this->render_html_banner( $banner );
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Rendre une bannière image
	 */
	private function render_image_banner( $banner ) {
		$image_id = get_post_meta( $banner->ID, '_bmp_image_id', true );
		$image_link = get_post_meta( $banner->ID, '_bmp_image_link', true );
		$dimensions = get_post_meta( $banner->ID, '_bmp_dimensions', true );

		if ( ! $image_id ) {
			return '';
		}

		// Déterminer les dimensions
		$width = '';
		$height = '';
		$style = '';

		if ( $dimensions === 'custom' ) {
			$width = get_post_meta( $banner->ID, '_bmp_custom_width', true );
			$height = get_post_meta( $banner->ID, '_bmp_custom_height', true );
		} elseif ( preg_match( '/^([0-9]{2,4})x([0-9]{2,4})$/', $dimensions, $matches ) ) {
			$width  = $matches[1];
			$height = $matches[2];
		}

		if ( $width && $height ) {
			$style = 'width: ' . intval( $width ) . 'px; height: ' . intval( $height ) . 'px;';
		}

		$image_html = wp_get_attachment_image( $image_id, 'full', false, [ 'style' => $style ] );
		if ( ! $image_html ) {
			return '';
		}

		if ( $image_link ) {
			return '<a href="' . esc_url( $image_link ) . '" target="_blank" rel="nofollow sponsored noopener">' . $image_html . '</a>';
		} else {
			return $image_html;
		}
	}

	/**
	 * Rendre une bannière HTML/JS
	 */
	private function render_html_banner( $banner ) {
		$html_code = get_post_meta( $banner->ID, '_bmp_html', true );

		if ( empty( $html_code ) ) {
			return '';
		}

		return '<div class="bmp-html-banner">' . $html_code . '</div>';
	}

	/**
	 * Insérer une bannière dans le contenu d'un article
	 */
	private function insert_banner_in_content( $content, $banner_html ) {
		// Diviser le contenu en paragraphes
		$paragraphs = explode( '</p>', $content );

		// Insérer après le premier paragraphe non vide
		$inserted = false;
		foreach ( $paragraphs as $key => $paragraph ) {
			if ( ! $inserted && ! empty( trim( strip_tags( $paragraph ) ) ) ) {
				$paragraphs[ $key ] = $paragraph . '</p>' . $banner_html;
				$inserted = true;
				break;
			}
		}

		return implode( '</p>', $paragraphs );
	}

	/**
	 * Shortcode pour afficher une bannière manuellement
	 */
	public function shortcode_banner( $atts ) {
		$atts = shortcode_atts( [
			'location' => 'header',
			'limit' => 1
		], $atts, 'bmp_banner' );

		$allowed_locations = [ 'header', 'footer', 'sidebar1', 'sidebar2', 'in_article', 'in_listing' ];
		$location = sanitize_key( $atts['location'] );
		if ( ! in_array( $location, $allowed_locations, true ) ) {
			return '';
		}

		$banners = $this->get_banners_for_location( $location );

		if ( empty( $banners ) ) {
			return '';
		}

		// Limiter le nombre de bannières
		$limit = max( 1, min( 20, absint( $atts['limit'] ) ) );
		$banners = array_slice( $banners, 0, $limit );

		$html = '<div class="bmp-banner-container bmp-shortcode-banners">';
		foreach ( $banners as $banner ) {
			$html .= $this->render_banner( $banner );
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Afficher le debug global
	 */
	public function display_global_debug() {
		if ( ! current_user_can( BMP_CAPABILITY ) ) {
			return;
		}

		$all_banners = get_posts( [
			'post_type' => 'bmp_banners',
			'post_status' => 'publish',
			'posts_per_page' => -1
		] );
		echo '<div style="background: #0073aa; color: white; padding: 15px; margin: 10px 0; border-radius: 4px; font-family: monospace; font-size: 13px; position: fixed; top: 0; left: 0; right: 0; z-index: 9999; max-height: 350px; overflow-y: auto;">';
		echo '<strong>BMP DEBUG - Liste des bannières (' . count( $all_banners ) . ')</strong><br><br>';
		if ( ! empty( $all_banners ) ) {
			foreach ( $all_banners as $banner ) {
				$status = get_post_meta( $banner->ID, '_bmp_status', true );
				$type = get_post_meta( $banner->ID, '_bmp_type', true );
				$positions = get_post_meta( $banner->ID, '_bmp_positions', true );
				$device = get_post_meta( $banner->ID, '_bmp_device', true );
				$image_id = get_post_meta( $banner->ID, '_bmp_image_id', true );
				$html_code = get_post_meta( $banner->ID, '_bmp_html', true );
				echo '<strong>ID ' . $banner->ID . ':</strong> ' . esc_html($banner->post_title) . '<br>';
				echo '- Statut: ' . esc_html($status) . ' | Type: ' . esc_html($type) . ' | Device: ' . esc_html($device) . '<br>';
				echo '- Positions: ' . esc_html( is_array($positions) ? implode(", ", $positions) : $positions ) . '<br>';
				if ( $type === 'image' ) {
					echo '- Image ID: ' . esc_html($image_id) . '<br>';
				} else {
					echo '- HTML Code: ' . ( ! empty( $html_code ) ? 'Présent (' . strlen( $html_code ) . ' chars)' : 'Vide' ) . '<br>';
				}
				echo '<br>';
			}
		} else {
			echo 'Aucune bannière trouvée.';
		}
		echo '</div>';
	}

	/**
	 * Charger les assets CSS/JS
	 */
	public function enqueue_assets() {
		if ( ! $this->has_frontend_banners() ) {
			return;
		}

		wp_enqueue_style(
			'bmp-frontend',
			BMP_URL . 'admin/css/bmp-frontend.css',
			[],
			BMP_VERSION
		);
	}

	private function has_frontend_banners() {
		foreach ( [ 'header', 'footer', 'sidebar1', 'sidebar2', 'in_article', 'in_listing' ] as $location ) {
			if ( ! empty( $this->get_banners_for_location( $location ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fallback pour les thèmes sans wp_body_open, sans injecter de HTML dans wp_head.
	 */
	public function display_header_banners_fallback() {
		if ( ! did_action( 'wp_body_open' ) ) {
			$this->display_header_banners();
		}
	}
}
