<?php
/**
 * WP-Cron registration for Order Machine.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers cron schedules and handlers (token refresh in Sprint 2; sync/engine later).
 */
class SOM_Cron {

	const HOOK_REFRESH_TOKENS = 'som_refresh_tokens';

	/**
	 * Wire hooks (call on every request after plugins_loaded).
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedules' ) );
		add_action( self::HOOK_REFRESH_TOKENS, array( __CLASS__, 'refresh_tokens' ) );
	}

	/**
	 * Custom intervals from settings (token refresh) + placeholders for later sprints.
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_schedules( $schedules ) {
		$settings = SOM_Settings::get();
		$minutes  = max( 5, (int) $settings['token_refresh_interval_minutes'] );

		$schedules['som_token_refresh'] = array(
			'interval' => $minutes * MINUTE_IN_SECONDS,
			'display'  => sprintf(
				/* translators: %d: minutes */
				__( 'Order Machine token refresh (%d min)', 'order-machine' ),
				$minutes
			),
		);

		$poll = max( 1, (int) $settings['poll_interval_minutes'] );
		$schedules['som_order_poll'] = array(
			'interval' => $poll * MINUTE_IN_SECONDS,
			'display'  => sprintf(
				/* translators: %d: minutes */
				__( 'Order Machine order poll (%d min)', 'order-machine' ),
				$poll
			),
		);

		return $schedules;
	}

	/**
	 * Schedule events (activation / settings save).
	 *
	 * @return void
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( self::HOOK_REFRESH_TOKENS ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'som_token_refresh', self::HOOK_REFRESH_TOKENS );
		}
	}

	/**
	 * Clear scheduled events (deactivation).
	 *
	 * @return void
	 */
	public static function clear_events() {
		$timestamp = wp_next_scheduled( self::HOOK_REFRESH_TOKENS );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK_REFRESH_TOKENS );
			$timestamp = wp_next_scheduled( self::HOOK_REFRESH_TOKENS );
		}
	}

	/**
	 * Reschedule after interval settings change.
	 *
	 * @return void
	 */
	public static function reschedule_refresh() {
		self::clear_events();
		self::schedule_events();
	}

	/**
	 * Proactively refresh eBay/Etsy tokens. Skips dummy credentials.
	 *
	 * @return void
	 */
	public static function refresh_tokens() {
		foreach ( array( 'ebay', 'etsy' ) as $slug ) {
			if ( ! SOM_Channels::is_connected( $slug ) ) {
				continue;
			}

			$creds = SOM_Channels::get_credentials( $slug );
			if ( ! empty( $creds['dummy'] ) ) {
				continue;
			}

			if ( 'ebay' === $slug ) {
				$result = SOM_Channel_Ebay::refresh_token_if_needed( false );
			} else {
				$result = SOM_Channel_Etsy::refresh_token_if_needed( false );
			}

			if ( is_wp_error( $result ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional ops signal
				error_log( 'Order Machine token refresh (' . $slug . '): ' . $result->get_error_message() );
			}
		}
	}
}
