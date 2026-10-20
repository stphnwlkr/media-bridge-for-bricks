# Image conversion verification, beta.14

Post-upload conversion uses locally bundled encoders without a Cimo Pro dependency. It compares one full-image preview per format, then encodes every stored image size only after the administrator selects a result. Each preview runs in a separate worker, released afterward. The exact cause of the earlier generic WebAssembly abort was not reproduced; the revised workflow completed all three previews for a 2673 × 2126 photograph.

## Runtime checks on localhost:8889

- PNG fixture: ten physical files converted to AVIF with matching dimensions, references updated in HTML, Gutenberg JSON, serialized Bricks data and options. Original hashes remained unchanged. Restore preserved subsequent content edits.
- JPEG XL fixture: ten alternate files saved with responsive picture markup and original-image fallback. Existing picture elements were not nested. Restore removed the alternate mapping.
- JPEG fixture: full image and two generated sizes compressed at quality 80, with 44.7% total savings. Restore recovered the original attachment.
- Revised comparison workflow: WebP, AVIF and JPEG XL previews completed for the 5.7-megapixel photograph. Selecting WebP then encoded the remaining two sizes. Total savings were 55.6%. Quality, completion date, per-size results and Restore remained available after reload in the normal split layout.
- Camera, lens, ISO, exposure, aperture, focal length and capture date remained readable after conversion through the retained source. GPS follows the existing privacy setting.
- PNG current-format compression also produced a preview in the local module worker.
- A changed attachment rejected a stale comparison before commit.

## Regression checks

The self-cleaning WordPress CLI test in tests/conversion-regression.php passed 23 checks covering authorization, traversal, unsupported formats, HTTP-upload validation, incomplete jobs, reference updates, serialization, source preservation, restore, intervening edits, rollback after a denied reference and stale metadata.

## Browser and scope limits

Actual browser testing used the Chromium-based in-app browser. Safari and Firefox have not been tested directly. Encoding depends on module workers, WebAssembly, createImageBitmap and OffscreenCanvas 2D. These are standard browser APIs rather than Chromium-specific APIs; older browsers, restrictive security policies or insufficient memory can prevent encoding. JPEG XL previews use a bundled decoder; public images retain their original fallback.

Reference updates cover current-site WordPress content, supported metadata and options. They do not rewrite arbitrary custom database tables, PHP objects, external sites, disk files or attachment GUIDs. Originals remain available. Multisite behavior has not been verified in a live network.

## Package checks

The final beta.14 distribution passed Plugin Check with zero errors. Five existing query-performance warnings remain in folder filtering and import code. Changed JavaScript and conversion PHP files passed syntax checks. Disposable image and reference fixtures were restored and removed; original user images were retained.

## Beta.15 progress overlay

The user reported successful Safari and Firefox testing of beta.14, with processing feedback difficult to see. Beta.15 adds an overlay with a spinner, a prominent live status message and a keep-open reminder during comparison, encoding, saving and restoration. The panel uses the manager appearance colors and respects reduced-motion preferences. Underlying dialog controls are inert while processing, and their previous state and keyboard focus are restored afterward.

A real-browser comparison on a disposable 2673 × 2126 JPEG completed WebP, AVIF and JPEG XL previews. The overlay was visible during processing and hidden afterward. Readback confirmed no inert siblings remained, aria-busy was false and keyboard focus returned to the comparison button. No conversion was applied to user images. New overlay testing used Chromium; Safari and Firefox feedback above applies to beta.14.
