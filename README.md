# Uplink Media Manager

Version 1.2.0 adds Ramka Gallery as a separate block, with the demo’s dark lightbox, Peek and Theater views, and a +N photo tile. Both gallery blocks let you set the opening view and the number of visible photos.

The manager provides folders, an enhanced media manager, native WordPress media controls, local image compression and restoration, file replacement, EXIF inspection, optional maps, and live gallery/slideshow blocks. It works with any WordPress theme. Bricks 2.4 or later adds a shared folder provider and EXIF dynamic tags.

WordPress.org approved Uplink Media Manager under the confirmed `uplink-media-manager` slug on October 30, 2026. The package uses that directory name and translation domain. The `media-bridge-for-bricks.php` entry point and existing taxonomy, option keys, metadata, block identifiers, shortcodes, hooks and provider ID remain for update compatibility.

Build the installable ZIP with `python3 tools/build.py`. Runtime source is directly editable. The bundled Ramka viewer can be rebuilt using the instructions in assets/ramka-gallery/SOURCES.md. Third-party source/build/license references are bundled with their assets and documented in readme.txt.

Full usage, permissions, external-service disclosures and conversion limits are in readme.txt.

## Verification

The October 25, 2026 review build was installed and checked on vanilla WordPress at localhost:8887 and Bricks at localhost:8889, both WordPress 7.1.3 and PHP 8.4. Verification logs and private local backups are excluded from the plugin package.

Regression suites cover folder assignments and nonce rejection, conversion and rollback, compression/restoration roles, image-size selection, file replacement, galleries, GPS, cyclic serialized data and same-format recompression of both ordinary and Cimo-optimized WebP. Exact pre-recompression bytes and metadata are restored. Theme switching is tested in a disposable installation.

A browser reproduction using a disposable copy of the vanilla site confirmed that taxonomy-filtered WordPress attachment queries do not automatically observe new uploads. The native library now refreshes its current query and folder counters after completed uploads. Three Cimo-processed images appeared automatically in the selected folder, increasing its count from 11 to 14. Cimo WebP recompression was also exercised in the browser, including all five image sizes and restoration.

Run PHP integration suites through WP-CLI `eval-file` in a disposable WordPress installation with the plugin active. Studio-managed sites use `studio wp eval-file`. Suites create and remove their own fixtures and restore settings. Avoid running settings-mutating suites while editing those same settings in a browser.

Native WordPress uploads include Keep originals when Cimo is available, including the separate Add Media screen. Tests verified byte-identical EXIF JPEG uploads and restoration of normal Cimo compression for the next upload. Folder counts refresh after native deletions and assignment changes. Enhanced display options include Show EXIF in inspector; its initial value follows the EXIF settings tab while allowing a browser override.
