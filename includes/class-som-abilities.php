<?php
/**
 * Read-only WordPress Abilities for MCP (Sprint 11 / Phase 12 + U7).
 *
 * Registers only when Settings → MCP is enabled. Never exposes channel credentials.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers Order Machine Abilities when MCP toggle is on.
 */
class SOM_Abilities {

	const CATEGORY = 'order-machine';

	/**
	 * Wire category + ability hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = SOM_Settings::get();
		return ! empty( $settings['mcp_enabled'] );
	}

	/**
	 * @return void
	 */
	public static function register_category() {
		if ( ! self::is_enabled() || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Order Machine', 'order-machine' ),
				'description' => __( 'Read-only Order Machine orders, catalogue, purchasing, batches, listings, and media.', 'order-machine' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function register_abilities() {
		if ( ! self::is_enabled() || ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$meta = array(
			'mcp' => array(
				'public' => true,
				'type'   => 'tool',
			),
		);

		wp_register_ability(
			'order-machine/get-orders',
			array(
				'label'               => __( 'Get orders', 'order-machine' ),
				'description'         => __( 'List/filter Order Machine orders by status, channel, date, or search.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status'    => array(
							'type'        => 'string',
							'description' => 'Filter: open, complete, needs_mapping, cancelled, needs_workflow, or empty for all.',
						),
						'channel'   => array(
							'type'        => 'string',
							'description' => 'Channel slug: ebay, etsy, external.',
						),
						'date_from' => array( 'type' => 'string', 'description' => 'Y-m-d' ),
						'date_to'   => array( 'type' => 'string', 'description' => 'Y-m-d' ),
						's'         => array( 'type' => 'string', 'description' => 'Search buyer name or external order ID.' ),
						'paged'     => array( 'type' => 'integer', 'default' => 1 ),
						'per_page'  => array( 'type' => 'integer', 'default' => 20 ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_orders' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-order-detail',
			array(
				'label'               => __( 'Get order detail', 'order-machine' ),
				'description'         => __( 'Full order detail including items, personalisation, address, and workflow progress.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'order_id' => array( 'type' => 'integer', 'description' => 'Order Machine order ID.' ),
					),
					'required'   => array( 'order_id' ),
				),
				'execute_callback'    => array( __CLASS__, 'get_order_detail' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-products',
			array(
				'label'               => __( 'Get products', 'order-machine' ),
				'description'         => __( 'Product catalogue including material recipes.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status'  => array( 'type' => 'string', 'default' => 'active' ),
						's'       => array( 'type' => 'string' ),
						'paged'   => array( 'type' => 'integer', 'default' => 1 ),
						'per_page'=> array( 'type' => 'integer', 'default' => 50 ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_products' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-materials',
			array(
				'label'               => __( 'Get materials', 'order-machine' ),
				'description'         => __( 'Current material stock levels.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status'  => array( 'type' => 'string', 'default' => 'active' ),
						's'       => array( 'type' => 'string' ),
						'paged'   => array( 'type' => 'integer', 'default' => 1 ),
						'per_page'=> array( 'type' => 'integer', 'default' => 50 ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_materials' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-listings',
			array(
				'label'               => __( 'Get listings', 'order-machine' ),
				'description'         => __( 'Cached listing data (price, quantity, channel). Credentials never included.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'channel' => array( 'type' => 'string' ),
						's'       => array( 'type' => 'string' ),
						'paged'   => array( 'type' => 'integer', 'default' => 1 ),
						'per_page'=> array( 'type' => 'integer', 'default' => 50 ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_listings' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-media',
			array(
				'label'               => __( 'Get media', 'order-machine' ),
				'description'         => __( 'WordPress media library items (full library; install treated as dedicated).', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'   => array( 'type' => 'string' ),
						'per_page' => array( 'type' => 'integer', 'default' => 20, 'maximum' => 100 ),
						'paged'    => array( 'type' => 'integer', 'default' => 1 ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_media' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-suppliers',
			array(
				'label'               => __( 'Get suppliers', 'order-machine' ),
				'description'         => __( 'List/filter material suppliers.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						's'        => array( 'type' => 'string' ),
						'paged'    => array( 'type' => 'integer', 'default' => 1 ),
						'per_page' => array( 'type' => 'integer', 'default' => 20 ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_suppliers' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-purchase-orders',
			array(
				'label'               => __( 'Get purchase orders', 'order-machine' ),
				'description'         => __( 'List/filter purchase orders (header fields; use get-purchase-order for lines).', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status'      => array( 'type' => 'string', 'description' => 'ordered, partially_received, received, cancelled.' ),
						'supplier_id' => array( 'type' => 'integer' ),
						's'           => array( 'type' => 'string' ),
						'paged'       => array( 'type' => 'integer', 'default' => 1 ),
						'per_page'    => array( 'type' => 'integer', 'default' => 20 ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_purchase_orders' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-purchase-order',
			array(
				'label'               => __( 'Get purchase order detail', 'order-machine' ),
				'description'         => __( 'One purchase order including line items and landed-cost fields.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'purchase_order_id' => array( 'type' => 'integer', 'description' => 'Purchase order ID.' ),
					),
					'required'   => array( 'purchase_order_id' ),
				),
				'execute_callback'    => array( __CLASS__, 'get_purchase_order' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-workflow-material-goals',
			array(
				'label'               => __( 'Get workflow material goals', 'order-machine' ),
				'description'         => __( 'Cost-ceiling goals for a workflow template or material.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'workflow_template_id' => array( 'type' => 'integer' ),
						'material_id'          => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_workflow_material_goals' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-batches',
			array(
				'label'               => __( 'Get batches', 'order-machine' ),
				'description'         => __( 'List step batches (open only by default).', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status'         => array( 'type' => 'string' ),
						'batch_group_id' => array( 'type' => 'integer' ),
						'include_done'   => array( 'type' => 'boolean', 'default' => false ),
						'paged'          => array( 'type' => 'integer', 'default' => 1 ),
						'per_page'       => array( 'type' => 'integer', 'default' => 50 ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_batches' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-batch',
			array(
				'label'               => __( 'Get batch detail', 'order-machine' ),
				'description'         => __( 'One batch including member orders.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'batch_id' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'batch_id' ),
				),
				'execute_callback'    => array( __CLASS__, 'get_batch' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-batch-groups',
			array(
				'label'               => __( 'Get batch groups', 'order-machine' ),
				'description'         => __( 'Configured batch groups (thank-you card, shipping label, etc.).', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'execute_callback'    => array( __CLASS__, 'get_batch_groups' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);
	}

	/**
	 * @param mixed $input Unused (Abilities API may pass input).
	 * @return bool
	 */
	public static function can_read( $input = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_orders( $input = null ) {
		$input = is_array( $input ) ? $input : array();
		$args  = array(
			'status'    => isset( $input['status'] ) ? (string) $input['status'] : '',
			'channel'   => isset( $input['channel'] ) ? (string) $input['channel'] : '',
			'date_from' => isset( $input['date_from'] ) ? (string) $input['date_from'] : '',
			'date_to'   => isset( $input['date_to'] ) ? (string) $input['date_to'] : '',
			's'         => isset( $input['s'] ) ? (string) $input['s'] : '',
			'paged'     => isset( $input['paged'] ) ? max( 1, (int) $input['paged'] ) : 1,
		);

		$result = SOM_Orders::query( $args );
		$orders = array();
		foreach ( $result['orders'] as $row ) {
			$orders[] = array(
				'id'                => (int) $row->id,
				'channel_slug'      => (string) $row->channel_slug,
				'channel_name'      => (string) $row->channel_name,
				'external_order_id' => (string) $row->external_order_id,
				'buyer_name'        => (string) $row->buyer_name,
				'order_date'        => (string) $row->order_date,
				'is_complete'       => (int) $row->is_complete,
				'current_step_name' => isset( $row->current_step_name ) ? (string) $row->current_step_name : '',
				'is_cancelled'      => ! empty( $row->is_cancelled ),
				'has_unmatched'     => ! empty( $row->unmatched_count ),
			);
		}

		return array(
			'orders' => $orders,
			'total'  => (int) $result['total'],
			'pages'  => (int) $result['pages'],
			'paged'  => (int) $result['paged'],
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_order_detail( $input = null ) {
		$input    = is_array( $input ) ? $input : array();
		$order_id = isset( $input['order_id'] ) ? (int) $input['order_id'] : 0;
		$order    = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_not_found', __( 'Order not found.', 'order-machine' ) );
		}

		$items = array();
		foreach ( $order->items as $item ) {
			$items[] = array(
				'id'                   => (int) $item->id,
				'product_id'           => null !== $item->product_id && '' !== $item->product_id ? (int) $item->product_id : null,
				'product_name'         => (string) ( $item->product_name ?? '' ),
				'quantity'             => (int) $item->quantity,
				'personalisation_text' => $item->personalisation_text,
				'unit_price'           => $item->unit_price,
			);
		}

		$progress = array();
		foreach ( $order->workflow_progress as $row ) {
			$progress[] = array(
				'workflow_step_id' => (int) $row->workflow_step_id,
				'step_name'        => (string) $row->step_name,
				'step_order'       => (int) $row->step_order,
				'status'           => (string) $row->status,
				'timer_ends_at'    => $row->timer_ends_at,
				'last_error'       => isset( $row->last_error ) ? (string) $row->last_error : '',
			);
		}

		$address = $order->shipping_address;
		if ( is_string( $address ) ) {
			$decoded = json_decode( $address, true );
			$address = is_array( $decoded ) ? $decoded : array();
		}

		return array(
			'id'                  => (int) $order->id,
			'channel_slug'        => (string) $order->channel_slug,
			'external_order_id'   => (string) $order->external_order_id,
			'buyer_name'          => (string) $order->buyer_name,
			'shipping_address'    => is_array( $address ) ? $address : array(),
			'formatted_address'   => SOM_Orders::format_address( $order->shipping_address ),
			'order_date'          => (string) $order->order_date,
			'is_complete'         => (int) $order->is_complete,
			'is_cancelled'        => ! empty( $order->is_cancelled ),
			'has_unmatched'       => ! empty( $order->has_unmatched ),
			'current_step_id'     => $order->current_step_id ? (int) $order->current_step_id : null,
			'current_step_name'   => (string) $order->current_step_name,
			'workflow_unassigned' => $order->workflow_unassigned,
			'items'               => $items,
			'workflow_progress'   => $progress,
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_products( $input = null ) {
		$input  = is_array( $input ) ? $input : array();
		$result = SOM_Products::query(
			array(
				'status'   => isset( $input['status'] ) ? (string) $input['status'] : 'active',
				's'        => isset( $input['s'] ) ? (string) $input['s'] : '',
				'paged'    => isset( $input['paged'] ) ? max( 1, (int) $input['paged'] ) : 1,
				'per_page' => isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 50,
			)
		);

		$products = array();
		foreach ( $result['products'] as $row ) {
			$recipe  = SOM_Products::get_recipe( (int) $row->id );
			$costing = SOM_Products::recipe_costing( (int) $row->id );
			if ( ! is_array( $costing ) ) {
				$costing = array();
			}
			$lines = array();
			foreach ( $recipe as $line ) {
				$lines[] = array(
					'material_id'       => (int) $line->material_id,
					'material_name'     => (string) $line->material_name,
					'quantity_per_unit' => (float) $line->quantity_per_unit,
					'unit'              => (string) ( $line->material_unit ?? '' ),
				);
			}
			$products[] = array(
				'id'                   => (int) $row->id,
				'name'                 => (string) $row->name,
				'sku'                  => (string) $row->sku,
				'is_active'            => (int) $row->is_active,
				'workflow_template_id' => $row->workflow_template_id ? (int) $row->workflow_template_id : null,
				'target_selling_price' => isset( $costing['target_selling_price'] ) ? $costing['target_selling_price'] : null,
				'material_cost'        => isset( $costing['material_cost'] ) ? $costing['material_cost'] : null,
				'platform_fees'        => isset( $costing['platform_fees'] ) ? $costing['platform_fees'] : null,
				'fee_source'           => isset( $costing['fee_source'] ) ? (string) $costing['fee_source'] : 'none',
				'profit'               => isset( $costing['profit'] ) ? $costing['profit'] : null,
				'margin_percent'       => isset( $costing['margin_percent'] ) ? $costing['margin_percent'] : null,
				'recipe'               => $lines,
			);
		}

		return array(
			'products' => $products,
			'total'    => (int) $result['total'],
			'pages'    => (int) $result['pages'],
			'paged'    => (int) $result['paged'],
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_materials( $input = null ) {
		$input  = is_array( $input ) ? $input : array();
		$result = SOM_Materials::query(
			array(
				'status'   => isset( $input['status'] ) ? (string) $input['status'] : 'active',
				's'        => isset( $input['s'] ) ? (string) $input['s'] : '',
				'paged'    => isset( $input['paged'] ) ? max( 1, (int) $input['paged'] ) : 1,
				'per_page' => isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 50,
			)
		);

		$materials = array();
		foreach ( $result['materials'] as $row ) {
			$materials[] = array(
				'id'                    => (int) $row->id,
				'name'                  => (string) $row->name,
				'unit'                  => (string) $row->unit,
				'current_stock'         => (float) $row->current_stock,
				'low_stock_threshold'   => (float) $row->low_stock_threshold,
				'is_active'             => (int) $row->is_active,
				'unit_cost'             => null !== $row->unit_cost && '' !== $row->unit_cost ? (float) $row->unit_cost : null,
				'weighted_average'      => isset( $row->weighted_average ) ? (float) $row->weighted_average : null,
				'total_value_on_hand'   => null !== $row->total_value_on_hand && '' !== $row->total_value_on_hand
					? (float) $row->total_value_on_hand
					: null,
				'preferred_supplier_id' => ! empty( $row->preferred_supplier_id ) ? (int) $row->preferred_supplier_id : null,
				'goal_alert_level'      => isset( $row->goal_alert_level ) ? (string) $row->goal_alert_level : '',
			);
		}

		return array(
			'materials' => $materials,
			'total'     => (int) $result['total'],
			'pages'     => (int) $result['pages'],
			'paged'     => (int) $result['paged'],
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_listings( $input = null ) {
		$input  = is_array( $input ) ? $input : array();
		$result = SOM_Listings::query(
			array(
				'channel'  => isset( $input['channel'] ) ? (string) $input['channel'] : '',
				's'        => isset( $input['s'] ) ? (string) $input['s'] : '',
				'paged'    => isset( $input['paged'] ) ? max( 1, (int) $input['paged'] ) : 1,
				'per_page' => isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 50,
			)
		);

		$listings = array();
		foreach ( $result['listings'] as $row ) {
			$listings[] = array(
				'id'                  => (int) $row->id,
				'product_id'          => (int) $row->product_id,
				'product_name'        => isset( $row->product_name ) ? (string) $row->product_name : '',
				'channel_slug'        => (string) $row->channel_slug,
				'external_listing_id' => (string) $row->external_listing_id,
				'title'               => (string) ( $row->title ?? '' ),
				'price'               => $row->price,
				'quantity_available'  => (int) $row->quantity_available,
			);
		}

		return array(
			'listings' => $listings,
			'total'    => (int) $result['total'],
			'pages'    => (int) $result['pages'],
			'paged'    => (int) $result['paged'],
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_media( $input = null ) {
		$input    = is_array( $input ) ? $input : array();
		$per_page = isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 20;
		$paged    = isset( $input['paged'] ) ? max( 1, (int) $input['paged'] ) : 1;
		$search   = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';

		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => $per_page,
				'paged'          => $paged,
				's'              => $search,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'        => (int) $post->ID,
				'title'     => get_the_title( $post ),
				'url'       => wp_get_attachment_url( $post->ID ),
				'mime_type' => (string) $post->post_mime_type,
				'date'      => (string) $post->post_date_gmt,
			);
		}

		return array(
			'media' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
			'paged' => $paged,
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_suppliers( $input = null ) {
		$input  = is_array( $input ) ? $input : array();
		$result = SOM_Suppliers::query(
			array(
				's'        => isset( $input['s'] ) ? (string) $input['s'] : '',
				'paged'    => isset( $input['paged'] ) ? max( 1, (int) $input['paged'] ) : 1,
				'per_page' => isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 20,
			)
		);

		$suppliers = array();
		foreach ( $result['suppliers'] as $row ) {
			$suppliers[] = array(
				'id'           => (int) $row->id,
				'name'         => (string) $row->name,
				'website'      => isset( $row->website ) ? $row->website : null,
				'contact_info' => isset( $row->contact_info ) ? $row->contact_info : null,
				'notes'        => isset( $row->notes ) ? $row->notes : null,
			);
		}

		return array(
			'suppliers' => $suppliers,
			'total'     => (int) $result['total'],
			'pages'     => (int) $result['pages'],
			'paged'     => (int) $result['paged'],
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_purchase_orders( $input = null ) {
		$input  = is_array( $input ) ? $input : array();
		$result = SOM_Purchase_Orders::query(
			array(
				'status'      => isset( $input['status'] ) ? (string) $input['status'] : '',
				'supplier_id' => isset( $input['supplier_id'] ) ? (int) $input['supplier_id'] : 0,
				's'           => isset( $input['s'] ) ? (string) $input['s'] : '',
				'paged'       => isset( $input['paged'] ) ? max( 1, (int) $input['paged'] ) : 1,
				'per_page'    => isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 20,
			)
		);

		$orders = array();
		foreach ( $result['orders'] as $row ) {
			$orders[] = array(
				'id'            => (int) $row->id,
				'supplier_id'   => (int) $row->supplier_id,
				'supplier_name' => isset( $row->supplier_name ) ? (string) $row->supplier_name : '',
				'order_date'    => (string) $row->order_date,
				'received_date' => isset( $row->received_date ) ? $row->received_date : null,
				'status'        => (string) $row->status,
				'shipping_cost' => null !== $row->shipping_cost && '' !== $row->shipping_cost ? (float) $row->shipping_cost : 0.0,
				'other_cost'    => null !== $row->other_cost && '' !== $row->other_cost ? (float) $row->other_cost : 0.0,
			);
		}

		return array(
			'purchase_orders' => $orders,
			'total'           => (int) $result['total'],
			'pages'           => (int) $result['pages'],
			'paged'           => (int) $result['paged'],
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_purchase_order( $input = null ) {
		$input = is_array( $input ) ? $input : array();
		$id    = isset( $input['purchase_order_id'] ) ? (int) $input['purchase_order_id'] : 0;
		$order = SOM_Purchase_Orders::get( $id );
		if ( ! $order ) {
			return new WP_Error( 'som_po_missing', __( 'Purchase order not found.', 'order-machine' ) );
		}

		$items = array();
		foreach ( $order->items as $item ) {
			$items[] = array(
				'id'                => (int) $item->id,
				'material_id'       => (int) $item->material_id,
				'material_name'     => isset( $item->material_name ) ? (string) $item->material_name : '',
				'quantity_ordered'  => (float) $item->quantity_ordered,
				'quantity_received' => null !== $item->quantity_received && '' !== $item->quantity_received
					? (float) $item->quantity_received
					: 0.0,
				'item_cost'         => (float) $item->item_cost,
				'landed_unit_cost'  => null !== $item->landed_unit_cost && '' !== $item->landed_unit_cost
					? (float) $item->landed_unit_cost
					: null,
			);
		}

		return array(
			'id'            => (int) $order->id,
			'supplier_id'   => (int) $order->supplier_id,
			'supplier_name' => isset( $order->supplier_name ) ? (string) $order->supplier_name : '',
			'order_date'    => (string) $order->order_date,
			'received_date' => isset( $order->received_date ) ? $order->received_date : null,
			'status'        => (string) $order->status,
			'shipping_cost' => (float) $order->shipping_cost,
			'other_cost'    => (float) $order->other_cost,
			'notes'         => isset( $order->notes ) ? $order->notes : null,
			'items'         => $items,
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_workflow_material_goals( $input = null ) {
		$input       = is_array( $input ) ? $input : array();
		$workflow_id = isset( $input['workflow_template_id'] ) ? (int) $input['workflow_template_id'] : 0;
		$material_id = isset( $input['material_id'] ) ? (int) $input['material_id'] : 0;

		if ( $workflow_id > 0 ) {
			$rows = SOM_Workflow_Material_Goals::list_for_workflow( $workflow_id );
		} elseif ( $material_id > 0 ) {
			$rows = SOM_Workflow_Material_Goals::list_for_material( $material_id );
		} else {
			return new WP_Error( 'som_goals_filter', __( 'Provide workflow_template_id or material_id.', 'order-machine' ) );
		}

		$goals = array();
		foreach ( $rows as $row ) {
			$goals[] = array(
				'id'                        => (int) $row->id,
				'workflow_template_id'      => (int) $row->workflow_template_id,
				'material_id'               => (int) $row->material_id,
				'goal_unit_cost'            => (float) $row->goal_unit_cost,
				'warning_threshold_percent' => (float) $row->warning_threshold_percent,
				'material_name'             => isset( $row->material_name ) ? (string) $row->material_name : '',
				'workflow_name'             => isset( $row->workflow_name ) ? (string) $row->workflow_name : '',
			);
		}

		return array( 'goals' => $goals );
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_batches( $input = null ) {
		$input = is_array( $input ) ? $input : array();
		$result = SOM_Batches::query(
			array(
				'status'         => isset( $input['status'] ) ? (string) $input['status'] : '',
				'batch_group_id' => isset( $input['batch_group_id'] ) ? (int) $input['batch_group_id'] : 0,
				'include_done'   => ! empty( $input['include_done'] ),
				'paged'          => isset( $input['paged'] ) ? max( 1, (int) $input['paged'] ) : 1,
				'per_page'       => isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 50,
			)
		);

		$batches = array();
		foreach ( $result['batches'] as $row ) {
			$batches[] = array(
				'id'             => (int) $row->id,
				'batch_group_id' => (int) $row->batch_group_id,
				'group_name'     => isset( $row->group_name ) ? (string) $row->group_name : '',
				'group_key'      => isset( $row->group_key ) ? (string) $row->group_key : '',
				'status'         => (string) $row->status,
				'item_count'     => isset( $row->item_count ) ? (int) $row->item_count : 0,
				'action_type'    => isset( $row->group_action_type ) ? (string) $row->group_action_type : '',
			);
		}

		return array(
			'batches' => $batches,
			'total'   => (int) $result['total'],
			'pages'   => (int) $result['pages'],
			'paged'   => (int) $result['paged'],
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_batch( $input = null ) {
		$input   = is_array( $input ) ? $input : array();
		$batch_id = isset( $input['batch_id'] ) ? (int) $input['batch_id'] : 0;
		$batch    = SOM_Batches::get( $batch_id );
		if ( ! $batch ) {
			return new WP_Error( 'som_batch_missing', __( 'Batch not found.', 'order-machine' ) );
		}

		$group = SOM_Batch_Groups::get( (int) $batch->batch_group_id );
		$members = array();
		foreach ( SOM_Batches::get_items_with_orders( $batch_id ) as $item ) {
			$members[] = array(
				'order_id'          => (int) $item->order_id,
				'external_order_id' => isset( $item->external_order_id ) ? (string) $item->external_order_id : '',
				'buyer_name'        => isset( $item->buyer_name ) ? (string) $item->buyer_name : '',
			);
		}

		return array(
			'id'             => (int) $batch->id,
			'batch_group_id' => (int) $batch->batch_group_id,
			'group_name'     => $group ? (string) $group->display_name : '',
			'group_key'      => $group ? (string) $group->key : '',
			'action_type'    => $group ? (string) $group->action_type : '',
			'status'         => (string) $batch->status,
			'last_error'     => isset( $batch->last_error ) ? $batch->last_error : null,
			'members'        => $members,
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_batch_groups( $input = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$groups = array();
		foreach ( SOM_Batch_Groups::list_all() as $row ) {
			$groups[] = array(
				'id'           => (int) $row->id,
				'key'          => (string) $row->key,
				'display_name' => (string) $row->display_name,
				'batch_size'   => (int) $row->batch_size,
				'action_type'  => (string) $row->action_type,
			);
		}
		return array( 'batch_groups' => $groups );
	}
}
