<?php
/**
 * Credential encryption helpers for Order Machine.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts / decrypts sensitive strings at rest (channel OAuth tokens, secrets).
 *
 * Prefers `SOM_ENCRYPTION_KEY` from wp-config; falls back to a key derived from `wp_salt( 'auth' )`.
 */
class SOM_Crypto {

	const PREFIX = 'som1:';

	/**
	 * Encrypt a plaintext string.
	 *
	 * @param string $plaintext Raw value.
	 * @return string Prefixed ciphertext, or empty string on failure / empty input.
	 */
	public static function encrypt( $plaintext ) {
		$plaintext = (string) $plaintext;
		if ( '' === $plaintext ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return self::PREFIX . 'b64:' . base64_encode( $plaintext );
		}

		$key    = self::key_bytes();
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return '';
		}

		return self::PREFIX . base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a value produced by encrypt().
	 *
	 * @param string $ciphertext Prefixed ciphertext.
	 * @return string Plaintext, or empty string on failure.
	 */
	public static function decrypt( $ciphertext ) {
		$ciphertext = (string) $ciphertext;
		if ( '' === $ciphertext ) {
			return '';
		}

		if ( 0 !== strpos( $ciphertext, self::PREFIX ) ) {
			// Legacy / seed plaintext — return as-is so callers can migrate.
			return $ciphertext;
		}

		$payload = substr( $ciphertext, strlen( self::PREFIX ) );

		if ( 0 === strpos( $payload, 'b64:' ) ) {
			$decoded = base64_decode( substr( $payload, 4 ), true );
			return false === $decoded ? '' : $decoded;
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$raw = base64_decode( $payload, true );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}

		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', self::key_bytes(), OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : $plain;
	}

	/**
	 * Encrypt a JSON-serializable array for storage in `credentials`.
	 *
	 * @param array $data Token payload.
	 * @return string Encrypted JSON, or empty string.
	 */
	public static function encrypt_json( array $data ) {
		$json = wp_json_encode( $data );
		if ( ! is_string( $json ) || '' === $json ) {
			return '';
		}
		return self::encrypt( $json );
	}

	/**
	 * Decrypt a credentials column value into an array.
	 *
	 * @param string|null $ciphertext Encrypted JSON from DB.
	 * @return array<string, mixed>
	 */
	public static function decrypt_json( $ciphertext ) {
		$plain = self::decrypt( (string) $ciphertext );
		if ( '' === $plain ) {
			return array();
		}

		$decoded = json_decode( $plain, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * 32-byte encryption key.
	 *
	 * @return string
	 */
	private static function key_bytes() {
		if ( defined( 'SOM_ENCRYPTION_KEY' ) && is_string( SOM_ENCRYPTION_KEY ) && '' !== SOM_ENCRYPTION_KEY ) {
			return hash( 'sha256', SOM_ENCRYPTION_KEY, true );
		}

		return hash( 'sha256', wp_salt( 'auth' ) . '|som-crypto', true );
	}
}
