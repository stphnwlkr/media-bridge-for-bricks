# Publishing preparation snapshot, October 20, 2026

This records the checks before publication. The maintainer subsequently approved public GitHub repository creation, the initial source commit and push, and WordPress.org submission. See publication-status.md for the final outcome.

Prepared locally with Pressship 0.2.2 using its WordPress plugin publishing skill and https://pressship.org/ai. No validation was bypassed. Nothing was uploaded, submitted, committed or pushed. No Git branch, tag or remote was changed. The repository has no commits or remotes; creating and populating the public GitHub repository is awaiting approval.

## Identity

| Item | Prepared value |
| --- | --- |
| Plugin name | Media Bridge for Bricks |
| Author | Stephen Walker |
| WordPress.org account and contributor | stphnwlkr |
| Plugin slug, package folder and text domain | media-bridge-for-bricks |
| Entry point | media-bridge-for-bricks.php |
| Version and stable tag | 1.0.0-beta.15 |
| PHP namespace | UplinkPress\MediaBridgeForBricks |
| First-party global prefix | uplink_mbb_ / UPLINK_MBB_ |
| Block namespace | uplinkpress/bricks-collection-gallery |
| Canonical gallery shortcode | uplink_mbb_gallery |
| Planned public GitHub repository | stphnwlkr/media-bridge-for-bricks |
| Documentation URL | https://plugins.uplink.press/articles/uplink-media-bridge-for-bricks/ |
| License | GPL-3.0-or-later |

The PHP namespace is consistent across all 20 include files, with no duplicate first-party class names. The entry point's global function is prefixed. Translation domains and block metadata match the slug. No production PHP references to Etch remain, and no Bricks identifiers were found in the original Etch PHP project. This audit checks the supplied projects; it does not reserve names across the WordPress ecosystem.

Persistent taxonomy, option and metadata identifiers are retained to preserve beta data. The earlier bricks_collection_gallery shortcode remains a compatibility alias only when another plugin has not registered it. Settings and documentation now show the uniquely prefixed shortcode.

## Validation and tests

- Pressship verify completed with remote WordPress.org readme validation and managed Plugin Check enabled.
- Readme findings: zero. Plugin Check errors: zero. Plugin Check warnings: five, listed below. These remain open for maintainer review; they were not suppressed or accepted as a publishing exception.
- Pressship pack completed with validation enabled.
- Pressship publish . --submit --dry-run -y completed successfully and reported that no upload was attempted.
- The initial automatic-route dry run stopped without publishing because Subversion was unavailable and the saved account session had expired. After the maintainer signed in, Pressship whoami confirmed stphnwlkr and status found no matching submitted plugin. The public SVN URL returned HTTP 404. The intended route is a new review submission, not an SVN release. No Subversion installation is needed for that upload route.
- PHP syntax checks passed for the changed gallery and settings classes. The gallery/slideshow integration suite passed 14 checks, including canonical and legacy shortcode output.
- A scoped source scan found no private-key, GitHub-token or AWS-access-key patterns. This is not a substitute for a full security audit.
- GitHub search returned no accessible repository matching the proposed owner and name. No repository was created.

## Findings requiring review

| Location | Plugin Check warning |
| --- | --- |
| `includes/class-native-media-library.php:344` | `WordPress.DB.SlowDBQuery.slow_db_query_tax_query` |
| `includes/class-folder-import.php:95` | `WordPress.DB.SlowDBQuery.slow_db_query_meta_key` |
| `includes/class-folder-import.php:95` | `WordPress.DB.SlowDBQuery.slow_db_query_meta_value` |
| `includes/class-bricks-folder-provider.php:40` | `WordPress.DB.SlowDBQuery.slow_db_query_tax_query` |
| `includes/class-bricks-folder-provider.php:85` | `WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude` |

Two warnings concern the standard taxonomy queries used for folder filtering. Two concern the term-meta lookup used to reuse imported-folder mappings. One concerns excluding the moved folder from its destination sibling query. These are performance warnings, not evidence that the queries have been benchmarked on large libraries. They remain visible and need review before upload approval.

## Package

`builds/pressship/media-bridge-for-bricks.zip` contains 104 files, 2,928,452 bytes, approximately 2.79 MB. Its archive integrity and byte-for-byte match with current runtime source were verified after the dry run.

Included: plugin entry point, includes, assets, blocks, readme.txt, GPL license, WASM codecs, readable bundled JavaScript/source maps, third-party licenses and source/build references.

Excluded through .pressshipignore and Pressship defaults: tests, developer tools, internal reports, marketing files, repository metadata, editor configuration and prior build ZIPs. The complete source repository retains development files. Verification logs live under verification/pressship and are excluded from both the plugin distribution and Git by the existing ignore rules.

SHA-256: `3a25e779c5b6968114bf3457dec74fcd34be55fddf739aab571f8cbddcbcae5e`

## Next steps requiring approval

1. Create the public GitHub repository stphnwlkr/media-bridge-for-bricks, make the initial source commit, add its remote and push the main branch. Use Stephen Walker as the commit author. Review public source contents before approving.
2. Review the five performance warnings. Resolve them or explicitly approve proceeding with those findings.
3. Separately approve uploading this exact plugin package to WordPress.org for a new review submission. A submission does not mean the plugin has been approved or published.

Do not run an upload, Git mutation or SVN command until the maintainer approves that specific action. After any runtime source changes, rerun verification, packaging and the submission dry run.

## Test-site checker comparison after GitHub approval

The test site's active plugin is beta.15 and links directly to the full source checkout. Running Plugin Check against that development directory produced 84 errors and 480 warnings. Every error was in files excluded from the submission ZIP: prior build ZIPs, hidden development files or test fixtures. The checker also scanned a nested generated check tree, duplicating some warnings.

Running the same test-site Plugin Check command against an extraction of the exact submission ZIP produced zero errors and the same five runtime query-performance warnings. Both raw reports are retained under verification/pressship. The source checkout and installable distribution have different scan scopes; use the built package for WordPress.org submission checks. No runtime findings were suppressed.
