<?php
/**
 * Internal product production jobs (make-to-stock).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Produce N on the Internal channel; credit linked material on workflow complete.
 */
class SOM_Production {

	const CHANNEL_SLUG = 'internal';

	/**
	 * Action fired when an order workflow becomes complete.
	 *
	 * @var string
	 */
	const HOOK_ORDER_COMPLETED = 'som_order_completed';

	/**
	 * Register completion listener.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::HOOK_ORDER_COMPLETED, array( __CLASS__, 'on_order_completed' ), 10, 1 );
	}

	/**
	 * Create a production order for an internal product (one line, quantity N).
	 *
	 * Reserves input materials and funds input budgets via the normal create path.
	 *
	 * @param int $product_id Internal product PK.
	 * @param int $quantity   Units to produce (N ≥ 1).
	 * @return int|WP_Error New order ID.
	 */
	public static function produce( $product_id, $quantity ) {
		$product_id = (int) $product_id;
		$quantity   = (int) $quantity;

		if ( $product_id < 1 ) {
			return new WP_Error( 'som_produce_product', __( 'Invalid product.', 'order-machine' ) );
		}
		if ( $quantity < 1 ) {
			return new WP_Error( 'som_produce_qty', __( 'Produce quantity must be at least 1.', 'order-machine' ) );
		}

		$product = SOM_Products::get( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'som_produce_product', __( 'Product not found.', 'order-machine' ) );
		}
		if ( empty( $product->is_internal ) ) {
			return new WP_Error(
				'som_produce_not_internal',
				__( 'Only internal products can be produced.', 'order-machine' )
			);
		}
		if ( empty( $product->is_active ) ) {
			return new WP_Error(
				'som_produce_inactive',
				__( 'Cannot produce an inactive product.', 'order-machine' )
			);
		}
		if ( empty( $product->workflow_template_id ) ) {
			return new WP_Error(
				'som_produce_workflow',
				__( 'Assign a workflow template before producing.', 'order-machine' )
			);
		}
		if ( empty( $product->linked_material_id ) ) {
			$linked = SOM_Products::ensure_linked_material( $product_id );
			if ( is_wp_error( $linked ) ) {
				return $linked;
			}
			$product = SOM_Products::get( $product_id );
		}
		if ( empty( $product->linked_material_id ) ) {
			return new WP_Error(
				'som_produce_linked',
				__( 'Internal product has no linked output material.', 'order-machine' )
			);
		}

		$recipe = SOM_Products::get_recipe( $product_id );
		if ( empty( $recipe ) ) {
			return new WP_Error(
				'som_produce_recipe',
				__( 'Add a material recipe (inputs) before producing.', 'order-machine' )
			);
		}

		$external_id = sprintf(
			'PROD-%d-%s-%s',
			$product_id,
			gmdate( 'YmdHis' ),
			wp_generate_password( 4, false, false )
		);

		return SOM_Order_Sync::create_from_external(
			array(
				'channel'           => self::CHANNEL_SLUG,
				'external_order_id' => $external_id,
				'buyer_name'        => __( 'Production', 'order-machine' ),
				'shipping_address'  => array(),
				'items'             => array(
					array(
						'product_id' => $product_id,
						'quantity'   => $quantity,
						'unit_price' => 0,
						'sku'        => $product->sku ? (string) $product->sku : '',
					),
				),
				'raw_payload'       => array(
					'kind'       => 'production',
					'product_id' => $product_id,
					'quantity'   => $quantity,
				),
			)
		);
	}

	/**
	 * Credit linked output material when a production order completes.
	 *
	 * @param int $order_id Order PK.
	 * @return void
	 */
	public static function on_order_completed( $order_id ) {
		$order_id = (int) $order_id;
		if ( $order_id < 1 ) {
			return;
		}

		$result = self::credit_output_for_order( $order_id );
		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[Order Machine] Production output credit failed for order %d: %s',
					$order_id,
					$result->get_error_message()
				)
			);
		}
	}

	/**
	 * Credit linked material for a completed internal production order (idempotent).
	 *
	 * @param int $order_id Order PK.
	 * @return true|WP_Error
	 */
	public static function credit_output_for_order( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$order    = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_produce_order', __( 'Order not found.', 'order-machine' ) );
		}
		if ( empty( $order->is_complete ) ) {
			return new WP_Error( 'som_produce_incomplete', __( 'Order is not complete.', 'order-machine' ) );
		}
		if ( ! empty( $order->is_cancelled ) ) {
			return true;
		}

		$channel_slug = isset( $order->channel_slug ) ? (string) $order->channel_slug : '';
		if ( self::CHANNEL_SLUG !== $channel_slug ) {
			return true;
		}

		$product_id = SOM_Workflow_Engine::primary_product_id( $order );
		if ( $product_id < 1 ) {
			return true;
		}

		$product = SOM_Products::get( $product_id );
		if ( ! $product || empty( $product->is_internal ) || empty( $product->linked_material_id ) ) {
			return true;
		}

		$log_t = SOM_DB::table( 'material_stock_log' );
		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$log_t} WHERE order_id = %d AND reason = %s",
				$order_id,
				'production_output'
			)
		);
		if ( $existing > 0 ) {
			return true;
		}

		// Inputs must already be reserved (create path reserves before assign).
		$reserved = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$log_t} WHERE order_id = %d AND reason = %s",
				$order_id,
				'new_order'
			)
		);
		if ( $reserved < 1 ) {
			return true;
		}

		$qty = 0;
		if ( ! empty( $order->items ) && is_array( $order->items ) ) {
			foreach ( $order->items as $item ) {
				if ( (int) $item->product_id === $product_id ) {
					$qty += max( 0, (int) $item->quantity );
				}
			}
		}
		if ( $qty < 1 ) {
			return new WP_Error( 'som_produce_qty', __( 'Production order has no quantity to credit.', 'order-machine' ) );
		}

		$input_cogs = SOM_Analytics::order_material_cogs( $order_id );
		$unit_cost  = SOM_Material_Costing::round4( $input_cogs / $qty );
		$value      = SOM_Material_Costing::round4( $unit_cost * $qty );

		$result = SOM_Materials::adjust_stock(
			(int) $product->linked_material_id,
			(float) $qty,
			array(
				'order_id'          => $order_id,
				'reason'            => 'production_output',
				'unit_cost_at_time' => $unit_cost,
				'value_change'      => $value,
				'sync_unit_cost'    => true,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}
