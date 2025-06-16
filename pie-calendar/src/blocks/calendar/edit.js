/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from "@wordpress/i18n";

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import {
  useBlockProps,
  InspectorControls,
  InspectorAdvancedControls,
} from "@wordpress/block-editor";
import {
  PanelRow,
  PanelBody,
  SelectControl,
  CheckboxControl,
  FormTokenField,
} from "@wordpress/components";
import { useEntityRecords } from "@wordpress/core-data";
import { useState, useEffect, useRef } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import { addQueryArgs } from "@wordpress/url";
import { SUPPORTED_LOCALES } from "./locales";
/**
 * Import the full calendar library.
 */

import FullCalendar from "@fullcalendar/react";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import "./editor.scss";

/**
 * Our Calendar component. This essentially renders calendar using FullCalendar.
 */
function Calendar({ attributes, events }) {
  const calendarRef = useRef(null);
  const defaultView = "dayGridMonth";
  const [viewTitle, setViewTitle] = useState('');

  useEffect(() => {
    if (calendarRef.current) {
      const viewType = attributes.view || defaultView;
      calendarRef.current.getApi().changeView(viewType);
      setViewTitle(calendarRef.current.getApi().view.title);
    }
  }, [attributes.view, attributes.wraptitles]);

  const updateTitle = () => {
    if (calendarRef.current) {
      setViewTitle(calendarRef.current.getApi().view.title);
    }
  };

  return (
    <>
      <div className="piecal-controls fc">
        <div
          className="piecal-controls__view-title"
          aria-live="polite"
          role="status"
        >
          <span className="visually-hidden">
            {`${viewTitle} - current view is ${calendarRef.current?.getApi().view.type}`}
          </span>
          <span aria-hidden="true">
            {viewTitle}
          </span>
        </div>
        <label className="piecal-controls__view-chooser">
          Choose View
          <select 
            value={calendarRef.current?.getApi().view.type}
            onChange={(e) => calendarRef.current?.getApi().changeView(e.target.value)}
            disabled
          >
            <option value="dayGridMonth">View Chooser</option>
          </select>
        </label>
        <div className="piecal-controls__navigation-button-group">
          <button
            className="fc-button fc-button-primary piecal-controls__today-button"
            onClick={() => {
              calendarRef.current?.getApi().today();
              updateTitle();
            }}>
            Today
          </button>
          <button
            className="fc-button fc-button-primary piecal-controls__prev-button"
            onClick={() => {
              calendarRef.current?.getApi().prev();
              updateTitle();
            }}
            aria-label={`Previous ${calendarRef.current?.getApi().view.type}`}
          >
            &lt;
          </button>
          <button
            className="fc-button fc-button-primary piecal-controls__next-button"
            onClick={() => {
              calendarRef.current?.getApi().next();
              updateTitle();
            }}
            aria-label={`Next ${calendarRef.current?.getApi().view.type}`}
          >
            &gt;
          </button>
        </div>
      </div>
      <style data-fullcalendar></style>
      <FullCalendar
        ref={calendarRef}
        plugins={[dayGridPlugin, timeGridPlugin, listPlugin]}
        initialView={attributes.view || defaultView}
        editable={false}
        events={events}
        contentHeight="auto"
        locale={attributes.locale}
        headerToolbar={false}
        validRange={attributes.hidepastevents ? {
          start: new Date()
        } : undefined}
      />
    </>
  );
}

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
  // Our list of all post types on the site.
  const [postTypes, setPostTypes] = useState([]);

  // Our list of events.
  const [events, setEvents] = useState([]);

  // Add this new state for managing suggestions
  const [suggestions, setSuggestions] = useState([]);

  const blockWrapperClass = "piecal-wrapper";

  let wrapperClass = blockWrapperClass;

  if (attributes.wraptitles) {
    wrapperClass += " piecal-wrap-event-titles";
  }

  if (attributes.widget === "true") {
    wrapperClass += " piecal-wrapper--widget";
  }

  if (attributes.widget === "responsive") {
    wrapperClass += " piecal-wrapper--responsive-widget";
  }

  if (attributes.theme) {
    wrapperClass += " piecal-theme-" + attributes.theme;
  }

  // Fetch all post types on the site.
  const { records: postTypeRecords, hasResolved: postTypehasResolved } =
    useEntityRecords("root", "postType", {
      per_page: -1,
      visibility: "public",
      viewable: true,
    });

  // Fetch all events of the selected post type. This is a custom rest API endpoint that you can filter.
  // This will re-run every time the post type changes.
  useEffect(() => {
    apiFetch({
      path: addQueryArgs("/piecal/v1/events", {
        allAttributes: attributes,
      }),
      method: "GET",
    }).then((events) => {
      // update events to jsondecode rset
      const updatedEvents = events.map((event) => {
        event.rset = JSON.parse(event.rset ?? "{}");
        return event;
      });

      setEvents(updatedEvents);
    });
  }, [attributes]);

  // Update the post types effect to set suggestions
  useEffect(() => {
    if (postTypehasResolved) {
      setPostTypes(postTypeRecords.filter((postType) => postType.viewable));
      if (piecalGbVars?.explicitAllowedPostTypes?.length > 0) {
        setSuggestions(
          postTypeRecords
            .filter((postType) => postType.viewable)
            .filter((postType) =>
              piecalGbVars.explicitAllowedPostTypes.includes(postType.slug)
            )
            .map((postType) => postType.name)
        );
      } else {
        setSuggestions(
          postTypeRecords
            .filter((postType) => postType.viewable)
            .map((postType) => postType.name)
        );
      }
    }
  }, [postTypehasResolved]);

  return (
    <div {...useBlockProps({ className: wrapperClass })}>
      <InspectorControls>
        <PanelBody title={__("Calendar Settings", "piecal")} initialOpen={true}>
          {postTypes.length > 0 && (
            <PanelRow>
              <FormTokenField
                label={__("Post Types", "piecal")}
                value={
                  attributes.type
                    ? attributes.type.map((slug) => {
                        const postType = postTypes.find(
                          (pt) => pt.slug === slug
                        );
                        return postType ? postType.name : slug;
                      })
                    : []
                }
                suggestions={suggestions}
                __experimentalValidateInput={(value) =>
                  suggestions.includes(value)
                }
                onChange={(tokens) => {
                  const slugs = tokens.map((token) => {
                    const postType = postTypes.find((pt) => pt.name === token);
                    return postType ? postType.slug : token;
                  });
                  setAttributes({ type: slugs });
                }}
                help={__(
                  "Select post types to display in the calendar.",
                  "piecal"
                )}
              />
            </PanelRow>
          )}

          <PanelRow>
            <SelectControl
              label={__("View", "piecal")}
              value={attributes.view}
              help={__(
                "Choose the default calendar view that visitors will see when the page loads.",
                "piecal"
              )}
              options={[
                { label: __("Default", "piecal"), value: "" },
                {
                  label: __("Month - Classic", "piecal"),
                  value: "dayGridMonth",
                },
                { label: __("Month - List", "piecal"), value: "listMonth" },
                {
                  label: __("Week - Time Grid", "piecal"),
                  value: "timeGridWeek",
                },
                { label: __("Week - List", "piecal"), value: "listWeek" },
                { label: __("Week - Classic", "piecal"), value: "dayGridWeek" },
                { label: __("Day", "piecal"), value: "listDay" },
              ]}
              onChange={(view) => setAttributes({ view })}
            />
          </PanelRow>
          {(attributes.view === "" || attributes.view === "dayGridMonth") && (
            <PanelRow>
              <SelectControl
                label={__("Widget", "piecal")}
                value={attributes.widget}
                help={__(
                  "Enable widget mode for a more compact calendar suitable for sidebars. 'Responsive' switches to widget mode on mobile only.",
                  "piecal"
                )}
                options={[
                  { label: __("Default", "piecal"), value: "" },
                  { label: __("True", "piecal"), value: "true" },
                  { label: __("Responsive", "piecal"), value: "responsive" },
                ]}
                onChange={(widget) => setAttributes({ widget })}
              />
            </PanelRow>
          )}
          <PanelRow>
            <SelectControl
              label={__("Theme", "piecal")}
              value={attributes.theme}
              help={__(
                "Choose between light, dark, or adaptive theme. Adaptive will match your visitor's system preferences.",
                "piecal"
              )}
              options={[
                { label: __("Default", "piecal"), value: "" },
                { label: __("Dark", "piecal"), value: "dark" },
                { label: __("Adaptive", "piecal"), value: "adaptive" },
              ]}
              onChange={(theme) => setAttributes({ theme })}
            />
          </PanelRow>
          <PanelRow>
            <CheckboxControl
              label={__("Wrap Titles", "piecal")}
              checked={attributes.wraptitles}
              help={__(
                "When enabled, event titles will wrap to multiple lines instead of being truncated with an ellipsis.",
                "piecal"
              )}
              onChange={(wraptitles) => setAttributes({ wraptitles })}
            />
          </PanelRow>
          <PanelRow>
            <SelectControl
              label={__("Locale", "piecal")}
              value={attributes.locale}
              help={__(
                "Use a locale code to change the language of the calendar. For example, 'en-US' for English (United States).",
                "piecal"
              )}
              options={SUPPORTED_LOCALES}
              onChange={(locale) => setAttributes({ locale })}
            />
          </PanelRow>
          <PanelRow>
            <CheckboxControl
              label={__("Hide Timezone", "piecal")}
              checked={attributes.hidetimezone}
              help={__(
                "Tick this box to hide the event timezone from the calendar footer.",
                "piecal"
              )}
              onChange={(hidetimezone) => setAttributes({ hidetimezone })}
            />
          </PanelRow>
        </PanelBody>
      </InspectorControls>
      <InspectorAdvancedControls>
        <PanelRow>
          <CheckboxControl
            label={__("Disable Automatic End Dates", "piecal")}
            checked={attributes.automaticenddates}
            help={__(
              "By default, events with no end date will automatically end 1 hour after their start time. Disable to show events with no end date.",
              "piecal"
            )}
            onChange={(automaticenddates) =>
              setAttributes({ automaticenddates })
            }
          />
        </PanelRow>
      </InspectorAdvancedControls>
      <Calendar attributes={attributes} events={events} />
    </div>
  );
}
