# Legacy cleanup security review, beta.9

This update adds source-plugin status detection and explicitly authorized legacy media-folder cleanup. Previous import and protected-media checks remain in place.

Source status uses WordPress plugin inventory and activation APIs, including network activation, rather than inferring activation from leftover taxonomy rows. The source list remains server-allowlisted. Standard source directories and plugin header names identify supported editions.

The optional post-import action is off by default. A completed import receipt records the current site, source, folder signatures, destination mapping, options and permission skips. Cleanup requires media-assignment import without skips and verifies that every remaining source relationship is represented in Media Bridge. Changed source folders, missing destinations, missing assignments, non-media relationships and shared taxonomy terms prevent cleanup.

Each cleanup request checks a dedicated nonce and administrator upload/folder permissions. Cleanup jobs belong to one account and use the same atomic mutex as imports. Imports are blocked while cleanup is running. Work is limited to 100 relationships and 30 folder deletions per request. WordPress term APIs remove folder metadata and relationships. Only the known Wicked media-folder ordering key is additionally removed; broad metadata or option-prefix deletion is not used.

Site-plugin deactivation uses WordPress's deactivation API with network-wide behavior explicitly disabled. Site-active source files require activation permission. Network-active source files are excluded from deactivation, and their activation is preserved. Network-active cleanup requires the explicit post-import authorization and successful retirement verification. It uses the current blog's options and database tables, checks the receipt's blog ID, and never switches blogs or changes network options.

The source plugin can remain installed after cleanup. No plugin files, media bytes, attachment records, Media Bridge folders, unrelated metadata, licenses, plugin-wide settings or other content-type folders are intentionally removed. Source galleries and shortcodes can break when their referenced folders are deleted; the UI discloses that consequence before consent.

Status detection for all five providers was tested in active, inactive and removed states. Disposable source fixtures tested large batches, destination preservation, unrelated metadata, source folder metadata, known Wicked ordering metadata, explicit confirmation, changed site IDs, missing imported assignments, non-media relationships, permissions, job IDs and import/cleanup locking. Invalid nonce and malformed/unauthorized request checks passed without modifying activation or job state.

Single-site deactivation used the real WordPress API. Multisite activation and restricted site-administrator behavior were simulated on the local single-site installation. This is not live multisite end-to-end verification or a security certification. User legacy data was not cleared during testing.
