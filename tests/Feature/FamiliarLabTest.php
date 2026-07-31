<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot access the familiar lab', function () {
    $this->get(route('familiar-lab'))
        ->assertRedirect(route('login'));
});

test('authenticated users receive the validated familiar lab props', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('familiar-lab'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('familiar-lab')
            ->where('manifest.key', 'queue-wyrm')
            ->has('manifest.stages', 3)
            ->has('manifest.evolutions', 2)
            ->has('manifest.overlays', 2)
            ->has('quests', 4)
            ->where('stageLabels.stage-1', 'Summoned')
        );
});

test('familiar lab props contain public URLs without local filesystem paths', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('familiar-lab'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('manifest.stages.stage-1.idle.src', '/assets/familiars/queue-wyrm/stage-1/idle/sprite-sheet.png')
        ->missing('manifest.stages.stage-1.idle.path')
    );

    expect($response->getContent())->not->toContain(base_path())
        ->and($response->getContent())->not->toContain('prototype-assets');
});
