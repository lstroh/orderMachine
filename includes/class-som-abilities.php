<?php
/**
 * Read-only WordPress Abilities for MCP (Sprint 11 / Phase 12).
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
				'description' => __( 'Read-only Order Machine orders, catalogue, listings, and media.', 'order-machine' ),
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
			$recipe = SOM_Products::get_recipe( (int) $row->id );
			$lines  = array();
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
				'id'                  => (int) $row->id,
				'name'                => (string) $row->name,
				'unit'                => (string) $row->unit,
				'current_stock'       => (float) $row->current_stock,
				'low_stock_threshold' => (float) $row->low_stock_threshold,
				'is_active'           => (int) $row->is_active,
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
}
