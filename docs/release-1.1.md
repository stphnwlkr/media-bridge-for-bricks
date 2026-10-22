# Bricks 1.1 release preparation

Prepared from Etch 2.6.5 on October 20, 2026. This branch is held until WordPress.org approves the initial Bricks submission. It does not update or replace the submitted 1.0.0 review package. No Bricks release tag, WordPress.org reupload or SVN release is authorized before that approval. Preserve the pending product-name correction recorded by the maintainer in the working copy documentation; coordinate the final name with the review team before release.

Ported changes: preview-first workflow, per-format quality sliders, original/compressed comparison dividers, large-preview quality and Apply compression, progressive previews, stale-result protection, padding and contrast improvements, JPEG XL information popup, master compression setting, enabled WebP/AVIF/JPEG XL/JPG choices, independent compression/restoration role grants and upload/image-edit enforcement. Existing Bricks folders, Cimo integration, metadata and conversion backup identifiers are preserved. GPS/map functionality already matches the Etch implementation and its existing regression checks passed.

Defaults: compression, WebP and AVIF on; JPEG XL and JPG off; compression and restoration roles administrator only. Settings from other tabs preserve selections. Restore works with compression disabled. Only selected formats are accepted at staging and commit.

Validation: 23 conversion regression checks, 10 compression-settings checks, 14 permission checks and 30 Editor-account checks passed on an isolated WordPress installation running Bricks. GPS checks passed. Plugin Check reports zero errors and the same five existing performance warnings. PHP/JavaScript syntax and distribution packaging checked. Browser verification covered settings, WebP/AVIF preview generation, synchronized large-preview quality changes, a real HTTP WebP save at quality 79 and restoration of the original JPEG.

Before release after approval: rerun package validation, confirm submission status and branding with the review team, and publish version 1.1.0 through the approved route. Keep this pull request in draft until the approval gate is satisfied.

## Size selection and UI update, October 21

The 1.1 candidate now includes All sizes, Main image only and Selected sizes. Recorded custom sizes and aliases are covered. Unselected files remain unchanged. Web Awesome 3.14.0 Free controls are bundled locally. See optimization-sizes.md for the coverage and 85 new scope assertions. Browser checks saved only the main image, then only two custom sizes, and restored both. This update remains held for WordPress.org approval.


## Etch 2.7.0 parity, October 22, 2026

The release/1.1 candidate now ports the shared changes from released Etch 2.7.0: settings exclusions and independent permissions, dedicated Compression tab, all/main/selected/custom-size coverage, Web Awesome Enhanced controls, unified search and inputs, consistent spacing and close buttons, bounded inspector scrolling, compact overview previews and Inspect controls, folder drag moves, selection help popup, 330px desktop picker and joined icon actions with right-aligned 1rem/400 count, solid disabled outlines, GPS provider links and persistent error feedback. Lucide SVGs with ISC/MIT notices replace Pro icons. No multi-image optimization shortcut or Pro icon files are included.

Bricks folder taxonomy, unlimited nesting, Bricks provider, Cimo upload/optimization routes, mbb_optimization metadata, restoration keys and existing imports remain Bricks-specific. Etch-only loop-generator controls are not introduced. The main checkout and its pending 1.0.0 submission/branding documents remain unchanged.

Validation: compression settings, scope/exclusion/alias/custom-size restoration, independent permissions, 30 Editor checks, folder moves/order, 23 conversion regression checks, folder/Bricks integration, file replacement, generated EXIF and Cimo metadata integration, GPS and shared UI asset/settings tests passed. The Cimo browser algorithm tests passed. The older cimo-exif-backup test requires a site-specific image ID 753 unavailable in the isolated site; generated EXIF and Cimo integration tests were used instead. PHP/JavaScript syntax pass. Plugin Check has zero errors and the same five existing performance warnings. Independent review found the missing explicit map enqueue and stale toast feedback; both corrected and covered by checks. Browser verification and test-site installation evidence are in verification/etch270-port/.

This is a test candidate, not authorization to publish a Bricks release or replace the pending WordPress.org package.


Installed October 22 on the Bricks test site, http://localhost:8889/wp-admin/upload.php?page=bricks-media-folders . All package entries match installed bytes. Before/after fingerprints confirm raw settings, 26 attachments and folder records are unchanged. Actual browser checks covered the single-row selection group (all5buttons44x44), help popup/Escape, upload, inspector/old restoration record and the Compression tab. No console errors; no real images converted or restored. Backup: /tmp/bricks-before-etch270-port-20261022-153846.zip .

Final check totals: settings10, sizes/exclusions96, permissions14, Editor30, folder move21, ordering24, conversion23, GPS19, Bricks/folder integration35, replacement15, generated EXIF7, EXIF/Cimo21, shared UI8, plus drag-handler and Cimo browser-algorithm regressions. Final Plugin Check:0errors,5existingwarnings. Runtime excludes all tests and verification artifacts.
