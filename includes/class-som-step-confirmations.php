<?php
/**
 * Workflow confirmation checklist helpers.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Confirmation step kinds, marketplace URLs, and tick persistence.
 */
class SOM_Step_Confirmations {

	const KIND_PRINT_VS_REQUEST  = 'print_vs_request';
	const KIND_SHIPPING_ADDRESS  = 'shipping_address';
	const KIND_PACKING_ITEMS     = 'packing_items';

	/**
	 * Allowed confirmation_kind values.
	 *
	 * @return string[]
	 */
	public static function kinds() {
		return array(
			self::KIND_PRINT_VS_REQUEST,
			self::KIND_SHIPPING_ADDRESS,
			self::KIND_PACKING_ITEMS,
		);
	}

	/**
	 * Labels for the workflow step editor dropdown.
	 *
	 * @return array<string, string>
	 */
	public static function kind_choices() {
		return array(
			self::KIND_PRINT_VS_REQUEST => __( 'Print vs client request', 'order-machine' ),
			self::KIND_SHIPPING_ADDRESS => __( 'Shipping address vs marketplace', 'order-machine' ),
			self::KIND_PACKING_ITEMS    => __( 'Packing items', 'order-machine' ),
		);
	}

	/**
	 * Normalize a stored or form confirmation_kind.
	 *
	 * @param mixed $kind Raw value.
	 * @return string|null
	 */
	public static function sanitize_kind( $kind ) {
		$kind = is_string( $kind ) ? sanitize_key( $kind ) : '';
		if ( '' === $kind || ! in_array( $kind, self::kinds(), true ) ) {
			return null;
		}
		return $kind;
	}

	/**
	 * Whether a step row has a confirmation gate.
	 *
	 * @param object|array|null $step Step row.
	 * @return bool
	 */
	public static function has_confirmation( $step ) {
		return null !== self::kind_from_step( $step );
	}

	/**
	 * @param object|array|null $step Step.
	 * @return string|null
	 */
	public static function kind_from_step( $step ) {
		if ( is_array( $step ) ) {
			$kind = isset( $step['confirmation_kind'] ) ? $step['confirmation_kind'] : null;
		} elseif ( is_object( $step ) ) {
			$kind = isset( $step->confirmation_kind ) ? $step->confirmation_kind : null;
		} else {
			return null;
		}
		return self::sanitize_kind( $kind );
	}

	/**
	 * Decode confirmation_state JSON from a progress row.
	 *
	 * @param object|string|array|null $progress_or_json Progress row or raw JSON.
	 * @return array<string, mixed>
	 */
	public static function decode_state( $progress_or_json ) {
		$raw = $progress_or_json;
		if ( is_object( $progress_or_json ) ) {
			$raw = isset( $progress_or_json->confirmation_state ) ? $progress_or_json->confirmation_state : null;
		}
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Whether confirmation_state satisfies the kind for this order.
	 *
	 * @param string               $kind    Confirmation kind.
	 * @param array<string, mixed> $state   Decoded state.
	 * @param object               $order   Order from SOM_Orders::get().
	 * @return bool
	 */
	public static function is_complete( $kind, array $state, $order ) {
		$kind = self::sanitize_kind( $kind );
		if ( null === $kind || ! $order ) {
			return false;
		}

		if ( self::KIND_PRINT_VS_REQUEST === $kind ) {
			return ! empty( $state['print_matches_request'] );
		}

		if ( self::KIND_SHIPPING_ADDRESS === $kind ) {
			return ! empty( $state['address_matches_marketplace'] );
		}

		if ( self::KIND_PACKING_ITEMS === $kind ) {
			$items = isset( $order->items ) && is_array( $order->items ) ? $order->items : array();
			if ( empty( $items ) ) {
				return false;
			}
			$checked = isset( $state['items'] ) && is_array( $state['items'] ) ? $state['items'] : array();
			foreach ( $items as $item ) {
				$id = (string) (int) $item->id;
				if ( empty( $checked[ $id ] ) ) {
					return false;
				}
			}
			return true;
		}

		return false;
	}

	/**
	 * Whether the current step on an order can advance past its confirmation gate.
	 *
	 * @param object $progress Progress row (may include confirmation_kind / confirmation_state).
	 * @param object $step     Workflow step row.
	 * @param object $order    Order row with items.
	 * @return bool
	 */
	public static function progress_is_complete( $progress, $step, $order ) {
		$kind = self::kind_from_step( $step );
		if ( null === $kind ) {
			return true;
		}
		return self::is_complete( $kind, self::decode_state( $progress ), $order );
	}

	/**
	 * Sanitize and persist ticks for the order's current confirmation step.
	 *
	 * @param int                  $order_id Order PK.
	 * @param array<string, mixed> $input    Posted ticks.
	 * @return true|WP_Error
	 */
	public static function save_for_order( $order_id, array $input ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$order    = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}
		if ( ! empty( $order->is_cancelled ) ) {
			return new WP_Error( 'som_order_cancelled', __( 'Cancelled orders cannot be confirmed.', 'order-machine' ) );
		}
		if ( ! empty( $order->is_complete ) ) {
			return new WP_Error( 'som_order_complete', __( 'Order workflow is already complete.', 'order-machine' ) );
		}

		$current_step_id = (int) $order->current_step_id;
		if ( $current_step_id < 1 ) {
			return new WP_Error( 'som_no_workflow', __( 'This order has no workflow assigned.', 'order-machine' ) );
		}

		$step = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE id = %d LIMIT 1',
				$current_step_id
			)
		);
		$kind = self::kind_from_step( $step );
		if ( null === $kind ) {
			return new WP_Error( 'som_no_confirmation', __( 'The current step has no confirmation checklist.', 'order-machine' ) );
		}

		$progress = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . SOM_DB::table( 'order_step_progress' ) . ' WHERE order_id = %d AND workflow_step_id = %d LIMIT 1',
				$order_id,
				$current_step_id
			)
		);
		if ( ! $progress ) {
			return new WP_Error( 'som_step_missing', __( 'Current workflow step not found.', 'order-machine' ) );
		}

		$state = self::build_state_from_input( $kind, $input, $order );
		$json  = wp_json_encode( $state );
		if ( false === $json ) {
			return new WP_Error( 'som_confirm_encode', __( 'Could not encode confirmation state.', 'order-machine' ) );
		}

		$updated = $wpdb->update(
			SOM_DB::table( 'order_step_progress' ),
			array( 'confirmation_state' => $json ),
			array( 'id' => (int) $progress->id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			return new WP_Error( 'som_confirm_save', __( 'Could not save confirmation checklist.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Build a sanitized confirmation_state from form/REST input.
	 *
	 * @param string               $kind  Kind.
	 * @param array<string, mixed> $input Input.
	 * @param object               $order Order.
	 * @return array<string, mixed>
	 */
	public static function build_state_from_input( $kind, array $input, $order ) {
		$kind = self::sanitize_kind( $kind );
		if ( self::KIND_PRINT_VS_REQUEST === $kind ) {
			return array(
				'print_matches_request' => ! empty( $input['print_matches_request'] ),
			);
		}
		if ( self::KIND_SHIPPING_ADDRESS === $kind ) {
			return array(
				'address_matches_marketplace' => ! empty( $input['address_matches_marketplace'] ),
			);
		}
		if ( self::KIND_PACKING_ITEMS === $kind ) {
			$posted = array();
			if ( isset( $input['items'] ) && is_array( $input['items'] ) ) {
				$posted = $input['items'];
			}
			$items_state = array();
			$items       = isset( $order->items ) && is_array( $order->items ) ? $order->items : array();
			foreach ( $items as $item ) {
				$id = (string) (int) $item->id;
				$items_state[ $id ] = ! empty( $posted[ $id ] ) || ! empty( $posted[ (int) $item->id ] );
			}
			return array( 'items' => $items_state );
		}
		return array();
	}

	/**
	 * Seller Hub / seller tools URL for the marketplace order.
	 *
	 * @param string $channel_slug ebay|etsy|….
	 * @param string $external_order_id Order/receipt ID.
	 * @return string|null
	 */
	public static function marketplace_order_url( $channel_slug, $external_order_id ) {
		$slug = sanitize_key( (string) $channel_slug );
		$id   = trim( (string) $external_order_id );
		if ( '' === $id ) {
			return null;
		}
		if ( 'ebay' === $slug ) {
			return 'https://www.ebay.co.uk/sh/ord/details?orderid=' . rawurlencode( $id );
		}
		if ( 'etsy' === $slug ) {
			return 'https://www.etsy.com/your/orders/' . rawurlencode( $id );
		}
		return null;
	}

	/**
	 * Public listing URL when listing ID is known.
	 *
	 * @param string $channel_slug ebay|etsy.
	 * @param string $external_listing_id Listing/item ID.
	 * @return string|null
	 */
	public static function marketplace_listing_url( $channel_slug, $external_listing_id ) {
		$slug = sanitize_key( (string) $channel_slug );
		$id   = trim( (string) $external_listing_id );
		if ( '' === $id ) {
			return null;
		}
		if ( 'ebay' === $slug ) {
			return 'https://www.ebay.co.uk/itm/' . rawurlencode( $id );
		}
		if ( 'etsy' === $slug ) {
			return 'https://www.etsy.com/listing/' . rawurlencode( $id );
		}
		return null;
	}

	/**
	 * Best-effort listing ID for a line item (column, else raw_payload).
	 *
	 * @param object $item  Order item row.
	 * @param object $order Order with channel_slug + raw_payload.
	 * @return string
	 */
	public static function listing_id_for_item( $item, $order ) {
		if ( ! empty( $item->external_listing_id ) ) {
			return trim( (string) $item->external_listing_id );
		}
		return self::extract_listing_id_from_payload( $order, $item );
	}

	/**
	 * Dig listing ID from stored raw_payload when the column is empty.
	 *
	 * @param object      $order Order.
	 * @param object|null $item  Optional line to match by personalisation/qty.
	 * @return string
	 */
	public static function extract_listing_id_from_payload( $order, $item = null ) {
		if ( ! $order || empty( $order->raw_payload ) ) {
			return '';
		}
		$data = is_string( $order->raw_payload ) ? json_decode( (string) $order->raw_payload, true ) : $order->raw_payload;
		if ( ! is_array( $data ) ) {
			return '';
		}

		$slug = isset( $order->channel_slug ) ? sanitize_key( (string) $order->channel_slug ) : '';

		if ( 'ebay' === $slug ) {
			$lines = isset( $data['lineItems'] ) && is_array( $data['lineItems'] ) ? $data['lineItems'] : array();
			foreach ( $lines as $line ) {
				if ( ! is_array( $line ) ) {
					continue;
				}
				$id = ! empty( $line['legacyItemId'] ) ? (string) $line['legacyItemId'] : '';
				if ( '' === $id && ! empty( $line['sku'] ) ) {
					$id = (string) $line['sku'];
				}
				if ( '' === $id ) {
					continue;
				}
				if ( null === $item ) {
					return $id;
				}
				// Prefer first listing id when single-item orders; otherwise return first non-empty.
				return $id;
			}
		}

		if ( 'etsy' === $slug ) {
			$txns = isset( $data['transactions'] ) && is_array( $data['transactions'] ) ? $data['transactions'] : array();
			foreach ( $txns as $txn ) {
				if ( ! is_array( $txn ) ) {
					continue;
				}
				$id = ! empty( $txn['listing_id'] ) ? (string) $txn['listing_id'] : '';
				if ( '' !== $id ) {
					return $id;
				}
			}
		}

		return '';
	}

	/**
	 * Human label for a packing line checkbox.
	 *
	 * @param object $item Order item.
	 * @return string
	 */
	public static function packing_item_label( $item ) {
		$qty   = max( 1, (int) ( $item->quantity ?? 1 ) );
		$name  = ! empty( $item->product_name ) ? (string) $item->product_name : __( 'Unmatched item', 'order-machine' );
		$label = sprintf(
			/* translators: 1: quantity, 2: product name */
			__( '%1$d× %2$s', 'order-machine' ),
			$qty,
			$name
		);
		$person = isset( $item->personalisation_text ) ? trim( (string) $item->personalisation_text ) : '';
		if ( '' !== $person ) {
			$label .= ' — “' . $person . '”';
		}
		return $label;
	}
}
