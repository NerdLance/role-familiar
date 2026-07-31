# Queue Wyrm asset contract

`resources/data/familiars/queue-wyrm.json` is the sole asset metadata authority. PHP loads and validates it through `App\Support\Familiars\FamiliarAssetCatalog`; future React code must consume the backend-provided manifest rather than duplicating URLs or frame data.

## Published paths

- Idle sheets: `public/assets/familiars/queue-wyrm/stage-{1,2,3}/idle/sprite-sheet.png`
- Evolution sheets: `public/assets/familiars/queue-wyrm/evolutions/stage-{1-to-2,2-to-3}/sprite-sheet.png`
- Overlay sheets: `public/assets/familiars/queue-wyrm/overlays/{thinking,listening}/sprite-sheet.png`
- Quest icons: `public/assets/familiars/queue-wyrm/quests/{scroll-1,scroll-2,observatory-lens,magical-book}/icon.png`

All manifest `src` values are public URLs beginning with `/assets/familiars/queue-wyrm/`; local source paths are never exposed.

## Schema

The manifest has a stable `key`, display `name`, and integer `version`. It includes three numbered stages, named evolution transitions with source/destination stage keys, two overlays, and four quests.

`sprite_sheet` assets declare `src`, `frameWidth`, `frameHeight`, `frameCount`, `columns`, `rows`, `fps`, `loop`, and a `reducedMotionFallback` source/frame. The current sheets use 3×3 grids. Idle and overlays loop; evolution transitions are one-shot. Playback speed remains deliberately unset (`null`) until it is confirmed.

`image` assets declare the same dimensions and a one-frame 1×1 layout. The TypeScript contract also declares a discriminated `frame_sequence` type for future ordered, separately supplied frames. Overlay position supports an anchor plus x/y offsets, while the current assets intentionally leave it `null`.

## Validation

Run `php artisan familiar:validate-assets` to validate required sections, public URL safety, source file existence, frame-count/grid consistency, and expected PNG canvas dimensions. It returns nonzero on any error.
