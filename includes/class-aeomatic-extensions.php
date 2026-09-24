<?php
/**
 * Extension API — the contract other plugins (currently AEOmatic Pro) use to
 * hook into the free plugin. Bumping AEOMATIC_API_VERSION is a breaking change
 * that extensions must react to; adding hooks or optional methods is not.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Extensions {

	/** @var array<string,array{name:string,version:string,tier:string,url:string}> */
	private static $modules = array();

	public static function api_version(): string {
		return defined( 'AEOMATIC_API_VERSION' ) ? AEOMATIC_API_VERSION : '0.0.0';
	}

	/**
	 * Called by extensions on plugins_loaded (or later) to announce themselves.
	 * Duplicate slugs are rejected — first registration wins.
	 */
	public static function register_module( string $slug, array $args ): bool {
		$slug = sanitize_key( $slug );
		if ( '' === $slug || isset( self::$modules[ $slug ] ) ) {
			return false;
		}
		self::$modules[ $slug ] = wp_parse_args(
			$args,
			array(
				'name'    => $slug,
				'version' => '0.0.0',
				'tier'    => 'free',
				'url'     => '',
			)
		);
		return true;
	}

	/** @return array<string,array{name:string,version:string,tier:string,url:string}> */
	public static function get_modules(): array {
		return self::$modules;
	}

	public static function is_module_active( string $slug ): bool {
		return isset( self::$modules[ sanitize_key( $slug ) ] );
	}

	/**
	 * True when any Pro extension is registered. Used by the free plugin to
	 * suppress the Go Pro nag without hard-coding the Pro plugin's class name.
	 */
	public static function has_pro(): bool {
		foreach ( self::$modules as $mod ) {
			if ( 'free' !== $mod['tier'] ) {
				return true;
			}
		}
		return false;
	}
}
