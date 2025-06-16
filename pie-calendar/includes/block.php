<?php
/**
 * All functionality related to the custom block.
 */


/**
 * Register block type.
 *
 * @return void
 */
function piecal_register_block_type() {

	$block_path = PIECAL_DIR . 'build/blocks/calendar/block.json';
	register_block_type( $block_path );

	$block_path = PIECAL_DIR . 'build/blocks/event-info/block.json';
	register_block_type( $block_path );
}
add_action( 'init', 'piecal_register_block_type' );



/**
 * Remove any default atts if they are empty.
 *
 * @param array $atts The calendar block attributes.
 * @return array
 */
function piecal_calendar_block_atts_filter( $atts ) {
	foreach ( $atts as $key => $value ) {
		if ( empty( $value ) ) {
			unset( $atts[ $key ] );
		}
	}
	return $atts;
}
add_filter( 'piecal_calendar_block_atts', 'piecal_calendar_block_atts_filter' );


/**
 * Remove any default atts if they are empty.
 *
 * @param array $atts The calendar block attributes.
 * @return array
 */
function piecal_info_block_atts_filter( $atts ) {
	foreach ( $atts as $key => $value ) {
		if ( empty( $value ) ) {
			unset( $atts[ $key ] );
		}
	}
	return $atts;
}
add_filter( 'piecal_info_block_atts', 'piecal_info_block_atts_filter' );


/**
 * Register the REST endpoint.
 *
 * @return void
 */
function piecal_register_rest_endpoint() {
	register_rest_route(
		'piecal/v1',
		'/events',
		array(
			'methods'             => 'GET',
			'callback'            => 'piecal_get_events',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'piecal_register_rest_endpoint' );


/**
 * Get the events from the database.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response
 */
function piecal_get_events( $request ) {

	// Check if request is from block editor
	if (!isset($_SERVER['HTTP_REFERER']) || 
        (strpos($_SERVER['HTTP_REFERER'], '/wp-admin/post.php') === false && 
         strpos($_SERVER['HTTP_REFERER'], '/wp-admin/post-new.php') === false &&
		 strpos($_SERVER['HTTP_REFERER'], '/wp-admin/site-editor.php') === false)) {
        return new WP_Error('unauthorized', 'This endpoint is only available within the block editor', array('status' => 403));
    }

	$atts = $request->get_params();

	$args = array(
		'post_type'      => isset($atts['allAttributes']['type']) ? 
			array_map('sanitize_key', (array)$atts['allAttributes']['type']) : 
			'any',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'   => '_piecal_is_event',
				'value' => '1',
			),
			array(
				'key'     => '_piecal_start_date',
				'value'   => '',
				'compare' => 'NOT IN',
			),
		),
	);

	// This should probably be fixed upstream in piecal-pro.php.
	if ( isset( $atts['allAttributes']['taxonomy'] ) && '' === $atts['allAttributes']['taxonomy'] ) {
		unset( $atts['allAttributes']['taxonomy'] );
		unset( $atts['allAttributes']['terms'] );
		unset( $atts['allAttributes']['operator'] );
	}

	$events = new WP_Query( apply_filters( 'piecal_event_query_args', $args, $atts['allAttributes'] ) );
	$events = array_map(
		function ( $event ) {

			global $post;
			$post = $event; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $post );

			$event = array(
				// 'id'     => $event->ID,
				'postId' => $event->ID,
				'title'  => $event->post_title,
				'start'  => get_post_meta( $event->ID, '_piecal_start_date', true ),
				'end'    => get_post_meta( $event->ID, '_piecal_end_date', true ),
				'allDay' => get_post_meta(get_the_ID(), '_piecal_is_allday') ? get_post_meta(get_the_ID(), '_piecal_is_allday', true) : "false"
			);

			$event = apply_filters( 'piecal_event_array_filter', $event );

			wp_reset_postdata();

			return $event;
		},
		$events->posts
	);

	$events = apply_filters( 'piecal_events_array_filter', $events, null, null, ( ! isset( $atts['allAttributes']['adaptivetimezone'] ) && apply_filters( 'piecal_use_adaptive_timezones', false ) ) );

	return rest_ensure_response( $events );
}
