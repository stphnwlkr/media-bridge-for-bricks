# Media Bridge for Bricks

WordPress media tools with folders shared directly with Bricks' media manager.

Version: 1.0.0. Requires the active Bricks theme 2.4 or later, WordPress 6.8 or later, and PHP 8.3 or later. Tested locally with Bricks 2.4.3, WordPress 7.1.3, and PHP 8.4.26.

## Media views

Choose a view under **Media > Media Bridge settings**.

- **Enhanced view** provides the Media Bridge library, folder tree, bulk assignments, custom media order, grid/list/masonry layouts, uploads, attachment details, image editing, alt text, EXIF metadata, and media health checks.
- **WordPress view** adds the same folders to the standard WordPress media library and media dialogs.

Folders use one attachment taxonomy, `mbb_folder`. Creating, renaming, moving, or assigning a folder in Bricks updates the same records used by the WordPress views. There is no synchronization job and no imposed folder-depth limit. Folder deletion keeps the media files. Files may belong to multiple folders in WordPress; moving a file in Bricks replaces its folder assignments.

File replacement preserves the attachment ID and metadata. Keeping the existing URL requires the same final file type and extension. Choosing a new filename can change the type; existing files remain available for old content links. GPS metadata stays hidden by default. The EXIF inspector tab appears automatically when the image contains camera metadata.

## CIMO and EXIF

The settings screen uses the same hero, tabs, cards, and floating save controls as the original bridge. The **Bricks EXIF** and **CIMO** tabs keep their settings independent.

CIMO integration is enabled by default when CIMO Image Optimizer is active. Uploads and file replacement include its local pre-upload optimization and a **Keep originals** switch. Closing the workspace restores CIMO's previous optimization setting. Attachment details show CIMO savings, format, size, and processing data. Stale optimization records are not shown as current. CIMO remains optional.

In Bricks' dynamic data picker, open **Media Bridge · EXIF**. Tags use the current attachment in an image loop, or the post's featured image. Append an attachment ID to choose a specific image:

```text
{mbb_exif_camera}
{mbb_exif_lens}
{mbb_exif_aperture}
{mbb_exif_shutter_speed}
{mbb_exif_iso}
{mbb_exif_camera:123}
```

The picker also includes focal length, capture date, credit, copyright, dimensions, file size, alt text, and caption. GPS coordinates and altitude appear only when enabled in settings. With valid coordinates, the EXIF tab includes a Leaflet map using the plugin's own bundled Leaflet 1.9.4 library, a photo marker, satellite imagery from Esri, and an OpenStreetMap street layer. External maps require a separate opt-in, disabled by default. Tiles load only when the map is visible. See readme.txt for service terms, privacy disclosures and the included Leaflet license/source information. No GPS data means no map; disabling GPS omits coordinates from the response and prevents map assets from loading. Missing fields return an empty string. Private attachment metadata requires permission to read its parent post. EXIF values refresh when the image or its metadata changes.

## Bricks integration

The plugin registers `media-bridge` through `bricks/media_folder_providers/register`. Bricks selects it when no earlier available provider is active. HappyFiles retains its own folder storage and takes precedence under Bricks' automatic provider selection. The Import tab copies HappyFiles media folders and optional attachment assignments into Media Bridge. Use the Folder behavior preference to select Media Bridge in Bricks while HappyFiles remains active. Later changes are not synchronized, and HappyFiles galleries, shortcodes and custom media ordering are not converted.

Bricks 2.4.3 limits its upload-verification helper to HappyFiles. Media Bridge handles `bricks_verify_media_upload_folder` at priority 1 only when its own provider is active. The handler uses Bricks' builder nonce validation, checks upload and attachment edit permissions, and reads back the actual assignment. Other providers continue through Bricks' original handler.

The project has independent PHP classes, asset names, settings, nonces, and metadata keys. It has no dependency on the source project's builder, collection taxonomy, migration data, gallery generator, or external folder adapters.

## Folder import and access

Open **Media > Media Bridge settings > Import** and choose HappyFiles, Wicked Folders, Folders by Premio, Enhanced Media Library or WP Media Folder by JoomUnited. Detected folder and media-assignment counts appear for each source. Stored records are detected even with the source plugin inactive. The import copies empty and nested media folders and preserves existing Media Bridge folders and assignments. Source records are preserved unless optional cleanup is selected. Files are never moved. Repeat runs reuse source mappings and matching sibling names. Pause and resume with the same administrator account. The source and original options remain locked while a job runs. Paused jobs expire after 30 minutes.

New HappyFiles and Wicked Folders siblings follow source folder positions. Other sources use source term ID order. Only media folders and optional attachment assignments transfer. Dynamic virtual folders, source ownership/access rules, colors, custom media ordering, galleries and shortcodes do not transfer. Imported folders use Media Bridge's shared permissions. See `docs/folder-imports.md` for formats and verification scope.

Settings and imports require administrator capabilities. Shared folder management requires `manage_categories`; media assignments require `upload_files` and edit permission for each attachment. Private and password-protected parent posts are checked before inspector, EXIF and gallery output. Health results are filtered for the current account, including when read from a shared cache. These checks do not make WordPress upload URLs private.

Documentation: https://plugins.uplink.press/articles/uplink-media-bridge-for-bricks/

Article draft: `docs/overview-article.md`. Security findings and scope: `docs/security-review-beta.7.md` and `docs/security-review-beta.8.md`.

## Local development

The repository root is the plugin root.

Test site: `http://localhost:8889/`

For local testing, run `python3 tools/build.py` and install the generated ZIP in WordPress. The build also stages a clean runtime copy under `builds/test-site/media-bridge-for-bricks`. Rebuild and reinstall after source edits. The Studio test site uses a normal plugin directory. Developer tests, reports and old archives stay outside the installed plugin, so local Plugin Check results match distribution checks. Deactivation and deletion preserve folders and settings. This beta has no data-erasure feature.

Run tests on a test installation with the plugin active. They create isolated fixtures and remove them afterward:

```sh
studio wp eval-file tests/integration.php --path /path/to/wordpress
studio wp eval-file tests/file-replacement.php --path /path/to/wordpress
studio wp eval-file tests/media-order.php --path /path/to/wordpress
studio wp eval-file tests/exif-cimo.php --path /path/to/wordpress
studio wp eval-file tests/exif-reader.php --path /path/to/wordpress
studio wp eval-file tests/gps-map.php --path /path/to/wordpress
```

`python3 tools/build.py` creates the installable ZIP in `builds/`. It includes the plugin entry point, includes, assets, blocks, readme, and license. Tests, development notes, and screenshots stay out of the distribution.

## License

GPL-3.0-or-later. Copyright Stephen Walker. Adapted from the author's Media Bridge project.

## Post-upload optimization

The Optimization tab compares WebP, AVIF and JPEG XL using locally bundled browser encoders, without requiring Cimo Pro. It generates a full-image preview for each format, then processes all stored sizes after a result is selected. JPEG and PNG images can also be compressed in their current format when no optimization record exists. A progress overlay remains visible during processing.

WebP and AVIF conversions update the attachment and supported current-site WordPress references while retaining original files and restoration data. JPEG XL uses picture sources with the original image as fallback. Saved results include quality, completion time, total savings and per-size figures. Restore reverses supported references while preserving subsequent content edits. Reference updates do not cover arbitrary custom tables, PHP objects, external sites or disk files; original URLs continue to work. The EXIF inspector reads retained sources when converted files no longer contain embedded camera metadata.

CIMO remains optional for pre-upload optimization. Its own records and backups remain separate from Media Bridge conversions. See `docs/image-conversion-verification.md` for tested behavior and limitations.

## Gallery and slideshow blocks

The WordPress block editor includes Media Bridge Gallery and Media Bridge Slideshow. Both use a live Media Bridge folder, optionally including child folders, and support custom folder ordering. Gallery layouts, responsive slideshows, captions, a custom or native lightbox, fullscreen, zoom, thumbnail strips, slideshow playback and EXIF controls come from the original gallery implementation. No Etch builder adapters are included. Use [uplink_mbb_gallery collection="folder-slug"] in shortcode contexts. Disable the blocks under Media Bridge settings if they are not needed. Saved content remains in WordPress while disabled. The earlier `[bricks_collection_gallery]` shortcode remains a compatibility alias when that name is available.

## Source status and cleanup

Each import source distinguishes plugin activation from stored folder data. Removed or inactive plugins with folders are labelled as leftover data. The import has an unchecked option to deactivate the source and clear its verified legacy media-folder records after successful migration. Only site-active plugin files are deactivated; network activation remains unchanged. All cleanup is scoped to the current site.

A separate confirmed cleanup action is available after a fresh import for inactive/removed sources. Source folders, their metadata and source media assignments are removed only after verification. Files, attachments, Media Bridge records, other content-type folders and plugin settings remain. See `docs/legacy-folder-cleanup.md` and `docs/security-review-beta.9.md` for scope and test limitations.

## Folder counts and import progress

Folder counts describe media assignments, including unattached images and media attached to unpublished posts. Existing stored counters are refreshed once when an administrator opens WordPress admin after updating. This does not change folder relationships. Import progress shows all assignments checked; completion separates newly added assignments from those already present. Resume continues the saved job.

## Local compression and format comparison

The Optimization tab provides local compression and WebP, AVIF and JPEG XL previews, all-size conversion, saved per-size statistics and Restore originals. No Cimo Pro license is required. Cimo remains an optional upload integration. See readme.txt for reference-update coverage, JPEG XL fallback behavior and codec sources.

## Publishing preparation

Use Pressship from this source directory. `.pressshipignore` excludes development tools, tests, reports and marketing files while retaining runtime assets and third-party licenses and source references.

```sh
npx pressship verify .
npx pressship pack . --output-dir builds/pressship
npx pressship publish . --dry-run -y --output-dir builds/pressship
```

Review all findings before publishing. Uploads, SVN commits, Git commits, tags, remotes and repository creation require the maintainer's approval. Source repository: https://github.com/stphnwlkr/media-bridge-for-bricks.
