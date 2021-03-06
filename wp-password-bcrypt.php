<?php
/**
 * Plugin Name: WP Password bcrypt
 * Plugin URI:  https://www.extendwings.com
 * Description: Replaces wp_hash_password and wp_check_password with PHP 5.5's password_hash and password_verify.
 * Author:      Daisuke Takahashi
 * Author URI:  https://www.extendwings.com
 * Version:     1.1.2
 * Licence:     MIT
 * Network:     true
 *
 * @package WP_Password_Bcrypt
 */

require_once( plugin_dir_path( __FILE__ ) . 'includes/password.php' );

if ( ! function_exists( 'wp_check_password' ) ) :
	/**
	 * Check if user has entered correct password, supports bcrypt and pHash.
	 *
	 * @param string     $password Plaintext password.
	 * @param string     $hash Hash of password.
	 * @param int|string $user_id User ID.
	 * @return mixed|void
	 */
	function wp_check_password( $password, $hash, $user_id = '' ) {
		$check = password_verify( $password, $hash );

		if ( ! $check ) {
			if ( 0 === strpos( $hash, '$P$' ) || 0 === strpos( $hash, '$H$' ) ) { // If the hash is still portable hash or phpBB3 hash...
				global $wp_hasher;

				if ( empty( $wp_hasher ) ) {
					require_once( ABSPATH . WPINC . '/class-phpass.php' );

					$wp_hasher = new PasswordHash( 8, true ); // WPCS: override ok.
				}

				$check = $wp_hasher->CheckPassword( $password, $hash );
			} elseif ( 0 !== strpos( $hash, '$' ) ) { // If the hash is still md5...
				$check = hash_equals( $hash, md5( $password ) );
			}
		}

		if ( $check && $user_id ) { // Rehash using new hash.
			$cost = apply_filters( 'wp_hash_password_cost', 10 );

			if ( password_needs_rehash( $hash, PASSWORD_DEFAULT, [ 'cost' => $cost ] ) ) {
				$hash = wp_set_password( $password, $user_id );
			}
		}

		return apply_filters( 'check_password', $check, $password, $hash, $user_id );
	}
endif;

if ( ! function_exists( 'wp_hash_password' ) ) :
	/**
	 * Hash password using bcrypt
	 *
	 * @param string $password Plaintext password.
	 * @return bool|string
	 */
	function wp_hash_password( $password ) {
		$cost = apply_filters( 'wp_hash_password_cost', 10 );

		return password_hash( $password, PASSWORD_DEFAULT, [ 'cost' => $cost ] );
	}
endif;

if ( ! function_exists( 'wp_set_password' ) ) :
	/**
	 * Set password using bcrypt
	 *
	 * @param string $password Plaintext password.
	 * @param int    $user_id User ID.
	 * @return bool|string
	 */
	function wp_set_password( $password, $user_id ) {
		global $wpdb;

		$hash = wp_hash_password( $password );

		$wpdb->update(
			$wpdb->users,
			[
				'user_pass'           => $hash,
				'user_activation_key' => '',
			],
			[
				'ID' => $user_id,
			]
		);

		wp_cache_delete( $user_id, 'users' );

		return $hash;
	}
endif;
