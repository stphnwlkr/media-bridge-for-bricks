# Importing media folders

Open Media > Media Bridge settings > Import. The source picker lists HappyFiles, Wicked Folders, Folders by Premio, Enhanced Media Library and WP Media Folder by JoomUnited. Each source shows its detected media-folder and assignment counts. A source without stored folders cannot be selected.

Stored taxonomy records can be detected even if their source plugin is inactive. The plugin must have created media folders on this WordPress site. The importer does not fetch folders from another website or accept an exported file.

Select a source, choose whether to copy media assignments, and select Import folders. Empty folders and nested folders are included. Existing attachments are reused, and their current Media Bridge assignments remain intact. Files are not copied, moved or uploaded again.

Use Media Bridge folders in Bricks after import selects the Media Bridge provider while other plugins stay active. It preserves the selected WordPress media experience and all other settings. Leave this option off to retain the current Bricks provider preference.

## Resume and repeat

Requests process up to 30 folders or 100 media assignments. Pause waits for the current request to finish. Reload and resume with the same administrator account. The source and options are stored with the job; changing browser defaults does not change a resumed import. Finish the current source before selecting another. A paused job can be replaced after 30 minutes.

Repeating an import reuses source mappings. For folders without a prior mapping, a matching name under the same parent reuses the existing destination. Existing destination names, positions and hierarchy are preserved. Newly created HappyFiles and Wicked Folders siblings follow their source folder positions. Other sources use source term ID order for new siblings.

The importer is a one-time copy. Later changes are not synchronized. Source records are preserved unless the separate, optional cleanup is selected. See `legacy-folder-cleanup.md` for status labels, deactivation and cleanup scope.

## What transfers

| Source | Stored media taxonomy | Folder behavior |
| --- | --- | --- |
| HappyFiles | `happyfiles_category` | Includes empty and nested folders; retains beta.7 import mappings |
| Wicked Folders | `wf_attachment_folders` | Imports stored media folders and sibling order; excludes dynamic virtual folders |
| Folders by Premio | `media_folder` | Imports media folders; ignores post/page folders |
| Enhanced Media Library | `media_category` | Imports media categories as folders, including hierarchy |
| WP Media Folder by JoomUnited | `wpmf-category` | Excludes the synthetic media-root folder and places its children at the top level |

Only attachment relationships are copied. Post, page, user and plugin folders are outside the importer. Counts represent media-to-folder relationships, so an image assigned to two folders contributes two assignments.

Imported folders use Media Bridge's shared folder permissions. Source ownership and access restrictions, folder colors, custom media ordering, galleries and shortcodes do not transfer. Review restricted source folders before copying them into a shared library. Review dependent content before removing the source plugin.

## Access and safety

Imports require administrator settings permission, upload permission and target folder management and assignment permissions. Every attachment also requires edit permission. Each request checks a nonce. Jobs are account-owned, source identifiers are allowlisted and an atomic lock prevents simultaneous mutation requests.

Invalid hierarchies or changed source/destination folders stop the job. Start another run to retry safely. Existing source mappings and attachment relationships prevent duplicates.

## Verification scope

The HappyFiles integration and security suite passed against the installed plugin. The five adapters were checked with isolated storage-format fixtures for nesting, empty folders, multiple assignments, excluded post relationships, inactive taxonomies, source preservation and repeat imports. Wicked Folders' taxonomy and `wf_order` key were verified against the official WordPress.org 4.2.0 package. The browser also completed a Wicked Folders fixture import. Other commercial plugin editions were not installed for end-to-end UI testing.

HappyFiles' installed importer and [published importer list](https://happyfiles.io/) were reviewed as the comparison. This implementation uses its own read-only taxonomy adapters and existing Media Bridge batching engine.
