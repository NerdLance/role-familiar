# Queue Wyrm asset inventory

Inventory performed on 2026-07-31 from every file in `prototype-assets/queue-wyrm`. All source files are valid non-interlaced, 8-bit RGBA PNGs. They support transparency and contain no APNG animation chunks; the sheets below are runtime frame atlases, not intrinsically animated image files.

| Source filename | Size | Canvas | Runtime frame layout | Animated file | Empty frames | Duplicate status | Naming notes |
| --- | ---: | --- | --- | --- | --- | --- | --- |
| `queue-wyrm-idle-stage-1.png` | 92,219 B | 384×384 | 3×3 sheet; 9 × 128×128 | No | None | Unique SHA-256 | Clear |
| `queue-wyrm-idle-stage-2.png` | 80,273 B | 384×384 | 3×3 sheet; 9 × 128×128 | No | None | Unique SHA-256 | Clear |
| `queue-wyrm-idle-stage-3.png` | 102,505 B | 384×384 | 3×3 sheet; 9 × 128×128 | No | None | Unique SHA-256 | Clear |
| `queue-wyrm-stage-1-to-2.png` | 98,547 B | 384×384 | 3×3 sheet; 9 × 128×128 | No | None | Unique SHA-256 | Clear |
| `queue-wyrm-stage-2-to-3.png` | 108,958 B | 384×384 | 3×3 sheet; 9 × 128×128 | No | None | Unique SHA-256 | Clear |
| `overlay-thinking.png` | 6,057 B | 96×96 | 3×3 sheet; 9 × 32×32 | No | None | Unique SHA-256 | Clear |
| `overlay-listening.png` | 2,654 B | 96×96 | 3×3 sheet; 9 × 32×32 | No | None | Unique SHA-256 | Clear |
| `quest-scroll-1.png` | 1,820 B | 48×48 | Single image; 1 × 48×48 | No | N/A | Unique SHA-256 | Clear |
| `quest-scroll-2.png` | 2,227 B | 48×48 | Single image; 1 × 48×48 | No | N/A | Unique SHA-256 | Clear |
| `quest-lens.png` | 1,467 B | 48×48 | Single image; 1 × 48×48 | No | N/A | Unique SHA-256 | Maps to `observatory-lens` by visual/intent; the filename omits “observatory”. |
| `quest-book-1.png` | 1,470 B | 48×48 | Single image; 1 × 48×48 | No | N/A | Unique SHA-256 | Maps to `magical-book` by visual/intent; the filename omits “magical”. |

No exact duplicates were found. Coarse low-resolution similarity flagged the two scroll icons and evolution-adjacent familiar sheets, as expected for related art; it is not evidence of duplication.

## Consistency and confirmation

- No empty sprites were detected: every tile in all seven sheets has visible pixels.
- Canvas sizes are internally consistent for each class: familiar/evolution sheets are 384×384, overlays 96×96, icons 48×48. The class-to-class differences are intentional, not a defect.
- The source PNGs do not encode frames per second. `fps` is therefore `null` in the manifest and requires manual confirmation before playback is implemented.
- Overlay anchor and pixel offset cannot be derived from a standalone sheet. `position` is `null` pending visual layout direction.
- No lossy conversion was performed. Public runtime copies retain the exact PNG sprite sheets and icons.
