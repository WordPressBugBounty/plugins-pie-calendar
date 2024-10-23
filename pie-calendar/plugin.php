<?php

/**
 *
 * @link              https://piecalendar.com
 * @since             1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       Pie Calendar
 * Plugin URI:        https://piecalendar.com
 * Description:       Turn any post type into a calendar event and display it on a calendar.
 * Version:           1.2.4
 * Author:            Elijah Mills & Jonathan Jernigan
 * Author URI:        https://piecalendar.com/about
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       piecal
 * Domain Path:       /languages
 * Requires PHP: 7.4
 * Requires at least: 5.9
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'PIECAL_VERSION', '1.2.4' );
define( 'PIECAL_PATH', plugin_dir_url( __FILE__ ) );
define( 'PIECAL_DIR', plugin_dir_path( __FILE__ ) );

// Includes
include_once( PIECAL_DIR . 'includes/metabox.php' );

// File for registering & rendering shortcode.
include_once( PIECAL_DIR . '/includes/shortcode.php' );
include_once( PIECAL_DIR . '/includes/piecal-info-shortcode.php' );

// Register scripts & styles
function piecal_register_scripts_and_styles() {
	wp_register_script( 'alpinejs', PIECAL_PATH . 'vendor/alpine.3.11.1.js', ['alpinefocus'] );
	wp_register_script( 'alpinefocus', PIECAL_PATH . 'vendor/alpine.focus.3.11.1.js' );
	wp_register_script( 'fullcalendar', PIECAL_PATH . 'vendor/fullcalendar.6.1.4.js' );
	wp_register_script( 'fullcalendar-locales', PIECAL_PATH . 'vendor/fullcalendar.locales-all.global.min.js' );
	wp_register_script( 'piecal-utils', PIECAL_PATH . 'includes/js/piecal-utils.js', array(), PIECAL_VERSION );
	wp_register_style( 'piecalCSS', PIECAL_PATH . 'css/piecal.css', array(), PIECAL_VERSION );
	wp_register_style( 'piecalThemeDarkCSS', PIECAL_PATH . 'css/piecal-theme-dark.css', array(), PIECAL_VERSION );
	wp_register_style( 'piecalThemeDarkCSSAdaptive', PIECAL_PATH . 'css/piecal-theme-dark-adaptive.css', array(), PIECAL_VERSION );
}
add_action('wp_enqueue_scripts', 'piecal_register_scripts_and_styles');

// Defer Alpine script
add_filter( 'script_loader_tag', function ( $tag, $handle ) {

    if ( !in_array($handle, ['alpinejs', 'alpinefocus']) )
        return $tag;

    return str_replace( ' src', ' defer="defer" src', $tag );
}, 10, 2 );


// Register required post meta fields.
add_action( 'init', 'piecal_register_post_meta' );

function piecal_register_post_meta() {
	register_post_meta( '', '_piecal_is_event', [
		'show_in_rest' => true,
		'single' => true,
		'type' => 'boolean',
        'auth_callback' => function() { 
            return current_user_can('edit_posts');
        }
	] );
    register_post_meta( '', '_piecal_start_date', [
		'show_in_rest' => true,
		'single' => true,
		'type' => 'string',
        'auth_callback' => function() { 
            return current_user_can('edit_posts');
        }
	] );
    register_post_meta( '', '_piecal_end_date', [
		'show_in_rest' => true,
		'single' => true,
		'type' => 'string',
        'auth_callback' => function() { 
            return current_user_can('edit_posts');
        }
	] );
	register_post_meta( '', '_piecal_is_allday', [
		'show_in_rest' => true,
		'single' => true,
		'type' => 'boolean',
        'auth_callback' => function() { 
            return current_user_can('edit_posts');
        }
	] );

	add_action( 'admin_notices', 'piecal_admin_notice' );

};

function piecal_admin_notice() {
	if( isset( $_GET['piecal-dismiss-notice'] ) ) {
		update_option( 'piecal_hide_onboarding_notice', true );
		return;
	}

	if( get_option( 'piecal_hide_onboarding_notice' ) ) {
		return;
	}

	?>
	<div class="notice notice-success">
	<p><?php _e( "<p>Pie Calendar has been activated.</p> 
				<details>
					<summary>Quick Start Guide</summary>
					<ul>
						<li><strong>Step 1:</strong> Edit any post, page, or custom post type and enable the <strong>Show on Calendar</strong> toggle.</li>
						<li><strong>Step 2:</strong> Set a start date and time.</li>
						<li><strong>Step 3:</strong> Add the <code>[piecal]</code> shortcode wherever you want to display your calendar.</li>
					</ul>
					<p>That's it! Check out <a href='https://www.youtube.com/watch?v=ncdab1v_B1M'>this video</a> to learn how get started in <strong>under 4 minutes.</strong></p>
					<p>Or <a href='https://docs.piecalendar.com/'>click here</a> to view our extensive documentation.</p>
				</details>
				<p><a href='?piecal-dismiss-notice=true'>Dismiss this notice.</a></p>", 'piecal' ); 
	?></p>
	</div>
	<?php
}

// Load our custom meta script for Gutenberg
add_action( 'enqueue_block_editor_assets', function() {
    if( !post_type_supports( get_post_type(), 'custom-fields' ) ) return;

	wp_enqueue_script(
		'piecalendar-custom-meta-plugin', 
		PIECAL_PATH . '/build/index.js', 
		[ 'wp-edit-post' ],
		false,
		false
	);
} );

// Localize some information in Gutenberg for access in our custom meta script & blocks
function piecal_gutenberg_vars() {
    wp_localize_script(
		'piecalendar-custom-meta-plugin',
        'piecalGbVars',
        array(
			'isWooActive' => is_plugin_active( 'woocommerce/woocommerce.php' ),
			'isEddActive' => is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ),
			'explicitAllowedPostTypes' => apply_filters('piecal_explicit_allowed_post_types', [])
		)
    );
}
add_action( 'enqueue_block_editor_assets', 'piecal_gutenberg_vars' );

// Add link for Pro on plugins page
function piecal_add_plugin_row_meta( $plugin_meta, $plugin_file ) {

	// If we are not on the correct plugin, abort.
	if ( 'pie-calendar/plugin.php' !== $plugin_file ) {
		return $plugin_meta;
	}

	$get_pro  = '<a href="https://piecalendar.com/?utm_campaign=upgrade&utm_source=plugin-page&utm_medium=upgrade-to-pro" aria-label="' . esc_attr( __( 'Navigate to the Pie Calendar website to purchase the Pro version.', 'piecal' ) ) . '" target="_blank" style="color: #D53637; font-weight: bold">';
	$get_pro .= __( 'Upgrade to Pro', 'piecal' );
	$get_pro .= '</a>';

	$row_meta = array(
		'get_pro' => apply_filters('piecal_get_pro_plugin_meta_link', $get_pro)
	);

	$plugin_meta = array_merge( $plugin_meta, $row_meta );

	return $plugin_meta;
}
add_filter( 'plugin_row_meta', 'piecal_add_plugin_row_meta', 10, 2 );
 
/**
 * Load plugin textdomain.
 */
function piecal_load_textdomain() {
  load_plugin_textdomain( 'piecal', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
}

add_action( 'init', 'piecal_load_textdomain' );

/**
 * Set script translations
 * This doesn't work yet.
 */
// add_action( 'wp_enqueue_scripts', 'piecal_load_js_translations', 100 );

// function piecal_load_js_translations() {
//     wp_set_script_translations( 
//          'piecalendar-custom-meta-plugin',
//          'piecal',
//          plugin_dir_path( __FILE__ ) . 'languages'
//     );
// }

/**
 * Get offset in seconds by a given date. This is used to detect DST and output the proper offset.
 */
function piecal_get_gmt_offset_by_date( $date ) {
	$piecalTZCheckStart = new DateTime($date);
    $piecalTZ = new DateTimeZone(wp_timezone_string());
    $piecalTZOffset = $piecalTZ->getOffset($piecalTZCheckStart) / 60 / 60;

	return $piecalTZOffset;
}
/**
 * GMT Offset Utility
 * WordPress's get_option('gmt_offset') doesn't have the proper +/-00:00 format, so we have to transform it here.
 */
function piecal_site_gmt_offset( $offset = null ) {
	// Get the gmt_offset option, or fallback to +00:00 if the option isn't set.
	$gmt_offset = null;
	
	if( $offset !== null ) {
		$gmt_offset = $offset;
	} else {
		$gmt_offset = get_option( 'gmt_offset' ) ?? '+00:00';
	}

	// Early return for if the gmt_offset option is missing.
	if( $gmt_offset === '+00:00' ) {
		return $gmt_offset;
	}

	// Get the GMT offset as an interval. This conveniently excludes any decimal values.
	$gmt_offset_int = intval($gmt_offset);

	// Next, we get our GMT offset number only without any +/- or decimal values.
	$gmt_offset_number_only = abs($gmt_offset_int);

	// GMT offsets in WordPress are returned as decimal representations, e.g. 5.5 = 05:30, so we have to get the decimal value alone here.
	// We subtract the $gmt_offset_int from $gmt_offset to get the remaining decimal value.
	$gmt_offset_decimal = $gmt_offset_int - $gmt_offset;

	// Finally, we convert the isolated decimal value to a representation of minutes, e.g. .5 becomes 30 and .75 becomes 45
	$gmt_offset_decimal_as_minutes = abs($gmt_offset_decimal * 60);

	// Here we determine whether to use a + or - symbol by checking the gmt_offset_int's value against 0.
	$gmt_offset_plus_or_minus = $gmt_offset_int > 0 ? '+' : '-';

	// Now we can combine all of the parts to get a properly formatted offset for use in setting our event times
	$gmt_final_offset = sprintf('%s%02d:%02d', $gmt_offset_plus_or_minus, $gmt_offset_number_only, $gmt_offset_decimal_as_minutes);

	return $gmt_final_offset;
}

// Add PHP info to JS variables for use on front-end
add_action( 'piecal_after_core_frontend_scripts', 'piecal_add_php_vars_to_js' );

function piecal_add_php_vars_to_js() { 
    $useAdaptiveTimezones = apply_filters('piecal_use_adaptive_timezones', false);

    wp_localize_script( 'piecal-utils', 'piecalVars', [
        'useAdaptiveTimezones' => $useAdaptiveTimezones
    ] );
}
