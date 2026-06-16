# WP All Import Add-On for The Events Calendar

This extension is needed to successfully migrate The Events Calendar and/or Event Tickets data from one WordPress site to another. It is the glue between The Events Calendar/Event Tickets and [WP All Import Pro](https://www.wpallimport.com/import-wordpress-csv-xml-excel/) (premium).

> ⚠️ **Note:** the extension is primarily intended to help migrate data from The Events Calendar to another The Events Calendar 6.0+ using the new data storage system (after data migration).
> Importing from other sources may be possible with additional setup and tweaks, please note however, that is beyond the official scope of this extension and support.

---

## 1. Compatibility

Currently, this add-on supports the following calendar and ticket data to be migrated:

- Venues
- Organizers
- Single events, including all-day and multi-day events
- Recurring Events (from version 1.2.0)
- Event Series (from version 1.2.0)
- RSVPs and Attendees
- Tickets Commerce Tickets, Orders, and Attendees

The add-on currently **DOES NOT** support the following data types:

- Event Tickets Plus/WooCommerce tickets, orders, and attendees

Adding support for these is on our roadmap.

---

## 2. Requirements

- [The Events Calendar](https://wordpress.org/plugins/the-events-calendar/) – for event-related data
- [Events Calendar Pro](https://theeventscalendar.com/products/wordpress-events-calendar/) 7.6.1 or later – for recurring events and event series
- [Event Tickets](https://wordpress.org/plugins/event-tickets/) – for ticket and attendee-related data
- [WP All Export](https://wordpress.org/plugins/wp-all-export/) (free works, Pro is recommended) on the source site where you are migrating events from.
- [WP All Import **Pro**](https://www.wpallimport.com/import-wordpress-csv-xml-excel/) (premium) on the destination site to which you are migrating events.
- WP All Import Add-On (this extension) on the destination site to which you are migrating events.

---

## 3. Preamble

The migration process is fairly simple: the data needs to be exported from one site and imported into the other site. This has to be done for every post type you would like to migrate separately. It is as easy as it sounds. Still, there are a few details to pay attention to to make sure the migration is successful.

It is recommended that you clean up event data on the source site, so that you only migrate data that is needed to avoid bloating your database.

Consider cleaning up event data on the destination site as well to start with a clean slate. Without that, you might get unexpected results, including some data getting deleted.

It is always a good idea to create a database backup of the destination site before starting the migration. To prevent any surprises, it is even better to test the migration on a staging site first, which should be an exact copy of the live destination site. **The Events Calendar is not responsible for any data loss.**

### 3.1. "Lock" the site

When you start exporting the data from the source site, make sure that you "lock" the data so that there are no more changes when you are midway through the export. Otherwise, the data can be corrupted after migration.

### 3.2. The order of importing data

The different post types of The Events Calendar and Event Tickets plugins are linked together in a certain way. This is why it's really important that the different post types are imported in a specific order. Otherwise, they cannot be re-linked during the import, and the migration will not be successful.

Here is the order in which the post types have to be imported. If you are not migrating a certain post type, you can skip that.

| Data Type                  | Post Type               |
|----------------------------|-------------------------|
| Venues                     | tribe_venue             |
| Organizers                 | tribe_organizer         |
| Events                     | tribe_events            |
| Series                     | tribe_event_series      |
| RSVP "Tickets"             | tribe_rsvp_tickets      |
| RSVP Attendees             | tribe_rsvp_attendees    |
| Tickets Commerce Tickets   | tec_tc_ticket           |
| Tickets Commerce Orders    | tec_tc_order            |
| Tickets Commerce Attendees | tec_tc_attendee         |

---

## 4. The Migration Process

### 4.1. Exporting the data

_We are going to assume that The Events Calendar and WP All Export (free or Pro) are installed and activated on the source site. For exporting Series, Events Calendar Pro is needed as well._

1. On the WordPress dashboard, head over to **WP Export > New Export**.
2. Choose "Specific Post Type" and, from the dropdown, select the post type you want to export. [image 01]
3. After choosing the post type, you will see a notification showing how many will be exported.

If you have WP All Export Pro, then continue with the **Pro** steps below (section 4.1.1). If you only have the free version, skip to [4.1.2 Exporting with WP All Export Free](#exporting-with-wp-all-export-free).

> **Important!** Migrating the Series post type requires some manual steps, so you need to follow the steps of the free version even if you have Pro.

#### 4.1.1. Exporting with WP All Export Pro

WP All Export Pro does most of the heavy lifting for you, automatically adding all necessary data to the export file.

4. With WP All Export Pro installed, you can add further filtering options to export only posts with specific properties.
5. Click on the blue **Migrate** button to proceed to the last step before the export. [image 02]
6. Skip ahead to [4.1.3. Final Export Steps](#final-export-steps).

#### 4.1.2. Exporting with WP All Export Free

With the free version of WP All Export, you need to manually select all the data you want included in the export.

4. Click on the **Customize Export File** button to advance to the next step. [image 03]
5. In the Drag & Drop step, select the data you want exported. On the right side of the screen, there is a column called "Available Data", divided into different sections. Open each section and drag the items into the frame on the left. The following must be included for a successful migration:
   - **Standard** – All
   - **Media**
     - Images: Image URL, Image Title, Image Caption, Image Description, Image Alt Text, Image Featured
     - Attachments: Attachment URL
   - **Taxonomies** – All
   - **Custom Fields** – All (without this, events will be missing crucial data like start and end date)
   - **Other** – All

6. **When exporting Series**, you must also add one more field containing the events linked to the Series:
   - Click on the **Add Field** button.
   - Add `posts_in_series` as the Column name.
   - Leave the other fields unchanged and click **Save**. [image 04]

   After saving, clicking **Preview** should show a column named `posts_in_series` populated with post IDs. [image 05]

7. At the bottom of this screen, you can save the setup as a template, or load an existing one. [image 06] When done, click **Continue**.

#### 4.1.3. Final Export Steps
_This is where the Free and Pro paths merge..._

8. On the Export Settings page, you can configure advanced settings to customize your export further. These are mostly useful for repeated or scheduled exports. For a one-time migration, no changes are needed here.
9. Click the green **Confirm & Run Export** button (top right) or the blue **Save & Run Export** button (bottom of screen) to start the export. [image 07]
10. When the export reaches 100%, download the data by clicking the blue **Bundle** button to save a `.zip` file to your PC. [image 08]

The export for this post type is done. Repeat the above steps for all post types you would like to migrate.

---

### 4.2. Importing the data

_We will assume that The Events Calendar, WP All Import Pro, and this extension are installed and activated on the destination site. For importing Series, Events Calendar Pro is needed as well._

When importing, pay close attention to the **order of import** described in [3.2. The order of importing data](#the-order-of-importing-data) above.

1. On the WordPress dashboard, head over to **All Import > New Import**.
2. Choose **Upload a file** and select the bundle `.zip` file you downloaded. If the file has been uploaded before and you are re-running the import, you can select "Use existing file" instead.
3. WP All Import will upload the file and try to select the right post type automatically. If the relevant plugin is not activated, WP All Import will not recognize the post type and the import will not work as expected. [image 09]

> **If you are importing a Series, jump to [4.3 Importing a Series](#importing-a-series) section below.**

4. Click the grey **Skip to step 4** button to go to the import settings page.
   - Alternatively, click **Continue to Step 2** to review your import file and optionally filter which posts to import.
   - In Step 3, you can map the incoming data elements to the correct post fields.
5. On the Import Settings page, verify the **Unique Identifier** field is set to `{id[1]}`. There are other settings available to fine-tune the import, but for a full migration nothing more is needed. Click **Continue**. [image 10]
6. On the confirmation page, double-check the import summary, then click the green **Confirm & Run Import** button. [image 11]
7. On the next screen, follow the progress of the import. The import finishes successfully when you see **"Import Complete!"** [image 12][image 13]
8. To verify that your import succeeded, review the new events under **Events > All Events**.

The import for this post type is done. Repeat the above steps for all post types you would like to import, following the correct order.

> For more detailed information about how the export and import works with WP All Export/Import, visit their [documentation](https://www.wpallimport.com/documentation/).

---

### 4.3. Importing a Series

Follow steps 1–3 of the [4.2. Importing the data](#importing-the-data) section above, then continue here.

4. After uploading the file, click **Continue to Step 2**.
5. If you want to import all the data, click **Continue to Step 3**.
6. In Step 3, map the post IDs connected to the Series:
   - Open the **Custom Fields** section and click **Add Custom Field** at the bottom.
   - In the right column, find `posts_in_series` and drag it into the **value** field. It should populate the value `{posts_in_series[1]}`.
   - In the **Name** field enter `posts_in_series`.
   - Click **Continue to Step 4**. [image 14]
7. In Step 4, verify the **Unique Identifier** field:
   - Clear the field.
   - Drag and drop `id` from the right column into the field. It should add the value `{id[1]}`.
   - Click **Continue**. [image 15]
8. On the confirmation page, double-check the import summary, then click the green **Confirm & Run Import** button.
9. Follow the progress of the import. The import finishes successfully when you see **"Import Complete!"**
10. If all went well, all Series should be imported with all recurring events assigned to them, as they were on the source site.

---

## 5. Logging

WP All Export/Import keeps a detailed log of its actions. The logs can be downloaded and reviewed after an import/export is done.

The Events Calendar also adds its own entries to the logs, which can provide information in case something doesn't work out. These log entries are colored blue to make them stand out.

---

## 6. Advanced Topics, Hooks

### 6.1. Forcing the import when related post type is missing

There are post types that are linked to a different post type and would not work without them. For example, an RSVP attendee depends on the RSVP ticket and the Event the RSVP ticket is linked to. If any of those are missing, the RSVP attendee will not be imported.

It is possible to override this behavior with a filter and force the import even if the related post type doesn't exist:

```php
apply_filters( 'tec_labs_wpai_force_import_' . $data['posttype'], false, $data, $import_id )
```

Where `$data['posttype']` is the post type you want to force-import.

For example, to force-import RSVP attendees even when the related ticket or event is missing:

```php
add_filter( 'tec_labs_wpai_force_import_tribe_rsvp_attendees', function() { return true; } );
```

### 6.2. Keeping empty metadata

Post types can accumulate a lot of metadata, and some of those might have empty values. During import, empty metadata will be skipped to minimize unnecessary data. There are two ways to override this.

#### 6.2.1. Force all

Force all empty metadata to be imported:

```php
add_filter( 'tec_labs_wpai_keep_empty_meta', function() { return true; } );
```

#### 6.2.2. Force selected

Define specific meta keys to keep even when they have an empty value:

```php
add_filter( 'tec_labs_wpai_keep_post_meta_meta_keys', 'tec_wpai_keep_empty_meta_keys' );

function tec_wpai_keep_empty_meta_keys( $keep_post_meta_meta_keys ) {
    $keep_post_meta_meta_keys = [
        'my_custom_meta_key_1',
        'my_custom_meta_key_2',
    ];

    return $keep_post_meta_meta_keys;
}
```

### 6.3. Mismatching post type

By default, importing Events Calendar or Event Tickets post types as a different post type is not allowed. To override this, add the following to your `functions.php`:

```php
add_filter( 'tec_labs_wpai_delete_mismatching_post_type', function() { return false; } );
```

Note: this only affects post types belonging to The Events Calendar and Event Tickets. It has no effect on other post types.

### 6.4. Setting a default post type

_Since version 1.1.0_

You can change the default post type with the following snippet:

```php
add_filter( 'tec_labs_wpai_default_post_type', static function() { return 'my_custom_post_type'; } );
```

Keep in mind, the post type has an effect on the `tec_labs_wpai_force_import_' . $data['posttype']` filter.

---

## 7. Changelog

### 1.2.0
- July 3, 2025
- **Version** – Events Calendar Pro 7.6.1 or higher is required for the migration of Series.
- **Feature** – Add support for Event Series and Recurring Events.
- **Fix** – Ensure other non-TEC post types can be imported when the extension is active.
- **Deprecated** – Deprecated the `tec_labs_wpai_is_post_type_set` filter without replacement.

### 1.1.0
- September 12, 2024
- **Feature** – Add the `tec_labs_wpai_is_post_type_set` filter to allow force importing when the post type is not defined in the source.
- **Feature** – Add the `tec_labs_wpai_default_post_type` filter to allow changing the default post type used, in case it is missing from the source.
- **Tweak** – Add more details to some log messages.
- **Tweak** – Adjust error logging to better handle special characters in log messages. (Props to Rob Gabaree.)

### 1.0.0
- October 17, 2023
- Initial release
