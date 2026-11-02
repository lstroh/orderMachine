<?php
/**
 * Read-only WordPress Abilities for MCP (Sprint 11 / Phase 12 + U7 + UP15-S1).
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
	 * Keys that must never appear on an ability payload.
	 *
	 * @var string[]
	 */
	const SECRET_KEYS = array(
		'credentials',
		'script_config',
		'command_template',
		'client_secret',
		'api_key',
		'raw_payload',
	);

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
				'description' => __( 'Read-only Order Machine orders, catalogue, purchasing, batches, listings, media, workflows, budgets, boards, trays, channels, and analytics.', 'order-machine' ),
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

		self::register_read_abilities( $meta );
	}

	/**
	 * Package 15 read-only abilities. Same category, meta, and permission as the rest.
	 *
	 * @param array<string, mixed> $meta Ability meta.
	 * @return void
	 */
	private static function register_read_abilities( array $meta ) {
		wp_register_ability(
			'order-machine/get-workflow-templates',
			array(
				'label'               => __( 'Get workflow templates', 'order-machine' ),
				'description'         => __( 'List workflow templates. Empty status returns every template, including inactive.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array(
							'type'        => 'string',
							'description' => 'active, inactive, or empty for all. Default empty.',
							'default'     => '',
						),
						'kind'     => array(
							'type'        => 'string',
							'description' => 'make, pack, post_ship, or empty for all.',
						),
						's'        => array( 'type' => 'string', 'description' => 'Search name or description.' ),
						'paged'    => array( 'type' => 'integer', 'default' => 1 ),
						'per_page' => array( 'type' => 'integer', 'default' => 20 ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_workflow_templates' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-workflow-template',
			array(
				'label'               => __( 'Get workflow template', 'order-machine' ),
				'description'         => __( 'One workflow template with its make-template products and steps. Step scripts are omitted.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'workflow_template_id' => array( 'type' => 'integer', 'description' => 'Workflow template ID.' ),
					),
					'required'   => array( 'workflow_template_id' ),
				),
				'execute_callback'    => array( __CLASS__, 'get_workflow_template' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-budgets',
			array(
				'label'               => __( 'Get budgets', 'order-machine' ),
				'description'         => __( 'List budgets. Defaults to active, matching the Budgets screen.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						's'        => array( 'type' => 'string', 'description' => 'Name search.' ),
						'type'     => array( 'type' => 'string', 'description' => 'material, manual, or empty for both.' ),
						'status'   => array(
							'type'        => 'string',
							'description' => 'active (default), inactive, or all.',
							'default'     => 'active',
						),
						'paged'    => array( 'type' => 'integer', 'default' => 1 ),
						'per_page' => array( 'type' => 'integer', 'default' => 20 ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_budgets' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-budget',
			array(
				'label'               => __( 'Get budget', 'order-machine' ),
				'description'         => __( 'One budget with its ledger and product and workflow link ids.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'budget_id' => array( 'type' => 'integer', 'description' => 'Budget ID.' ),
					),
					'required'   => array( 'budget_id' ),
				),
				'execute_callback'    => array( __CLASS__, 'get_budget' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-shipping-packages',
			array(
				'label'               => __( 'Get shipping packages', 'order-machine' ),
				'description'         => __( 'List shipping packages. Defaults to all, matching the packages screen.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						's'        => array( 'type' => 'string', 'description' => 'Name search.' ),
						'status'   => array(
							'type'        => 'string',
							'description' => 'all (default), active, or inactive.',
							'default'     => 'all',
						),
						'paged'    => array( 'type' => 'integer', 'default' => 1 ),
						'per_page' => array( 'type' => 'integer', 'default' => 20 ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_shipping_packages' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-shipping-package',
			array(
				'label'               => __( 'Get shipping package', 'order-machine' ),
				'description'         => __( 'One shipping package and its material lines.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'shipping_package_id' => array( 'type' => 'integer', 'description' => 'Shipping package ID.' ),
					),
					'required'   => array( 'shipping_package_id' ),
				),
				'execute_callback'    => array( __CLASS__, 'get_shipping_package' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-make-board',
			array(
				'label'               => __( 'Get Make board', 'order-machine' ),
				'description'         => __( 'Make board cards. Not paginated. Step scripts are omitted.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'channel'              => array( 'type' => 'string', 'description' => 'Channel slug.' ),
						'product_id'           => array( 'type' => 'integer' ),
						'workflow_template_id' => array( 'type' => 'integer' ),
						's'                    => array( 'type' => 'string', 'description' => 'Search.' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_make_board' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-pack-board',
			array(
				'label'               => __( 'Get Pack board', 'order-machine' ),
				'description'         => __( 'Pack board cards. Not paginated. Marketplace payloads are omitted.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'channel' => array( 'type' => 'string', 'description' => 'Channel slug. Internal is already excluded.' ),
						's'       => array( 'type' => 'string', 'description' => 'Search.' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_pack_board' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-post-ship-board',
			array(
				'label'               => __( 'Get Post-ship board', 'order-machine' ),
				'description'         => __( 'Post-ship board cards. Not paginated. Unknown scope stays the active default.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'scope'                => array(
							'type'        => 'string',
							'description' => 'active (default), unstarted, or complete.',
						),
						'channel'              => array( 'type' => 'string', 'description' => 'Channel slug.' ),
						'workflow_template_id' => array( 'type' => 'integer' ),
						's'                    => array( 'type' => 'string', 'description' => 'Search.' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_post_ship_board' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-trays',
			array(
				'label'               => __( 'Get trays', 'order-machine' ),
				'description'         => __( 'Site tray max, held trays, and free tray numbers.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'execute_callback'    => array( __CLASS__, 'get_trays' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-channels',
			array(
				'label'               => __( 'Get channels', 'order-machine' ),
				'description'         => __( 'Channel id, slug, name, active flag, and last sync. Credentials are never included.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'execute_callback'    => array( __CLASS__, 'get_channels' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-channel-fee-estimates',
			array(
				'label'               => __( 'Get channel fee estimates', 'order-machine' ),
				'description'         => __( 'Fee estimate rows grouped by channel.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'execute_callback'    => array( __CLASS__, 'get_channel_fee_estimates' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-recurring-platform-expenses',
			array(
				'label'               => __( 'Get recurring platform expenses', 'order-machine' ),
				'description'         => __( 'Recurring platform expenses with channel and listing names. No pages key.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'listing_id' => array( 'type' => 'integer' ),
						'channel_id' => array( 'type' => 'integer' ),
						'limit'      => array( 'type' => 'integer', 'default' => 100 ),
						'offset'     => array( 'type' => 'integer', 'default' => 0 ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_recurring_platform_expenses' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-analytics',
			array(
				'label'               => __( 'Get analytics', 'order-machine' ),
				'description'         => __( 'Analytics dashboard payload, including its timing section.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::analytics_input_schema(),
				'execute_callback'    => array( __CLASS__, 'get_analytics' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-analytics-timing',
			array(
				'label'               => __( 'Get analytics timing', 'order-machine' ),
				'description'         => __( 'Workflow timing section of the analytics dashboard.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::analytics_input_schema(),
				'execute_callback'    => array( __CLASS__, 'get_analytics_timing' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'order-machine/get-material-stock-log',
			array(
				'label'               => __( 'Get material stock log', 'order-machine' ),
				'description'         => __( 'Recent stock log rows for one material.', 'order-machine' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'material_id' => array( 'type' => 'integer', 'description' => 'Material ID.' ),
						'limit'       => array( 'type' => 'integer', 'default' => 10 ),
					),
					'required'   => array( 'material_id' ),
				),
				'execute_callback'    => array( __CLASS__, 'get_material_stock_log' ),
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
			'per_page'  => self::capped_limit( $input, 'per_page', SOM_Orders::PER_PAGE ),
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

		return self::without_secret_keys(
			array(
				'orders' => $orders,
				'total'  => (int) $result['total'],
				'pages'  => (int) $result['pages'],
				'paged'  => (int) $result['paged'],
			)
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
			$listing_id    = SOM_Step_Confirmations::listing_id_for_item( $item, $order );
			$make_progress = array();
			if ( isset( $item->make_progress ) && is_array( $item->make_progress ) ) {
				foreach ( $item->make_progress as $progress_row ) {
					$make_progress[] = self::map_make_progress_row( $progress_row );
				}
			}
			$items[] = array(
				'id'                   => (int) $item->id,
				'product_id'           => null !== $item->product_id && '' !== $item->product_id ? (int) $item->product_id : null,
				'product_name'         => (string) ( $item->product_name ?? '' ),
				'quantity'             => (int) $item->quantity,
				'personalisation_text' => $item->personalisation_text,
				'unit_price'           => $item->unit_price,
				'external_listing_id'  => '' !== $listing_id ? $listing_id : null,
				'listing_url'          => SOM_Step_Confirmations::marketplace_listing_url( (string) $order->channel_slug, $listing_id ),
				'make_progress'        => $make_progress,
			);
		}

		$progress = array();
		foreach ( $order->workflow_progress as $row ) {
			$progress[] = array(
				'workflow_step_id'    => (int) $row->workflow_step_id,
				'step_name'           => (string) $row->step_name,
				'step_order'          => (int) $row->step_order,
				'status'              => (string) $row->status,
				'timer_ends_at'       => $row->timer_ends_at,
				'last_error'          => isset( $row->last_error ) ? (string) $row->last_error : '',
				'confirmation_kind'    => SOM_Step_Confirmations::sanitize_kind(
					isset( $row->confirmation_kind ) ? $row->confirmation_kind : null
				),
				'confirmation_state'   => SOM_Step_Confirmations::decode_state( $row ),
				'workflow_template_id' => self::nullable_id( isset( $row->workflow_template_id ) ? $row->workflow_template_id : null ),
			);
		}

		$address = $order->shipping_address;
		if ( is_string( $address ) ) {
			$decoded = json_decode( $address, true );
			$address = is_array( $decoded ) ? $decoded : array();
		}

		return self::without_secret_keys(
			array(
				'id'                    => (int) $order->id,
				'channel_slug'          => (string) $order->channel_slug,
				'external_order_id'     => (string) $order->external_order_id,
				'marketplace_order_url' => SOM_Step_Confirmations::marketplace_order_url(
					(string) $order->channel_slug,
					(string) $order->external_order_id
				),
				'buyer_name'            => (string) $order->buyer_name,
				'shipping_address'      => is_array( $address ) ? $address : array(),
				'formatted_address'     => SOM_Orders::format_address( $order->shipping_address ),
				'shipment'              => SOM_Shipments::summary_for_api( (int) $order->id ),
				'order_date'            => (string) $order->order_date,
				'is_complete'           => (int) $order->is_complete,
				'is_cancelled'          => ! empty( $order->is_cancelled ),
				'has_unmatched'         => ! empty( $order->has_unmatched ),
				'current_step_id'       => $order->current_step_id ? (int) $order->current_step_id : null,
				'current_step_name'     => (string) $order->current_step_name,
				'workflow_unassigned'   => $order->workflow_unassigned,
				'pack_workflow_template_id' => self::nullable_id( isset( $order->pack_workflow_template_id ) ? $order->pack_workflow_template_id : null ),
				'post_ship_workflow_template_id' => self::nullable_id( isset( $order->post_ship_workflow_template_id ) ? $order->post_ship_workflow_template_id : null ),
				'post_ship_started_at'  => isset( $order->post_ship_started_at ) ? $order->post_ship_started_at : null,
				'pack_hold_reason'      => isset( $order->pack_hold_reason ) ? $order->pack_hold_reason : null,
				'shipping_package_id'   => self::nullable_id( isset( $order->shipping_package_id ) ? $order->shipping_package_id : null ),
				'tray_numbers'          => self::tray_numbers_for_order( (int) $order->id ),
				'notes'                 => self::map_order_notes( (int) $order->id ),
				'items'                 => $items,
				'workflow_progress'     => $progress,
			)
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
				'id'                             => (int) $row->id,
				'name'                           => (string) $row->name,
				'sku'                            => (string) $row->sku,
				'is_active'                      => (int) $row->is_active,
				'workflow_template_id'           => $row->workflow_template_id ? (int) $row->workflow_template_id : null,
				'workflow_template_name'         => isset( $row->workflow_name ) && null !== $row->workflow_name && '' !== $row->workflow_name
					? (string) $row->workflow_name
					: null,
				'pack_workflow_template_id'      => self::nullable_id( isset( $row->pack_workflow_template_id ) ? $row->pack_workflow_template_id : null ),
				'post_ship_workflow_template_id' => self::nullable_id( isset( $row->post_ship_workflow_template_id ) ? $row->post_ship_workflow_template_id : null ),
				'weight_grams'                   => self::nullable_number( isset( $row->weight_grams ) ? $row->weight_grams : null ),
				'package_id'                     => self::nullable_id( isset( $row->package_id ) ? $row->package_id : null ),
				'package_id_multi'               => self::nullable_id( isset( $row->package_id_multi ) ? $row->package_id_multi : null ),
				'planned_shipping_gbp'           => self::nullable_number( isset( $row->planned_shipping_gbp ) ? $row->planned_shipping_gbp : null ),
				'step_instruction_overrides'     => SOM_Step_Instructions::get_overrides_for_product( (int) $row->id ),
				'step_task_overrides'            => SOM_Step_Tasks::get_overrides_for_product( (int) $row->id ),
				'target_selling_price'           => isset( $costing['target_selling_price'] ) ? $costing['target_selling_price'] : null,
				'material_cost'                  => isset( $costing['material_cost'] ) ? $costing['material_cost'] : null,
				'platform_fees'                  => isset( $costing['platform_fees'] ) ? $costing['platform_fees'] : null,
				'fee_source'                     => isset( $costing['fee_source'] ) ? (string) $costing['fee_source'] : 'none',
				'profit'                         => isset( $costing['profit'] ) ? $costing['profit'] : null,
				'margin_percent'                 => isset( $costing['margin_percent'] ) ? $costing['margin_percent'] : null,
				'recipe'                         => $lines,
			);
		}

		return self::without_secret_keys(
			array(
				'products' => $products,
				'total'    => (int) $result['total'],
				'pages'    => (int) $result['pages'],
				'paged'    => (int) $result['paged'],
			)
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
				'description'         => isset( $row->description ) ? $row->description : null,
				'price'               => $row->price,
				'quantity_available'  => (int) $row->quantity_available,
				'last_synced_at'      => isset( $row->last_synced_at ) ? $row->last_synced_at : null,
			);
		}

		return self::without_secret_keys(
			array(
				'listings' => $listings,
				'total'    => (int) $result['total'],
				'pages'    => (int) $result['pages'],
				'paged'    => (int) $result['paged'],
			)
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

	/**
	 * @return array<string, mixed>
	 */
	private static function analytics_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'som_range'           => array(
					'type'        => 'string',
					'description' => '7, 30 (default), 90, year, or custom.',
					'default'     => '30',
				),
				'som_date_from'       => array( 'type' => 'string', 'description' => 'Y-m-d when som_range is custom.' ),
				'som_date_to'         => array( 'type' => 'string', 'description' => 'Y-m-d when som_range is custom.' ),
				'som_granularity'     => array(
					'type'        => 'string',
					'description' => 'daily (default), weekly, or monthly.',
					'default'     => 'daily',
				),
				'som_channel'         => array( 'type' => 'integer', 'description' => 'Channel id. 0 is every channel.', 'default' => 0 ),
				'som_materials'       => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'som_timing_kind'     => array( 'type' => 'string', 'description' => 'make (default) or pack.', 'default' => 'make' ),
				'som_timing_template' => array( 'type' => 'integer', 'description' => 'Template id. 0 keeps the timing fallback.', 'default' => 0 ),
			),
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_workflow_templates( $input = null ) {
		$input  = is_array( $input ) ? $input : array();
		$status = array_key_exists( 'status', $input ) && null !== $input['status'] ? (string) $input['status'] : '';
		$result = SOM_Workflows::query(
			array(
				'status'   => $status,
				'kind'     => isset( $input['kind'] ) ? (string) $input['kind'] : '',
				's'        => isset( $input['s'] ) ? (string) $input['s'] : '',
				'paged'    => isset( $input['paged'] ) ? max( 1, (int) $input['paged'] ) : 1,
				'per_page' => self::capped_limit( $input, 'per_page', SOM_Workflows::PER_PAGE ),
			)
		);

		$templates = array();
		foreach ( $result['templates'] as $row ) {
			$templates[] = self::map_workflow_template_row( $row );
		}

		return self::without_secret_keys(
			array(
				'templates' => $templates,
				'total'     => (int) $result['total'],
				'pages'     => (int) $result['pages'],
				'paged'     => (int) $result['paged'],
			)
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_workflow_template( $input = null ) {
		$input = is_array( $input ) ? $input : array();
		$id    = isset( $input['workflow_template_id'] ) ? (int) $input['workflow_template_id'] : 0;
		$row   = $id > 0 ? SOM_Workflows::get( $id ) : null;
		if ( ! $row ) {
			return new WP_Error( 'som_workflow_template_missing', __( 'Workflow template not found.', 'order-machine' ) );
		}

		$steps = isset( $row->steps ) && is_array( $row->steps ) ? $row->steps : array();
		$payload = self::map_workflow_template_row( $row, count( $steps ) );

		$products = array();
		$assigned = isset( $row->products ) && is_array( $row->products ) ? $row->products : array();
		foreach ( $assigned as $product ) {
			$products[] = array(
				'id'   => (int) $product->id,
				'name' => (string) $product->name,
			);
		}

		$mapped_steps = array();
		foreach ( $steps as $step ) {
			$mapped_steps[] = self::map_workflow_step( $step );
		}

		$payload['products'] = $products;
		$payload['steps']    = $mapped_steps;

		return self::without_secret_keys( $payload );
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_budgets( $input = null ) {
		$input  = is_array( $input ) ? $input : array();
		$status = isset( $input['status'] ) ? (string) $input['status'] : 'active';
		$result = SOM_Budgets::query(
			array(
				's'         => isset( $input['s'] ) ? (string) $input['s'] : '',
				'type'      => isset( $input['type'] ) ? (string) $input['type'] : '',
				'is_active' => self::budget_is_active_filter( $status ),
				'paged'     => isset( $input['paged'] ) ? max( 1, (int) $input['paged'] ) : 1,
				'per_page'  => self::capped_limit( $input, 'per_page', SOM_Budgets::PER_PAGE ),
			)
		);

		$budgets = array();
		foreach ( $result['budgets'] as $row ) {
			$budgets[] = self::map_budget_row( $row );
		}

		return self::without_secret_keys(
			array(
				'budgets' => $budgets,
				'total'   => (int) $result['total'],
				'pages'   => (int) $result['pages'],
				'paged'   => (int) $result['paged'],
			)
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_budget( $input = null ) {
		$input = is_array( $input ) ? $input : array();
		$id    = isset( $input['budget_id'] ) ? (int) $input['budget_id'] : 0;
		$row   = $id > 0 ? SOM_Budgets::get( $id ) : null;
		if ( ! $row ) {
			return new WP_Error( 'som_budget_missing', __( 'Budget not found.', 'order-machine' ) );
		}

		$payload = self::map_budget_row( $row );
		$ledger  = array();
		foreach ( SOM_Budgets::get_ledger( $id ) as $entry ) {
			$ledger[] = array(
				'id'                     => (int) $entry->id,
				'budget_id'              => (int) $entry->budget_id,
				'order_id'               => self::nullable_id( $entry->order_id ),
				'purchase_order_item_id' => self::nullable_id( $entry->purchase_order_item_id ),
				'change_amount'          => (float) $entry->change_amount,
				'reason'                 => (string) $entry->reason,
				'notes'                  => isset( $entry->notes ) ? $entry->notes : null,
				'created_at'             => (string) $entry->created_at,
			);
		}
		$payload['ledger']                = $ledger;
		$payload['product_link_ids']      = SOM_Budgets::get_product_link_ids( $id );
		$payload['workflow_link_ids']     = SOM_Budgets::get_workflow_link_ids( $id );

		return self::without_secret_keys( $payload );
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_shipping_packages( $input = null ) {
		$input  = is_array( $input ) ? $input : array();
		$status = isset( $input['status'] ) && '' !== (string) $input['status'] ? (string) $input['status'] : 'all';
		$result = SOM_Shipping_Packages::query(
			array(
				's'        => isset( $input['s'] ) ? (string) $input['s'] : '',
				'status'   => $status,
				'paged'    => isset( $input['paged'] ) ? max( 1, (int) $input['paged'] ) : 1,
				'per_page' => self::capped_limit( $input, 'per_page', SOM_Shipping_Packages::PER_PAGE ),
			)
		);

		$packages = array();
		foreach ( $result['packages'] as $row ) {
			$packages[] = self::map_shipping_package( $row );
		}

		return self::without_secret_keys(
			array(
				'packages' => $packages,
				'total'    => (int) $result['total'],
				'pages'    => (int) $result['pages'],
				'paged'    => (int) $result['paged'],
			)
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_shipping_package( $input = null ) {
		$input = is_array( $input ) ? $input : array();
		$id    = isset( $input['shipping_package_id'] ) ? (int) $input['shipping_package_id'] : 0;
		$row   = $id > 0 ? SOM_Shipping_Packages::get( $id ) : null;
		if ( ! $row ) {
			return new WP_Error( 'som_shipping_package_missing', __( 'Shipping package not found.', 'order-machine' ) );
		}

		$payload   = self::map_shipping_package( $row );
		$materials = array();
		foreach ( SOM_Shipping_Packages::get_materials( $id ) as $line ) {
			$materials[] = array(
				'id'                   => (int) $line->id,
				'material_id'          => (int) $line->material_id,
				'material_name'        => isset( $line->material_name ) ? (string) $line->material_name : '',
				'material_unit'        => isset( $line->material_unit ) ? (string) $line->material_unit : '',
				'quantity_per_package' => (float) $line->quantity_per_package,
			);
		}
		$payload['materials'] = $materials;

		return self::without_secret_keys( $payload );
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_make_board( $input = null ) {
		$input  = is_array( $input ) ? $input : array();
		$result = SOM_Orders::query_make_board(
			array(
				'channel'              => isset( $input['channel'] ) ? (string) $input['channel'] : '',
				'product_id'           => isset( $input['product_id'] ) ? (int) $input['product_id'] : 0,
				'workflow_template_id' => isset( $input['workflow_template_id'] ) ? (int) $input['workflow_template_id'] : 0,
				's'                    => isset( $input['s'] ) ? (string) $input['s'] : '',
			)
		);

		return self::without_secret_keys(
			array(
				'orders'         => isset( $result['orders'] ) ? $result['orders'] : array(),
				'total'          => (int) $result['total'],
				'capped'         => ! empty( $result['capped'] ),
				'warn'           => ! empty( $result['warn'] ),
				'workflow_steps' => self::map_workflow_steps( isset( $result['workflow_steps'] ) ? $result['workflow_steps'] : array() ),
			)
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_pack_board( $input = null ) {
		$input  = is_array( $input ) ? $input : array();
		$result = SOM_Pack::query_board(
			array(
				'channel' => isset( $input['channel'] ) ? (string) $input['channel'] : '',
				's'       => isset( $input['s'] ) ? (string) $input['s'] : '',
			)
		);

		return self::without_secret_keys(
			array(
				'orders' => isset( $result['orders'] ) ? $result['orders'] : array(),
				'total'  => (int) $result['total'],
				'capped' => ! empty( $result['capped'] ),
				'warn'   => ! empty( $result['warn'] ),
			)
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_post_ship_board( $input = null ) {
		$input = is_array( $input ) ? $input : array();
		$args  = array(
			'channel'              => isset( $input['channel'] ) ? (string) $input['channel'] : '',
			'workflow_template_id' => isset( $input['workflow_template_id'] ) ? (int) $input['workflow_template_id'] : 0,
			's'                    => isset( $input['s'] ) ? (string) $input['s'] : '',
		);
		if ( array_key_exists( 'scope', $input ) ) {
			$args['scope'] = (string) $input['scope'];
		}

		$result = SOM_Post_Ship::query_board( $args );

		return self::without_secret_keys(
			array(
				'orders'         => isset( $result['orders'] ) ? $result['orders'] : array(),
				'total'          => (int) $result['total'],
				'capped'         => ! empty( $result['capped'] ),
				'warn'           => ! empty( $result['warn'] ),
				'scope'          => isset( $result['scope'] ) ? (string) $result['scope'] : SOM_Post_Ship::sanitize_scope( '' ),
				'workflow_steps' => self::map_workflow_steps( isset( $result['workflow_steps'] ) ? $result['workflow_steps'] : array() ),
			)
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_trays( $input = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$held = array();
		foreach ( SOM_Trays::held_tray_numbers() as $tray_number => $order_id ) {
			$held[] = array(
				'tray_number' => (int) $tray_number,
				'order_id'    => (int) $order_id,
			);
		}
		usort(
			$held,
			static function ( $a, $b ) {
				return $a['tray_number'] <=> $b['tray_number'];
			}
		);

		return self::without_secret_keys(
			array(
				'max'  => SOM_Trays::get_max(),
				'held' => $held,
				'free' => SOM_Trays::free_tray_numbers(),
			)
		);
	}

	/**
	 * Five public columns only. Do not call SOM_Channels::get_by_slug() or get_credentials().
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_channels( $input = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		global $wpdb;

		$table = SOM_DB::table( 'channels' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only; credentials column is not selected.
		$rows = $wpdb->get_results(
			"SELECT id, slug, display_name, is_active, last_synced_at FROM {$table} ORDER BY id ASC"
		);
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$channels = array();
		foreach ( $rows as $row ) {
			$channels[] = array(
				'id'             => (int) $row->id,
				'slug'           => (string) $row->slug,
				'display_name'   => (string) $row->display_name,
				'is_active'      => (int) $row->is_active,
				'last_synced_at' => isset( $row->last_synced_at ) ? $row->last_synced_at : null,
			);
		}

		return self::without_secret_keys( array( 'channels' => $channels ) );
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_channel_fee_estimates( $input = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$channels = array();
		foreach ( SOM_Channel_Fee_Estimates::list_grouped_by_channel() as $channel_id => $rows ) {
			$estimates = array();
			foreach ( $rows as $row ) {
				$estimates[] = array(
					'id'              => (int) $row->id,
					'channel_id'      => (int) $row->channel_id,
					'fee_component'   => (string) $row->fee_component,
					'rate_type'       => (string) $row->rate_type,
					'rate_value'      => (float) $row->rate_value,
					'order_value_min' => self::nullable_number( $row->order_value_min ),
					'order_value_max' => self::nullable_number( $row->order_value_max ),
					'is_enabled'      => (int) $row->is_enabled,
					'notes'           => isset( $row->notes ) ? $row->notes : null,
					'created_at'      => (string) $row->created_at,
					'updated_at'      => (string) $row->updated_at,
				);
			}
			$channels[] = array(
				'channel_id' => (int) $channel_id,
				'estimates'  => $estimates,
			);
		}

		return self::without_secret_keys( array( 'channels' => $channels ) );
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_recurring_platform_expenses( $input = null ) {
		$input  = is_array( $input ) ? $input : array();
		$limit  = self::capped_limit( $input, 'limit', 100 );
		$offset = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;
		$args   = array(
			'limit'  => $limit,
			'offset' => $offset,
		);
		if ( ! empty( $input['listing_id'] ) ) {
			$args['listing_id'] = (int) $input['listing_id'];
		}
		if ( ! empty( $input['channel_id'] ) ) {
			$args['channel_id'] = (int) $input['channel_id'];
		}

		$result   = SOM_Platform_Fee_Sync::list_recurring( $args );
		$expenses = array();
		foreach ( $result['rows'] as $row ) {
			$expenses[] = array(
				'id'                  => (int) $row->id,
				'channel_id'          => (int) $row->channel_id,
				'listing_id'          => self::nullable_id( $row->listing_id ),
				'external_entry_id'   => (string) $row->external_entry_id,
				'fee_type'            => (string) $row->fee_type,
				'amount'              => (float) $row->amount,
				'incurred_date'       => (string) $row->incurred_date,
				'notes'               => isset( $row->notes ) ? $row->notes : null,
				'created_at'          => (string) $row->created_at,
				'channel_name'        => isset( $row->channel_name ) ? (string) $row->channel_name : '',
				'channel_slug'        => isset( $row->channel_slug ) ? (string) $row->channel_slug : '',
				'listing_title'       => isset( $row->listing_title ) ? $row->listing_title : null,
				'external_listing_id' => isset( $row->external_listing_id ) ? $row->external_listing_id : null,
			);
		}

		return self::without_secret_keys(
			array(
				'expenses' => $expenses,
				'total'    => (int) $result['total'],
				'limit'    => $limit,
				'offset'   => $offset,
			)
		);
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_analytics( $input = null ) {
		$input   = is_array( $input ) ? $input : array();
		$filters = SOM_Analytics::parse_filters( $input );
		return self::without_secret_keys( SOM_Analytics::dashboard_payload( $filters ) );
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function get_analytics_timing( $input = null ) {
		$input   = is_array( $input ) ? $input : array();
		$filters = SOM_Analytics::parse_filters( $input );
		return self::without_secret_keys( SOM_Analytics::timing_payload( $filters ) );
	}

	/**
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_material_stock_log( $input = null ) {
		$input       = is_array( $input ) ? $input : array();
		$material_id = isset( $input['material_id'] ) ? (int) $input['material_id'] : 0;
		$material    = $material_id > 0 ? SOM_Materials::get( $material_id ) : null;
		if ( ! $material ) {
			return new WP_Error( 'som_material_missing', __( 'Material not found.', 'order-machine' ) );
		}

		$limit   = self::capped_limit( $input, 'limit', SOM_Materials::LOG_LIMIT );
		$entries = array();
		foreach ( SOM_Materials::get_stock_log( $material_id, $limit ) as $row ) {
			$entries[] = array(
				'id'                     => (int) $row->id,
				'material_id'            => (int) $row->material_id,
				'order_id'               => self::nullable_id( $row->order_id ),
				'change_qty'             => (float) $row->change_qty,
				'reason'                 => (string) $row->reason,
				'purchase_order_item_id' => self::nullable_id( $row->purchase_order_item_id ),
				'unit_cost_at_time'      => self::nullable_number( $row->unit_cost_at_time ),
				'value_change'           => self::nullable_number( $row->value_change ),
				'created_at'             => (string) $row->created_at,
				'external_order_id'      => isset( $row->external_order_id ) && null !== $row->external_order_id
					? (string) $row->external_order_id
					: null,
			);
		}

		return self::without_secret_keys(
			array(
				'material_id' => $material_id,
				'entries'     => $entries,
			)
		);
	}

	/**
	 * Active / inactive / all, matching the Budgets screen. Unknown values stay active.
	 *
	 * @param string $status Raw status.
	 * @return int|string 1, 0, or '' for all.
	 */
	private static function budget_is_active_filter( $status ) {
		$status = sanitize_key( (string) $status );
		if ( 'inactive' === $status ) {
			return 0;
		}
		if ( 'all' === $status ) {
			return '';
		}
		return 1;
	}

	/**
	 * @param array<string, mixed> $input   Input.
	 * @param string               $key     Input key.
	 * @param int                  $default Default when the key is absent.
	 * @return int
	 */
	private static function capped_limit( array $input, $key, $default ) {
		if ( ! array_key_exists( $key, $input ) || null === $input[ $key ] || '' === $input[ $key ] ) {
			return (int) $default;
		}
		return max( 1, min( 100, (int) $input[ $key ] ) );
	}

	/**
	 * @param mixed $value Raw id.
	 * @return int|null
	 */
	private static function nullable_id( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$id = (int) $value;
		return $id > 0 ? $id : null;
	}

	/**
	 * @param mixed $value Raw number.
	 * @return float|null
	 */
	private static function nullable_number( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		return (float) $value;
	}

	/**
	 * @param object   $row        Template row.
	 * @param int|null $step_count Step count when the list query did not select it.
	 * @return array<string, mixed>
	 */
	private static function map_workflow_template_row( $row, $step_count = null ) {
		if ( null === $step_count ) {
			$step_count = isset( $row->step_count ) ? (int) $row->step_count : 0;
		}
		return array(
			'id'              => (int) $row->id,
			'name'            => (string) $row->name,
			'description'     => isset( $row->description ) && null !== $row->description ? (string) $row->description : null,
			'kind'            => (string) $row->kind,
			'uses_trays'      => (int) $row->uses_trays,
			'output_per_run'  => isset( $row->output_per_run ) ? (int) $row->output_per_run : 1,
			'is_active'       => (int) $row->is_active,
			'step_count'      => (int) $step_count,
			'product_count'   => isset( $row->product_count ) ? (int) $row->product_count : 0,
		);
	}

	/**
	 * @param array<int, object> $steps Step rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function map_workflow_steps( $steps ) {
		$mapped = array();
		if ( ! is_array( $steps ) ) {
			return $mapped;
		}
		foreach ( $steps as $step ) {
			$mapped[] = self::map_workflow_step( $step );
		}
		return $mapped;
	}

	/**
	 * Step shape shared by template detail and board workflow_steps.
	 *
	 * @param object $step Step row.
	 * @return array<string, mixed>
	 */
	private static function map_workflow_step( $step ) {
		return array(
			'id'                      => (int) $step->id,
			'step_order'              => (int) $step->step_order,
			'name'                    => (string) $step->name,
			'requires_manual_confirm' => (int) $step->requires_manual_confirm,
			'timer_seconds'           => self::nullable_int( isset( $step->timer_seconds ) ? $step->timer_seconds : null ),
			'planned_seconds'         => self::nullable_int( isset( $step->planned_seconds ) ? $step->planned_seconds : null ),
			'batch_group_id'          => self::nullable_id( isset( $step->batch_group_id ) ? $step->batch_group_id : null ),
			'confirmation_kind'       => SOM_Step_Confirmations::sanitize_kind(
				isset( $step->confirmation_kind ) ? $step->confirmation_kind : null
			),
			'instructions'            => isset( $step->instructions ) && null !== $step->instructions ? (string) $step->instructions : '',
			'tasks'                   => SOM_Step_Tasks::decode_list( isset( $step->task_list_json ) ? $step->task_list_json : null ),
		);
	}

	/**
	 * @param object $row Budget row.
	 * @return array<string, mixed>
	 */
	private static function map_budget_row( $row ) {
		return array(
			'id'                    => (int) $row->id,
			'name'                  => (string) $row->name,
			'type'                  => (string) $row->type,
			'material_id'           => self::nullable_id( $row->material_id ),
			'funding_method'        => (string) $row->funding_method,
			'funding_value'         => self::nullable_number( $row->funding_value ),
			'target_reserve_amount' => self::nullable_number( $row->target_reserve_amount ),
			'current_balance'       => null !== $row->current_balance && '' !== $row->current_balance ? (float) $row->current_balance : 0.0,
			'notes'                 => isset( $row->notes ) ? $row->notes : null,
			'is_active'             => (int) $row->is_active,
			'created_at'            => (string) $row->created_at,
			'updated_at'            => (string) $row->updated_at,
		);
	}

	/**
	 * @param object $row Package row.
	 * @return array<string, mixed>
	 */
	private static function map_shipping_package( $row ) {
		return array(
			'id'                 => (int) $row->id,
			'name'               => (string) $row->name,
			'length_mm'          => (int) $row->length_mm,
			'width_mm'           => (int) $row->width_mm,
			'height_mm'          => (int) $row->height_mm,
			'tare_weight_grams'  => (float) $row->tare_weight_grams,
			'is_active'          => (int) $row->is_active,
			'is_default'         => (int) $row->is_default,
		);
	}

	/**
	 * Progress fields plus joined step fields. script_config and command_template stay off.
	 *
	 * @param object $row Make progress row.
	 * @return array<string, mixed>
	 */
	private static function map_make_progress_row( $row ) {
		return array(
			'id'                      => (int) $row->id,
			'order_id'                => (int) $row->order_id,
			'order_item_id'           => (int) $row->order_item_id,
			'workflow_step_id'        => (int) $row->workflow_step_id,
			'status'                  => (string) $row->status,
			'timer_ends_at'           => $row->timer_ends_at,
			'retry_count'             => isset( $row->retry_count ) ? (int) $row->retry_count : 0,
			'last_error'              => isset( $row->last_error ) ? (string) $row->last_error : '',
			'confirmation_state'      => SOM_Step_Confirmations::decode_state( $row ),
			'task_checks_json'        => isset( $row->task_checks_json ) ? $row->task_checks_json : null,
			'manual_unblock_at'       => isset( $row->manual_unblock_at ) ? $row->manual_unblock_at : null,
			'manual_unblock_by'       => self::nullable_id( isset( $row->manual_unblock_by ) ? $row->manual_unblock_by : null ),
			'manual_unblock_gates'    => isset( $row->manual_unblock_gates ) ? $row->manual_unblock_gates : null,
			'started_at'              => isset( $row->started_at ) ? $row->started_at : null,
			'completed_at'            => isset( $row->completed_at ) ? $row->completed_at : null,
			'actual_seconds'          => self::nullable_int( isset( $row->actual_seconds ) ? $row->actual_seconds : null ),
			'created_at'              => isset( $row->created_at ) ? (string) $row->created_at : '',
			'updated_at'              => isset( $row->updated_at ) ? (string) $row->updated_at : '',
			'step_name'               => isset( $row->step_name ) ? (string) $row->step_name : '',
			'step_order'              => isset( $row->step_order ) ? (int) $row->step_order : 0,
			'requires_manual_confirm' => isset( $row->requires_manual_confirm ) ? (int) $row->requires_manual_confirm : 0,
			'timer_seconds'           => self::nullable_int( isset( $row->timer_seconds ) ? $row->timer_seconds : null ),
			'planned_seconds'         => self::nullable_int( isset( $row->planned_seconds ) ? $row->planned_seconds : null ),
			'workflow_template_id'    => self::nullable_id( isset( $row->workflow_template_id ) ? $row->workflow_template_id : null ),
			'batch_group_id'          => self::nullable_id( isset( $row->batch_group_id ) ? $row->batch_group_id : null ),
			'confirmation_kind'       => SOM_Step_Confirmations::sanitize_kind(
				isset( $row->confirmation_kind ) ? $row->confirmation_kind : null
			),
		);
	}

	/**
	 * @param mixed $value Raw integer column.
	 * @return int|null
	 */
	private static function nullable_int( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		return (int) $value;
	}

	/**
	 * Held tray numbers for an order, ascending.
	 *
	 * @param int $order_id Order PK.
	 * @return array<int, int>
	 */
	private static function tray_numbers_for_order( $order_id ) {
		$numbers = array();
		foreach ( SOM_Trays::held_for_order( $order_id ) as $row ) {
			$numbers[] = (int) $row->tray_number;
		}
		return $numbers;
	}

	/**
	 * @param int $order_id Order PK.
	 * @return array<int, array<string, mixed>>
	 */
	private static function map_order_notes( $order_id ) {
		$notes = array();
		foreach ( SOM_Order_Notes::list_for_order( $order_id ) as $note ) {
			$notes[] = array(
				'id'         => (int) $note->id,
				'body'       => (string) $note->body,
				'created_at' => (string) $note->created_at,
				'user_id'    => (int) $note->user_id,
			);
		}
		return $notes;
	}

	/**
	 * Drop secret keys at every nesting level, including objects the queries attached.
	 *
	 * @param mixed $value Payload fragment.
	 * @return mixed
	 */
	private static function without_secret_keys( $value ) {
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$clean = array();
		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) && in_array( $key, self::SECRET_KEYS, true ) ) {
				continue;
			}
			$clean[ $key ] = self::without_secret_keys( $item );
		}
		return $clean;
	}
}
