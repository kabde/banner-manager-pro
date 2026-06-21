<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe pour g&eacute;rer les m&eacute;taboxes des popups
 */
class BMP_Popup_Meta {

	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'add_boxes' ] );
		add_action( 'save_post_bmp_popups', [ $this, 'save' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
	}

	/* --------- 1. M&eacute;tabox --------- */
	public function add_boxes() {
		if ( ! current_user_can( BMP_CAPABILITY ) ) {
			return;
		}

		add_meta_box(
			'bmp_popup_details',
			'D&eacute;tails du popup',
			[ $this, 'render_box' ],
			'bmp_popups',
			'normal',
			'high'
		);
	}

	public function render_box( $post ) {
		$status     = get_post_meta( $post->ID, '_bmp_popup_status',     true ) ?: 'active';
		$type       = get_post_meta( $post->ID, '_bmp_popup_type',       true ) ?: 'image';
		$image_id   = get_post_meta( $post->ID, '_bmp_popup_image_id',   true );
		$image_link = get_post_meta( $post->ID, '_bmp_popup_image_link', true );
		$html_code  = get_post_meta( $post->ID, '_bmp_popup_html',       true );
		$selected_post = get_post_meta( $post->ID, '_bmp_popup_post_id',  true ) ?: '';
		$post_button   = get_post_meta( $post->ID, '_bmp_popup_post_btn', true ) ?: 'Lire l\'article';
		$position   = get_post_meta( $post->ID, '_bmp_popup_position',   true ) ?: 'center';
		$size       = get_post_meta( $post->ID, '_bmp_popup_size',       true ) ?: 'medium';
		$trigger    = get_post_meta( $post->ID, '_bmp_popup_trigger',    true ) ?: 'delay';
		$delay      = get_post_meta( $post->ID, '_bmp_popup_delay',      true ) ?: 5;
		$scroll_pct = get_post_meta( $post->ID, '_bmp_popup_scroll_pct', true ) ?: 50;
		$device     = get_post_meta( $post->ID, '_bmp_popup_device',     true ) ?: 'both';
		$display_on = get_post_meta( $post->ID, '_bmp_popup_display_on', true ) ?: 'all';
		$frequency  = get_post_meta( $post->ID, '_bmp_popup_frequency',  true ) ?: 'always';
		$target_post     = get_post_meta( $post->ID, '_bmp_popup_target_post', true ) ?: '';
		$target_page     = get_post_meta( $post->ID, '_bmp_popup_target_page', true ) ?: '';
		$target_category = get_post_meta( $post->ID, '_bmp_popup_target_category', true ) ?: '';
		$auto_close      = get_post_meta( $post->ID, '_bmp_popup_auto_close', true ) ?: 0;
		$priority        = get_post_meta( $post->ID, '_bmp_popup_priority', true ) ?: 10;
		$warnings   = $this->get_configuration_warnings( $post->ID );

		wp_nonce_field( 'bmp_save_popup', 'bmp_popup_nonce' );
		?>
		<?php if ( $warnings ) : ?>
			<div class="notice notice-warning inline bmp-config-warning">
				<p><strong><?php esc_html_e( 'Configuration &agrave; compl&eacute;ter', 'banner-manager-pro' ); ?></strong></p>
				<ul>
					<?php foreach ( $warnings as $warning ) : ?>
						<li><?php echo esc_html( $warning ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="bmp-admin-layout">
			<div class="bmp-admin-main">
				<section class="bmp-admin-section">
					<h3><?php esc_html_e( 'Status', 'banner-manager-pro' ); ?></h3>
					<div class="bmp-field-grid">
						<label><input type="radio" name="bmp_popup_status" value="active" <?php checked( $status, 'active' ); ?>/> <?php esc_html_e( 'Actif', 'banner-manager-pro' ); ?></label>
						<label><input type="radio" name="bmp_popup_status" value="inactive" <?php checked( $status, 'inactive' ); ?>/> <?php esc_html_e( 'Inactif', 'banner-manager-pro' ); ?></label>
					</div>
				</section>

				<section class="bmp-admin-section">
					<h3><?php esc_html_e( 'Creative', 'banner-manager-pro' ); ?></h3>
					<div class="bmp-field-grid">
						<label><input type="radio" name="bmp_popup_type" value="image" <?php checked( $type, 'image' ); ?>/> <?php esc_html_e( 'Image', 'banner-manager-pro' ); ?></label>
						<label><input type="radio" name="bmp_popup_type" value="html" <?php checked( $type, 'html' ); ?>/> <?php esc_html_e( 'HTML/JS', 'banner-manager-pro' ); ?></label>
						<label><input type="radio" name="bmp_popup_type" value="post" <?php checked( $type, 'post' ); ?>/> <?php esc_html_e( 'Article / Page', 'banner-manager-pro' ); ?></label>
					</div>

					<div id="bmp-popup-image-fields" style="<?php echo ( $type === 'image' ) ? '' : 'display:none'; ?>">
						<input type="hidden" name="bmp_popup_image_id" id="bmp_popup_image_id" value="<?php echo esc_attr( $image_id ); ?>">
						<p>
							<button type="button" class="button" id="bmp_popup_pick_image"><?php esc_html_e( 'Choisir une image', 'banner-manager-pro' ); ?></button>
							<span id="bmp_popup_image_preview">
								<?php if ( $image_id ) echo wp_get_attachment_image( $image_id, [ 150, 150 ] ); ?>
							</span>
						</p>
						<p class="description bmp-empty-state" id="bmp-popup-image-empty" <?php echo $image_id ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Aucune image s&eacute;lectionn&eacute;e.', 'banner-manager-pro' ); ?></p>
						<p>
							<button type="button" class="button button-link-delete" id="bmp_popup_remove_image" <?php echo ! $image_id ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Supprimer l\'image', 'banner-manager-pro' ); ?></button>
						</p>
						<p><label><?php esc_html_e( 'Lien cible', 'banner-manager-pro' ); ?><br>
							<input type="url" name="bmp_popup_image_link" id="bmp_popup_image_link" style="width:100%" value="<?php echo esc_attr( $image_link ); ?>">
						</label></p>
					</div>

					<div id="bmp-popup-html-fields" style="<?php echo ( $type === 'html' ) ? '' : 'display:none'; ?>">
						<p><label><?php esc_html_e( 'Code HTML / JavaScript &agrave; ins&eacute;rer', 'banner-manager-pro' ); ?><br>
							<textarea name="bmp_popup_html" id="bmp_popup_html" rows="10" style="width:100%; font-family: monospace; font-size: 12px;" placeholder="<?php esc_attr_e( 'Ins&eacute;rez votre code HTML, JavaScript, ou contenu popup ici...', 'banner-manager-pro' ); ?>"><?php echo esc_textarea( $html_code ); ?></textarea>
						</label></p>
						<p class="description"><?php esc_html_e( 'Le code HTML/JS doit &ecirc;tre r&eacute;serv&eacute; aux administrateurs de confiance.', 'banner-manager-pro' ); ?></p>
					</div>

					<div id="bmp-popup-post-fields" style="<?php echo ( $type === 'post' ) ? '' : 'display:none'; ?>">
						<?php
						$post_display = get_post_meta( $post->ID, '_bmp_popup_post_display', true ) ?: 'card';
						?>
						<table class="form-table" style="margin:0;">
							<tr>
								<th style="padding:8px 0;width:140px;"><?php esc_html_e( 'Article / Page', 'banner-manager-pro' ); ?></th>
								<td style="padding:8px 0;">
									<input type="text" id="bmp-post-search" placeholder="🔍 Rechercher un article ou une page..." style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;margin-bottom:6px;font-size:13px;">
									<select name="bmp_popup_post_id" id="bmp_popup_post_id" style="width:100%;max-height:200px;" size="8">
										<?php
										// Articles
										$articles = get_posts( [ 'post_type' => 'post', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ] );
										if ( $articles ) :
										?>
										<optgroup label="📝 Articles">
											<?php foreach ( $articles as $ap ) : ?>
												<option value="<?php echo esc_attr( $ap->ID ); ?>" <?php selected( $selected_post, $ap->ID ); ?>><?php echo esc_html( $ap->post_title ?: '(sans titre)' ); ?></option>
											<?php endforeach; ?>
										</optgroup>
										<?php endif; ?>
										<?php
										// Pages
										$pages = get_posts( [ 'post_type' => 'page', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ] );
										if ( $pages ) :
										?>
										<optgroup label="📄 Pages">
											<?php foreach ( $pages as $ap ) : ?>
												<option value="<?php echo esc_attr( $ap->ID ); ?>" <?php selected( $selected_post, $ap->ID ); ?>><?php echo esc_html( $ap->post_title ?: '(sans titre)' ); ?></option>
											<?php endforeach; ?>
										</optgroup>
										<?php endif; ?>
										<?php
										// Custom Post Types
										$cpt_types = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
										foreach ( $cpt_types as $cpt ) :
											if ( in_array( $cpt->name, [ 'bmp_banners', 'bmp_popups', 'srp_redirect' ], true ) ) continue;
											$cpt_posts = get_posts( [ 'post_type' => $cpt->name, 'posts_per_page' => 50, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ] );
											if ( $cpt_posts ) :
										?>
										<optgroup label="<?php echo esc_attr( $cpt->labels->name ); ?>">
											<?php foreach ( $cpt_posts as $ap ) : ?>
												<option value="<?php echo esc_attr( $ap->ID ); ?>" <?php selected( $selected_post, $ap->ID ); ?>><?php echo esc_html( $ap->post_title ?: '(sans titre)' ); ?></option>
											<?php endforeach; ?>
										</optgroup>
										<?php endif; endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th style="padding:8px 0;"><?php esc_html_e( 'Mode d\'affichage', 'banner-manager-pro' ); ?></th>
								<td style="padding:8px 0;">
									<label style="display:block;margin-bottom:6px;">
										<input type="radio" name="bmp_popup_post_display" value="card" <?php checked( $post_display, 'card' ); ?>>
										<strong>Carte</strong> — Image + titre + extrait + bouton "Lire la suite"
									</label>
									<label style="display:block;">
										<input type="radio" name="bmp_popup_post_display" value="full" <?php checked( $post_display, 'full' ); ?>>
										<strong>Complet</strong> — Tout le contenu de l'article dans le popup
									</label>
								</td>
							</tr>
							<tr id="bmp-popup-btn-row" style="<?php echo $post_display === 'full' ? 'display:none;' : ''; ?>">
								<th style="padding:8px 0;"><?php esc_html_e( 'Texte du bouton', 'banner-manager-pro' ); ?></th>
								<td style="padding:8px 0;">
									<input type="text" name="bmp_popup_post_btn" value="<?php echo esc_attr( $post_button ); ?>" style="width:100%;" placeholder="Lire l'article">
								</td>
							</tr>
						</table>
					</div>
				</section>

				<!-- Design section (for all types) -->
				<section class="bmp-admin-section">
					<h3><?php esc_html_e( 'Design', 'banner-manager-pro' ); ?></h3>
					<?php
					$popup_bg    = get_post_meta( $post->ID, '_bmp_popup_bg_color', true ) ?: '#ffffff';
					$popup_btn_c = get_post_meta( $post->ID, '_bmp_popup_btn_color', true ) ?: '#111827';
					$popup_close_c = get_post_meta( $post->ID, '_bmp_popup_close_color', true ) ?: '';
					$popup_radius = get_post_meta( $post->ID, '_bmp_popup_radius', true ) ?: '16';
					?>
					<table class="form-table" style="margin:0;">
						<tr>
							<th style="padding:8px 0;width:140px;">Fond du popup</th>
							<td style="padding:8px 0;">
								<input type="color" name="bmp_popup_bg_color" value="<?php echo esc_attr( $popup_bg ); ?>" style="width:50px;height:32px;padding:0;border:1px solid #d1d5db;border-radius:4px;cursor:pointer;">
								<code style="margin-left:8px;font-size:12px;color:#6b7280;"><?php echo esc_html( $popup_bg ); ?></code>
							</td>
						</tr>
						<tr id="bmp-popup-btn-color-row" style="<?php echo ( $type !== 'post' ) ? 'display:none;' : ''; ?>">
							<th style="padding:8px 0;">Bouton CTA</th>
							<td style="padding:8px 0;">
								<input type="color" name="bmp_popup_btn_color" value="<?php echo esc_attr( $popup_btn_c ); ?>" style="width:50px;height:32px;padding:0;border:1px solid #d1d5db;border-radius:4px;cursor:pointer;">
								<code style="margin-left:8px;font-size:12px;color:#6b7280;"><?php echo esc_html( $popup_btn_c ); ?></code>
							</td>
						</tr>
						<tr>
							<th style="padding:8px 0;">Bouton fermer</th>
							<td style="padding:8px 0;">
								<select name="bmp_popup_close_color">
									<option value="" <?php selected( $popup_close_c, '' ); ?>>Auto (clair sur fond sombre, sombre sur fond clair)</option>
									<option value="light" <?php selected( $popup_close_c, 'light' ); ?>>Clair (blanc)</option>
									<option value="dark" <?php selected( $popup_close_c, 'dark' ); ?>>Sombre (noir)</option>
								</select>
							</td>
						</tr>
						<tr>
							<th style="padding:8px 0;">Border radius</th>
							<td style="padding:8px 0;">
								<input type="range" name="bmp_popup_radius" min="0" max="24" value="<?php echo esc_attr( $popup_radius ); ?>" style="width:150px;vertical-align:middle;">
								<span style="margin-left:8px;font-size:13px;color:#374151;"><?php echo esc_html( $popup_radius ); ?>px</span>
							</td>
						</tr>
					</table>
				</section>

				<section class="bmp-admin-section">
					<h3><?php esc_html_e( 'Position', 'banner-manager-pro' ); ?></h3>
					<div class="bmp-field-grid bmp-position-grid">
						<label class="bmp-position-card">
							<input type="radio" name="bmp_popup_position" value="center" <?php checked( $position, 'center' ); ?>>
							<span class="bmp-position-icon">&#8865;</span>
							<span><?php esc_html_e( 'Centre', 'banner-manager-pro' ); ?></span>
						</label>
						<label class="bmp-position-card">
							<input type="radio" name="bmp_popup_position" value="right" <?php checked( $position, 'right' ); ?>>
							<span class="bmp-position-icon">&#8866;</span>
							<span><?php esc_html_e( 'Droite', 'banner-manager-pro' ); ?></span>
						</label>
						<label class="bmp-position-card">
							<input type="radio" name="bmp_popup_position" value="left" <?php checked( $position, 'left' ); ?>>
							<span class="bmp-position-icon">&#8867;</span>
							<span><?php esc_html_e( 'Gauche', 'banner-manager-pro' ); ?></span>
						</label>
						<label class="bmp-position-card">
							<input type="radio" name="bmp_popup_position" value="bottom" <?php checked( $position, 'bottom' ); ?>>
							<span class="bmp-position-icon">&#8869;</span>
							<span><?php esc_html_e( 'Bas', 'banner-manager-pro' ); ?></span>
						</label>
						<label class="bmp-position-card">
							<input type="radio" name="bmp_popup_position" value="top" <?php checked( $position, 'top' ); ?>>
							<span class="bmp-position-icon">&#8868;</span>
							<span><?php esc_html_e( 'Haut', 'banner-manager-pro' ); ?></span>
						</label>
					</div>
				</section>

				<section class="bmp-admin-section" id="bmp-popup-size-section" style="<?php echo ( $position !== 'center' ) ? 'display:none;' : ''; ?>">
					<h3><?php esc_html_e( 'Taille', 'banner-manager-pro' ); ?></h3>
					<select name="bmp_popup_size" style="width:100%">
						<option value="small" <?php selected( $size, 'small' ); ?>><?php esc_html_e( 'Petit (400px)', 'banner-manager-pro' ); ?></option>
						<option value="medium" <?php selected( $size, 'medium' ); ?>><?php esc_html_e( 'Moyen (600px)', 'banner-manager-pro' ); ?></option>
						<option value="large" <?php selected( $size, 'large' ); ?>><?php esc_html_e( 'Grand (800px)', 'banner-manager-pro' ); ?></option>
						<option value="fullscreen" <?php selected( $size, 'fullscreen' ); ?>><?php esc_html_e( 'Plein &eacute;cran', 'banner-manager-pro' ); ?></option>
					</select>
				</section>

				<section class="bmp-admin-section">
					<h3><?php esc_html_e( 'D&eacute;clenchement', 'banner-manager-pro' ); ?></h3>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'D&eacute;clencheur', 'banner-manager-pro' ); ?></th>
							<td>
								<select name="bmp_popup_trigger" id="bmp_popup_trigger">
									<option value="immediate" <?php selected( $trigger, 'immediate' ); ?>><?php esc_html_e( 'Imm&eacute;diat (d&egrave;s le chargement)', 'banner-manager-pro' ); ?></option>
									<option value="delay" <?php selected( $trigger, 'delay' ); ?>><?php esc_html_e( 'Apr&egrave;s un d&eacute;lai', 'banner-manager-pro' ); ?></option>
									<option value="scroll" <?php selected( $trigger, 'scroll' ); ?>><?php esc_html_e( 'Au scroll', 'banner-manager-pro' ); ?></option>
									<option value="exit_intent" <?php selected( $trigger, 'exit_intent' ); ?>><?php esc_html_e( 'Exit intent', 'banner-manager-pro' ); ?></option>
								</select>
							</td>
						</tr>
						<tr id="bmp-popup-delay-row" style="<?php echo ( $trigger !== 'delay' ) ? 'display:none' : ''; ?>">
							<th><?php esc_html_e( 'D&eacute;lai avant affichage (secondes)', 'banner-manager-pro' ); ?></th>
							<td><input type="number" name="bmp_popup_delay" min="0" max="60" value="<?php echo esc_attr( absint( $delay ) ); ?>" style="width:80px">
							<p class="description"><?php esc_html_e( 'Par d&eacute;faut : 5 secondes', 'banner-manager-pro' ); ?></p></td>
						</tr>
						<tr id="bmp-popup-scroll-row" style="<?php echo ( $trigger !== 'scroll' ) ? 'display:none' : ''; ?>">
							<th><?php esc_html_e( 'Scroll (%)', 'banner-manager-pro' ); ?></th>
							<td><input type="number" name="bmp_popup_scroll_pct" min="10" max="100" value="<?php echo esc_attr( absint( $scroll_pct ) ); ?>" style="width:80px"></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Dur&eacute;e d\'affichage (secondes)', 'banner-manager-pro' ); ?></th>
							<td><input type="number" name="bmp_popup_auto_close" min="0" max="300" value="<?php echo esc_attr( absint( $auto_close ) ); ?>" style="width:80px">
							<p class="description"><?php esc_html_e( '0 = reste visible jusqu\'&agrave; fermeture manuelle', 'banner-manager-pro' ); ?></p></td>
						</tr>
					</table>
				</section>

				<section class="bmp-admin-section">
					<h3><?php esc_html_e( 'Ciblage', 'banner-manager-pro' ); ?></h3>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Appareil', 'banner-manager-pro' ); ?></th>
							<td>
								<div class="bmp-field-grid">
									<label><input type="radio" name="bmp_popup_device" value="desktop" <?php checked( $device, 'desktop' ); ?>/> <?php esc_html_e( 'Desktop', 'banner-manager-pro' ); ?></label>
									<label><input type="radio" name="bmp_popup_device" value="mobile" <?php checked( $device, 'mobile' ); ?>/> <?php esc_html_e( 'Mobile', 'banner-manager-pro' ); ?></label>
									<label><input type="radio" name="bmp_popup_device" value="both" <?php checked( $device, 'both' ); ?>/> <?php esc_html_e( 'Les 2', 'banner-manager-pro' ); ?></label>
								</div>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Afficher sur', 'banner-manager-pro' ); ?></th>
							<td>
								<select name="bmp_popup_display_on" id="bmp_popup_display_on">
									<option value="all" <?php selected( $display_on, 'all' ); ?>><?php esc_html_e( 'Tout le site', 'banner-manager-pro' ); ?></option>
									<option value="posts" <?php selected( $display_on, 'posts' ); ?>><?php esc_html_e( 'Tous les articles', 'banner-manager-pro' ); ?></option>
									<option value="pages" <?php selected( $display_on, 'pages' ); ?>><?php esc_html_e( 'Toutes les pages', 'banner-manager-pro' ); ?></option>
									<option value="specific_post" <?php selected( $display_on, 'specific_post' ); ?>><?php esc_html_e( 'Un article spécifique', 'banner-manager-pro' ); ?></option>
									<option value="specific_page" <?php selected( $display_on, 'specific_page' ); ?>><?php esc_html_e( 'Une page spécifique', 'banner-manager-pro' ); ?></option>
									<option value="category" <?php selected( $display_on, 'category' ); ?>><?php esc_html_e( 'Une catégorie', 'banner-manager-pro' ); ?></option>
									<option value="homepage" <?php selected( $display_on, 'homepage' ); ?>><?php esc_html_e( "Page d'accueil uniquement", 'banner-manager-pro' ); ?></option>
								</select>
							</td>
						</tr>
						<tr id="bmp-popup-specific-post-row" style="<?php echo $display_on !== 'specific_post' ? 'display:none' : ''; ?>">
							<th><?php esc_html_e( 'Article', 'banner-manager-pro' ); ?></th>
							<td>
								<select name="bmp_popup_target_post" style="width:100%">
									<option value=""><?php esc_html_e( '— Sélectionner —', 'banner-manager-pro' ); ?></option>
									<?php
									$posts_list = get_posts(['post_type' => 'post', 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC']);
									foreach ($posts_list as $p) :
									?>
										<option value="<?php echo esc_attr($p->ID); ?>" <?php selected($target_post, $p->ID); ?>><?php echo esc_html($p->post_title); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr id="bmp-popup-specific-page-row" style="<?php echo $display_on !== 'specific_page' ? 'display:none' : ''; ?>">
							<th><?php esc_html_e( 'Page', 'banner-manager-pro' ); ?></th>
							<td>
								<?php wp_dropdown_pages([
									'name' => 'bmp_popup_target_page',
									'show_option_none' => '— Sélectionner —',
									'option_none_value' => '',
									'selected' => $target_page,
								]); ?>
							</td>
						</tr>
						<tr id="bmp-popup-category-row" style="<?php echo $display_on !== 'category' ? 'display:none' : ''; ?>">
							<th><?php esc_html_e( 'Catégorie', 'banner-manager-pro' ); ?></th>
							<td>
								<?php wp_dropdown_categories([
									'name' => 'bmp_popup_target_category',
									'show_option_none' => '— Sélectionner —',
									'option_none_value' => '',
									'selected' => $target_category,
									'hide_empty' => false,
								]); ?>
							</td>
						</tr>
					</table>
				</section>

				<section class="bmp-admin-section">
					<h3><?php esc_html_e( 'Fr&eacute;quence', 'banner-manager-pro' ); ?></h3>
					<select name="bmp_popup_frequency" style="width:100%">
						<option value="always" <?php selected( $frequency, 'always' ); ?>><?php esc_html_e( '&Agrave; chaque visite', 'banner-manager-pro' ); ?></option>
						<option value="session" <?php selected( $frequency, 'session' ); ?>><?php esc_html_e( 'Une fois par session', 'banner-manager-pro' ); ?></option>
						<option value="day" <?php selected( $frequency, 'day' ); ?>><?php esc_html_e( 'Une fois par jour', 'banner-manager-pro' ); ?></option>
						<option value="week" <?php selected( $frequency, 'week' ); ?>><?php esc_html_e( 'Une fois par semaine', 'banner-manager-pro' ); ?></option>
						<option value="once" <?php selected( $frequency, 'once' ); ?>><?php esc_html_e( 'Une seule fois', 'banner-manager-pro' ); ?></option>
					</select>
				</section>

				<section class="bmp-admin-section">
					<h3><?php esc_html_e( 'Priorité', 'banner-manager-pro' ); ?></h3>
					<p class="description" style="margin-bottom:8px"><?php esc_html_e( 'Quand plusieurs popups ciblent la même page, celui avec la priorité la plus haute est affiché. Les popups de même priorité sont en rotation aléatoire.', 'banner-manager-pro' ); ?></p>
					<input type="number" name="bmp_popup_priority" min="0" max="100" value="<?php echo esc_attr( absint( $priority ) ); ?>" style="width:80px">
				</section>
			</div>

			<aside class="bmp-admin-preview">
				<div class="bmp-preview-toolbar">
					<strong><?php esc_html_e( 'Preview', 'banner-manager-pro' ); ?></strong>
					<button type="button" class="button button-small bmp-popup-preview-mode is-active" data-mode="desktop"><?php esc_html_e( 'Desktop', 'banner-manager-pro' ); ?></button>
					<button type="button" class="button button-small bmp-popup-preview-mode" data-mode="mobile"><?php esc_html_e( 'Mobile', 'banner-manager-pro' ); ?></button>
				</div>
				<div class="bmp-preview-frame bmp-preview-desktop" id="bmp-popup-live-preview">
					<?php echo $this->render_preview_html( $type, $image_id, $html_code, $image_link ); ?>
				</div>
			</aside>
		</div>
		<?php
	}

	private function render_preview_html( $type, $image_id, $html_code, $image_link = '' ) {
		if ( 'html' === $type ) {
			if ( empty( trim( (string) $html_code ) ) ) {
				return '<div class="bmp-preview-empty">' . esc_html__( 'Aucun code HTML &agrave; pr&eacute;visualiser.', 'banner-manager-pro' ) . '</div>';
			}

			$preview = current_user_can( 'unfiltered_html' ) ? $html_code : wp_kses_post( $html_code );
			return '<iframe class="bmp-preview-iframe" sandbox="allow-scripts allow-popups allow-forms" srcdoc="' . esc_attr( $preview ) . '"></iframe>';
		}

		if ( ! $image_id ) {
			return '<div class="bmp-preview-empty">' . esc_html__( 'Aucune image s&eacute;lectionn&eacute;e.', 'banner-manager-pro' ) . '</div>';
		}

		$image = wp_get_attachment_image( $image_id, 'medium', false, [ 'class' => 'bmp-preview-image' ] );
		if ( ! $image ) {
			return '<div class="bmp-preview-empty">' . esc_html__( 'Image introuvable.', 'banner-manager-pro' ) . '</div>';
		}

		if ( $image_link ) {
			return '<a href="' . esc_url( $image_link ) . '" target="_blank" rel="noopener noreferrer">' . $image . '</a>';
		}

		return $image;
	}

	private function get_configuration_warnings( $post_id ) {
		$status    = get_post_meta( $post_id, '_bmp_popup_status', true ) ?: 'active';
		$type      = get_post_meta( $post_id, '_bmp_popup_type', true ) ?: 'image';
		$image_id  = absint( get_post_meta( $post_id, '_bmp_popup_image_id', true ) );
		$html_code = get_post_meta( $post_id, '_bmp_popup_html', true );
		$warnings  = [];

		if ( 'active' !== $status ) {
			return [];
		}

		if ( 'image' === $type ) {
			if ( ! $image_id ) {
				$warnings[] = __( 'Un popup image actif doit avoir une image.', 'banner-manager-pro' );
			}
		} elseif ( 'post' === $type ) {
			$sel_post = absint( get_post_meta( $post_id, '_bmp_popup_post_id', true ) );
			if ( ! $sel_post || ! get_post( $sel_post ) ) {
				$warnings[] = __( 'Un popup article actif doit avoir un article sélectionné.', 'banner-manager-pro' );
			}
		} elseif ( empty( trim( (string) $html_code ) ) ) {
			$warnings[] = __( 'Un popup HTML actif doit avoir du code HTML/JS.', 'banner-manager-pro' );
		}

		return $warnings;
	}

	public function admin_notices() {
		$screen = get_current_screen();
		if ( ! $screen || 'bmp_popups' !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		if ( isset( $_GET['bmp_popup_duplicated'] ) && '1' === sanitize_key( wp_unslash( $_GET['bmp_popup_duplicated'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Popup dupliqu&eacute; en brouillon.', 'banner-manager-pro' ) . '</p></div>';
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id ) {
			return;
		}

		$warnings = $this->get_configuration_warnings( $post_id );
		if ( ! $warnings ) {
			return;
		}

		echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Configuration du popup incompl&egrave;te.', 'banner-manager-pro' ) . '</strong></p><ul>';
		foreach ( $warnings as $warning ) {
			echo '<li>' . esc_html( $warning ) . '</li>';
		}
		echo '</ul></div>';
	}

	/* --------- 2. Sauvegarde --------- */
	public function save( $post_id ) {
		$nonce = isset( $_POST['bmp_popup_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['bmp_popup_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bmp_save_popup' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( BMP_CAPABILITY ) || ! current_user_can( 'edit_post', $post_id ) ) return;

		$post_data = wp_unslash( $_POST );

		// Status
		$status = isset( $post_data['bmp_popup_status'] ) && in_array( $post_data['bmp_popup_status'], [ 'active', 'inactive' ], true ) ? $post_data['bmp_popup_status'] : 'active';
		update_post_meta( $post_id, '_bmp_popup_status', $status );

		// Type
		$type = isset( $post_data['bmp_popup_type'] ) && in_array( $post_data['bmp_popup_type'], [ 'image', 'html', 'post' ], true ) ? $post_data['bmp_popup_type'] : 'image';
		update_post_meta( $post_id, '_bmp_popup_type', $type );

		// Image fields
		if ( 'image' === $type ) {
			update_post_meta( $post_id, '_bmp_popup_image_id',   absint( $post_data['bmp_popup_image_id'] ?? 0 ) );
			update_post_meta( $post_id, '_bmp_popup_image_link', esc_url_raw( $post_data['bmp_popup_image_link'] ?? '' ) );
			delete_post_meta( $post_id, '_bmp_popup_html' );
		} elseif ( 'post' === $type ) {
			update_post_meta( $post_id, '_bmp_popup_post_id', absint( $post_data['bmp_popup_post_id'] ?? 0 ) );
			update_post_meta( $post_id, '_bmp_popup_post_btn', sanitize_text_field( $post_data['bmp_popup_post_btn'] ?? 'Lire l\'article' ) );
			$post_display = in_array( $post_data['bmp_popup_post_display'] ?? '', [ 'card', 'full' ], true ) ? $post_data['bmp_popup_post_display'] : 'card';
			update_post_meta( $post_id, '_bmp_popup_post_display', $post_display );
		} else {
			$html = $post_data['bmp_popup_html'] ?? '';
			$html = current_user_can( 'unfiltered_html' ) ? $html : wp_kses_post( $html );
			update_post_meta( $post_id, '_bmp_popup_html', $html );
			delete_post_meta( $post_id, '_bmp_popup_image_id' );
			delete_post_meta( $post_id, '_bmp_popup_image_link' );
		}

		// Position
		$position = isset( $post_data['bmp_popup_position'] ) && in_array( $post_data['bmp_popup_position'], [ 'center', 'right', 'left', 'bottom', 'top' ], true ) ? $post_data['bmp_popup_position'] : 'center';
		update_post_meta( $post_id, '_bmp_popup_position', $position );

		// Size
		$size = isset( $post_data['bmp_popup_size'] ) && in_array( $post_data['bmp_popup_size'], [ 'small', 'medium', 'large', 'fullscreen' ], true ) ? $post_data['bmp_popup_size'] : 'medium';
		update_post_meta( $post_id, '_bmp_popup_size', $size );

		// Trigger
		$trigger = isset( $post_data['bmp_popup_trigger'] ) && in_array( $post_data['bmp_popup_trigger'], [ 'immediate', 'delay', 'scroll', 'exit_intent' ], true ) ? $post_data['bmp_popup_trigger'] : 'delay';
		update_post_meta( $post_id, '_bmp_popup_trigger', $trigger );

		// Delay
		$delay = isset( $post_data['bmp_popup_delay'] ) ? absint( $post_data['bmp_popup_delay'] ) : 2;
		$delay = min( 60, max( 0, $delay ) );
		update_post_meta( $post_id, '_bmp_popup_delay', $delay );

		// Scroll percentage
		$scroll_pct = isset( $post_data['bmp_popup_scroll_pct'] ) ? absint( $post_data['bmp_popup_scroll_pct'] ) : 50;
		$scroll_pct = min( 100, max( 10, $scroll_pct ) );
		update_post_meta( $post_id, '_bmp_popup_scroll_pct', $scroll_pct );

		// Auto-close
		$auto_close = isset( $post_data['bmp_popup_auto_close'] ) ? absint( $post_data['bmp_popup_auto_close'] ) : 0;
		$auto_close = min( 300, max( 0, $auto_close ) );
		update_post_meta( $post_id, '_bmp_popup_auto_close', $auto_close );

		// Design
		update_post_meta( $post_id, '_bmp_popup_bg_color', sanitize_hex_color( $post_data['bmp_popup_bg_color'] ?? '#ffffff' ) ?: '#ffffff' );
		update_post_meta( $post_id, '_bmp_popup_btn_color', sanitize_hex_color( $post_data['bmp_popup_btn_color'] ?? '#111827' ) ?: '#111827' );
		$close_color = in_array( $post_data['bmp_popup_close_color'] ?? '', [ '', 'light', 'dark' ], true ) ? $post_data['bmp_popup_close_color'] : '';
		update_post_meta( $post_id, '_bmp_popup_close_color', $close_color );
		update_post_meta( $post_id, '_bmp_popup_radius', min( 24, max( 0, absint( $post_data['bmp_popup_radius'] ?? 16 ) ) ) );

		// Device
		$device = isset( $post_data['bmp_popup_device'] ) && in_array( $post_data['bmp_popup_device'], [ 'desktop', 'mobile', 'both' ], true ) ? $post_data['bmp_popup_device'] : 'both';
		update_post_meta( $post_id, '_bmp_popup_device', $device );

		// Display on
		$allowed_display = ['all', 'posts', 'pages', 'specific_post', 'specific_page', 'category', 'homepage'];
		$display_on = isset( $post_data['bmp_popup_display_on'] ) && in_array( $post_data['bmp_popup_display_on'], $allowed_display, true ) ? $post_data['bmp_popup_display_on'] : 'all';
		update_post_meta( $post_id, '_bmp_popup_display_on', $display_on );

		// Target post
		update_post_meta( $post_id, '_bmp_popup_target_post', absint( $post_data['bmp_popup_target_post'] ?? 0 ) );
		// Target page
		update_post_meta( $post_id, '_bmp_popup_target_page', absint( $post_data['bmp_popup_target_page'] ?? 0 ) );
		// Target category
		update_post_meta( $post_id, '_bmp_popup_target_category', absint( $post_data['bmp_popup_target_category'] ?? 0 ) );
		// Priority
		$priority = isset( $post_data['bmp_popup_priority'] ) ? absint( $post_data['bmp_popup_priority'] ) : 10;
		$priority = min( 100, max( 0, $priority ) );
		update_post_meta( $post_id, '_bmp_popup_priority', $priority );

		// Frequency
		$frequency = isset( $post_data['bmp_popup_frequency'] ) && in_array( $post_data['bmp_popup_frequency'], [ 'always', 'session', 'day', 'week', 'once' ], true ) ? $post_data['bmp_popup_frequency'] : 'always';
		update_post_meta( $post_id, '_bmp_popup_frequency', $frequency );
	}

	/* --------- 3. Assets admin --------- */
	public function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'bmp_popups' ) {
			return;
		}

		if ( $screen->base === 'post' ) {
			wp_enqueue_media();
			wp_enqueue_script( 'jquery' );

			$script_path = BMP_PATH . 'admin/js/bmp-popup-admin-cpt.js';
			$script_url  = BMP_URL . 'admin/js/bmp-popup-admin-cpt.js';

			wp_enqueue_script(
				'bmp-popup-admin-cpt',
				$script_url,
				[ 'jquery', 'media-views' ],
				file_exists( $script_path ) ? (string) filemtime( $script_path ) : BMP_VERSION,
				true
			);
		}

		wp_register_style( 'bmp-popup-admin', false, [], BMP_VERSION );
		wp_enqueue_style( 'bmp-popup-admin' );
		wp_add_inline_style( 'bmp-popup-admin', '
			.bmp-admin-layout {
				display: grid;
				grid-template-columns: minmax(0, 1fr) 320px;
				gap: 20px;
				align-items: start;
			}
			.bmp-admin-main {
				min-width: 0;
			}
			.bmp-admin-section {
				border: 1px solid #dcdcde;
				background: #fff;
				border-radius: 4px;
				margin: 0 0 14px;
				padding: 16px;
			}
			.bmp-admin-section h3 {
				margin: 0 0 12px;
				font-size: 14px;
				line-height: 1.4;
			}
			.bmp-field-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
				gap: 8px 14px;
			}
			.bmp-empty-state {
				color: #b32d2e;
				margin: 6px 0 0;
			}
			.bmp-config-warning ul,
			.notice ul {
				list-style: disc;
				margin-left: 20px;
			}
			.bmp-admin-preview {
				position: sticky;
				top: 42px;
				border: 1px solid #dcdcde;
				background: #fff;
				border-radius: 4px;
				padding: 12px;
			}
			.bmp-preview-toolbar {
				display: flex;
				align-items: center;
				gap: 8px;
				margin-bottom: 12px;
			}
			.bmp-preview-toolbar strong {
				margin-right: auto;
			}
			.bmp-popup-preview-mode.is-active {
				border-color: #2271b1;
				color: #0a4b78;
			}
			.bmp-preview-frame {
				display: flex;
				align-items: center;
				justify-content: center;
				min-height: 180px;
				overflow: auto;
				padding: 16px;
				background: #f6f7f7;
				border: 1px dashed #c3c4c7;
				border-radius: 4px;
			}
			.bmp-preview-mobile {
				max-width: 180px;
				min-height: 260px;
				margin: 0 auto;
			}
			.bmp-preview-image,
			#bmp-popup-live-preview img {
				display: block;
				max-width: 100%;
				height: auto;
			}
			.bmp-preview-empty {
				color: #646970;
				text-align: center;
			}
			.bmp-preview-html {
				max-width: 100%;
			}
			.bmp-preview-iframe {
				width: 100%;
				min-height: 220px;
				border: 0;
				background: #fff;
			}
			#bmp-popup-html-fields textarea {
				background-color: #f8f9fa;
				border: 1px solid #ddd;
				border-radius: 4px;
				padding: 10px;
			}
			#bmp-popup-html-fields textarea:focus {
				border-color: #0073aa;
				box-shadow: 0 0 0 1px #0073aa;
			}

			/* Styles pour les colonnes du listing */
			.wp-list-table .column-status {
				width: 80px;
			}
			.wp-list-table .column-type {
				width: 120px;
			}
			.wp-list-table .column-position {
				width: 100px;
			}
			.wp-list-table .column-trigger {
				width: 120px;
			}
			.wp-list-table .column-frequency {
				width: 120px;
			}
			.wp-list-table .column-status span {
				padding: 2px 6px;
				border-radius: 3px;
				font-size: 0.85em;
			}
			.wp-list-table .column-type span {
				font-weight: 500;
			}

			/* Position cards */
			.bmp-position-card {
				display: flex;
				flex-direction: column;
				align-items: center;
				gap: 6px;
				padding: 12px;
				border: 2px solid #dcdcde;
				border-radius: 6px;
				cursor: pointer;
				transition: all 150ms;
				text-align: center;
			}
			.bmp-position-card:hover {
				border-color: #0073aa;
			}
			.bmp-position-card input[type="radio"] {
				display: none;
			}
			.bmp-position-card:has(input:checked) {
				border-color: #0073aa;
				background: #f0f6fc;
				box-shadow: 0 0 0 1px #0073aa;
			}
			.bmp-position-icon {
				font-size: 24px;
				line-height: 1;
				color: #1d2327;
			}
			.bmp-position-grid {
				grid-template-columns: repeat(5, 1fr) !important;
			}
			@media (max-width: 960px) {
				.bmp-admin-layout {
					grid-template-columns: 1fr;
				}
				.bmp-admin-preview {
					position: static;
				}
				.bmp-position-grid {
					grid-template-columns: repeat(3, 1fr) !important;
				}
			}
		' );
	}
}
