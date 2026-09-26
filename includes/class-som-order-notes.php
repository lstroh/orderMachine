<?php
/**
 * Admin-only threaded notes on orders.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Append-only order notes (order detail UI).
 */
class SOM_Order_Notes {

	/**
	 * Soft/hard max length for note body (chars).
	 */
	const MAX_LENGTH = 5000;

	/**
	 * List notes for an order (oldest first).
	 *
	 * @param int $order_id Order PK.
	 * @return array<int, object>
	 */
	public static function list_for_order( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		if ( $order_id < 1 ) {
			return array();
		}

		$table = SOM_DB::table( 'order_notes' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_id, user_id, body, created_at
				FROM {$table}
				WHERE order_id = %d
				ORDER BY created_at ASC, id ASC",
				$order_id
			)
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as $row ) {
			$row->author_name = self::author_label( (int) $row->user_id );
		}

		return $rows;
	}

	/**
	 * Add a note to an order (append-only).
	 *
	 * @param int         $order_id Order PK.
	 * @param string      $body     Plain text.
	 * @param int|null    $user_id  WP user ID (defaults to current user).
	 * @return int|WP_Error New note ID.
	 */
	public static function add( $order_id, $body, $user_id = null ) {
		global $wpdb;

		$order_id = (int) $order_id;
		if ( $order_id < 1 || ! SOM_Orders::get( $order_id ) ) {
			return new WP_Error( 'som_note_order', __( 'Order not found.', 'order-machine' ) );
		}

		$body = self::sanitize_body( $body );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		$user_id = (int) $user_id;
		if ( $user_id < 1 ) {
			return new WP_Error( 'som_note_user', __( 'A logged-in user is required to add a note.', 'order-machine' ) );
		}

		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			SOM_DB::table( 'order_notes' ),
			array(
				'order_id'   => $order_id,
				'user_id'    => $user_id,
				'body'       => $body,
				'created_at' => $now,
			),
			array( '%d', '%d', '%s', '%s' )
		);

		if ( ! $ok ) {
			return new WP_Error( 'som_note_create', __( 'Could not save note.', 'order-machine' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Sanitize and validate note body.
	 *
	 * @param string $body Raw text.
	 * @return string|WP_Error
	 */
	public static function sanitize_body( $body ) {
		$body = sanitize_textarea_field( (string) $body );
		$body = trim( $body );
		if ( '' === $body ) {
			return new WP_Error( 'som_note_empty', __( 'Note cannot be empty.', 'order-machine' ) );
		}
		if ( strlen( $body ) > self::MAX_LENGTH ) {
			return new WP_Error(
				'som_note_length',
				sprintf(
					/* translators: %d: max characters */
					__( 'Note cannot exceed %d characters.', 'order-machine' ),
					self::MAX_LENGTH
				)
			);
		}
		return $body;
	}

	/**
	 * Display name for a WP user.
	 *
	 * @param int $user_id User PK.
	 * @return string
	 */
	public static function author_label( $user_id ) {
		$user_id = (int) $user_id;
		$user    = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( ! $user ) {
			return sprintf(
				/* translators: %d: user id */
				__( 'User #%d', 'order-machine' ),
				$user_id
			);
		}
		$name = trim( (string) $user->display_name );
		if ( '' !== $name ) {
			return $name;
		}
		$login = trim( (string) $user->user_login );
		return '' !== $login ? $login : sprintf(
			/* translators: %d: user id */
			__( 'User #%d', 'order-machine' ),
			$user_id
		);
	}
}
