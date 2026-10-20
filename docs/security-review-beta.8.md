# Folder import security review, beta.8

This update expands the administrator-only import engine to five allowlisted media taxonomy formats. The beta.7 folder-management and protected-image fixes remain in place. See `security-review-beta.7.md` for that review's findings and wider scope.

The only request-selected input used to identify a source is a provider key. It must match a server-defined provider. Requests cannot supply a taxonomy, database table, path or SQL fragment. All source queries use WordPress's own table names and prepared values. Inactive-plugin support reads retained taxonomy records without loading source-plugin code or registering its taxonomy in the live request.

Every import request checks a nonce and administrator, upload, folder-management and assignment permissions. Every media assignment also checks attachment edit permission. Job ownership, source locking, bounded batches and the compare-and-delete mutex are retained. New source mappings include their provider identifier, while legacy HappyFiles mappings and running jobs remain compatible.

Source folders and assignments are read only. Only attachment relationships transfer, and each is appended to existing Media Bridge relationships. Stored Wicked Folders media terms are supported; dynamic virtual folders are excluded. The synthetic WP Media Folder root is omitted and its children are normalized to top-level destinations. Missing or changed parents stop processing.

Imported folders use Media Bridge's shared access model. Source ownership and access restrictions are not converted. The settings panel discloses this before import. Folder counts and names are visible only to administrators on the import settings page. Source names, counts and status messages are escaped in PHP or assigned as text in JavaScript.

Validation used all five source storage formats, inactive taxonomy records, empty/nested folders, non-media relationship exclusion, repeat imports, existing destination reuse, source preservation, more than 30 folders and 100 attachments, per-image permission denial, source changes during an active job, and changed hierarchy rejection. The existing HappyFiles/security suite was rerun. Wicked Folders' schema was checked against the official WordPress.org 4.2.0 package, and its fixture import completed through the browser.

This is a targeted review of this update. Other commercial source plugins were checked with schema fixtures rather than installed full editions. It is not an audit or security certification of those plugins. No source-plugin cleanup or deletion action is exposed.
