# Media Bridge for Bricks: one media library across WordPress and Bricks

Media Bridge for Bricks brings folder organization, image information and an expanded media workspace to sites built with Bricks Builder. Its WordPress views and its provider in the Bricks media manager use the same folder records. Organize an image in one place and the change is available in the other.

It is designed for people who work with large libraries, publish photography, or want more control over finding and maintaining their site's images.

## Choose how you work

The plugin offers two media experiences. The WordPress Media Library keeps the familiar WordPress screens and adds folder controls. The Enhanced Manager provides a dedicated workspace with alternate layouts, richer filters, bulk organization and an attachment inspector.

Choose your default experience in Media Bridge settings. In the Enhanced Manager, you can customize the manager label and colors, and choose between numbered pagination and loading more images as you scroll.

## Organize folders once

Create nested folders, move images between them and arrange media in a custom order. Media Bridge does not impose a folder depth limit. Parent folders can show their own image counts or include images in their subfolders.

Folders organize existing WordPress attachments. Adding an image to a folder does not create another copy of the file. Deleting a folder leaves its media in the library.

Bricks' media manager can use the Media Bridge folder provider, so the folders you maintain in WordPress are available while you build pages. The plugin is built for Bricks and does not require a separate folder plugin.

## Find issues and edit image information

The Enhanced Manager includes Media Health checks for missing alternative text, missing generated sizes, broken files, oversized images, older formats and suspected duplicates. These checks help identify items worth reviewing. A suspected duplicate still needs your judgment before deletion.

The attachment inspector brings titles, alternative text, captions and descriptions into the same workspace. File replacement keeps the attachment ID, folder assignments and custom order. Keeping the existing URL requires the replacement's final file type to match the existing file.

## Put EXIF data to work

For images with available metadata, the inspector can show the camera, lens, exposure, aperture, ISO, focal length and capture date. Media Bridge reads WordPress image metadata and available original-image EXIF, including a Cimo backup when appropriate. It cannot reconstruct information that was removed from every stored copy.

Enable the Bricks EXIF integration to make these values available in the dynamic data picker under Media Bridge · EXIF. Use the current attachment in an image loop, the featured image on a post, or an explicit attachment ID. For example, `{mbb_exif_camera}` displays the camera for the current image, while `{mbb_exif_camera:123}` selects attachment 123. Missing values produce empty output.

GPS information has a separate setting and is disabled by default. When GPS is enabled and an image contains coordinates, you can also enable a location map in the EXIF tab. The plugin bundles Leaflet locally. Satellite tiles come from Esri, with an OpenStreetMap street layer available. Visible maps contact those services and disclose the viewed area and normal browser request information. Photo files are not uploaded to the map services. External maps are optional and disabled by default.

## Optimize with Cimo

The optional Cimo integration brings pre-upload optimization into the upload workspace. A Keep originals switch lets you bypass that processing for an upload, which is useful when preserving embedded metadata matters.

The Enhanced Manager's Optimization tab provides local compression and WebP, AVIF and JPEG XL comparison without Cimo Pro. Preview the full image, choose a format, and Media Bridge converts its stored sizes and retains original files for restoration. Quality, date, totals and per-size savings stay visible afterward.

WebP and AVIF update WordPress and Bricks references. JPEG XL uses picture sources with original-format fallback. Cimo remains an optional pre-upload integration.

## Build galleries and slideshows in Gutenberg

Media Bridge Gallery and Media Bridge Slideshow are available in the WordPress block editor when the gallery setting is enabled. Select a Media Bridge folder and choose the presentation and image details you want to display.

Because the blocks use folder contents, adding an image to the selected folder updates the gallery without selecting that image again in each block. Gallery options include layouts, captions, lightbox controls and EXIF information. Slideshow controls provide a way to browse the same images one at a time.

A shortcode is also available, for example `[uplink_mbb_gallery collection="portfolio"]`.

## Bring your existing folders across

Open Media Bridge settings and select Import to copy folders from HappyFiles, Wicked Folders, Folders by Premio, Enhanced Media Library or WP Media Folder by JoomUnited. The source picker shows detected media folders and assignments, including records retained while a source plugin is inactive.

The importer copies stored folder hierarchy, including empty folders, and can add the folder assignments to existing WordPress attachments. Source folders and assignments stay unchanged unless you choose optional cleanup. Original files stay unchanged. Imports run in batches and can resume using the same administrator account. Repeating an import reuses previously imported folders.

Select Use Media Bridge folders in Bricks after import to use the copied folders while the source plugin stays active. Change this later under Folder behavior.

The source picker distinguishes active plugins, installed but inactive plugins, and leftover data from removed plugins. An optional import setting deactivates site-active source plugins and clears verified legacy media-folder data after migration. On multisite, it preserves network activation and affects only the current site. Files, Media Bridge folders and plugin settings remain in place. Review source-plugin galleries and shortcodes before clearing their folders.

This is a one-time copy. Later changes are not synchronized. Imported folders use Media Bridge's shared permissions. Source ownership and access rules, colors, custom media ordering, galleries and shortcodes are not converted. Wicked Folders' dynamic virtual folders are excluded. Review restricted folders and dependent content before migrating or removing the source plugin.

## Requirements and access

Media Bridge for Bricks requires WordPress 6.8 or later, PHP 8.3 or later, and Bricks 2.4 or later. Cimo is optional; local post-upload optimization does not require it.

Administrators manage settings and folder imports. Shared folder management requires WordPress category-management permission, which administrators and editors have by default. Uploaders can assign media they are allowed to edit. The plugin checks access to private and password-protected parent posts before returning attachment information through its inspector, EXIF integration and galleries.

Start by choosing your preferred media experience, creating a folder or importing existing folders, and selecting Media Bridge as the folder provider in Bricks.

## Compare image formats and restore originals

The Optimization tab previews WebP, AVIF and JPEG XL locally. Choose a format to convert the full image, every stored generated size and any unscaled original. This post-upload workflow needs no Cimo Pro license. Cimo can still be used separately during upload.

WebP and AVIF conversions preserve the attachment ID and update WordPress and Bricks references. JPEG XL uses picture sources with the original format as fallback. Originals are retained for external links and restoration. Saved results show quality, completion date, total savings and the before/after size of each file. Restore originals returns the attachment and references to their prior state while preserving later content edits.
