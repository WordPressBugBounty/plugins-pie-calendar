<?php
// Pie Calendar start & end date shortcode(s)
add_shortcode('piecal-info', 'render_piecal_info');

function render_piecal_info( $atts ) {
    require_once( PIECAL_DIR . '/includes/utils/General.php' );

    if( !get_post_meta( get_the_ID(), '_piecal_is_event', true ) ) 
        return;

    $allowedFragments = ['start', 'end', 'timezone', 'all', "allday", "timezone"];
    
    if( isset( $atts['fragments'] ) && !empty( $atts['fragments'] ) ) {
        $atts['fragments'] = Piecal\Utils\General::filterArrayByAllowlist( $atts['fragments'], $allowedFragments );
    } else {
        $atts['fragments'] = ['all'];
    }

    if( empty( $atts['fragments'] ) ) {
        $atts['fragments'] = ['all'];
    }

    /* Translators: This string is used to separate the date and time in the default format output by the piecal-info shortcode. Each letter must be escaped by a backslash. */
    $format = $atts['format'] ?? get_option('date_format') . __(' \a\t ', 'piecal') . get_option('time_format');
    $format_date_only = $atts['format'] ?? get_option('date_format');

    /* Translators: This string is for displaying the time zone via the Pie Calendar Info shortcode */
    $timezone = ( !isset( $atts['hidetimezone'] ) && apply_filters('piecal_use_adaptive_timezones', false) ) ? __( 'Events are listed in the following time zone: ', 'piecal' ) . wp_timezone_string() . ' (GMT ' . piecal_site_gmt_offset( piecal_get_gmt_offset_by_date( get_post_meta( get_the_ID(), '_piecal_start_date', true ) ) ) . ')' : null;
    if( isset( $_GET['timezone'] ) && apply_filters('piecal_use_adaptive_timezones', false) ) {
        /* Translators: This string is for displaying the viewer's time zone via the Pie Calendar Info shortcode */
        $timezone =  __( 'Event times are listed in your local time zone: ', 'piecal' ) . $_GET['timezone'];
    }

    $show_timezone = true;
    $timezoneObj = new DateTimeZone( $_GET['timezone'] ?? wp_timezone_string() );

    if ( isset( $_GET['eventstart'] ) && is_numeric( $_GET['eventstart'] ) && $timezoneObj ) {
        $startDate = sanitize_text_field( $_GET['eventstart'] );
        
        if ( $date = new DateTime( '@' . $startDate ) ) {
            $date->setTimezone( $timezoneObj );
            $startDate     = $date->format( "Y-m-d H:i:s" );
        }
    } else {
        $startDate = get_post_meta(get_the_ID(), apply_filters( 'piecal_start_date_meta_key', '_piecal_start_date' ), true );
    }

    if ( isset( $_GET['eventend'] ) && is_numeric( $_GET['eventend'] ) && $_GET['eventend'] != 0 && $timezoneObj ) {
        $endDate = sanitize_text_field( $_GET['eventend'] );
        
        if ( $date = new DateTime( '@' . $endDate ) ) {
            $date->setTimezone( $timezoneObj );
            $endDate       = $date->format( "Y-m-d H:i:s" );
        }
    } else {
        $endDate = get_post_meta(get_the_ID(), apply_filters( 'piecal_end_date_meta_key', '_piecal_end_date' ), true );
    }

    $start = $startDate ? date( $format, strtotime( $startDate ) ) : false;
    $start_date_only = $startDate ? date( $format_date_only, strtotime( $startDate ) ) : false;

    $end = $endDate ? date( $format, strtotime( $endDate ) ) : false;
    $end_date_only = $endDate ? date( $format_date_only, strtotime( $endDate ) ) : false;

    $allday = get_post_meta( get_the_ID(), '_piecal_is_allday', true ) ?? false;

    /* Translators: The 'Starts on' prepend text for the piecal-info shortcode */
    $start_prepend = apply_filters( 'piecal_info_start_prepend', __("Starts on", 'piecal') );

    /* Translators: This string is used for the start date/time output by the piecal-info shortcode. %1$s is the 'Starts on' prepend. %2$s is the start date & time. */
    $info_string_start = sprintf( esc_html__( '%1$s %2$s.', 'piecal' ), $start_prepend, $start );
    $info_string_start_date_only = sprintf( esc_html__( '%1$s %2$s.', 'piecal' ), $start_prepend, $start_date_only );

    /* Translators: The 'Ends on' prepend text for the piecal-info shortcode. */
    $end_prepend = apply_filters( 'piecal_info_end_prepend', __( "Ends on", 'piecal' ) );

    /* Translators: This string is used for the end date/time output by the piecal-info shortcode. Placeholder is the end date & time. */
    $info_string_end = sprintf( esc_html__( ' %1$s %2$s.', 'piecal' ), $end_prepend, $end );
    $info_string_end_date_only = sprintf( esc_html__( ' %1$s %2$s.', 'piecal' ), $end_prepend, $end_date_only );

    /* Translators: This string is output at the end of the start/end date/time output by the piecal-info shortcode if the event is marked as all day. */
    $info_string_allday = apply_filters( 'piecal_info_lasts_all_day', __( ' Lasts all day.', 'piecal' ) );

    ob_start();
    ?>
    <div class="piecal-info">
        <p class="piecal-info__date">
            <?php
            // Start date only
            if( $start && in_array('start', $atts['fragments'] ?? [] ) ) {
                echo $info_string_start;
            }

            // Start & end date, not all day
            if( $start && $end && in_array('all', $atts['fragments'] ?? [] ) ) {
                echo $info_string_start . '<br>' . $info_string_end;
            }

            // End date
            if( $end && in_array('end', $atts['fragments'] ?? [] ) ) {
                echo $info_string_end;
            }

            if( $allday && Piecal\Utils\General::foundInArray( ['allday', 'all'], $atts['fragments'] ?? [] ) ) {
                echo "<br>" . $info_string_allday;
            }
            ?>
        </p>
        <?php if( empty($allday) && $show_timezone === true && Piecal\Utils\General::foundInArray( ['timezone', 'all'], $atts['fragments'] ?? [] ) ) { ?>
            <p class="piecal-info__timezone">
                <?php echo $timezone; ?>
            </p>
        <?php } ?>
    </div>
    <?php
    return ob_get_clean();
}