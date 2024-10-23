<?php

add_shortcode("piecal", "piecal_render_calendar");

if ( ! function_exists( 'piecal_render_calendar' ) ) {

    function piecal_render_calendar( $atts ) {

        $atts = apply_filters('piecal_shortcode_atts', $atts);
        
        $args = [
            'post_type'     => $atts['type'] ?? 'any',
            'post_status'   => 'publish',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'      => '_piecal_is_event',
                    'value'    => '1',
                ],
                [
                    'key'      => '_piecal_start_date',
                    'value'    => '',
                    'compare'  => 'NOT IN'
                ]
            ],
        ];

        $events = new WP_Query( apply_filters('piecal_event_query_args', $args, $atts ) );
        $eventsArray = [];

        // Append GMT offset
        $appendOffset = false;

        if( !isset( $atts['adaptivetimezone'] ) && apply_filters('piecal_use_adaptive_timezones', false) ) {
            $appendOffset = true;
        }

        $automaticEndDates = true;
        if( isset( $atts['automaticenddates'] ) ) {
            $automaticEndDates = false;
        }

        // Temporarily remove read more links for our post details
        add_filter('excerpt_more', 'piecal_replace_read_more', 99);

        while( $events->have_posts() ) {
            $events->the_post();
            $startDate = get_post_meta( get_the_ID(), apply_filters( 'piecal_start_date_meta_key', '_piecal_start_date' ), true );
            $endDate = get_post_meta( get_the_ID(), apply_filters( 'piecal_end_date_meta_key', '_piecal_end_date' ), true );
            $postType = get_post_type_object( get_post_type() );
            $allday = get_post_meta(get_the_ID(), '_piecal_is_allday') ? get_post_meta(get_the_ID(), '_piecal_is_allday', true) : "false";

            // Force all day events to start at 12:00.
            if( $allday == true && $allday != "false" ) {
                $startDate = new DateTime( $startDate );
                $startDate = $startDate->setTime( 12, 0, 0 );
                $startDate = $startDate->format('Y-m-d\TH:i:s');
            }

            if( ( !isset( $endDate ) || !$endDate ) &&
            $automaticEndDates ) {
                $automaticEndDate = new DateTime( $startDate );
                $automaticEndDate = $automaticEndDate->add( new DateInterval('PT1H' ) );

                $endDate = $automaticEndDate->format('Y-m-d\TH:i:s');
            } else if( $automaticEndDates ) {
                // Ensure end date is in required format
                $endDate = new DateTime( $endDate );
                $endDate = $endDate->format('Y-m-d\TH:i:s');
            }

            // Ensure start date is in required format
            $startDate = new DateTime( $startDate );
            $startDate = $startDate->format('Y-m-d\TH:i:s');

            if( $appendOffset ) {
                $startDate .= piecal_site_gmt_offset( piecal_get_gmt_offset_by_date( $startDate ) );
                
                if( isset( $endDate ) && !empty( $endDate ) ) {
                    $endDate = $endDate . piecal_site_gmt_offset( piecal_get_gmt_offset_by_date( $endDate ) );
                }
            }

            $event = [
                "title" => str_replace("&amp;", "&", htmlentities(get_the_title(), ENT_QUOTES)),
                "start" => $startDate,
                "end" => $endDate ?? null,
                "details" => str_replace("&amp;", "&", htmlentities(get_the_excerpt(), ENT_QUOTES) ),
                "permalink" => get_permalink(),
                "postType" => $postType->labels->singular_name ?? null,
                "postId" => get_the_ID()
            ];

            if( $allday == true &&
                $allday != "false") {
                $event["allDay"] = $allday;
            }

            $event = apply_filters('piecal_event_array_filter', $event);

            if( $event['postType'] != "null" ) {
                array_push( $eventsArray, $event );
            }
        }

        $eventsArray = apply_filters('piecal_events_array_filter', $eventsArray, $rangeStart = null, $rangeEnd = null, $appendOffset);

        remove_filter('excerpt_more', 'piecal_replace_read_more', 99);

        $allowedViews = ['dayGridMonth', 'listMonth', 'timeGridWeek', 'listWeek', 'dayGridWeek', 'listDay'];
        $initialView = $atts['view'] ?? 'dayGridMonth';
        
        if( !in_array($initialView, $allowedViews) ) {
            $initialView = 'dayGridMonth';
        }

    wp_enqueue_style('piecalCSS');

    $theme = $atts['theme'] ?? false;

    if( $theme && $theme == 'dark' )
        wp_enqueue_style('piecalThemeDarkCSS');

    if( $theme && $theme == 'adaptive' )
        wp_enqueue_style('piecalThemeDarkCSSAdaptive');

    do_action('piecal_before_core_frontend_scripts');

    wp_enqueue_script('alpinefocus');
    wp_enqueue_script('alpinejs');
    wp_enqueue_script('fullcalendar');
    wp_enqueue_script('piecal-utils');

    do_action('piecal_after_core_frontend_scripts');

    $locale = $atts['locale'] ?? get_bloginfo('language');

    if( $locale != 'en-US' ) {
        wp_enqueue_script('fullcalendar-locales');
    }

    $localeDateStringFormat = [
        'hour' => '2-digit',
        'minute' => '2-digit'
    ];

    $localeDateStringFormat = apply_filters( 'piecal_locale_date_string_format', $localeDateStringFormat );

    $allDayLocaleDateStringFormat = [];

    $allDayLocaleDateStringFormat = apply_filters( 'piecal_allday_locale_date_string_format', $allDayLocaleDateStringFormat );

    $wrapperClass = 'piecal-wrapper';
    $wrapperViewAttribute = 'dayGridMonth';

    if( isset( $atts['wraptitles'] ) ) {
        $wrapperClass .= ' piecal-wrap-event-titles';
    }

    if( isset( $atts['widget'] ) && $atts['widget'] == 'true' ) {
        $wrapperClass .= ' piecal-wrapper--widget';
    }

    if( isset( $atts['widget'] ) && $atts['widget'] == 'responsive' ) {
        $wrapperClass .= ' piecal-wrapper--responsive-widget';
    }

    $wrapperClass .= apply_filters( 'piecal_wrapper_class', null ) ? " " . apply_filters( 'piecal_wrapper_class', null ) : null;

    $customCalendarProps = [];
    $customCalendarProps = apply_filters('piecal_calendar_object_properties', $customCalendarProps, $eventsArray, $appendOffset, $atts);

    ob_start();
    ?>
    <script>
            let piecalAJAX = {
            ajaxURL: "<?php echo admin_url('admin-ajax.php'); ?>",
            ajaxNonce: "<?php echo wp_create_nonce('piecal_ajax_nonce'); ?>"
            }

            let alreadyExpandedOccurrences = [];
            
            document.addEventListener('DOMContentLoaded', function() {
                var pieCalendarFirstLoad = true;
                var calendarEl = document.getElementById('calendar');
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    headerToolbar: false,
                    initialView: "<?php echo esc_attr( $initialView ); ?>",
                    editable: false,
                    events: <?php echo json_encode($eventsArray); ?>,
                    direction: "<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>",
                    contentHeight: "auto",
                    locale: "<?php echo $locale; ?>",
                    eventTimeFormat: <?php echo json_encode($localeDateStringFormat); ?>,
                    dayHeaderFormat: { weekday: 'long' },
                    eventClick: function( info ) {
                        Alpine.store("calendarEngine").eventTitle = info.event._def.title;
                        Alpine.store("calendarEngine").eventStart = info.event.start;
                        Alpine.store("calendarEngine").eventEnd = info.event.end;
                        Alpine.store("calendarEngine").eventDetails = info.event._def.extendedProps.details;
                        Alpine.store("calendarEngine").eventUrl = info.event._def.extendedProps.permalink;
                        Alpine.store("calendarEngine").eventAllDay = info.event.allDay;
                        Alpine.store("calendarEngine").eventType = info.event._def.extendedProps.postType;
                        Alpine.store('calendarEngine').showPopover = true;
                        Alpine.store('calendarEngine').eventActualEnd = info.event._def.extendedProps.actualEnd;
                        Alpine.store('calendarEngine').appendOffset = "<?php echo $appendOffset; ?>";

                        // Always pass through event data via the URL if it's a recurring instance, or if adaptive timezones are enabled.
                        // Do not pass through event data via the URL if it's a non-recurring instance and adaptive timezones are disabled.
                        if( info.event._def.extendedProps.isRecurringInstance || ( !info.event._def.extendedProps.isRecurringInstance && piecalVars.useAdaptiveTimezones && Alpine.store('calendarEngine').appendOffset ) ) {
                            // Construct the URL with parameters
                            const baseUrl    = info.event._def.extendedProps.permalink;
                            const eventStart = new Date( info.event.start );
                            const eventEnd   = new Date( info.event.end );
                            const viewerTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;


                            const url        = new URL( baseUrl );
                            url.searchParams.append( 'eventstart', Math.floor( eventStart.getTime() / 1000 ) );
                            url.searchParams.append( 'eventend', Math.floor( eventEnd.getTime() / 1000 ) );
                            url.searchParams.append( 'timezone', viewerTimezone );

                            // Assign the constructed URL to the store
                            Alpine.store("calendarEngine").eventUrl = url.toString();
                        }

                        <?php do_action( 'piecal_additional_event_click_js' ); ?>

                        if( info.jsEvent.type == "keydown" ) {
                            setTimeout( () => {
                                document.querySelector('.piecal-popover__inner > button').focus();
                            }, 100);
                        }
                    },
                    eventDataTransform: function(event) {  
                        // Safely decode encoded HTML entities for output as titles
                        let scrubber = document.createElement('textarea');
                        scrubber.innerHTML = event.title;
                        event.title = scrubber.value;

                        // Extend end date for all day events that span multiple days
                        let { actualEnd, end } = piecalUtils.getAlldayMultidayEventEnd( event ) ?? {};

                        if( actualEnd && end ) {    
                            event.actualEnd = actualEnd;
                            event.end = end;
                        }

                        <?php do_action( 'piecal_additional_event_data_transform_js' ); ?>

                        return event;  
                    },
                    dateClick: function( info ) {
                        if( info.jsEvent.target.tagName != 'A' ) return;

                        piecalChangeView('listDay');
                        this.gotoDate(info.dateStr);

                        <?php do_action( 'piecal_additional_date_click_js' ); ?>
                    },
                    eventDidMount: function( info ) {
                        let link = info.el;

                        const locale = info.view.dateEnv.locale.codeArg;

                        const formattedTime = new Intl.DateTimeFormat(locale, {
                            hour: 'numeric',
                            minute: 'numeric',
                            hour12: true
                        });

                        const formattedDate = new Intl.DateTimeFormat(locale, {
                            day: 'numeric',
                            month: 'numeric',
                            year: 'numeric'
                        });

                        if( link.tagName == 'TR' ) {
                            link = info.el.querySelector('a');
                        }

                        if( !link || link.tagName != "A" ) return;

                        link.setAttribute('role', 'button');
                        link.setAttribute('href', 'javascript:void(0)');

                        if( info.event.allDay ) {
                            /* Translators: Text for all-day event description. */
                            const allDayDescriptionText = <?php _e("'All-day event'", 'piecal'); ?>;

                            link.setAttribute('aria-label', `${allDayDescriptionText} - ${info.event.title}`);
                        }

                        // Handle multi-day event aria label to let screen readers know the event spans multiple days
                        if( info.event.end && (info.event.end - info.event.start) > (24 * 60 * 60 * 1000) ) {
                            
                            const startDate = formattedDate.format(info.event.start);
                            const startTime = info.event.allDay ? '' : formattedTime.format(info.event.start);

                            const endDate = formattedDate.format(info.event.end);
                            const endTime = info.event.allDay ? '' :formattedTime.format(info.event.end);

                            /* Translators: Text describing span of multi-day event. */
                            const spanText = <?php _e("'to'", 'piecal'); ?>;

                            /* Translators: Text for multi-day event description. */
                            const multiDayDescriptionText = <?php _e("'Multi-day event running from'", 'piecal'); ?>;

                            /* Translators: Text for multi-day all-day event description. */
                            const multiDayAllDayDescriptionText = <?php _e("'Multi-day, all-day event running from'", 'piecal'); ?>;

                            const descriptionText = info.event.allDay ? multiDayAllDayDescriptionText : multiDayDescriptionText;

                            /* Translators: Text describing span of multi-day event. */
                            link.setAttribute('aria-label', `${descriptionText} ${startDate} ${startTime} ${spanText} ${endDate} ${endTime} - ${info.event.title}`);
                        }

                        <?php do_action( 'piecal_additional_event_did_mount_js' ); ?>
                    },
                    dayCellDidMount: function( info ) {
                        let dayLink = info.el.querySelector('.fc-daygrid-day-top a');

                        if( !dayLink ) return;

                        dayLink.setAttribute('role', 'button');
                        dayLink.setAttribute('href', 'javascript:void(0)');

                        // Prevent double read out of button label
                        dayLink.closest('td').removeAttribute('aria-labelledby');
                        
                        setTimeout( () => {
                            if( info.el.querySelector('.fc-daygrid-day-events .fc-daygrid-event-harness') ) {
                                dayLink.setAttribute('aria-label', dayLink.getAttribute('aria-label') + ', has events.');
                            }
                        }, 100);

                        dayLink.addEventListener('keydown', (event) => {
                            if( event.key == "Enter" || event.key == ' ' ) {
                                event.preventDefault();
                                piecalChangeView('listDay');
                                window.calendar.gotoDate(info.date);

                                setTimeout( () => {
                                    let focusTarget = document.querySelector('.fc-list-day-text');
                                    focusTarget?.setAttribute('tabindex', '0');
                                    focusTarget?.focus();
                                }, 100);
                            }
                        })

                        <?php do_action( 'piecal_additional_day_cell_did_mount_js' ); ?>
                    },
                    dayHeaderContent: function( info ) {
                        let overriddenDayHeaderViews = ['dayGridMonth', 'timeGridWeek', 'dayGridWeek'];

                        if( overriddenDayHeaderViews.includes(info.view.type) ) {
                            return '';
                        }

                        <?php do_action( 'piecal_additional_day_header_content_js' ); ?>

                        return info.text;
                    },
                    dayHeaderDidMount: function( info ) {
                        let dayHeaderLink = info.el.querySelector('a');

                        let fullDayName = piecalUtils.getShortenedDayNames(info.text, 'full');
                        let shortDayName = piecalUtils.getShortenedDayNames(info.text, 'short');
                        let singleLetterDayName = piecalUtils.getShortenedDayNames(info.text, 'single');

                        let shortenableViews = ['dayGridMonth', 'timeGridWeek', 'dayGridWeek'];

                        if( shortenableViews.includes(info.view.type) ) {
                            dayHeaderLink.innerHTML = `<span class="piecal-grid-day-header-text piecal-grid-day-header-text--full">${fullDayName}</span>
                                                       <span class="piecal-grid-day-header-text piecal-grid-day-header-text--short">${shortDayName}</span>
                                                       <span class="piecal-grid-day-header-text piecal-grid-day-header-text--single-letter">${singleLetterDayName}</span>`;
                        }

                        <?php do_action( 'piecal_additional_day_header_did_mount_js' ); ?>
                    },
                    <?php 
                    foreach( $customCalendarProps as $prop ) echo $prop;
                    ?>
                });
                    calendar.render();
                    window.calendar = calendar;
            });

            function piecalChangeView( view ) {
                document.querySelector('.piecal-wrapper').setAttribute('data-view', view);
                window.calendar.changeView(view);
                Alpine.store('calendarEngine').calendarView = view;
                Alpine.store('calendarEngine').viewTitle = window.calendar.currentData.viewTitle;
                Alpine.store('calendarEngine').viewSpec = window.calendar.currentData.viewSpec.buttonTextDefault;
            }

            function piecalGotoToday() {
                console.log('today');
            }

            function piecalNextInView() {
                window.calendar.next();
            }

            function piecalPreviousInView() {
                console.log('prev');
            }

            function piecalSkipCalendar() {
                let focusedCalendar = document.querySelector('.piecal-wrapper:focus-within');
                let focusablesInCalendar = focusedCalendar.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"]');
                let lastFocusable = focusablesInCalendar[focusablesInCalendar.length - 1];

                let focusablesInDocument = document.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"]');
                let targetFocusable = Array.prototype.indexOf.call(focusablesInDocument, lastFocusable) + 1;

                focusablesInDocument[targetFocusable].focus();
            }

            document.addEventListener('alpine:init', () => {
                Alpine.store('calendarEngine', {
                    viewTitle: "Loading",
                    viewSpec: "Loading",
                    buttonText: {},
                    showPopover: false,
                    locale: "<?php echo $locale; ?>",
                    localeDateStringFormat: <?php echo json_encode( $localeDateStringFormat ); ?>,
                    allDayLocaleDateStringFormat: <?php echo json_encode( $allDayLocaleDateStringFormat ); ?>,
                    calendarView: "<?php echo esc_attr( $initialView ); ?>",
                    eventTitle: "Loading...",
                    eventDetails: "Loading...",
                    eventType: "Loading...",
                    eventStart: "Loading...",
                    eventAllDay: false,
                    eventActualEnd: null,
                    eventEnd: "Loading...",
                    eventUrl: "/",
                    safeOutput( input ) {
                        let scrubber = document.createElement('textarea');
                        scrubber.innerHTML = input;
                        return scrubber.value;
                    }
                })
            })

            window.addEventListener('DOMContentLoaded', () => {
                Alpine.store('calendarEngine').viewTitle = window.calendar.currentData.viewTitle;
                Alpine.store('calendarEngine').viewSpec = window.calendar.currentData.viewSpec.buttonTextDefault;
                Alpine.store('calendarEngine').buttonText = window.calendar.currentData.localeDefaults.buttonText;
            })

            window.addEventListener('keydown', (e) => {
                if( e.keyCode == 27 || e.key == 'Escape' ) Alpine.store('calendarEngine').showPopover = false;

            })
        </script>
        <div 
        class="<?php echo $wrapperClass; ?>" 
        data-view="<?php echo $wrapperViewAttribute; ?>";
        x-data
        >
            <div class="piecal-controls fc">
                <button
                    class="piecal-controls__skip-calendar fc-button fc-button-primary"
                    onClick="piecalSkipCalendar()">
                        <?php _e('Skip Calendar', 'piecal'); ?>
                </button>
                <div
                class="piecal-controls__view-title" 
                aria-live="polite"
                role="status"
                >
                  <span class="visually-hidden" x-text="$store.calendarEngine.viewTitle + ' - current view is ' + $store.calendarEngine.calendarView"></span>
                  <span aria-hidden="true" x-text="$store.calendarEngine.viewTitle"></span>
                </div>
                <button 
                    class="piecal-controls__back-to-month fc-button fc-button-primary"
                    aria-label="<?php _e( 'Back to full month view.', 'piecal' ); ?>"
                    onClick="piecalChangeView('dayGridMonth')">
                        <?php _e('Back To Full Month', 'piecal'); ?>
                </button>
                <label class="piecal-controls__view-chooser">
                    <?php
                    /* Translators: Label for calendar view chooser. */
                    _e('Choose View', 'piecal')
                    ?>
                    <select x-model="$store.calendarEngine.calendarView" @change="piecalChangeView($store.calendarEngine.calendarView)">
                        <option value="dayGridMonth">
                            <?php 
                                /* Translators: String for Month - Classic view in view picker dropdown. */
                                _e( 'Month - Classic', 'piecal' ); 
                            ?>
                        </option>
                        <option value="listMonth">
                            <?php 
                                /* Translators: String for Month - List view in view picker dropdown. */
                                _e( 'Month - List', 'piecal' ); 
                            ?>
                        </option>
                        <option value="timeGridWeek">
                            <?php 
                                /* Translators: String for Week - Time Grid view in view picker dropdown. */
                                _e( 'Week - Time Grid', 'piecal' ); 
                            ?>
                        </option>
                        <option value="listWeek">
                            <?php 
                                /* Translators: String for Week - List view in view picker dropdown. */
                                _e( 'Week - List', 'piecal' ); 
                            ?>
                        </option>
                        <option value="dayGridWeek">
                            <?php 
                                /* Translators: String for Week - Classic view in view picker dropdown. */
                                _e( 'Week - Classic', 'piecal' ); 
                            ?>
                        </option>
                        <option value="listDay">
                            <?php 
                                /* Translators: String for Day - Classic view in view picker dropdown. */
                                _e( 'Day', 'piecal' ); 
                            ?>
                        </option>
                    </select>
                </label>
                <div class="piecal-controls__navigation-button-group">
                    <button 
                    class="fc-button fc-button-primary piecal-controls__today-button"
                    @click="window.calendar.today(); $store.calendarEngine.viewTitle = window.calendar.currentData.viewTitle"
                    x-text="$store.calendarEngine.buttonText.today ?? 'Today'">
                    </button>
                    <button 
                    class="fc-button fc-button-primary piecal-controls__prev-button"
                    @click="window.calendar.prev(); $store.calendarEngine.viewTitle = window.calendar.currentData.viewTitle"
                    :aria-label="$store.calendarEngine.buttonText.prev + ' ' + $store.calendarEngine.viewSpec"><</button>
                    <button 
                    class="fc-button fc-button-primary piecal-controls__next-button"
                    @click="window.calendar.next(); $store.calendarEngine.viewTitle = window.calendar.currentData.viewTitle" 
                    :aria-label="$store.calendarEngine.buttonText.next + ' ' + $store.calendarEngine.viewSpec">></button>
                </div>
            </div>
            <div id="calendar"></div>
            <div 
                class="piecal-popover" 
                x-show="$store.calendarEngine.showPopover"
                style="display: none;">
                    <div 
                    class="piecal-popover__inner" 
                    role="dialog"
                    aria-labelledby="piecal-popover__title--01"
                    aria-describedby="piecal-popover__details--01"
                    @click.outside="$store.calendarEngine.showPopover = false"
                    x-trap.noscroll="$store.calendarEngine.showPopover">
                        <button 
                        class="piecal-popover__close-button" 
                        title="<?php
                        /* Translators: Label for close button in Pie Calendar popover. */
                        _e( 'Close event details', 'piecal' )
                        ?>"
                        @click="$store.calendarEngine.showPopover = false">
                        </button>
                        <?php do_action('piecal_popover_before_title', $atts); ?>
                        <p class="piecal-popover__title" id="piecal-popover__title--01" x-text="$store.calendarEngine.safeOutput( $store.calendarEngine.eventTitle )">Event Title</p>
                        <?php do_action('piecal_popover_after_title', $atts); ?>
                        <hr>
                        <div class="piecal-popover__meta">
                            <?php do_action('piecal_popover_before_meta', $atts); ?>
                            <p>
                            <?php
                            /* Translators: Label for event start date in Pie Calendar popover. */
                            _e('Starts', 'piecal')
                            ?>
                            </p>
                            <p 
                            aria-labelledby="piecal-event-start-date" 
                            x-text="!$store.calendarEngine.eventAllDay ? new Date($store.calendarEngine.eventStart).toLocaleDateString( $store.calendarEngine.locale, $store.calendarEngine.localeDateStringFormat ) : new Date($store.calendarEngine.eventStart).toLocaleDateString( $store.calendarEngine.locale, $store.calendarEngine.allDayLocaleDateStringFormat )"></p>
                            <p x-show="$store.calendarEngine.eventEnd">
                            <?php
                            /* Translators: Label for event end date in Pie Calendar popover. */
                            _e('Ends', 'piecal')
                            ?>
                            </p>
                            <p 
                            x-show="$store.calendarEngine.eventEnd" 
                            x-text="!$store.calendarEngine.eventAllDay ? new Date($store.calendarEngine.eventEnd).toLocaleDateString( $store.calendarEngine.locale, $store.calendarEngine.localeDateStringFormat ) : new Date($store.calendarEngine.eventActualEnd).toLocaleDateString( $store.calendarEngine.locale, $store.calendarEngine.allDayLocaleDateStringFormat )"></p>
                            <?php do_action('piecal_popover_after_meta', $atts); ?>
                        </div>
                        <hr>
                        <?php do_action('piecal_popover_before_details', $atts); ?>
                        <?php echo apply_filters('piecal_popover_details', '<p class="piecal-popover__details" id="piecal-popover__details--01" x-text="$store.calendarEngine.safeOutput( $store.calendarEngine.eventDetails )"></p>'); ?>
                        <?php do_action('piecal_popover_after_details', $atts); ?>
                        <?php do_action('piecal_popover_before_view_link', $atts); ?>
                        <a class="piecal-popover__view-link" :href="<?php echo apply_filters( 'piecal_popover_link_url', '$store.calendarEngine.eventUrl' ); ?>">
                        <?php
                        $filtered_popover_link = apply_filters( 'piecal_popover_link_text', null );

                        if( $filtered_popover_link == null ) {
                        /* Translators: Label for "View <Post Type>" in Pie Calendar popover. */
                            _e('View ', 'piecal');
                            ?>
                            <span x-text="$store.calendarEngine.eventType"></span>
                            <?php
                        } else {
                            echo $filtered_popover_link;
                        }
                        ?>
                        </a>
                        <?php
                        echo apply_filters('piecal_popover_after_view_link', null);
                        ?>
                    </div>
            </div>
        </div>
        <div class="piecal-footer">
            <?php
            if( !isset( $atts['hidetimezone'] ) && !isset($atts['adaptivetimezone']) && apply_filters('piecal_use_adaptive_timezones', false) ) {
                /* Translators: This string is for displaying the viewer's time zone via the Pie Calendar Info shortcode */
                $footer_text = __( 'Event times are listed in your local time zone: ', 'piecal' );

                echo apply_filters('piecal-footer', $footer_text . "<span x-text='Intl.DateTimeFormat().resolvedOptions().timeZone'></span>");
            }
            ?>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }
}

if ( ! function_exists( 'piecal_replace_read_more' ) ) {
    function piecal_replace_read_more( $more ) {
        return '...';
    }
}