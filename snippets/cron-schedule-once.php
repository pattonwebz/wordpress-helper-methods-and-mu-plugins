<?php
/**
 * Snippet: Schedule a cron event exactly once, and keep it in step.
 *
 * Problem: A plugin that calls wp_schedule_event() on activation stacks a duplicate event every
 *          time the plugin is activated, and a changed interval never takes effect on sites that
 *          already have the old event scheduled.
 * Trap:    Three of them. wp_schedule_event() does not check whether the event already exists, so
 *          guard it with wp_next_scheduled(). wp_clear_scheduled_hook() needs the same arguments
 *          the event was scheduled with, or it clears nothing. And a changed recurrence schedule
 *          has to be re-registered before the event is rescheduled, or WordPress rejects the new
 *          schedule name and silently keeps the old one.
 * Paste:   In a plugin's main file.
 * Verify:  Activate the plugin three times: wp_next_scheduled() should still report a single event.
 *          Change EVENT_VERSION and reload: the event should be cleared and recreated at the new
 *          interval, not doubled.
 */

const EVENT_HOOK    = 'myplugin_sync_job';
const EVENT_VERSION = '1.0.0';

/**
 * Register the custom schedule. Must run before any event using it is scheduled.
 *
 * @param array $schedules Existing schedules.
 * @return array
 */
function myplugin_cron_schedules( array $schedules ): array {
	$schedules['myplugin_every_15_minutes'] = array(
		'interval' => 15 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every 15 minutes', 'myplugin' ),
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'myplugin_cron_schedules' );

/**
 * Make sure exactly one event exists, at the current version's interval.
 */
function myplugin_schedule_events(): void {
	if ( get_option( 'myplugin_event_version' ) !== EVENT_VERSION ) {
		wp_clear_scheduled_hook( EVENT_HOOK );
		update_option( 'myplugin_event_version', EVENT_VERSION );
	}

	if ( ! wp_next_scheduled( EVENT_HOOK ) ) {
		wp_schedule_event( time() + 60, 'myplugin_every_15_minutes', EVENT_HOOK );
	}
}
add_action( 'init', 'myplugin_schedule_events' );

/**
 * Remove the event when the plugin is deactivated.
 */
function myplugin_unschedule_events(): void {
	wp_clear_scheduled_hook( EVENT_HOOK );
}
register_deactivation_hook( __FILE__, 'myplugin_unschedule_events' );
