<?php

use App\Support\Familiars\FamiliarAssetCatalog;
use InvalidArgumentException;

test('the queue wyrm manifest loads with its three idle stages', function () {
    $manifest = app(FamiliarAssetCatalog::class)->manifest();

    expect($manifest['key'])->toBe('queue-wyrm')
        ->and(array_keys($manifest['stages']))->toBe(['stage-1', 'stage-2', 'stage-3']);
});

test('the manifest includes both evolution transitions, overlays, and quest icons', function () {
    $manifest = app(FamiliarAssetCatalog::class)->manifest();

    expect(array_keys($manifest['evolutions']))->toBe(['stage-1-to-2', 'stage-2-to-3'])
        ->and(array_keys($manifest['overlays']))->toBe(['thinking', 'listening'])
        ->and(array_keys($manifest['quests']))->toBe(['scroll-1', 'scroll-2', 'observatory-lens', 'magical-book']);
});

test('every referenced asset exists and matches its declared frame dimensions', function () {
    $manifest = app(FamiliarAssetCatalog::class)->manifest();

    $assets = [
        ...array_map(fn (array $stage): array => $stage['idle'], $manifest['stages']),
        ...array_map(fn (array $evolution): array => $evolution['animation'], $manifest['evolutions']),
        ...array_map(fn (array $overlay): array => $overlay['animation'], $manifest['overlays']),
        ...array_map(fn (array $quest): array => $quest['icon'], $manifest['quests']),
    ];

    foreach ($assets as $asset) {
        $path = public_path(ltrim($asset['src'], '/'));
        $dimensions = getimagesize($path);

        expect($path)->toBeFile()
            ->and($dimensions)->not->toBeFalse()
            ->and($dimensions[0])->toBe($asset['frameWidth'] * $asset['columns'])
            ->and($dimensions[1])->toBe($asset['frameHeight'] * $asset['rows']);
    }
});

test('invalid manifests are rejected', function () {
    $path = tempnam(sys_get_temp_dir(), 'invalid-familiar-manifest-');
    file_put_contents($path, '{}');

    expect(fn () => app(FamiliarAssetCatalog::class)->manifest($path))
        ->toThrow(InvalidArgumentException::class);

    unlink($path);
});

test('the public manifest contains no local filesystem paths', function () {
    $manifest = app(FamiliarAssetCatalog::class)->publicManifest();
    $encodedManifest = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    expect($encodedManifest)->not->toContain(base_path())
        ->and($encodedManifest)->not->toContain('prototype-assets')
        ->and($encodedManifest)->toContain('/assets/familiars/queue-wyrm/');
});

test('the familiar asset validation command succeeds', function () {
    $this->artisan('familiar:validate-assets')
        ->expectsOutput('Queue Wyrm asset manifest is valid (version 1).')
        ->assertSuccessful();
});
