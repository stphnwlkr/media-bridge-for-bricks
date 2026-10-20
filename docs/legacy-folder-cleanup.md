# Plugin status and optional legacy folder cleanup

The Import screen shows plugin status separately from detected folder data for every supported source. A plugin can be active, installed but inactive, or not installed. Inactive and removed plugins with stored folders are labelled as leftover folder data. Network-wide activation is identified separately.

Folder detection reads stored records, so removing a plugin does not hide folders that can still be migrated.

## Clean up as part of import

The optional checkbox, **After import, deactivate the source plugin and clear its legacy folder data**, is off by default. Selecting it also selects media assignments. The import must complete without permission skips before any deactivation or cleanup can proceed.

On a single site, site-active instances of the selected source are deactivated. On multisite, only site-active instances are deactivated, with the required permission. Network-wide activation remains enabled. Cleanup uses only the current site's WordPress tables and options. It does not switch sites or modify network activation settings.

The source plugin files are not uninstalled. Its settings and licenses remain in place. Other content-type folders and all media files remain intact. Source-plugin galleries or shortcodes that refer to cleared folders can stop working, so review dependent content before choosing cleanup.

## Clean up an earlier import

Select the source and review Clear leftover folder data. A fresh import with media assignments is required to establish verification for this release. For a deactivated or removed source, acknowledge permanent deletion and choose Clear legacy folder data. For a network-active source, use the post-import option to authorize cleanup of this site's folders while preserving activation for the network.

Cleanup deletes the imported source media-folder taxonomy terms, their folder metadata and their source-to-media assignments. Known Wicked Folders ordering metadata for those media folders is also removed. Media Bridge folders, imported assignments, attachment IDs, media files, unrelated metadata and plugin-wide settings are preserved. Synthetic WP Media Folder roots are retained; they are not real imported folders and are excluded from detection counts.

This is targeted media-folder cleanup rather than an uninstall-data wipe. Unknown plugin metadata is not guessed or deleted.

## Verification and interruption

Each completed import records its site, source folder identities, hierarchy, mapping and options. Cleanup checks that the source still matches, imported destinations still exist, and every remaining source relationship is present in Media Bridge. New or changed folders, missing assignments, non-media relationships and shared legacy terms block cleanup.

Cleanup requires administrator import permissions, explicit consent, an account-owned job and a dedicated nonce. Site-plugin deactivation also requires activation permission. Work is limited to 100 relationships and 30 folders per request. Closing the page stops further requests; reload and resume with the same account. Imports cannot run while cleanup is in progress.

Partial cleanup keeps its remaining-folder plan so a retry does not require deleting additional data. If permissions or source records change during cleanup, it stops until the problem is resolved.

The cleanup and status tests used disposable source folders. Single-site deactivation ran through WordPress's own deactivation API. Network activation and restricted site-administrator behavior were simulated inside the single-site test installation. A live multisite network was not available for end-to-end verification.
