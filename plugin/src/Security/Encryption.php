<?php
/**
 * Encryption - Secure encryption/decryption for sensitive data.
 *
 * @package NotionSync
 * @since 0.1.0
 */

namespace NotionSync\Security;

/**
 * Class Encryption
 *
 * Provides secure encryption and decryption for sensitive data like API tokens.
 * Uses WordPress salts for encryption keys to ensure data can only be decrypted
 * on the same WordPress installation.
 */
class Encryption {

	/**
	 * Encryption method.
	 *
	 * @var string
	 */
	private const CIPHER = 'aes-256-cbc';

	/**
	 * Encrypt a value.
	 *
	 * @param string $value Plain text value to encrypt.
	 * @return string Encrypted value (base64 encoded).
	 */
	public static function encrypt( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$key = self::get_key();
		$iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::CIPHER ) );

		$encrypted = openssl_encrypt( $value, self::CIPHER, $key, 0, $iv );

		if ( false === $encrypted ) {
			return '';
		}

		// Combine IV and encrypted data for storage.
		return base64_encode( $iv . '::' . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a value.
	 *
	 * @param string $encrypted Encrypted value (base64 encoded).
	 * @return string Decrypted plain text value.
	 */
	public static function decrypt( $encrypted ) {
		if ( empty( $encrypted ) ) {
			return '';
		}

		$key = self::get_key();

		// Decode the base64 encoded value.
		$decoded = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded ) {
			return '';
		}

		// Split IV and encrypted data.
		$parts = explode( '::', $decoded, 2 );

		if ( count( $parts ) !== 2 ) {
			return '';
		}

		list( $iv, $encrypted_value ) = $parts;

		$decrypted = openssl_decrypt( $encrypted_value, self::CIPHER, $key, 0, $iv );

		if ( false === $decrypted ) {
			return '';
		}

		return $decrypted;
	}

	/**
	 * Get encryption key derived from WordPress salts.
	 *
	 * Uses WordPress authentication and secure auth salts to create
	 * a unique encryption key for this installation.
	 *
	 * @return string Encryption key.
	 */
	private static function get_key() {
		// Use WordPress salts to create a unique key for this installation.
		$salt = AUTH_KEY . SECURE_AUTH_KEY . LOGGED_IN_KEY . NONCE_KEY;

		// Create a 256-bit key from the salt.
		return hash( 'sha256', $salt, true );
	}

	/**
	 * Check if encryption is available.
	 *
	 * @return bool True if OpenSSL extension is available.
	 */
	public static function is_available() {
		return function_exists( 'openssl_encrypt' ) &&
				function_exists( 'openssl_decrypt' ) &&
				in_array( self::CIPHER, openssl_get_cipher_methods(), true );
	}
}
