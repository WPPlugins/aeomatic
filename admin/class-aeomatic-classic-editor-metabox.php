<?php
/**
 * Classic Editor metabox.
 *
 * Registers a metabox below the Classic Editor content area that mounts the
 * same 5 React panels as the Gutenberg sidebar (via assets/js/aeomatic-panels.js).
 * Only shown when the current post is being edited in the Classic Editor —
 * Gutenberg users continue to see the PluginSidebar path unchanged.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Classic_Editor_Metabox {

	const METABOX_ID = 'aeomatic-classic-metabox';

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_metaboxes( string $post_type, $post ): void {
		if ( ! $this->is_supported_post_type( $post_type ) ) {
			return;
		}
		if ( ! $this->is_classic_editor_screen() ) {
			return;
		}
		add_meta_box(
			self::METABOX_ID,
			__( 'AEOmatic', 'aeomatic' ),
			array( $this, 'render_metabox' ),
			$post_type,
			'normal',
			'high'
		);
	}

	public function render_metabox( $post ): void {
		// Container div — the React panels mount into this on DOMContentLoaded.
		// A min-height placeholder avoids layout jump before hydration.
		echo '<div id="aeomatic-classic-metabox-root" style="min-height:60px;">';
		echo '<p style="color:#888;font-size:12px;">' .
			esc_html__( 'Loading AEOmatic…', 'aeomatic' ) .
			'</p>';
		echo '</div>';
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		if ( ! $this->is_classic_editor_screen() ) {
			return;
		}

		$post = get_post();
		if ( ! $post || ! $this->is_supported_post_type( $post->post_type ) ) {
			return;
		}

		// Shared panels — same file the Gutenberg sidebar loads.
		wp_enqueue_script(
			'aeomatic-panels',
			AEOMATIC_URL . 'assets/js/aeomatic-panels.js',
			array( 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-api-fetch' ),
			AEOMATIC_VERSION,
			true
		);
		wp_set_script_translations( 'aeomatic-panels', 'aeomatic' );

		// Classic-only mount shell.
		wp_enqueue_script(
			'aeomatic-classic-metabox',
			AEOMATIC_URL . 'assets/js/classic-editor-metabox.js',
			array( 'aeomatic-panels' ),
			AEOMATIC_VERSION,
			true
		);
		wp_set_script_translations( 'aeomatic-classic-metabox', 'aeomatic' );

		// wp.components ships its own CSS. Without this the controls render
		// unstyled inside the metabox.
		wp_enqueue_style( 'wp-components' );

		wp_localize_script(
			'aeomatic-classic-metabox',
			'aeomaticClassic',
			array(
				'postId'    => (int) $post->ID,
				'postTitle' => (string) $post->post_title,
				'restUrl'   => esc_url_raw( rest_url( 'aeomatic/v1/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * True when this post type is enabled for AEOmatic.
	 */
	private function is_supported_post_type( string $post_type ): bool {
		if ( ! class_exists( 'AEOMATIC_Post_Types' ) ) {
			return false;
		}
		$enabled = AEOMATIC_Post_Types::get_enabled_slugs();
		return in_array( $post_type, $enabled, true );
	}

	/**
	 * True when the current admin screen is a post edit screen NOT using the
	 * block editor. Covers three cases: (a) the site has the Classic Editor
	 * plugin forcing classic, (b) the post type opts out of the block editor
	 * via `show_in_rest => false`, (c) the user selected Classic on a
	 * per-post basis. Guards against get_current_screen() being null on
	 * early hooks.
	 */
	private function is_classic_editor_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		// Only on post edit screens.
		if ( 'post' !== $screen->base ) {
			return false;
		}
		if ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return false;
		}
		return true;
	}
}
