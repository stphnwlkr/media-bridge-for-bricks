# Bricks 1.1 release preparation

Prepared from Etch 2.6.5 on October 20, 2026. This branch is held until WordPress.org approves the initial Bricks submission. It does not update or replace the submitted 1.0.0 review package. No Bricks release tag, WordPress.org reupload or SVN release is authorized before that approval. Preserve the pending product-name correction recorded by the maintainer in the working copy documentation; coordinate the final name with the review team before release.

Ported changes: preview-first workflow, per-format quality sliders, original/compressed comparison dividers, large-preview quality and Apply compression, progressive previews, stale-result protection, padding and contrast improvements, JPEG XL information popup, master compression setting, enabled WebP/AVIF/JPEG XL/JPG choices, independent compression/restoration role grants and upload/image-edit enforcement. Existing Bricks folders, Cimo integration, metadata and conversion backup identifiers are preserved. GPS/map functionality already matches the Etch implementation and its existing regression checks passed.

Defaults: compression, WebP and AVIF on; JPEG XL and JPG off; compression and restoration roles administrator only. Settings from other tabs preserve selections. Restore works with compression disabled. Only selected formats are accepted at staging and commit.

Validation: 23 conversion regression checks, 10 compression-settings checks, 14 permission checks and 30 Editor-account checks passed on an isolated WordPress installation running Bricks. GPS checks passed. Plugin Check reports zero errors and the same five existing performance warnings. PHP/JavaScript syntax and distribution packaging checked. Browser verification covered settings, WebP/AVIF preview generation, synchronized large-preview quality changes, a real HTTP WebP save at quality 79 and restoration of the original JPEG.

Before release after approval: rerun package validation, confirm submission status and branding with the review team, and publish version 1.1.0 through the approved route. Keep this pull request in draft until the approval gate is satisfied.
