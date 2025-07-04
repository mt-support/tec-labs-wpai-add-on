=== The Events Calendar Extension: WP All Import Add-On ===
Contributors: theeventscalendar
Donate link: https://evnt.is/29
Tags: events, calendar
Requires at least: 6.6
Tested up to: 6.8.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL version 3 or any later version
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WP All Import add-on for The Events Calendar.

== Description ==

WP All Import add-on for The Events Calendar. It can handle Events, Venues, and multiple Organizers, RSVPs, RSVP Attendees, Tickets Commerce Tickets, Tickets Commerce Attendees, and Tickets Commerce Orders.

The following are NOT supported: Series, Recurring Events, WooCommerce Tickets, WooCommerce Orders, WooCommerce Attendees.

== Installation ==

Install and activate like any other plugin!

* You can upload the plugin zip file via the *Plugins ‣ Add New* screen
* You can unzip the plugin and then upload to your plugin directory (typically _wp-content/plugins_) via FTP
* Once it has been installed or uploaded, simply visit the main plugin list and activate it

== Frequently Asked Questions ==

= Where can I find more extensions? =

Please visit our [extension library](https://theeventscalendar.com/extensions/) to learn about our complete range of extensions for The Events Calendar and its associated plugins.

= What if I experience problems? =

We're always interested in your feedback and our [Help Desk](https://support.theeventscalendar.com/) are the best place to flag any issues. Do note, however, that the degree of support we provide for extensions like this one tends to be very limited.

== Changelog ==

= [1.2.0] 2025-07-03 =

* Version - Events Calendar Pro 7.6.1 or higher is required for the migration of Series.
* Feature - Add support for Event Series and Recurring Events
* Fix - Ensure other non-TEC post types can be imported when the extension is active.
* Deprecated - Deprecated the `tec_labs_wpai_is_post_type_set` filter without replacement.

= [1.1.0] 2024-09-12 =

* Feature - Add the `tec_labs_wpai_is_post_type_set` filter to allow force importing when the post type is not defined in the source.
* Feature - Add the `tec_labs_wpai_default_post_type` filter to allow changing the default post type used in case it is missing from the source.
* Tweak - Add more details to some log messages.
* Tweak - Adjust error logging to better handle special characters in log messages. (Props to Rob Gabaree.)

= [1.0.0] 2023-10-17 =

* Initial release
