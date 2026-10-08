<?php
/**
 * Author profile fields.
 *
 * Adds an "AEO author authority" section to the WP user profile screen.
 * Values map to schema.org Person properties (knowsAbout, hasCredential,
 * worksFor, alumniOf, award, jobTitle) and feed AEOmatic's Person schema
 * for Article authors. Stronger author signals are a primary E-E-A-T input
 * and a measurable AI citation factor.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Author_Profile {

	const META_KEYS = array(
		'aeomatic_job_title'      => 'Job title',
		'aeomatic_works_for'      => 'Works for (organization)',
		'aeomatic_knows_about'    => 'Knows about (one topic per line)',
		'aeomatic_has_credential' => 'Credentials (one per line)',
		'aeomatic_alumni_of'      => 'Alumni of (one institution per line)',
		'aeomatic_award'          => 'Awards (one per line)',
	);

	public function register(): void {
		add_action( 'show_user_profile', array( $this, 'render' ) );
		add_action( 'edit_user_profile', array( $this, 'render' ) );
		add_action( 'personal_options_update',  array( $this, 'save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save' ) );
	}

	public function render( WP_User $user ): void {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'AEO author authority', 'aeomatic' ); ?></h2>
		<p class="description" style="max-width:640px;">
			<?php esc_html_e( 'These values feed AEOmatic\'s Person schema on every article you author. Filled-in author details (job, credentials, affiliations) give search engines and AI systems a named, verifiable author.', 'aeomatic' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<?php foreach ( self::META_KEYS as $key => $label ) :
				$is_textarea = in_array( $key, array( 'aeomatic_knows_about', 'aeomatic_has_credential', 'aeomatic_alumni_of', 'aeomatic_award' ), true );
				$value       = (string) get_user_meta( $user->ID, $key, true );
				?>
				<tr>
					<th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td>
						<?php if ( $is_textarea ) : ?>
							<textarea name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" rows="3" class="regular-text"><?php echo esc_textarea( $value ); ?></textarea>
						<?php else : ?>
							<input type="text" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	public function save( int $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		foreach ( array_keys( self::META_KEYS ) as $key ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$raw = wp_unslash( $_POST[ $key ] );
			if ( is_string( $raw ) && false !== strpos( $raw, "\n" ) ) {
				$clean = sanitize_textarea_field( $raw );
			} else {
				$clean = sanitize_text_field( (string) $raw );
			}
			update_user_meta( $user_id, $key, $clean );
		}
	}
}
