<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BMP_Popup_CPT {

	const POST_TYPE = 'bmp_popups';

	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_filter( 'manage_bmp_popups_posts_columns', [ $this, 'add_custom_columns' ] );
		add_action( 'manage_bmp_popups_posts_custom_column', [ $this, 'fill_custom_columns' ], 10, 2 );
		add_filter( 'manage_edit-bmp_popups_sortable_columns', [ $this, 'make_columns_sortable' ] );
		add_action( 'restrict_manage_posts', [ $this, 'render_admin_filters' ] );
		add_action( 'pre_get_posts', [ $this, 'apply_admin_filters' ] );
		add_filter( 'post_row_actions', [ $this, 'add_duplicate_action' ], 10, 2 );
		add_action( 'admin_action_bmp_duplicate_popup', [ $this, 'duplicate_popup' ] );
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
			'name'               => 'Popups',
			'singular_name'      => 'Popup',
			'menu_name'          => 'Popups',
			'add_new'            => 'Ajouter un popup',
			'add_new_item'       => 'Ajouter un nouveau popup',
			'edit_item'          => 'Modifier le popup',
			'new_item'           => 'Nouveau popup',
			'view_item'          => 'Voir le popup',
			'search_items'       => 'Rechercher des popups',
			'not_found'          => 'Aucun popup trouv&eacute;',
			'not_found_in_trash' => 'Aucun popup trouv&eacute; dans la corbeille',
		];

		$args = [
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-megaphone',
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => [ 'bmp_popup', 'bmp_popups' ],
			'capabilities'        => self::capabilities(),
			'map_meta_cap'        => false,
			'supports'            => [ 'title' ],
			'rewrite'             => false,
		];

		register_post_type( self::POST_TYPE, $args );
	}

	/* --------- 2. Colonnes personnalisées --------- */
	public function add_custom_columns( $columns ) {
		$new_columns = [];

		$new_columns['cb']        = $columns['cb'];
		$new_columns['title']     = $columns['title'];
		$new_columns['status']    = 'Statut';
		$new_columns['type']      = 'Type';
		$new_columns['position']  = 'Position';
		$new_columns['trigger']   = 'D&eacute;clencheur';
		$new_columns['frequency'] = 'Fr&eacute;quence';
		$new_columns['date']      = $columns['date'];

		return $new_columns;
	}

	public function fill_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'status':
				$status = get_post_meta( $post_id, '_bmp_popup_status', true ) ?: 'active';
				if ( $status === 'active' ) {
					echo '<span style="color: #46b450; font-weight: bold;">&#10003; Actif</span>';
				} else {
					echo '<span style="color: #dc3232; font-weight: bold;">&#10007; Inactif</span>';
				}
				break;

			case 'type':
				$type = get_post_meta( $post_id, '_bmp_popup_type', true ) ?: 'image';
				if ( $type === 'image' ) {
					echo '<span style="color: #0073aa;">&#128444;&#65039; Image</span>';
				} else {
					echo '<span style="color: #0073aa;">&#128196; HTML/JS</span>';
				}
				break;

			case 'position':
				$position = get_post_meta( $post_id, '_bmp_popup_position', true ) ?: 'center';
				$position_labels = [
					'center' => 'Centre',
					'right'  => 'Droite',
					'left'   => 'Gauche',
					'bottom' => 'Bas',
					'top'    => 'Haut',
				];
				echo esc_html( isset( $position_labels[ $position ] ) ? $position_labels[ $position ] : $position );
				break;

			case 'trigger':
				$trigger    = get_post_meta( $post_id, '_bmp_popup_trigger', true ) ?: 'delay';
				$delay      = get_post_meta( $post_id, '_bmp_popup_delay', true ) ?: 2;
				$scroll_pct = get_post_meta( $post_id, '_bmp_popup_scroll_pct', true ) ?: 50;
				if ( $trigger === 'immediate' ) {
					echo esc_html__( 'Imm&eacute;diat', 'banner-manager-pro' );
				} elseif ( $trigger === 'delay' ) {
					echo esc_html( 'D&eacute;lai ' . absint( $delay ) . 's' );
				} elseif ( $trigger === 'scroll' ) {
					echo esc_html( 'Scroll ' . absint( $scroll_pct ) . '%' );
				} else {
					echo 'Exit intent';
				}
				break;

			case 'frequency':
				$frequency = get_post_meta( $post_id, '_bmp_popup_frequency', true ) ?: 'always';
				$frequency_labels = [
					'always'  => 'Chaque visite',
					'session' => 'Par session',
					'day'     => 'Par jour',
					'week'    => 'Par semaine',
					'once'    => 'Une fois',
				];
				echo esc_html( isset( $frequency_labels[ $frequency ] ) ? $frequency_labels[ $frequency ] : $frequency );
				break;
		}
	}

	public function make_columns_sortable( $columns ) {
		$columns['status']    = 'status';
		$columns['type']      = 'type';
		$columns['position']  = 'position';
		$columns['frequency'] = 'frequency';
		return $columns;
	}

	/* --------- 3. Filtres admin --------- */
	public function render_admin_filters( $post_type ) {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		$filters = [
			'bmp_filter_popup_status' => [
				'label' => 'Tous les statuts',
				'meta'  => '_bmp_popup_status',
				'items' => [
					'active'   => 'Actif',
					'inactive' => 'Inactif',
				],
			],
			'bmp_filter_popup_position' => [
				'label' => 'Toutes les positions',
				'meta'  => '_bmp_popup_position',
				'items' => [
					'center' => 'Centre',
					'right'  => 'Droite',
					'left'   => 'Gauche',
					'bottom' => 'Bas',
					'top'    => 'Haut',
				],
			],
			'bmp_filter_popup_device' => [
				'label' => 'Tous les appareils',
				'meta'  => '_bmp_popup_device',
				'items' => [
					'desktop' => 'Desktop',
					'mobile'  => 'Mobile',
					'both'    => 'Les 2',
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
			'bmp_filter_popup_status' => [
				'meta_key' => '_bmp_popup_status',
				'values'   => [ 'active', 'inactive' ],
			],
			'bmp_filter_popup_position' => [
				'meta_key' => '_bmp_popup_position',
				'values'   => [ 'center', 'right', 'left', 'bottom', 'top' ],
			],
			'bmp_filter_popup_device' => [
				'meta_key' => '_bmp_popup_device',
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

		if ( $meta_query ) {
			$query->set( 'meta_query', $meta_query );
		}

		$orderby = $query->get( 'orderby' );
		$meta_key_map = [
			'status'    => '_bmp_popup_status',
			'type'      => '_bmp_popup_type',
			'position'  => '_bmp_popup_position',
			'frequency' => '_bmp_popup_frequency',
		];
		if ( isset( $meta_key_map[ $orderby ] ) ) {
			$query->set( 'meta_key', $meta_key_map[ $orderby ] );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/* --------- 4. Duplication --------- */
	public function add_duplicate_action( $actions, $post ) {
		if ( self::POST_TYPE !== $post->post_type || ! current_user_can( BMP_CAPABILITY ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?action=bmp_duplicate_popup&post=' . absint( $post->ID ) ),
			'bmp_duplicate_popup_' . absint( $post->ID )
		);

		$actions['bmp_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Dupliquer', 'banner-manager-pro' ) . '</a>';
		return $actions;
	}

	public function duplicate_popup() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! $post_id || ! current_user_can( BMP_CAPABILITY ) || ! current_user_can( 'edit_post', $post_id ) || ! wp_verify_nonce( $nonce, 'bmp_duplicate_popup_' . $post_id ) ) {
			wp_die( esc_html__( 'Action non autoris&eacute;e.', 'banner-manager-pro' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Popup introuvable.', 'banner-manager-pro' ) );
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

		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . absint( $new_id ) . '&bmp_popup_duplicated=1' ) );
		exit;
	}
}
