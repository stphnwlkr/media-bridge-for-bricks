# PNG optimization verification, beta.13

The integration used canvas.toBlob for PNG, where the quality argument does not provide PNG palette compression. Cimo Pro uses browser-image-compression for PNG. The adapter now bundles that MIT library locally and passes its result through Cimo Pro's smart optimizer and authenticated upload-size endpoint. Formats and pixel dimensions remain unchanged; a dimension check rejects automatic canvas-size reductions. Workers are disabled, so the compressor does not fetch CDN scripts. Animated PNG is left unchanged to preserve its frames.

## Tests on the reported images

Both images were optimized using the enhanced view's Optimize button on localhost:8889. Each includes the full image and nine generated sizes.

| Image | Sizes optimized | Total original bytes | Total optimized bytes | Saved |
| --- | ---: | ---: | ---: | ---: |
| image.png | 10 | 13,637,454 | 10,142,059 | 25.63% |
| tester26.png | 10 | 5,756,481 | 5,099,504 | 11.41% |

The full files changed from 3,193,700 to 2,459,877 bytes and from 1,399,290 to 1,181,297 bytes, respectively. All 20 files retain their original PNG MIME type and dimensions. Cimo records exist for every size, and each Cimo backup's SHA-256 matches its original file. Optimize is unavailable after records are saved. No source files or Cimo backups were deleted.

The JavaScript regression harness checks PNG compressor routing, same-format authenticated uploads, the existing JPEG route, equal/larger outputs, changed dimensions, changed format, APNG preservation, encoder errors, and REST-save errors. It uses controlled browser/API doubles; the real-browser tests above verify the installed Cimo integration.

Run the regression harness with `node tests/cimo-optimize.test.cjs`. PHP post-upload plan tests also cover permissions, generated sizes and existing optimization records.

The user's confirmation on October 20, 2026 verifies beta.12's folder-count correction on a live site. This PNG fix is separate. The live optimization site was not accessed; the PNG failure was reproduced and repaired using the two supplied test-site images.
