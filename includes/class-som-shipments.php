<?php
/**
 * Outbound shipment records (1:1 with orders).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD, Ship-step gates, optional proof attach, and channel tracking push.
 */
class SOM_Shipments {

	const DEFAULT_CARRIER = 'Royal Mail';
	const DEFAULT_SERVICE = '2nd Class';

	const PROOF_MAX_BYTES = 5242880; // 5 MB.

	/**
	 * Allowed proof MIME types.
	 *
	 * @return string[]
	 */
	public static function proof_mimes() {
		return array( 'image/jpeg', 'image/png', 'application/pdf' );
	}

	/**
	 * @param int $order_id Order PK.
	 * @return object|null
	 */
	public static function get_by_order( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		if ( $order_id < 1 ) {
			return null;
		}

		$table = SOM_DB::table( 'shipments' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d LIMIT 1",
				$order_id
			)
		);

		return $row ? $row : null;
	}

	/**
	 * Whether a required shipment row exists for the order.
	 *
	 * @param int $order_id Order PK.
	 * @return bool
	 */
	public static function has_required( $order_id ) {
		$row = self::get_by_order( $order_id );
		if ( ! $row ) {
			return false;
		}
		return '' !== trim( (string) $row->carrier )
			&& '' !== trim( (string) $row->service )
			&& '' !== trim( (string) $row->shipped_at )
			&& null !== $row->postage_paid
			&& '' !== (string) $row->postage_paid;
	}

	/**
	 * Status label for batch / UI: missing | untracked | tracked | pushed.
	 *
	 * @param int $order_id Order PK.
	 * @return string
	 */
	public static function status_key( $order_id ) {
		$row = self::get_by_order( $order_id );
		if ( ! $row || ! self::has_required( $order_id ) ) {
			return 'missing';
		}
		$tracking = trim( (string) ( $row->tracking_number ?? '' ) );
		if ( '' === $tracking ) {
			return 'untracked';
		}
		if ( ! empty( $row->tracking_pushed_at ) ) {
			return 'pushed';
		}
		return 'tracked';
	}

	/**
	 * Human label for status_key().
	 *
	 * @param string $key Status key.
	 * @return string
	 */
	public static function status_label( $key ) {
		$labels = array(
			'missing'   => __( 'Missing shipment', 'order-machine' ),
			'untracked' => __( 'Untracked', 'order-machine' ),
			'tracked'   => __( 'Tracked (not pushed)', 'order-machine' ),
			'pushed'    => __( 'Tracking pushed', 'order-machine' ),
		);
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
	}

	/**
	 * Whether this workflow step is a Ship step (requires shipment before done).
	 *
	 * Matches shipping_label batch group assignment or step name /^ship\b/i.
	 *
	 * @param object $step Workflow step row (needs name; batch_group_id optional).
	 * @return bool
	 */
	public static function is_ship_step( $step ) {
		if ( ! $step ) {
			return false;
		}

		$name = '';
		if ( isset( $step->name ) ) {
			$name = trim( (string) $step->name );
		} elseif ( isset( $step->step_name ) ) {
			$name = trim( (string) $step->step_name );
		}
		if ( $name && preg_match( '/^ship\b/i', $name ) ) {
			return true;
		}

		$group_id = isset( $step->batch_group_id ) ? (int) $step->batch_group_id : 0;
		if ( $group_id < 1 ) {
			return false;
		}

		$group = SOM_Batch_Groups::get( $group_id );
		return $group && 'shipping_label' === (string) $group->group_key;
	}

	/**
	 * Default form values when no shipment exists.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		$today = current_time( 'Y-m-d' );
		return array(
			'carrier'            => self::DEFAULT_CARRIER,
			'service'            => self::DEFAULT_SERVICE,
			'shipped_at'         => $today,
			'postage_paid'       => '',
			'tracking_number'    => '',
			'click_and_drop_ref' => '',
		);
	}

	/**
	 * Validate and normalise input for upsert.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function validate( array $input ) {
		$carrier = isset( $input['carrier'] ) ? sanitize_text_field( (string) $input['carrier'] ) : '';
		$service = isset( $input['service'] ) ? sanitize_text_field( (string) $input['service'] ) : '';
		$shipped = isset( $input['shipped_at'] ) ? sanitize_text_field( (string) $input['shipped_at'] ) : '';
		$postage = isset( $input['postage_paid'] ) ? wp_unslash( $input['postage_paid'] ) : '';
		$tracking = isset( $input['tracking_number'] ) ? sanitize_text_field( (string) $input['tracking_number'] ) : '';
		$cad_ref  = isset( $input['click_and_drop_ref'] ) ? sanitize_text_field( (string) $input['click_and_drop_ref'] ) : '';

		if ( '' === $carrier ) {
			return new WP_Error( 'som_shipment_carrier', __( 'Carrier is required.', 'order-machine' ) );
		}
		if ( '' === $service ) {
			return new WP_Error( 'som_shipment_service', __( 'Service is required.', 'order-machine' ) );
		}

		$shipped_at = self::normalize_shipped_at( $shipped );
		if ( is_wp_error( $shipped_at ) ) {
			return $shipped_at;
		}

		if ( '' === $postage || ! is_numeric( $postage ) ) {
			return new WP_Error( 'som_shipment_postage', __( 'Postage paid is required (GBP).', 'order-machine' ) );
		}
		$postage_paid = round( (float) $postage, 2 );
		if ( $postage_paid < 0 ) {
			return new WP_Error( 'som_shipment_postage', __( 'Postage paid cannot be negative.', 'order-machine' ) );
		}

		$tracking = '' !== $tracking ? $tracking : null;
		$cad_ref  = '' !== $cad_ref ? $cad_ref : null;

		return array(
			'carrier'            => $carrier,
			'service'            => $service,
			'shipped_at'         => $shipped_at,
			'postage_paid'       => $postage_paid,
			'tracking_number'    => $tracking,
			'click_and_drop_ref' => $cad_ref,
		);
	}

	/**
	 * Accept Y-m-d or MySQL datetime; store as UTC MySQL datetime (start of local day for date-only).
	 *
	 * @param string $value Date or datetime.
	 * @return string|WP_Error MySQL datetime UTC.
	 */
	private static function normalize_shipped_at( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return new WP_Error( 'som_shipment_shipped_at', __( 'Ship date is required.', 'order-machine' ) );
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			$tz  = wp_timezone();
			$dt  = date_create_immutable( $value . ' 00:00:00', $tz );
			if ( ! $dt ) {
				return new WP_Error( 'som_shipment_shipped_at', __( 'Invalid ship date.', 'order-machine' ) );
			}
			$dt = $dt->setTimezone( new DateTimeZone( 'UTC' ) );
			return $dt->format( 'Y-m-d H:i:s' );
		}

		$ts = strtotime( $value );
		if ( ! $ts ) {
			return new WP_Error( 'som_shipment_shipped_at', __( 'Invalid ship date.', 'order-machine' ) );
		}
		return gmdate( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * Create or update the shipment for an order (1:1).
	 *
	 * Changing tracking clears push state when the number changes.
	 *
	 * @param int                  $order_id Order PK.
	 * @param array<string, mixed> $input    Field values.
	 * @return object|WP_Error Shipment row.
	 */
	public static function upsert( $order_id, array $input ) {
		global $wpdb;

		$order_id = (int) $order_id;
		if ( $order_id < 1 || ! SOM_Orders::get( $order_id ) ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}

		$validated = self::validate( $input );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$existing = self::get_by_order( $order_id );
		$now      = current_time( 'mysql', true );
		$table    = SOM_DB::table( 'shipments' );

		$data = array(
			'carrier'            => $validated['carrier'],
			'service'            => $validated['service'],
			'shipped_at'         => $validated['shipped_at'],
			'postage_paid'       => $validated['postage_paid'],
			'tracking_number'    => $validated['tracking_number'],
			'click_and_drop_ref' => $validated['click_and_drop_ref'],
			'updated_at'         => $now,
		);

		if ( $existing ) {
			$old_tracking = trim( (string) ( $existing->tracking_number ?? '' ) );
			$new_tracking = $validated['tracking_number'] ? trim( (string) $validated['tracking_number'] ) : '';
			if ( $old_tracking !== $new_tracking ) {
				$data['tracking_pushed_at']  = null;
				$data['tracking_push_error'] = null;
			}

			$ok = $wpdb->update(
				$table,
				$data,
				array( 'order_id' => $order_id )
			);
			if ( false === $ok ) {
				return new WP_Error( 'som_shipment_save', __( 'Could not update shipment.', 'order-machine' ) );
			}
		} else {
			$data['order_id']   = $order_id;
			$data['created_at'] = $now;
			$ok                 = $wpdb->insert( $table, $data );
			if ( false === $ok ) {
				return new WP_Error( 'som_shipment_save', __( 'Could not create shipment.', 'order-machine' ) );
			}
		}

		$row = self::get_by_order( $order_id );
		return $row ? $row : new WP_Error( 'som_shipment_save', __( 'Shipment saved but could not be reloaded.', 'order-machine' ) );
	}

	/**
	 * Attach an uploaded proof file (optional).
	 *
	 * @param int $order_id Order PK.
	 * @param int $file_key $_FILES key index handled by caller via media_handle_upload.
	 * @return true|WP_Error
	 */
	public static function attach_proof_from_upload( $order_id, $file_key = 'som_shipment_proof' ) {
		$order_id = (int) $order_id;
		$row      = self::get_by_order( $order_id );
		if ( ! $row ) {
			return new WP_Error( 'som_shipment_missing', __( 'Save the shipment before attaching proof.', 'order-machine' ) );
		}

		if ( empty( $_FILES[ $file_key ] ) || empty( $_FILES[ $file_key ]['name'] ) ) {
			return new WP_Error( 'som_shipment_proof', __( 'No proof file selected.', 'order-machine' ) );
		}

		$file = $_FILES[ $file_key ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below / by media_handle_upload
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size > self::PROOF_MAX_BYTES ) {
			return new WP_Error( 'som_shipment_proof', __( 'Proof file must be 5 MB or smaller.', 'order-machine' ) );
		}

		$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$check = wp_check_filetype_and_ext(
			( $tmp && is_uploaded_file( $tmp ) ) ? $tmp : '',
			isset( $file['name'] ) ? (string) $file['name'] : ''
		);
		// Fallback MIME from upload when tmp check is sparse.
		$mime = ! empty( $check['type'] ) ? (string) $check['type'] : ( isset( $file['type'] ) ? (string) $file['type'] : '' );
		if ( ! in_array( $mime, self::proof_mimes(), true ) ) {
			return new WP_Error( 'som_shipment_proof', __( 'Proof must be a JPEG, PNG, or PDF.', 'order-machine' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload( $file_key, 0 );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$attachment_id = (int) $attachment_id;
		$att_mime      = get_post_mime_type( $attachment_id );
		if ( ! in_array( (string) $att_mime, self::proof_mimes(), true ) ) {
			wp_delete_attachment( $attachment_id, true );
			return new WP_Error( 'som_shipment_proof', __( 'Proof must be a JPEG, PNG, or PDF.', 'order-machine' ) );
		}

		// Remove previous proof if different.
		$old = ! empty( $row->proof_attachment_id ) ? (int) $row->proof_attachment_id : 0;
		if ( $old > 0 && $old !== $attachment_id ) {
			wp_delete_attachment( $old, true );
		}

		global $wpdb;
		$now = current_time( 'mysql', true );
		$ok  = $wpdb->update(
			SOM_DB::table( 'shipments' ),
			array(
				'proof_attachment_id' => $attachment_id,
				'updated_at'          => $now,
			),
			array( 'order_id' => $order_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'som_shipment_proof', __( 'Could not save proof attachment.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Clear proof attachment reference (optionally delete media).
	 *
	 * @param int  $order_id       Order PK.
	 * @param bool $delete_media   Delete WP attachment.
	 * @return true|WP_Error
	 */
	public static function remove_proof( $order_id, $delete_media = true ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$row      = self::get_by_order( $order_id );
		if ( ! $row ) {
			return new WP_Error( 'som_shipment_missing', __( 'Shipment not found.', 'order-machine' ) );
		}

		$old = ! empty( $row->proof_attachment_id ) ? (int) $row->proof_attachment_id : 0;
		$now = current_time( 'mysql', true );
		$ok  = $wpdb->update(
			SOM_DB::table( 'shipments' ),
			array(
				'proof_attachment_id' => null,
				'updated_at'          => $now,
			),
			array( 'order_id' => $order_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'som_shipment_proof', __( 'Could not remove proof.', 'order-machine' ) );
		}

		if ( $delete_media && $old > 0 ) {
			wp_delete_attachment( $old, true );
		}

		return true;
	}

	/**
	 * Map OM carrier display name to eBay shippingCarrierCode.
	 *
	 * @param string $carrier Carrier label.
	 * @return string
	 */
	public static function ebay_carrier_code( $carrier ) {
		$c = strtolower( trim( (string) $carrier ) );
		if ( false !== strpos( $c, 'royal mail' ) || 'rm' === $c ) {
			return 'RoyalMail';
		}
		return 'Other';
	}

	/**
	 * Map OM carrier to Etsy carrier_name.
	 *
	 * @param string $carrier Carrier label.
	 * @return string
	 */
	public static function etsy_carrier_name( $carrier ) {
		$c = strtolower( trim( (string) $carrier ) );
		if ( false !== strpos( $c, 'royal mail' ) || 'rm' === $c ) {
			return 'royal-mail';
		}
		return 'other';
	}

	/**
	 * Push tracking to the order's sales channel when tracking_number is set.
	 *
	 * Untracked → true (no-op). Already pushed → true. Failures set tracking_push_error.
	 *
	 * @param int $order_id Order PK.
	 * @return true|WP_Error
	 */
	public static function push_tracking( $order_id ) {
		$order_id = (int) $order_id;
		$order    = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}

		$shipment = self::get_by_order( $order_id );
		if ( ! $shipment ) {
			return new WP_Error( 'som_shipment_missing', __( 'No shipment recorded for this order.', 'order-machine' ) );
		}

		$tracking = trim( (string) ( $shipment->tracking_number ?? '' ) );
		if ( '' === $tracking ) {
			return true;
		}

		if ( ! empty( $shipment->tracking_pushed_at ) ) {
			return true;
		}

		$slug = isset( $order->channel_slug ) ? sanitize_key( (string) $order->channel_slug ) : '';
		$result = null;

		if ( 'ebay' === $slug ) {
			$result = SOM_Channel_Ebay::create_shipping_fulfillment(
				(string) $order->external_order_id,
				$tracking,
				self::ebay_carrier_code( (string) $shipment->carrier ),
				(string) $shipment->shipped_at
			);
		} elseif ( 'etsy' === $slug ) {
			$result = SOM_Channel_Etsy::create_receipt_shipment(
				(string) $order->external_order_id,
				$tracking,
				self::etsy_carrier_name( (string) $shipment->carrier ),
				(string) $shipment->shipped_at
			);
		} elseif ( 'external' === $slug ) {
			// External/test orders: mark pushed locally without a marketplace call.
			$result = true;
		} else {
			$result = new WP_Error(
				'som_shipment_push',
				__( 'Tracking push is not supported for this channel.', 'order-machine' )
			);
		}

		if ( is_wp_error( $result ) ) {
			self::set_push_state( $order_id, null, $result->get_error_message() );
			return $result;
		}

		self::set_push_state( $order_id, current_time( 'mysql', true ), null );
		return true;
	}

	/**
	 * After a Ship step completes: push if tracking present and not yet pushed.
	 *
	 * @param int $order_id Order PK.
	 * @return void
	 */
	public static function maybe_push_after_ship( $order_id ) {
		$shipment = self::get_by_order( (int) $order_id );
		if ( ! $shipment ) {
			return;
		}
		$tracking = trim( (string) ( $shipment->tracking_number ?? '' ) );
		if ( '' === $tracking || ! empty( $shipment->tracking_pushed_at ) ) {
			return;
		}
		self::push_tracking( (int) $order_id );
	}

	/**
	 * @param int         $order_id Order PK.
	 * @param string|null $pushed_at GMT datetime or null.
	 * @param string|null $error    Error message or null.
	 * @return void
	 */
	private static function set_push_state( $order_id, $pushed_at, $error ) {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$wpdb->update(
			SOM_DB::table( 'shipments' ),
			array(
				'tracking_pushed_at'  => $pushed_at,
				'tracking_push_error' => $error,
				'updated_at'          => $now,
			),
			array( 'order_id' => (int) $order_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Order IDs in a list missing a required shipment (for batch errors).
	 *
	 * @param int[] $order_ids Order PKs.
	 * @return int[]
	 */
	public static function missing_order_ids( array $order_ids ) {
		$missing = array();
		foreach ( $order_ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 && ! self::has_required( $id ) ) {
				$missing[] = $id;
			}
		}
		return $missing;
	}

	/**
	 * Compact summary for MCP / REST (no secrets).
	 *
	 * @param int $order_id Order PK.
	 * @return array<string, mixed>|null
	 */
	public static function summary_for_api( $order_id ) {
		$row = self::get_by_order( (int) $order_id );
		if ( ! $row ) {
			return null;
		}

		$proof_url = null;
		if ( ! empty( $row->proof_attachment_id ) && current_user_can( 'manage_options' ) ) {
			$url = wp_get_attachment_url( (int) $row->proof_attachment_id );
			$proof_url = $url ? $url : null;
		}

		return array(
			'carrier'             => (string) $row->carrier,
			'service'             => (string) $row->service,
			'shipped_at'          => (string) $row->shipped_at,
			'postage_paid'        => (float) $row->postage_paid,
			'tracking_number'     => $row->tracking_number ? (string) $row->tracking_number : null,
			'click_and_drop_ref'  => $row->click_and_drop_ref ? (string) $row->click_and_drop_ref : null,
			'tracking_pushed_at'  => $row->tracking_pushed_at ? (string) $row->tracking_pushed_at : null,
			'tracking_push_error' => $row->tracking_push_error ? (string) $row->tracking_push_error : null,
			'status'              => self::status_key( (int) $order_id ),
			'proof_attachment_id' => ! empty( $row->proof_attachment_id ) ? (int) $row->proof_attachment_id : null,
			'proof_url'           => $proof_url,
		);
	}

	/**
	 * Date (Y-m-d) for form input from stored UTC datetime.
	 *
	 * @param string $shipped_at UTC MySQL datetime.
	 * @return string
	 */
	public static function shipped_at_date_local( $shipped_at ) {
		$shipped_at = (string) $shipped_at;
		if ( '' === $shipped_at ) {
			return current_time( 'Y-m-d' );
		}
		$ts = strtotime( $shipped_at . ' UTC' );
		if ( ! $ts ) {
			$ts = strtotime( $shipped_at );
		}
		if ( ! $ts ) {
			return current_time( 'Y-m-d' );
		}
		return wp_date( 'Y-m-d', $ts );
	}
}
