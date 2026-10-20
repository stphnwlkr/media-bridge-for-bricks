=== Media Bridge for Bricks ===
Contributors: stphnwlkr
Tags: media, folders, bricks
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Enhanced and WordPress media views with shared folders in the Bricks media manager.

== Description ==

Requires the Bricks theme 2.4 or later.

Choose the enhanced Media Bridge library or the standard WordPress library with folder controls. Both use the same folder records as Media Bridge's provider in Bricks. Nest folders without a plugin-imposed depth limit.

Includes bulk folder assignment, custom media order, uploads, attachment details, image editing, file replacement, EXIF metadata, alt text controls, appearance settings, and media health checks.

CIMO pre-upload optimization and Keep originals controls are available when CIMO Image Optimizer is active. Bricks dynamic data includes a Media Bridge EXIF group for camera, lens, aperture, shutter speed, ISO, dates, dimensions, and other image fields. GPS tags are opt-in.

The settings screen includes tabs, cards, appearance controls and floating save controls.

Compare WebP, AVIF and JPEG XL with local browser encoders, then convert every stored image size after choosing a result. Post-upload optimization requires no Cimo Pro license. Saved statistics, original-file backups and restoration are available in the Optimization tab. JPEG XL uses the original image as fallback.

The WordPress block editor includes Media Bridge Gallery and Slideshow, using live folders and optional child folders. For shortcode contexts, use [uplink_mbb_gallery collection="your-folder-slug"]. Earlier beta shortcodes remain supported when their legacy name is available.

Import media folders and assignments from HappyFiles, Wicked Folders, Folders by Premio, Enhanced Media Library or WP Media Folder by JoomUnited with a one-time copy. Select Media Bridge as Bricks' folder provider while HappyFiles remains active for existing content. The import does not synchronize subsequent changes.

== External services ==

Maps are optional. Both "Include GPS coordinates and altitude" and "Enable external location maps" must be enabled by an administrator under Media > Media Bridge settings > Bricks EXIF. GPS fields are disabled by default on new installations. External maps are disabled by default on new installations and upgrades until explicitly enabled. GPS fields and dynamic tags work without external maps.

When an authorized media user opens an EXIF inspector containing valid GPS coordinates and the map becomes visible, the browser requests satellite tiles from Esri. Selecting OpenStreetMap requests street tiles from the OpenStreetMap Foundation. Panning and zooming request further tiles from the selected provider. Hidden maps, images without GPS, and disabled map settings make no map-tile requests. Leaflet JavaScript, CSS, images and readable source are included locally in this plugin; they are not fetched from a CDN or the Bricks theme.

Tile requests disclose the viewer's IP address, browser/request information and site origin to the selected provider. Tile addresses identify the viewed geographic area and zoom level, which can reveal the photo's approximate location. The plugin requests an origin-only cross-site referrer. Photo files, titles, captions, EXIF records, account details and WordPress credentials are not uploaded by this integration. Requests are made directly from the browser over HTTPS and follow the browser's normal cache behavior. The plugin does not prefetch tiles for offline use or run background map requests. Provider policies apply to their handling and retention of request data. This plugin does not provide a service-availability guarantee.

= Esri World Imagery =

Provides the default satellite imagery from services.arcgisonline.com when the map is displayed. Source attribution remains visible on the map. Use of imagery is subject to Esri's terms and the service's access and usage conditions.

* Service: https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer
* Terms: https://www.esri.com/en-us/legal/terms/web-site-service
* Privacy: https://www.esri.com/en-us/privacy/privacy-statements/privacy-statement
* Products and services privacy supplement: https://www.esri.com/en-us/privacy/privacy-statements/privacy-supplement

= OpenStreetMap =

Provides the optional street layer from tile.openstreetmap.org after the user selects it. Attribution links to OpenStreetMap contributors and copyright information. The public tile service is subject to its usage policy and capacity limits; it is not an unlimited or offline tile service.

The "Open in OpenStreetMap" link opens www.openstreetmap.org only when clicked. Its URL includes the photo's GPS latitude and longitude, rounded to six decimal places. Opening that link discloses those coordinates and the browser's normal request information to OpenStreetMap.

* Service: https://www.openstreetmap.org/
* Tile usage policy: https://operations.osmfoundation.org/policies/tiles/
* Copyright and license: https://www.openstreetmap.org/copyright
* Privacy: https://osmfoundation.org/wiki/Privacy_Policy
* Terms: https://osmfoundation.org/wiki/Terms_of_Use

== Privacy ==

EXIF is read from locally stored WordPress media. GPS metadata is hidden by default. Enabling GPS makes location tags available in Bricks; adding those tags to public content can publish a photo's location. Map service requests occur only as described above. There is no plugin telemetry or external license-check service. Optional CIMO integration uses the separately installed CIMO plugin, whose own settings and policies apply. Deactivation and deletion retain folders, assignments, settings and metadata in WordPress.

== Third-party libraries ==

jSquash WebP 1.5.0, AVIF 2.1.1 and JPEG XL 1.3.0 encoders are bundled locally in assets/vendor/jsquash, with the JPEG XL decoder for previews. They use Apache-2.0 wrappers and BSD codec licenses. Complete licenses and source/build links are included in that directory's SOURCES.md. Single-thread WebAssembly codecs run in a local background worker. No external encoding service, CDN, Cimo Pro license or cross-origin isolation headers are required.


Browser Image Compression 2.0.2 is included in assets/vendor/browser-image-compression under the MIT license. It includes UPNG.js and UZIP.js, also under MIT licenses. Complete licenses and a source map containing readable sources are included in that folder. Upstream source and build instructions: https://github.com/Donaldcwl/browser-image-compression

The PNG compressor runs inside Media Bridge's local worker with its own nested-worker mode disabled. It does not load a CDN or send images to an external service. Post-upload actions save through Media Bridge's authenticated API and do not require Cimo Pro.


Leaflet 1.9.4 is included in assets/vendor/leaflet under the GPL-compatible BSD 2-Clause license. Copyright 2010-2023 Volodymyr Agafonkin and 2010-2011 CloudMade. The complete license is in assets/vendor/leaflet/LICENSE; unmodified readable source is in leaflet-src.js, alongside the distributed JavaScript, source maps, CSS and images. No build step is needed to use these distributed files.

* Project: https://leafletjs.com/
* Source: https://github.com/Leaflet/Leaflet/tree/v1.9.4
* Official release package: https://registry.npmjs.org/leaflet/-/leaflet-1.9.4.tgz

== Installation ==

1. Install and activate Bricks 2.4 or later.
2. Upload and activate Media Bridge for Bricks.
3. Open Media > Media Bridge settings to select Enhanced view or WordPress view.
4. Open Bricks' Browser > Media to access Media Bridge folders when its provider is active.

== Frequently Asked Questions ==

= Does deleting a folder delete its files? =
No. Media files stay in WordPress.

= Does it synchronize HappyFiles folders? =
No ongoing synchronization runs. Import copies supported source folders and optional media assignments. Choose Media Bridge under Folder behavior to use the imported folders in Bricks; otherwise Bricks' automatic selection can use HappyFiles.

= What happens when I deactivate or delete the plugin? =
The plugin preserves its folders, assignments, settings, and metadata.

== Changelog ==

= 1.1.0 =

* Adds preview-first image comparisons with per-format quality sliders and larger before-and-after previews.
* Adds quality adjustment, savings and Apply compression inside the larger preview.
* Adds a compression switch and WebP, AVIF, JPEG XL and JPG format settings. WebP and AVIF default on.
* Adds independent role permissions for compression and restoration. Both default to administrators; upload and image-edit permissions remain required.
* Adds JPEG XL fallback information beside the format heading and improves padding, keyboard controls and color contrast.


= 1.0.0 =
* Prepare the tested beta.15 feature set for the initial WordPress.org release.
* Use a numeric release version, matching package metadata and the prefixed gallery shortcode.


= 1.0.0-beta.15 =
* Show a prominent progress overlay during image comparison, encoding, saving and restoration.
* Keep processing status readable in light and dark appearances and prevent interaction until the operation finishes.


= 1.0.0-beta.14 =
* Add local WebP, AVIF and JPEG XL comparison and conversion across all stored image sizes, without Cimo Pro.
* Move compression, comparison, saved statistics and restoration into the Optimization tab.
* Update WordPress content, Bricks data and serialized settings for WebP/AVIF; retain original files for external links and restoration.
* Serve JPEG XL as optional picture sources with original-format fallbacks.
* Preserve EXIF through retained originals and save quality, completion time and per-size savings.


= 1.0.0-beta.13 =
* Fix post-upload PNG optimization with a locally bundled PNG compressor, matching the compressor used by Cimo Pro.
* Keep image formats and dimensions unchanged and retain Cimo backups. Report per-file errors and treat equal-size results as unchanged.


= 1.0.0-beta.12 =
* Count folder assignments independently of attachment parent publication status.
* Automatically refresh existing folder counters on an administrator visit after updating.
* Show checked and already-present assignments in import progress and completion.
* Clarify how to resume saved progress after an interrupted request.


= 1.0.0-beta.11 =
* Show a simple empty state when no source has folders available; hide import and cleanup controls.


= 1.0.0-beta.10 =
* Do not restore a completed import selection when the source has no folders left.
* Keep pending post-import actions tied to their original source.


= 1.0.0-beta.9 =
* Distinguish active, inactive and removed source plugins from retained folder data.
* Add optional verified legacy folder cleanup after import and a separate cleanup action.
* Deactivate site-active sources when authorized while preserving network activation.
* Bind cleanup to the current site, completed imports, explicit consent and preserved assignments.


= 1.0.0-beta.8 =
* Add Wicked Folders, Folders by Premio, Enhanced Media Library and WP Media Folder imports alongside HappyFiles.
* Add source detection and media-assignment counts, including retained records from inactive plugins.
* Preserve source-specific mappings, media-only scope and original options when resuming.
* Preserve new Wicked Folders sibling positions and omit synthetic WP Media Folder roots.


= 1.0.0-beta.7 =
* Add a resumable, administrator-only HappyFiles folder and media-assignment import.
* Add a Bricks folder-provider preference and documentation links.
* Match Enhanced Manager settings styling and help text to the original bridge.
* Harden media permissions and protected image metadata.


= 1.0.0-beta.6 =
* Add Cimo Pro post-upload optimization for the full image and remaining generated image sizes in the enhanced inspector.
* Keep Cimo backups, original formats and dimensions, and refresh optimization details after processing.
* Read original EXIF from Cimo backups when available.
* Add Gutenberg gallery and slideshow blocks using shared Media Bridge folders.
* Hide Optimize on images with existing Cimo optimization data.


= 1.0.0-beta.5 =
* Bundle Leaflet 1.9.4, its readable source, images and BSD license inside the plugin.
* Add a separate external-map opt-in and service/privacy disclosures.

= 1.0.0-beta.4 =
* Add a GPS location map to both EXIF inspectors using Bricks' Leaflet library.
* Add satellite imagery, an OpenStreetMap layer, and a photo-location marker.
* Load maps only when GPS is enabled and valid coordinates are present.

= 1.0.0-beta.3 =
* Show the EXIF inspector tab automatically when metadata is present.
* Read Nikon lens tags and embedded EXIF when WordPress metadata contains empty defaults.

= 1.0.0-beta.2 =
* Match the original bridge admin UI.
* Add optional CIMO integration and optimization details.
* Add EXIF fields to the Bricks dynamic-data picker.

= 1.0.0-beta.1 =
* Initial Bricks-only release with enhanced and WordPress media views.
* Shared folders through the Bricks 2.4 media-folder provider API.
* Independent settings and folder storage with unrestricted nesting.

== Post-upload optimization ==

The Optimization tab provides local post-upload compression, format comparison, per-size statistics and restoration without Cimo Pro. See Local image conversion below for format support and reference-update coverage. Existing Cimo optimization data remains visible, and Cimo remains an optional pre-upload integration.

== Gallery and slideshow blocks ==

The WordPress block editor includes Media Bridge Gallery and Media Bridge Slideshow. Both use a live Media Bridge folder, optionally including child folders, and support custom folder ordering. Gallery layouts, responsive slideshows, captions, a custom or native lightbox, fullscreen, zoom, thumbnail strips, slideshow playback and EXIF controls come from the original gallery implementation. No Etch builder adapters are included. Use [bricks_collection_gallery collection="folder-slug"] in shortcode contexts. Disable the blocks under Media Bridge settings if they are not needed. Saved content remains in WordPress while disabled.

== Documentation ==

Overview and usage: https://plugins.uplink.press/articles/uplink-media-bridge-for-bricks/

== Permissions ==

Settings and folder imports require administrator capabilities. Shared folder management requires manage_categories. Assigning media requires upload_files and edit permission for each attachment. Inspector, EXIF and gallery output respects private and password-protected parent posts. This does not restrict direct access to WordPress upload URLs.

== Folder imports ==

Import supports HappyFiles, Wicked Folders, Folders by Premio, Enhanced Media Library and WP Media Folder by JoomUnited. Copies include stored folder hierarchy, empty folders and optional attachment assignments. Stored records are detected even when the source plugin is inactive. Post and page folders and virtual dynamic folders are excluded. Source data is preserved unless optional cleanup is selected. Media files stay unchanged.

Imported folders use shared Media Bridge permissions. Source access rules, ownership, colors, custom media ordering, galleries and shortcodes are not copied. No ongoing synchronization runs. Repeated imports reuse existing mappings. Pause and resume with the same administrator account; the job retains its source and original options.

== Legacy folder cleanup ==

Imports preserve source data by default. An unchecked import option can deactivate the selected site-active source and clear verified legacy media-folder data after a successful import with media assignments. On multisite, network activation stays enabled and cleanup affects only the current site.

Cleanup removes imported source folder terms, folder metadata and source assignments. It preserves files, attachments, Media Bridge folders, plugin files, settings, licenses, and other content-type folders. Source galleries or shortcodes using cleared folders can stop working. A separate cleanup action is available after a fresh verified import for inactive or removed sources. Explicit consent, administrator permissions and a nonce are required. Cleanup stops when source records or imported assignments no longer match.

== Local image conversion ==

The Enhanced Manager's Optimization tab prepares one full-image preview per format. After you choose a result, only that format is encoded for every existing generated size and any unscaled original. Saved results show the actual combined bytes and per-size savings. Quality scales differ by codec. Eligible images can also be compressed in their current format. Existing optimization records hide that compression action.

WebP/AVIF conversion preserves attachment IDs, titles, captions, alt text, folders and order. Exact known URLs are updated in WordPress content/excerpts, post metadata, term metadata and stored options, including safe serialized arrays and escaped JSON. Attachment GUIDs and unrelated values are preserved. Arbitrary PHP objects, custom database tables, disk files, remote sites and third-party caches are not rewritten. Original files remain available for those links. Bricks CSS is regenerated; clear other site/CDN caches as needed.

JPEG XL keeps the original attachment and its URLs as fallbacks. WordPress attachment images, post content and Bricks output can receive JPEG XL picture sources and responsive srcsets. Existing picture markup, CSS backgrounds, external links and cached HTML keep their original delivery. Disabling the plugin leaves original-format images usable.

Saved results include format, quality, completion time, original/converted totals and per-file savings. Restore originals reverses the local reference updates without discarding other content edits and restores the attachment metadata. It restores the state immediately before this conversion, including any earlier Cimo optimization. Original EXIF/GPS remains readable under the existing privacy settings. Restore before replacing or converting an attachment again. Missing or changed originals and stale comparison jobs stop the operation.

Administrator, media-upload and attachment-edit permissions plus a nonce are required. Processing is restricted to the current site. Each file must be local; conversion is limited to 100 physical files per attachment and 40 megapixels per file. Animated sources are left unchanged. Web Workers and WebAssembly must be allowed by the browser/site policy. JPEG XL previews use the bundled decoder when native browser support is absent. Encoding uses 8-bit canvas pixels; HDR/color-profile-preserving workflows are outside this version.

Original and converted files remain available after restoration for existing links. They are tracked for deletion when the attachment is permanently deleted. Temporary server uploads expire after two hours through WordPress cron. No ongoing synchronization runs. Optional pre-upload Cimo integration remains independent of these post-upload actions.
