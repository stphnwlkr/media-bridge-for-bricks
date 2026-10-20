# Publication status, October 20, 2026

## GitHub

Public source repository: https://github.com/stphnwlkr/media-bridge-for-bricks

The initial main-branch source push was confirmed at e7ddd995f5498011335c3a757375cf52ae55a0b1. Author: Stephen Walker. The release-version correction and this record are included in the subsequent main-branch push. No Git release tag or SVN release was created.

## WordPress.org

Submitted with Pressship after the maintainer explicitly approved upload. Account and contributor: stphnwlkr. Public author: Stephen Walker.

- Plugin: Media Bridge for Bricks
- Assigned slug and text domain: media-bridge-for-bricks
- Version: 1.0.0
- Plugin ID: 391574
- Current status, independently confirmed by Pressship status: Awaiting Review. The plugin has not yet been reviewed or approved.
- Submission date: October 20, 2026
- Automated submission scan: Pass
- Package: media-bridge-for-bricks.zip, 104 files, approximately 2.79 MB
- SHA-256: a89706d206a61584f56a7c0e585f4cf772bc5ad8f9eb878db62e0c452d3d749b

The first beta.15 upload was rejected because the submission form accepts only numbers and periods in the Version header. Pressship incorrectly returned a successful exit status while displaying that rejection. The rejected attempt was not treated as a submission. Plugin metadata was normalized to 1.0.0, matching the header, constant, readme stable tag and local build filename. Runtime features remain those tested in beta.15. Full verification and a submission dry run were repeated before retrying. The accepted submission and its exact version were confirmed through a separate status request.

## Test-site Plugin Check

The maintainer's exported report contained 49 errors, all in developer test scripts excluded from the release. It also included five runtime performance warnings twice because the checker traversed a nested build directory. A full source-directory CLI scan additionally flagged prior ZIPs and hidden development files.

The test site was changed from a source-checkout symlink to a normal installation of the clean 1.0.0 runtime package. WordPress settings, attachments, folder records and conversion backups were retained. The installed clean package reports zero errors and five query-performance warnings, matching the separately checked submission ZIP. No runtime findings were suppressed. The existing five warnings remain documented in publishing-readiness.md.

Rebuild with tools/build.py and reinstall the ZIP after source changes; the test site no longer loads source edits immediately. Development test scripts stay in the Git repository and are excluded from the distributed plugin.

The preparation report is a historical snapshot. Its earlier statements that no remote changes had yet occurred describe the checkpoint before the maintainer granted publication approval.
