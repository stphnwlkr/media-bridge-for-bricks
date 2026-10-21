# Optimization size scopes and Web Awesome UI

Prepared October 21, 2026. Etch 2.7.0 is a test candidate, not a public release. Bricks changes remain part of the held 1.1.0 candidate.

The previous implementation already iterated every generated entry in WordPress attachment metadata, including custom size names. It did not expose file selection. It deduplicated paths shared by multiple size names. Registered sizes that were never generated for a particular image are not existing files and are not created by compression. Files stored outside WordPress attachment metadata are outside this inventory.

New per-image scope: All sizes, Main image only, Selected sizes. The inventory lists unique physical files, dimensions and source bytes, with aliases grouped. All is the default. Preview still shows the main image; only selected files are encoded on apply. Scope is selected on the server before staging, checked against the owned session manifest, and cannot change after staging starts. Unknown/empty selections are rejected. Oversized original uploads are listed unavailable; the scaled main image can still be selected. Staging and commit enforce the encoding limit.

Partial conversion changes only selected image-size metadata and URL references. Main file identity/MIME changes only when full is selected. Unselected derivatives, original-upload metadata and editor backups remain intact when outside the selection. Responsive srcset works with mixed image formats. Statistics cover selected physical files only. The original snapshot supports restoration for all three scopes.

Web Awesome 3.14.0 Free components are locally bundled with MIT license and source references. Buttons, size radios, checkboxes and progress use its components. The embedded theme inherits the manager palette, excludes native/reset styles and uses no runtime CDN. Component and design skills were read from the official npm package. Existing quality-slider and comparison recipes are retained. Failed saves cancel staging and reset previews so users can retry with another scope; keyboard fallback focuses the Web Awesome retry button.

Verification: 85 size-scope assertions passed, covering standard sizes, registered custom sizes, historical custom sizes, aliases, empty/invalid selections, staging locks, exact main/derivative metadata, mixed-format srcset, selected statistics, restoration, byte preservation and oversized originals. Existing 23 conversion regression checks, 30 Editor checks and Enhanced media checks passed. The equivalent size tests also passed in Bricks. Independent review findings on oversized originals and failed-save recovery were fixed.

Browser tests on isolated Bricks: seven available files; Main image only saved one file and kept all six derivatives JPEG. Selected sizes saved custom_card and custom_hero only; the main and all four standard derivatives remained JPEG. The result table reported exactly two custom files and their savings. Restored after both tests. Screenshot and raw reports are in the local verification directory. No user media was converted for testing.

Plugin Check: zero errors; Etch retains two existing query-performance warnings, Bricks retains five. These were not suppressed. Builds and installation checks are kept in this verification directory. No WordPress.org release is authorized for these new changes yet.
