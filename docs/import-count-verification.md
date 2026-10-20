# Folder counts and import verification, beta.12

The reported screenshot showed parent cumulative totals that included subfolder relationships while child direct counts were zero. The Bricks plugin had left WordPress to choose its publication-based post term counter. That counter excludes unattached inherited attachments and attachments whose parent is unpublished. HappyFiles registers the generic relationship counter instead.

A controlled import reproduced the mismatch. All 105 unattached source images reached their child folder, but its stored count was zero. Draft-parent and private-parent child folders each had three imported relationships and a zero count. The published-parent child correctly displayed three. Source and destination image IDs were identical for every folder.

Media Bridge now registers WordPress's generic relationship count callback. A versioned, per-site recount runs once for an administrator after update. It refreshes existing counters without moving media or changing relationships. Both the WordPress view and Bricks provider then report the correct counts. A regression test covers unattached, draft, private and published parents, cumulative deduplication, migration permission, preserved relationships and deletion updates.

The browser imported 594 test attachments across 45 source folders with immediate children using separate AJAX requests. It added 480 assignments and recognized 114 that were already present. A database audit compared every source ID set with its destination set and verified all direct counts. A separate browser pause/reload/resume completed with all 594 assignments already present and zero permission skips. There is no built-in 300-item cap. The specific cause of the user's earlier pause was not reproduced.

A separate HappyFiles import explicitly covers root, first-level child, second-level child and third-level child. It creates 34 folders and 332 relationships, with 1, 7, 113 and 211 images directly assigned at the four depths. The first request saves at folder 30, before the deeper descendants are created. A fresh request creates the remaining folders; the browser then resumes the saved assignment phase. Verification passed for exact source and destination image IDs, every parent mapping, stored direct counts, and the WordPress and Bricks provider counts at every level. All 332 relationships imported, with zero permission skips. The disposable nested fixtures were removed and the previous import state restored.

Progress now reports checked assignments rather than only newly copied ones. Completion shows newly added and already-present counts separately. An interrupted request keeps its saved progress and displays a resume instruction.

All disposable fixture attachments, posts and folders were removed after verification. User source data and actual plugin activations were preserved.

The beta.12 distribution passed PHP syntax checks for the changed PHP sources and regression tests. WordPress Plugin Check against the extracted ZIP reported zero errors and five existing query-performance warnings. These checks do not establish the absence of all security vulnerabilities.
