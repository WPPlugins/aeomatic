<?php
/**
 * Block registration.
 *
 * Registers the aeomatic/faq parent block and aeomatic/faq-item child block
 * via metadata files in /blocks/.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Blocks {

	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	public function register_blocks(): void {
		register_block_type( AEOMATIC_DIR . 'blocks/faq', array(
			'render_callback' => array( $this, 'render_faq' ),
		) );
		register_block_type( AEOMATIC_DIR . 'blocks/faq-item', array(
			'render_callback' => array( $this, 'render_faq_item' ),
		) );
	}

	public function render_faq( array $attrs, string $content ): string {
		if ( empty( $content ) ) {
			return '';
		}
		return sprintf(
			'<div class="aeob-faq" itemscope itemtype="https://schema.org/FAQPage">%s</div>',
			$content
		);
	}

	public function render_faq_item( array $attrs ): string {
		$q = isset( $attrs['question'] ) ? wp_kses_post( $attrs['question'] ) : '';
		$a = isset( $attrs['answer'] ) ? wp_kses_post( $attrs['answer'] ) : '';
		if ( '' === trim( wp_strip_all_tags( $q ) ) || '' === trim( wp_strip_all_tags( $a ) ) ) {
			return '';
		}
		return sprintf(
			'<details class="aeob-faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">' .
			'<summary itemprop="name">%s</summary>' .
			'<div class="aeob-faq-answer" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">' .
			'<div itemprop="text">%s</div>' .
			'</div>' .
			'</details>',
			$q,
			$a
		);
	}

	public function enqueue_editor_assets(): void {
		// Shared panels module — same file Classic Editor loads. Must enqueue
		// before the sidebar shell since the shell consumes window.AeomaticPanels.
		wp_enqueue_script(
			'aeomatic-panels',
			AEOMATIC_URL . 'assets/js/aeomatic-panels.js',
			array( 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-api-fetch' ),
			AEOMATIC_VERSION,
			true
		);
		wp_set_script_translations( 'aeomatic-panels', 'aeomatic' );

		// Gutenberg PluginSidebar shell.
		wp_enqueue_script(
			'aeob-editor-sidebar',
			AEOMATIC_URL . 'assets/js/editor-sidebar.js',
			array( 'aeomatic-panels', 'wp-plugins', 'wp-edit-post' ),
			AEOMATIC_VERSION,
			true
		);
		wp_set_script_translations( 'aeob-editor-sidebar', 'aeomatic' );
	}
}
