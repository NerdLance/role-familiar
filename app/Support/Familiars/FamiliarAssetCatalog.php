<?php

namespace App\Support\Familiars;

use InvalidArgumentException;
use JsonException;

class FamiliarAssetCatalog
{
    /**
     * Load and validate the Queue Wyrm asset manifest.
     *
     * @return array<string, mixed>
     */
    public function manifest(?string $path = null): array
    {
        $path ??= resource_path('data/familiars/queue-wyrm.json');

        if (! is_file($path)) {
            throw new InvalidArgumentException("Familiar asset manifest is missing: {$path}");
        }

        try {
            $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("Familiar asset manifest contains invalid JSON: {$path}", previous: $exception);
        }

        if (! is_array($manifest)) {
            throw new InvalidArgumentException('Familiar asset manifest must decode to an object.');
        }

        $this->validate($manifest);

        return $manifest;
    }

    /**
     * Validate the manifest and every public asset it references.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function validate(array $manifest): void
    {
        foreach (['key', 'name', 'version', 'stages', 'evolutions', 'overlays', 'quests'] as $field) {
            if (! array_key_exists($field, $manifest)) {
                throw new InvalidArgumentException("Familiar asset manifest is missing the [{$field}] field.");
            }
        }

        if (! is_string($manifest['key']) || ! is_string($manifest['name']) || ! is_int($manifest['version'])) {
            throw new InvalidArgumentException('Familiar asset manifest has invalid root field types.');
        }

        foreach (['stage-1', 'stage-2', 'stage-3'] as $stage) {
            $definition = $manifest['stages'][$stage] ?? null;

            if (! is_array($definition) || ! is_int($definition['number'] ?? null) || ! is_array($definition['idle'] ?? null)) {
                throw new InvalidArgumentException("Familiar asset manifest has an invalid [{$stage}] definition.");
            }

            $this->validateAsset($definition['idle'], "stages.{$stage}.idle");
        }

        foreach (['stage-1-to-2', 'stage-2-to-3'] as $transition) {
            $definition = $manifest['evolutions'][$transition] ?? null;

            if (! is_array($definition) || ! is_string($definition['sourceStage'] ?? null) || ! is_string($definition['destinationStage'] ?? null) || ! is_array($definition['animation'] ?? null)) {
                throw new InvalidArgumentException("Familiar asset manifest has an invalid [{$transition}] evolution.");
            }

            $this->validateAsset($definition['animation'], "evolutions.{$transition}.animation");
        }

        foreach (['thinking', 'listening'] as $overlay) {
            $definition = $manifest['overlays'][$overlay] ?? null;

            if (! is_array($definition) || ! is_string($definition['key'] ?? null) || ! array_key_exists('position', $definition) || ! is_array($definition['animation'] ?? null)) {
                throw new InvalidArgumentException("Familiar asset manifest has an invalid [{$overlay}] overlay.");
            }

            $this->validateAsset($definition['animation'], "overlays.{$overlay}.animation");
        }

        foreach (['scroll-1', 'scroll-2', 'observatory-lens', 'magical-book'] as $quest) {
            $definition = $manifest['quests'][$quest] ?? null;

            if (! is_array($definition) || ! is_string($definition['key'] ?? null) || ! is_array($definition['icon'] ?? null)) {
                throw new InvalidArgumentException("Familiar asset manifest has an invalid [{$quest}] quest icon.");
            }

            $this->validateAsset($definition['icon'], "quests.{$quest}.icon");
        }
    }

    /**
     * Return a controlled representation containing public URLs only.
     *
     * @return array<string, mixed>
     */
    public function publicManifest(): array
    {
        return $this->manifest();
    }

    /**
     * @param  array<string, mixed>  $asset
     */
    private function validateAsset(array $asset, string $label): void
    {
        foreach (['kind', 'src', 'frameWidth', 'frameHeight', 'frameCount', 'columns', 'rows'] as $field) {
            if (! array_key_exists($field, $asset)) {
                throw new InvalidArgumentException("Asset [{$label}] is missing the [{$field}] field.");
            }
        }

        if (! in_array($asset['kind'], ['sprite_sheet', 'image'], true) || ! is_string($asset['src'])) {
            throw new InvalidArgumentException("Asset [{$label}] has an invalid kind or source.");
        }

        foreach (['frameWidth', 'frameHeight', 'frameCount', 'columns', 'rows'] as $field) {
            if (! is_int($asset[$field]) || $asset[$field] < 1) {
                throw new InvalidArgumentException("Asset [{$label}] has an invalid [{$field}] value.");
            }
        }

        if ($asset['frameCount'] !== $asset['columns'] * $asset['rows']) {
            throw new InvalidArgumentException("Asset [{$label}] has inconsistent frame metadata.");
        }

        if ($asset['kind'] === 'sprite_sheet' && (! array_key_exists('fps', $asset) || ! array_key_exists('loop', $asset))) {
            throw new InvalidArgumentException("Sprite sheet [{$label}] must declare fps and loop metadata.");
        }

        if (! str_starts_with($asset['src'], '/assets/familiars/queue-wyrm/') || str_contains($asset['src'], '..')) {
            throw new InvalidArgumentException("Asset [{$label}] must use a Queue Wyrm public URL.");
        }

        $path = public_path(ltrim($asset['src'], '/'));

        if (! is_file($path)) {
            throw new InvalidArgumentException("Asset [{$label}] does not exist at its public URL.");
        }

        $dimensions = getimagesize($path);

        if ($dimensions === false || $dimensions[0] !== $asset['frameWidth'] * $asset['columns'] || $dimensions[1] !== $asset['frameHeight'] * $asset['rows']) {
            throw new InvalidArgumentException("Asset [{$label}] dimensions do not match its manifest metadata.");
        }
    }
}
