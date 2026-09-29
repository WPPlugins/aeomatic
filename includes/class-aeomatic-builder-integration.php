<?php
/**
 * Page-builder editor integration.
 *
 * Page builders replace the WordPress edit screen with their own full-screen
 * editor, so the Gutenberg sidebar and the Classic Editor metabox never
 * render there. For each supported builder this class detects the builder's
 * editor window, enqueues the same shared React panels the other editors use
 * (assets/js/aeomatic-panels.js), and mounts them behind a floating
 * "AEOmatic" launcher button (assets/js/builder-launcher.js).
 *
 * Several builders edit on the front end (Beaver Builder, Bricks, Divi,
 * Oxygen), so this is registered outside is_admin(). Each detection is
 * guarded with function_exists()/class_exists() and a query-var fallback, so
 * an absent builder costs nothing.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Builder_Integration {

	public function register(): void {
		// Elementor fires its own action only while its editor is loading.
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_elementor' ) );

		// Front-end builders: the editor is the page itself with a query var.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_end_builders' ), 999 );

		// Beaver Builder 2.9+ renders its UI in a parent window, empties the
		// script queue there at wp_enqueue_scripts PHP_INT_MAX, and re-adds
		// only a whitelist plus whatever is enqueued on this action.
		add_action( 'fl_builder_ui_enqueue_scripts', array( $this, 'enqueue_front_end_builders' ) );

		// WPBakery's front-end editor is an admin screen (post.php?vc_action=vc_inline).
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wpbakery' ), 999 );
	}

	public function enqueue_elementor(): void {
		$this->enqueue( 'elementor', $this->admin_post_id() );
	}

	public function enqueue_front_end_builders(): void {
		$builder = $this->active_front_end_builder();
		if ( '' === $builder ) {
			return;
		}
		$this->enqueue( $builder, (int) get_queried_object_id() );
	}

	public function enqueue_wpbakery(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$inline = isset( $_GET['vc_action'] ) && 'vc_inline' === sanitize_key( wp_unslash( $_GET['vc_action'] ) );
		if ( ! $inline && ! ( function_exists( 'vc_is_frontend_editor' ) && vc_is_frontend_editor() ) ) {
			return;
		}
		$this->enqueue( 'wpbakery', $this->admin_post_id() );
	}

	/**
	 * Which front-end builder's editor is loading, or '' when none.
	 *
	 * Only the builder's main window counts — Bricks and Oxygen also load the
	 * page in a preview iframe, and a launcher there would be duplicated.
	 * builder-launcher.js also refuses to mount inside any iframe.
	 */
	private function active_front_end_builder(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( class_exists( 'FLBuilderModel' ) && method_exists( 'FLBuilderModel', 'is_builder_active' ) && FLBuilderModel::is_builder_active()
			&& ! isset( $_GET['fl_builder_ui_iframe'] ) ) {
			return 'beaver';
		}
		if ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) {
			return 'bricks';
		}
		// Oxygen (classic, 4.x). CT_VERSION guards against a stray
		// ?ct_builder on a site that does not run Oxygen.
		if ( defined( 'CT_VERSION' ) && ( defined( 'SHOW_CT_BUILDER' ) || isset( $_GET['ct_builder'] ) )
			&& ! defined( 'OXYGEN_IFRAME' ) && ! isset( $_GET['oxygen_iframe'] ) ) {
			return 'oxygen';
		}
		if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
			return 'divi';
		}
		// phpcs:enable
		return '';
	}

	/**
	 * Post ID on an admin-side builder screen (post.php?post=NNN&action=…).
	 */
	private function admin_post_id(): int {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		foreach ( array( 'post', 'post_id' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && (int) $_GET[ $key ] > 0 ) {
				return (int) $_GET[ $key ];
			}
		}
		// phpcs:enable
		$post = get_post();
		return $post ? (int) $post->ID : 0;
	}

	private function enqueue( string $builder, int $post_id ): void {
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post || ! class_exists( 'AEOMATIC_Post_Types' ) || ! AEOMATIC_Post_Types::is_enabled( $post->post_type ) ) {
			return;
		}

		// Shared panels — same file the Gutenberg sidebar and Classic metabox load.
		wp_enqueue_script(
			'aeomatic-panels',
			AEOMATIC_URL . 'assets/js/aeomatic-panels.js',
			array( 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-api-fetch' ),
			AEOMATIC_VERSION,
			true
		);
		wp_set_script_translations( 'aeomatic-panels', 'aeomatic' );

		wp_enqueue_script(
			'aeomatic-builder-launcher',
			AEOMATIC_URL . 'assets/js/builder-launcher.js',
			array( 'aeomatic-panels' ),
			AEOMATIC_VERSION,
			true
		);
		wp_set_script_translations( 'aeomatic-builder-launcher', 'aeomatic' );

		// wp.components ships its own CSS; builders do not load it.
		wp_enqueue_style( 'wp-components' );

		/**
		 * Filter where the floating launcher sits in a builder's editor.
		 *
		 * @param array  $position CSS offsets, e.g. [ 'right' => '20px', 'bottom' => '20px' ].
		 * @param string $builder  elementor|beaver|bricks|oxygen|divi|wpbakery
		 */
		$position = (array) apply_filters( 'aeomatic_builder_launcher_position', $this->default_position( $builder ), $builder );

		wp_localize_script(
			'aeomatic-builder-launcher',
			'aeomaticBuilder',
			array(
				'builder'   => $builder,
				'postId'    => (int) $post->ID,
				'postTitle' => (string) $post->post_title,
				'position'  => array_map( 'sanitize_text_field', $position ),
				'i18n'      => array(
					'launcher' => __( 'AEOmatic', 'aeomatic' ),
					'title'    => __( 'AEOmatic', 'aeomatic' ),
					'close'    => __( 'Close', 'aeomatic' ),
				),
			)
		);
	}

	/**
	 * Default launcher offsets, chosen to stay clear of each builder's own
	 * bottom-corner controls.
	 */
	private function default_position( string $builder ): array {
		switch ( $builder ) {
			case 'divi':
				// Divi's page-settings bar is centred at the bottom; its
				// help/history buttons sit bottom-right.
				return array( 'left' => '20px', 'bottom' => '20px' );
			case 'bricks':
				// Bricks' element panel is on the left, structure on the right.
				return array( 'right' => '20px', 'bottom' => '64px' );
			default:
				return array( 'right' => '20px', 'bottom' => '20px' );
		}
	}
}
