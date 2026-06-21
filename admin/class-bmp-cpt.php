<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BMP_CPT {

	const POST_TYPE = 'bmp_banners';

	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_filter( 'manage_bmp_banners_posts_columns', [ $this, 'add_custom_columns' ] );
		add_action( 'manage_bmp_banners_posts_custom_column', [ $this, 'fill_custom_columns' ], 10, 2 );
		add_filter( 'manage_edit-bmp_banners_sortable_columns', [ $this, 'make_columns_sortable' ] );
		add_action( 'restrict_manage_posts', [ $this, 'render_admin_filters' ] );
		add_action( 'pre_get_posts', [ $this, 'apply_admin_filters' ] );
		add_filter( 'post_row_actions', [ $this, 'add_duplicate_action' ], 10, 2 );
		add_action( 'admin_action_bmp_duplicate_banner', [ $this, 'duplicate_banner' ] );
	}

	public static function capabilities() {
		return [
			'edit_post'              => BMP_CAPABILITY,
			'read_post'              => BMP_CAPABILITY,
			'delete_post'            => BMP_CAPABILITY,
			'edit_posts'             => BMP_CAPABILITY,
			'edit_others_posts'      => BMP_CAPABILITY,
			'publish_posts'          => BMP_CAPABILITY,
			'read_private_posts'     => BMP_CAPABILITY,
			'delete_posts'           => BMP_CAPABILITY,
			'delete_private_posts'   => BMP_CAPABILITY,
			'delete_published_posts' => BMP_CAPABILITY,
			'delete_others_posts'    => BMP_CAPABILITY,
			'edit_private_posts'     => BMP_CAPABILITY,
			'edit_published_posts'   => BMP_CAPABILITY,
			'create_posts'           => BMP_CAPABILITY,
		];
	}

	public static function register() {
		$instance = new self();
		$instance->register_post_type();
	}

	/* --------- 1. Enregistrement du CPT --------- */
	public function register_post_type() {

		$labels = [
			'name'               => 'Bannières',
			'singular_name'      => 'Bannière',
			'menu_name'          => 'Bannières',
			'add_new'            => 'Ajouter une bannière',
			'add_new_item'       => 'Ajouter une nouvelle bannière',
			'edit_item'          => 'Modifier la bannière',
			'new_item'           => 'Nouvelle bannière',
			'view_item'          => 'Voir la bannière',
			'search_items'       => 'Rechercher des bannières',
			'not_found'          => 'Aucune bannière trouvée',
			'not_found_in_trash' => 'Aucune bannière trouvée dans la corbeille',
		];

		$args = [
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-format-image',
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => [ 'bmp_banner', 'bmp_banners' ],
			'capabilities'        => self::capabilities(),
			'map_meta_cap'        => false,
			'supports'            => [ 'title' ],
			'rewrite'             => false,
		];

		register_post_type( self::POST_TYPE, $args );
	}

	/* --------- 3. Colonnes personnalisées --------- */
	public function add_custom_columns( $columns ) {
		$new_columns = [];

		// Garder la colonne titre en premier
		$new_columns['cb'] = $columns['cb'];
		$new_columns['title'] = $columns['title'];

		// Ajouter nos colonnes personnalisées
		$new_columns['status'] = 'Statut';
		$new_columns['type'] = 'Type';
		$new_columns['positions'] = 'Emplacements';
		$new_columns['author'] = 'Auteur';
		$new_columns['date'] = $columns['date'];

		return $new_columns;
	}

	public function fill_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'status':
				$status = get_post_meta( $post_id, '_bmp_status', true ) ?: 'active';
				if ( $status === 'active' ) {
					echo '<span style="color: #46b450; font-weight: bold;">&#10003; Actif</span>';
				} else {
					echo '<span style="color: #dc3232; font-weight: bold;">&#10007; Inactif</span>';
				}
				break;

			case 'type':
				$type = get_post_meta( $post_id, '_bmp_type', true ) ?: 'image';
				if ( $type === 'image' ) {
					$image_id = get_post_meta( $post_id, '_bmp_image_id', true );
					if ( $image_id ) {
						echo '<span style="color: #0073aa;">Image</span>';
						echo '<br><small>' . wp_get_attachment_image( $image_id, [ 50, 50 ] ) . '</small>';
					} else {
						echo '<span style="color: #0073aa;">Image</span>';
						echo '<br><small style="color: #dc3232;">Aucune image</small>';
					}
				} else {
					echo '<span style="color: #0073aa;">HTML/JS</span>';
					$html = get_post_meta( $post_id, '_bmp_html', true );
					if ( $html ) {
						echo '<br><small style="color: #46b450;">Code présent</small>';
					} else {
						echo '<br><small style="color: #dc3232;">Aucun code</small>';
					}
				}
				break;

			case 'positions':
				$positions = (array) get_post_meta( $post_id, '_bmp_positions', true );
				if ( ! empty( $positions ) ) {
					$position_labels = [
						'header' => 'Haut de page',
						'after_header' => 'Après header',
						'footer' => 'Bas de page',
						'sidebar1' => 'Sidebar 1',
						'sidebar2' => 'Sidebar 2',
						'in_article' => 'Dans articles',
						'in_listing' => 'Entre articles'
					];
					$display_positions = [];
					foreach ( $positions as $position ) {
						if ( isset( $position_labels[ $position ] ) ) {
							$display_positions[] = $position_labels[ $position ];
						}
					}
					echo '<small>' . esc_html( implode( ', ', $display_positions ) ) . '</small>';
				} else {
					echo '<small style="color: #dc3232;">Aucun emplacement</small>';
				}
				break;

			case 'author':
				$author_id = get_post_field( 'post_author', $post_id );
				$author = get_userdata( $author_id );
				if ( $author ) {
					echo esc_html( $author->display_name );
				}
				break;
		}
	}

	public function make_columns_sortable( $columns ) {
		$columns['status'] = 'status';
		$columns['type'] = 'type';
		$columns['author'] = 'author';
		return $columns;
	}

	public function render_admin_filters( $post_type ) {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		$filters = [
			'bmp_filter_status' => [
				'label' => 'Tous les statuts',
				'meta'  => '_bmp_status',
				'items' => [
					'active'   => 'Actif',
					'inactive' => 'Inactif',
				],
			],
			'bmp_filter_type' => [
				'label' => 'Tous les types',
				'meta'  => '_bmp_type',
				'items' => [
					'image' => 'Image',
					'html'  => 'HTML/JS',
				],
			],
			'bmp_filter_device' => [
				'label' => 'Tous les appareils',
				'meta'  => '_bmp_device',
				'items' => [
					'desktop' => 'Desktop',
					'mobile'  => 'Mobile',
					'both'    => 'Les 2',
				],
			],
			'bmp_filter_position' => [
				'label' => 'Tous les emplacements',
				'meta'  => '_bmp_positions',
				'items' => [
					'header'       => 'Haut de page',
					'after_header' => 'Après header',
					'footer'       => 'Bas de page',
					'sidebar1'     => 'Sidebar 1',
					'sidebar2'     => 'Sidebar 2',
					'in_article'   => 'Dans articles',
					'in_listing'   => 'Entre articles',
				],
			],
		];

		foreach ( $filters as $query_key => $filter ) {
			$current = isset( $_GET[ $query_key ] ) ? sanitize_key( wp_unslash( $_GET[ $query_key ] ) ) : '';
			$current = array_key_exists( $current, $filter['items'] ) ? $current : '';
			echo '<select name="' . esc_attr( $query_key ) . '">';
			echo '<option value="">' . esc_html( $filter['label'] ) . '</option>';
			foreach ( $filter['items'] as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}
	}

	public function apply_admin_filters( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta_query = (array) $query->get( 'meta_query' );

		$allowed_filters = [
			'bmp_filter_status' => [
				'meta_key' => '_bmp_status',
				'values'   => [ 'active', 'inactive' ],
			],
			'bmp_filter_type' => [
				'meta_key' => '_bmp_type',
				'values'   => [ 'image', 'html' ],
			],
			'bmp_filter_device' => [
				'meta_key' => '_bmp_device',
				'values'   => [ 'desktop', 'mobile', 'both' ],
			],
		];

		foreach ( $allowed_filters as $query_key => $filter ) {
			$value = isset( $_GET[ $query_key ] ) ? sanitize_key( wp_unslash( $_GET[ $query_key ] ) ) : '';
			if ( $value && in_array( $value, $filter['values'], true ) ) {
				$meta_query[] = [
					'key'     => $filter['meta_key'],
					'value'   => $value,
					'compare' => '=',
				];
			}
		}

		$position = isset( $_GET['bmp_filter_position'] ) ? sanitize_key( wp_unslash( $_GET['bmp_filter_position'] ) ) : '';
		if ( $position && in_array( $position, [ 'header', 'footer', 'sidebar1', 'sidebar2', 'in_article', 'in_listing' ], true ) ) {
			$meta_query[] = [
				'key'     => '_bmp_positions',
				'value'   => '"' . $position . '"',
				'compare' => 'LIKE',
			];
		}

		if ( $meta_query ) {
			$query->set( 'meta_query', $meta_query );
		}

		$orderby = $query->get( 'orderby' );
		if ( in_array( $orderby, [ 'status', 'type' ], true ) ) {
			$query->set( 'meta_key', 'status' === $orderby ? '_bmp_status' : '_bmp_type' );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	public function add_duplicate_action( $actions, $post ) {
		if ( self::POST_TYPE !== $post->post_type || ! current_user_can( BMP_CAPABILITY ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?action=bmp_duplicate_banner&post=' . absint( $post->ID ) ),
			'bmp_duplicate_banner_' . absint( $post->ID )
		);

		$actions['bmp_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Dupliquer', 'banner-manager-pro' ) . '</a>';
		return $actions;
	}

	public function duplicate_banner() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! $post_id || ! current_user_can( BMP_CAPABILITY ) || ! current_user_can( 'edit_post', $post_id ) || ! wp_verify_nonce( $nonce, 'bmp_duplicate_banner_' . $post_id ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'banner-manager-pro' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Bannière introuvable.', 'banner-manager-pro' ) );
		}

		$new_id = wp_insert_post(
			[
				'post_type'   => self::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => sprintf( '%s (copie)', $post->post_title ),
				'post_author' => get_current_user_id(),
			],
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		foreach ( get_post_meta( $post_id ) as $meta_key => $values ) {
			if ( '_' !== substr( $meta_key, 0, 1 ) || '_edit_lock' === $meta_key || '_edit_last' === $meta_key ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $new_id, $meta_key, maybe_unserialize( $value ) );
			}
		}

		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . absint( $new_id ) . '&bmp_duplicated=1' ) );
		exit;
	}
}
