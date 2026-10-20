# Security review of Media Bridge for Bricks beta.7

Reviewed on October 20, 2026 against the separate Bricks plugin and its installable ZIP. Local verification used WordPress 7.1.3, Bricks 2.4.3 and PHP 8.4.26.

## Findings corrected

### Shared folder management was available to authors

The folder taxonomy previously mapped management, editing and deletion to `upload_files`. A normal author could therefore change shared folder structure through the authenticated folder endpoints and Bricks provider.

Management now requires `manage_categories`, available to editors and administrators by default. Assignment still requires `upload_files` and edit permission for each attachment. Settings and import require administrator permissions. Tests confirmed that authors cannot delete shared folders or move another author's attachment.

### Inspector metadata could include unreadable attachments

The enhanced manager's attachment serializer did not check access to a private parent post. A controlled author account that could not read the parent received camera metadata through the serializer used by the library response. The direct attachment-detail endpoint already required attachment edit permission, but that did not protect the broader library response.

A shared access check now runs before serialization and before EXIF cache lookup. It checks attachment visibility, private parent permissions and password-protected parents. Galleries and Bricks EXIF output use the same check. Shared Media Health cache results are filtered again for the current account before IDs and counts are returned. Tests warmed the caches as an administrator, switched to an author and confirmed that private metadata and health IDs were excluded. Anonymous and password-protected cases passed.

This is an application-output restriction. WordPress media files can still have directly reachable upload URLs, and this plugin does not provide protected file delivery or purge third-party page caches.

## Import protections

The HappyFiles import is an authenticated AJAX action. Every request checks its nonce, `manage_options`, upload permission and target taxonomy management and assignment permissions. Each copied attachment assignment also requires edit permission for that attachment.

Import sessions belong to the administrator who started them. Another administrator cannot take over an active session or use its job identifier. Random or stale identifiers fail. An atomic option lock serializes start and step requests. Lock release compares the token so one request cannot release another request's lock. A paused job can be replaced after 30 minutes; the request mutex expires after two minutes.

Folder batches are limited to 30. Assignment batches read at most 100 relationships, with keyset pagination and prepared SQL. Missing source or destination folders, invalid hierarchy and database errors stop processing. Destination source mappings and existing assignments allow a new import to continue without duplicate folders or relationships. Names are sanitized and UI output is escaped or assigned as text. No source HappyFiles records or media bytes are changed.

Tests covered 35 disposable source folders, nesting, empty folders and 210 attachment relationships across 105 disposable attachments. Repeat imports, destination edits, source preservation, ownership, concurrent requests and invalid nonces passed. The original HappyFiles test folder was then imported through the browser and confirmed in Bricks' active Media Bridge provider.

## Other reviewed areas

Authenticated mutation endpoints were reviewed for nonce and capability checks, including folder changes, attachment updates, deletion, file replacement, appearance settings and the Cimo plan. No first-party anonymous mutation endpoint, dynamic remote-code loader, command execution path or backdoor was found in the reviewed source.

Metadata, folder names, gallery attributes and captions were reviewed for output handling. EXIF values use text sanitization and escaped output. Gallery captions permit the WordPress safe HTML subset. The existing EXIF injection test passed. JavaScript uses text output for attachment information and import status. Its remaining HTML insertion handles bundled SVG markup or WordPress attachment compatibility markup supplied by trusted installed PHP code.

Replacement uploads use WordPress upload and MIME validation, per-attachment edit permission, confinement to the local uploads directory and format checks for URL-preserving replacement. The replacement regression suite passed, including rollback and preserved attachment information. File paths are derived from WordPress attachment records, not arbitrary request paths.

Cimo post-upload optimization requires the separate licensed Pro integration and its permissions. Images with existing optimization metadata have no action or server plan. Disabled integration and anonymous checks passed. Its engine and REST save implementation belong to the separately installed Cimo Pro plugin and were not independently audited here.

GPS output and external maps require separate settings. The map tests confirmed that disabling GPS omits GPS data and prevents map assets from loading. Leaflet is bundled locally with its source and license. Tile services are disclosed and external maps are disabled by default. No assertion is made that every third-party dependency or installed plugin is free of vulnerabilities.

## Verification and limits

All 16 first-party PHP files and 14 first-party JavaScript files passed syntax checks. The shared-folder integration suite and eight regression suites passed, covering file replacement, custom order, EXIF/Cimo, raw EXIF reading, map privacy, Cimo planning/backup recovery and Gutenberg galleries/slideshows. The dedicated import/security suite passed 30 checks.

WordPress Plugin Check against the extracted release reported zero errors and five performance warnings. Two refer to taxonomy filters, one to a sibling exclusion when moving a folder, and two to the source-ID term-meta lookup used for repeat imports. These are reviewed query-performance advisories, not security findings. Imports are bounded, but large production libraries should still be tested for runtime and database load.

This was a targeted manual review and local regression exercise. It is not a penetration-test certificate, an audit of WordPress/Bricks/HappyFiles/Cimo, or a guarantee of WordPress.org acceptance. No first-party code was uploaded to an external security scanner. The release has not been submitted or published.

WordPress security references used for the review: [nonce guidance](https://developer.wordpress.org/apis/security/nonces/), [capability checks](https://developer.wordpress.org/reference/functions/current_user_can/), and [common plugin review issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/).
