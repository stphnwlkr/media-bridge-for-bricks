=== Uplink Media Manager ===
Contributors: stphnwlkr
Tags: media library, folders, image optimization, gallery, bricks
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.2.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Organize WordPress media with folders, compression, galleries and optional Bricks integration.

== Description ==

Uplink Media Manager organizes your WordPress media library with folders, image optimization, and live folder galleries. It works with any WordPress theme. Bricks 2.4 or later is optional.

* Organize media in nested folders with bulk assignment and custom image order.
* Choose the enhanced media manager or the standard WordPress library with folder controls.
* Add Gallery and Slideshow displays in the WordPress block editor.
* Compare and optimize WebP, AVIF and JPEG XL locally, with backups and restoration.
* Edit and replace images, view EXIF metadata, manage alt text, and check media health.
* Import existing folders from HappyFiles, Wicked Folders and other supported media plugins.

= Gallery and Slideshow in the block editor =

Add **Uplink Media Manager Gallery** or **Uplink Media Manager Slideshow** from the block inserter. Slideshow is included as a Gallery block variation.

Both use a live Uplink Media Manager folder, optionally including child folders, and support custom folder ordering. Display images in gallery layouts or responsive slideshows, with captions, a custom or native lightbox, fullscreen, zoom, thumbnail strips, slideshow playback and EXIF controls.

For shortcode contexts, use `[uplink_mbb_gallery collection="folder-slug"]`. Earlier beta shortcodes remain supported when their legacy name is available. Disable the blocks under Media Manager Settings if they are not needed. Saved content remains in WordPress while disabled.

= Media library and folders =

Choose the enhanced Uplink Media Manager library or the standard WordPress library with folder controls. Both use the same folder records. The optional Bricks integration uses those records too. Nest folders without a plugin-imposed depth limit.

Includes bulk folder assignment, custom media order, uploads, attachment details, image editing, file replacement, EXIF metadata, alt text controls, appearance settings, and media health checks.

The settings screen includes tabs, cards, appearance controls and floating save controls.

= Image optimization and restoration =

The Optimization tab provides local post-upload compression, format comparison, per-size statistics and restoration without Cimo Pro. Format support and reference-update coverage are described below. Existing Cimo optimization data remains visible, and Cimo remains an optional pre-upload integration.

The Enhanced Manager's Optimization tab prepares one full-image preview per format. After you choose a result, only that format is encoded for every existing generated size and any unscaled original. Saved results show the actual combined bytes and per-size savings. Quality scales differ by codec. Images can also be recompressed in their current enabled format, including existing WebP files and images previously optimized by Cimo. An active Uplink Media Manager conversion must be restored before starting another conversion. Restoration recovers the exact state immediately before recompression.

WebP/AVIF conversion preserves attachment IDs, titles, captions, alt text, folders and order. Exact known URLs are updated in WordPress content/excerpts, post metadata, term metadata and stored options, including safe serialized arrays and escaped JSON. Attachment GUIDs and unrelated values are preserved. Arbitrary PHP objects, custom database tables, disk files, remote sites and third-party caches are not rewritten. Original files remain available for those links. Clear any page-builder, site or CDN caches after reference updates.

JPEG XL keeps the original attachment and its URLs as fallbacks. WordPress attachment images, post content and Bricks output can receive JPEG XL picture sources and responsive srcsets. Existing picture markup, CSS backgrounds, external links and cached HTML keep their original delivery. Disabling the plugin leaves original-format images usable.

Saved results include format, quality, completion time, original/converted totals and per-file savings. Restore originals reverses the local reference updates without discarding other content edits and restores the attachment metadata. It restores the state immediately before this conversion, including any earlier Cimo optimization. Original EXIF/GPS remains readable under the existing privacy settings. Restore before replacing or converting an attachment again. Missing or changed originals and stale comparison jobs stop the operation.

Administrator, media-upload and attachment-edit permissions plus a nonce are required. Processing is restricted to the current site. Each file must be local; conversion is limited to 100 physical files per attachment and 40 megapixels per file. Animated sources are left unchanged. Web Workers and WebAssembly must be allowed by the browser/site policy. JPEG XL previews use the bundled decoder when native browser support is absent. Encoding uses 8-bit canvas pixels; HDR/color-profile-preserving workflows are outside this version.

Original and converted files remain available after restoration for existing links. They are tracked for deletion when the attachment is permanently deleted. Temporary server uploads expire after two hours through WordPress cron. No ongoing synchronization runs. Optional pre-upload Cimo integration remains independent of these post-upload actions.

= Automatic AVIF image sizes and media health =

When the host cannot resize AVIF images, the browser automatically generates missing sizes during uploads. This works in the WordPress library and Enhanced Manager, including registered custom crops. Original AVIF files and their embedded EXIF stay unchanged; browser-generated copies omit EXIF. Keep the upload page open until processing finishes. Failed size generation can be retried without uploading the image again. Existing images can be repaired under Health > Missing image sizes. Local repair supports still AVIF originals up to 40 megapixels and 32 MB.

= Optional Bricks and CIMO integration =

Works with any WordPress theme. Bricks 2.4 or later is optional and adds shared folders in the Bricks media manager and EXIF dynamic tags.

CIMO pre-upload optimization and Keep originals controls are available when CIMO Image Optimizer is active. Bricks dynamic data includes a Uplink Media Manager EXIF group for camera, lens, aperture, shutter speed, ISO, dates, dimensions, and other image fields. GPS tags are opt-in.

EtchWP users should use Uplink Media Bridge for Etch.

= Folder imports =

Import supports HappyFiles, Wicked Folders, Folders by Premio, Enhanced Media Library and WP Media Folder by JoomUnited. Copies include stored folder hierarchy, empty folders and optional attachment assignments. Stored records are detected even when the source plugin is inactive. Post and page folders and virtual dynamic folders are excluded. Source data is preserved unless optional cleanup is selected. Media files stay unchanged.

Imported folders use shared Uplink Media Manager permissions. Source access rules, ownership, colors, custom media ordering, galleries and shortcodes are not copied. No ongoing synchronization runs. Repeated imports reuse existing mappings. Pause and resume with the same administrator account; the job retains its source and original options.

= Legacy folder cleanup =

Imports preserve source data by default. An unchecked import option can deactivate the selected site-active source and clear verified legacy media-folder data after a successful import with media assignments. On multisite, network activation stays enabled and cleanup affects only the current site.

Cleanup removes imported source folder terms, folder metadata and source assignments. It preserves files, attachments, Uplink Media Manager folders, plugin files, settings, licenses, and other content-type folders. Source galleries or shortcodes using cleared folders can stop working. A separate cleanup action is available after a fresh verified import for inactive or removed sources. Explicit consent, administrator permissions and a nonce are required. Cleanup stops when source records or imported assignments no longer match.

= Permissions =

Settings and folder imports require administrator capabilities. Shared folder management requires manage_categories. Assigning media requires upload_files and edit permission for each attachment. Inspector, EXIF and gallery output respects private and password-protected parent posts. This does not restrict direct access to WordPress upload URLs.

= Documentation =

Installation, usage, permissions, import and privacy instructions are included in this readme. The media manager works with any WordPress theme; Bricks integration is optional.

= Privacy =

EXIF is read from locally stored WordPress media. GPS metadata is hidden by default. Enabling GPS displays locations in the media inspector and makes location tags available through the optional Bricks integration; adding those tags to public content can publish a photo's location. Map service requests occur only as described above. There is no plugin telemetry or external license-check service. Optional CIMO integration uses the separately installed CIMO plugin, whose own settings and policies apply. Deactivation and deletion retain folders, assignments, settings and metadata in WordPress.

= External services =

Maps are optional. Both "Include GPS coordinates and altitude" and "Enable external location maps" must be enabled by an administrator under Media > Media Manager Settings > EXIF. GPS fields are disabled by default on new installations. External maps are disabled by default on new installations and upgrades until explicitly enabled. GPS fields and dynamic tags work without external maps.

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

= Source code and builds =

The plugin ZIP includes the editable PHP, JavaScript and CSS source for all first-party code. In particular, assets/gallery-lightbox.js is the original handwritten source, not a generated or minified bundle. Edit it directly; no npm, webpack, transpiler or other build step is required. The separate Ramka Gallery uses a compiled viewer. Its editable entry, pinned upstream source, build steps and licenses are listed in assets/ramka-gallery/SOURCES.md.

Public development repository: https://github.com/stphnwlkr/media-bridge-for-bricks

To create an installable package from the included source, place media-bridge-for-bricks.php, readme.txt, LICENSE and the includes, assets and blocks directories inside a media-bridge-for-bricks folder and ZIP that folder. Development tests and reports are not required at runtime.

Third-party compiled assets retain their upstream source, build and license references below and in their bundled SOURCES.md files. Browser Image Compression includes its readable original sources in its source map. Leaflet also includes leaflet-src.js.

= Third-party libraries =

jSquash WebP 1.5.0, AVIF 2.1.1 and JPEG XL 1.3.0 encoders are bundled locally in assets/vendor/jsquash, with the JPEG XL decoder for previews. They use Apache-2.0 wrappers and BSD codec licenses. Complete licenses and source/build links are included in that directory's SOURCES.md. The .wasm files are required runtime codecs for local WebP, AVIF and JPEG XL encoding and JPEG XL preview decoding. They are loaded by image-encode-worker.js and the jSquash JavaScript wrappers, not server executables. License and patent notices are retained as .txt files. Single-thread WebAssembly codecs run in a local background worker. No external encoding service, CDN, Cimo Pro license or cross-origin isolation headers are required.


Browser Image Compression 2.0.2 is included in assets/vendor/browser-image-compression under the MIT license. It includes UPNG.js and UZIP.js, also under MIT licenses. Complete licenses and a source map containing readable sources are included in that folder. Upstream source and build instructions: https://github.com/Donaldcwl/browser-image-compression

The PNG compressor runs inside Uplink Media Manager's local worker with its own nested-worker mode disabled. It does not load a CDN or send images to an external service. Post-upload actions save through Uplink Media Manager's authenticated API and do not require Cimo Pro.


Leaflet 1.9.4 is included in assets/vendor/leaflet under the GPL-compatible BSD 2-Clause license. Copyright 2010-2023 Volodymyr Agafonkin and 2010-2011 CloudMade. The complete license is in assets/vendor/leaflet/LICENSE; unmodified readable source is in leaflet-src.js, alongside the distributed JavaScript, source maps, CSS and images. No build step is needed to use these distributed files.

* Project: https://leafletjs.com/
* Source: https://github.com/Leaflet/Leaflet/tree/v1.9.4
* Official release package: https://registry.npmjs.org/leaflet/-/leaflet-1.9.4.tgz

= Third-party licenses =

Lucide outline icons are included under ISC/MIT licenses. Source revision and full license notices are included in assets/vendor/lucide/. Web Awesome 3.14.0 Free is bundled locally with its license and source notices.

== Screenshots ==

1. Organize photos in nested folders with search, alternate layouts, and camera metadata indicators.
2. The same photo library and folder hierarchy in Dark appearance.
3. Choose the default WordPress media workflow, manager label, folder behavior, and optional features.
4. Enable image metadata in Bricks and review the available EXIF dynamic tags.
5. Uplink Media Manager EXIF tags in the Bricks dynamic-data picker, including camera, lens, exposure, dimensions, and captions.
6. Choose output formats, exclude registered image sizes, and set separate compression and restoration permissions.
7. Edit title, alt text, captions, descriptions, and folder membership alongside a large image preview.
8. Attachment editing and folder assignments in Dark appearance.
9. Review the filename, file paths, dimensions, size, uploader, and attachment metadata.
10. File information and attachment details in Dark appearance.
11. View the original Nikon camera, aperture, focal length, ISO, shutter speed, capture date, and photo credit.
12. The same camera metadata and full image preview in Dark appearance.
13. Start local format previews from the image optimization tab. Originals are retained when compression is applied.
14. The optimization entry view in Dark appearance.
15. Compare generated WebP and AVIF previews, adjust quality, and choose which image sizes to optimize.
16. Inspect the original and AVIF preview with the comparison slider before applying compression.
17. Customize the Light palette with separate interface colors, contrast checks, and palette import/export.
18. Customize the Dark palette independently from Light mode and preview the result in the manager.

== Installation ==

1. Upload and activate Uplink Media Manager on a standard WordPress installation. Any theme is supported.
2. Open Media > Uplink Media Manager settings to select Enhanced view or WordPress view.
3. Optionally activate Bricks 2.4 or later. Its media manager can use the same folders, and its dynamic-data picker gains EXIF tags.
4. Switching themes preserves folders, assignments, settings, galleries and conversion backups.

== Frequently Asked Questions ==

= Do I need Bricks? =
No. The media manager, folders, imports, compression, restoration, file replacement, EXIF inspector, optional maps and gallery blocks work in standard WordPress. Bricks 2.4 or later adds integration with its media manager and dynamic-data picker.

= Does deleting a folder delete its files? =
No. Media files stay in WordPress.

= Does it synchronize HappyFiles folders? =
No ongoing synchronization runs. Import copies supported source folders and optional media assignments. Choose Uplink Media Manager under Folder behavior to use the imported folders in Bricks; otherwise Bricks' automatic selection can use HappyFiles.

= What happens when I deactivate or delete the plugin? =
The plugin preserves its folders, assignments, settings, and metadata.

== Changelog ==

= 1.2.1 =
* Saves changes to title, alt text, caption, and description when you leave a field, including when you click into another field or press Tab. Save also commits changes. Typing no longer triggers save requests.
* Shows the save confirmation below the inspector controls so it stays clear of the buttons.
* Defaults Ramka Gallery to a cropped mosaic and adds tile aspect-ratio options for original proportions, square, 4:3, 16:9, and 4:5. The lightbox shows the full photo.
* Adds an aperture button to view available EXIF photo details in the Ramka lightbox.
* Fixes oversized lazy-loaded thumbnails that could make the gallery jump when changing photos.

= 1.2.0 =
* Adds the separate Ramka Gallery block with the demo’s masonry photo wall, Peek and Theater views, scroll-snap navigation, zoom, and pull-to-dismiss.
* Adds a per-gallery opening view and visible photo limit with a +N tile in the Ramka Gallery and the existing custom Collection Gallery.

= 1.1.2 =
* Uses the original Dark appearance spacing as the shared layout for Light, Dark and Auto, with 18px manager padding.

= 1.1.1 =
* Fixes uploads being assigned to folders selected in previously closed media windows.
* Observes REST upload completion even when WordPress silently clears the uploading flag.
* Captures each upload destination when the file enters the queue, including Uncategorized.
* Keeps manager spacing and height consistent in Light, Dark and Auto appearance modes, including mobile layouts.
* Shows the installed version beside the WordPress media manager title.

= 1.1.0 =
* Automatically generates missing AVIF image sizes in the browser when the host lacks AVIF editing support, with custom crop support, progress and retry without duplicate uploads.
* Adds missing-size repair for existing images in Media Health. Preserves original AVIF files and EXIF, and validates attachment permissions and generated files.

* Fixes dialog and lightbox keyboard focus, background isolation, inspector keyboard scrolling, and accessibility labels. Uses Media Manager Settings for settings links.

* Restores Show EXIF in inspector in Enhanced display options and adds an administrator-controlled default on the EXIF settings tab.
* Adds Keep originals to native WordPress upload dialogs and the Add Media screen, allowing Cimo to be bypassed for metadata preservation.
* Refreshes native WordPress folder views and counts after uploads, deletions and folder assignment changes. Allows same-format recompression of existing and Cimo-optimized WebP images.
* Renames the plugin Uplink Media Manager and enables its WordPress features with any theme. Bricks integration is optional. Existing folder records, settings, shortcodes and block identifiers are retained.

* Fixes activation without Bricks, guards all required provider interfaces, uses the WordPress file uploader for conversion staging and bounds serialized reference traversal.
* Documents bundled editable source and limits notification assets to supported media and editor screens.

* Ports the Enhanced Manager interface and fixes from Etch 2.7.0, with local Web Awesome controls, consistent spacing, joined button groups, unified search and corrected input styling.
* Adds a dedicated Compression settings tab and administrator image-size exclusions honored by All sizes, with per-image overrides in Selected sizes.
* Adds drag-to-move folder assignment while retaining unrelated folder memberships and multiple-assignment actions.
* Refines inspector scrolling, compact format previews, large-preview quality controls, outside-click dismissal, close buttons and keyboard focus outlines.
* Adds local open-source Lucide outline icons, a compact selection toolbar with an information popup, and persistent error feedback.
* Adds Google Maps and Apple Maps links to photo-location maps and loads maps when opening Enhanced from the WordPress-default workflow.

* Adds All sizes, Main image only and Selected sizes with custom-size coverage, reversible partial conversion, and locally bundled Web Awesome controls.

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
* Add Gutenberg gallery and slideshow blocks using shared Uplink Media Manager folders.
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

