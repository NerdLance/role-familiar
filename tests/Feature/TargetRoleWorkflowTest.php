<?php

use App\Enums\TargetRoleSourceType;
use App\Enums\TargetRoleStatus;
use App\Models\RoleCompetency;
use App\Models\RoleResponsibility;
use App\Models\TargetRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function validTargetRolePayload(array $overrides = []): array
{
    return [
        'title' => 'Senior Platform Engineer',
        'company_name' => 'Acme',
        'seniority' => 'senior',
        'role_summary' => 'Own platform reliability and developer experience.',
        'interview_at' => '2026-09-15T14:30',
        'biggest_concern' => 'System design depth',
        'responsibilities' => [
            ['content' => 'Lead platform architecture', 'priority' => 'critical'],
            ['content' => 'Mentor engineers', 'priority' => 'high'],
        ],
        'competencies' => [
            [
                'name' => 'Distributed systems',
                'category' => 'technical',
                'priority' => 'critical',
                'source_evidence' => 'Explicitly entered by the user',
                'user_confidence' => 4,
            ],
            [
                'name' => 'Technical leadership',
                'category' => 'leadership',
                'priority' => 'high',
                'source_evidence' => null,
                'user_confidence' => 3,
            ],
        ],
        ...$overrides,
    ];
}

test('target role pages require an authenticated and verified user', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['target-roles.index', 'target-roles.create']);

test('target role routes are protected by auth and verified middleware', function () {
    $middleware = app('router')->getRoutes()->getByName('target-roles.index')->gatherMiddleware();

    expect($middleware)->toContain('auth')->toContain('verified');
});

test('a user can create a trimmed manual draft with ordered children', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('target-roles.store'), validTargetRolePayload([
        'title' => '  Senior Platform Engineer  ',
        'company_name' => '  Acme  ',
    ]));

    $targetRole = $user->targetRoles()->sole();

    $response->assertRedirect(route('target-roles.show', $targetRole));
    expect($targetRole->status)->toBe(TargetRoleStatus::Draft)
        ->and($targetRole->source_type)->toBe(TargetRoleSourceType::Manual)
        ->and($targetRole->title)->toBe('Senior Platform Engineer')
        ->and($targetRole->company_name)->toBe('Acme')
        ->and($targetRole->original_job_listing)->toBeNull()
        ->and($targetRole->responsibilities()->pluck('sort_order')->all())->toBe([0, 1])
        ->and($targetRole->competencies()->pluck('sort_order')->all())->toBe([0, 1]);
});

test('a draft can be updated reordered and cleared rows are removed', function () {
    $user = User::factory()->create();
    $targetRole = TargetRole::factory()->for($user)->manualDraft()->create();
    $firstResponsibility = RoleResponsibility::factory()->for($targetRole)->create(['content' => 'First', 'sort_order' => 0]);
    $secondResponsibility = RoleResponsibility::factory()->for($targetRole)->create(['content' => 'Second', 'sort_order' => 1]);
    $firstCompetency = RoleCompetency::factory()->for($targetRole)->create(['name' => 'First skill', 'sort_order' => 0]);
    $secondCompetency = RoleCompetency::factory()->for($targetRole)->create(['name' => 'Second skill', 'sort_order' => 1]);

    $payload = validTargetRolePayload([
        'title' => 'Updated role',
        'responsibilities' => [
            ['id' => $secondResponsibility->id, 'content' => 'Second updated', 'priority' => 'high'],
            ['id' => $firstResponsibility->id, 'content' => '   ', 'priority' => 'low'],
            ['content' => 'New responsibility', 'priority' => 'medium'],
        ],
        'competencies' => [
            ['id' => $secondCompetency->id, 'name' => 'Second skill', 'category' => 'leadership', 'priority' => 'high', 'source_evidence' => null, 'user_confidence' => 5],
            ['id' => $firstCompetency->id, 'name' => '', 'category' => 'technical', 'priority' => 'low', 'source_evidence' => null, 'user_confidence' => null],
            ['name' => 'New skill', 'category' => 'delivery', 'priority' => 'medium', 'source_evidence' => null, 'user_confidence' => null],
        ],
    ]);

    $this->actingAs($user)->put(route('target-roles.update', $targetRole), $payload)
        ->assertRedirect(route('target-roles.show', $targetRole));

    expect($targetRole->refresh()->title)->toBe('Updated role')
        ->and($targetRole->responsibilities()->pluck('content')->all())->toBe(['Second updated', 'New responsibility'])
        ->and($targetRole->responsibilities()->pluck('sort_order')->all())->toBe([0, 1])
        ->and($targetRole->competencies()->pluck('name')->all())->toBe(['Second skill', 'New skill'])
        ->and($targetRole->competencies()->pluck('sort_order')->all())->toBe([0, 1]);
});

test('child IDs from another role are rejected without changing either role', function () {
    $user = User::factory()->create();
    $targetRole = TargetRole::factory()->for($user)->create();
    $otherRole = TargetRole::factory()->for($user)->create();
    $foreignResponsibility = RoleResponsibility::factory()->for($otherRole)->create();
    $foreignCompetency = RoleCompetency::factory()->for($otherRole)->create();

    $this->actingAs($user)->put(route('target-roles.update', $targetRole), validTargetRolePayload([
        'responsibilities' => [['id' => $foreignResponsibility->id, 'content' => 'Injected', 'priority' => 'high']],
        'competencies' => [['id' => $foreignCompetency->id, 'name' => 'Injected', 'category' => 'technical', 'priority' => 'high', 'source_evidence' => null, 'user_confidence' => 3]],
    ]))->assertSessionHasErrors(['responsibilities.0.id', 'competencies.0.id']);

    $this->assertDatabaseMissing('role_responsibilities', ['target_role_id' => $targetRole->id, 'content' => 'Injected']);
    $this->assertDatabaseHas('role_responsibilities', ['id' => $foreignResponsibility->id, 'target_role_id' => $otherRole->id]);
});

test('confirmation requires a complete profile and timestamps every child', function () {
    $user = User::factory()->create();
    $targetRole = TargetRole::factory()->for($user)->manualDraft()->create(['role_summary' => null]);

    $this->actingAs($user)->post(route('target-roles.confirm', $targetRole))
        ->assertSessionHasErrors(['role_summary', 'responsibilities', 'competencies']);

    $targetRole->update(['role_summary' => 'A user-confirmed role summary.']);

    $responsibility = RoleResponsibility::factory()->for($targetRole)->create(['sort_order' => 0]);
    $competency = RoleCompetency::factory()->for($targetRole)->create(['sort_order' => 0]);

    $this->actingAs($user)->post(route('target-roles.confirm', $targetRole))
        ->assertRedirect(route('target-roles.show', $targetRole));

    expect($targetRole->refresh()->isActive())->toBeTrue()
        ->and($targetRole->confirmed_at)->not->toBeNull()
        ->and($responsibility->refresh()->user_confirmed_at)->not->toBeNull()
        ->and($competency->refresh()->user_confirmed_at)->not->toBeNull();
});

test('confirming a second role archives but does not delete the previous active role', function () {
    $user = User::factory()->create();
    $first = TargetRole::factory()->for($user)->confirmed()->create();
    $second = TargetRole::factory()->for($user)->manualDraft()->create();
    RoleResponsibility::factory()->for($second)->create(['sort_order' => 0]);
    RoleCompetency::factory()->for($second)->create(['sort_order' => 0]);

    $this->actingAs($user)->post(route('target-roles.confirm', $second))->assertRedirect();

    expect($first->refresh()->isArchived())->toBeTrue()
        ->and($first->trashed())->toBeFalse()
        ->and($second->refresh()->isActive())->toBeTrue()
        ->and($user->targetRoles()->active()->count())->toBe(1);
});

test('a confirmed role remains confirmed and new children become confirmed when edited', function () {
    $user = User::factory()->create();
    $targetRole = TargetRole::factory()->for($user)->confirmed()->create();

    $this->actingAs($user)->put(route('target-roles.update', $targetRole), validTargetRolePayload())
        ->assertRedirect();

    expect($targetRole->refresh()->isActive())->toBeTrue()
        ->and($targetRole->responsibilities()->whereNull('user_confirmed_at')->exists())->toBeFalse()
        ->and($targetRole->competencies()->whereNull('user_confirmed_at')->exists())->toBeFalse();
});

test('an active role can be archived and restoring it makes it active without losing history', function () {
    $user = User::factory()->create();
    $targetRole = TargetRole::factory()->for($user)->confirmed()->create();

    $this->actingAs($user)->post(route('target-roles.archive', $targetRole))->assertRedirect();
    expect($targetRole->refresh()->isArchived())->toBeTrue();

    $otherActive = TargetRole::factory()->for($user)->confirmed()->create();
    $this->actingAs($user)->post(route('target-roles.restore', $targetRole))->assertRedirect();

    expect($targetRole->refresh()->isActive())->toBeTrue()
        ->and($otherActive->refresh()->isArchived())->toBeTrue();
});

test('non-active roles are soft deleted and can be restored through the owner route', function () {
    $user = User::factory()->create();
    $targetRole = TargetRole::factory()->for($user)->manualDraft()->create();

    $this->actingAs($user)->delete(route('target-roles.destroy', $targetRole), ['confirmation' => true])
        ->assertRedirect(route('target-roles.index'));

    expect($targetRole->refresh()->trashed())->toBeTrue();
    $this->assertSoftDeleted($targetRole);
    $this->actingAs($user)->post(route('target-roles.restore', $targetRole))->assertRedirect();
    expect($targetRole->refresh()->trashed())->toBeFalse();
});

test('delete requires explicit confirmation and active roles must be archived first', function () {
    $user = User::factory()->create();
    $draft = TargetRole::factory()->for($user)->manualDraft()->create();
    $active = TargetRole::factory()->for($user)->confirmed()->create();

    $this->actingAs($user)->delete(route('target-roles.destroy', $draft))->assertSessionHasErrors('confirmation');
    $this->actingAs($user)->delete(route('target-roles.destroy', $active), ['confirmation' => true])->assertForbidden();
});

test('another user cannot resolve view mutate or restore a private role', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $targetRole = TargetRole::factory()->for($owner)->archived()->create();

    $this->actingAs($intruder)->get(route('target-roles.show', $targetRole))->assertNotFound();
    $this->actingAs($intruder)->get(route('target-roles.edit', $targetRole))->assertNotFound();
    $this->actingAs($intruder)->put(route('target-roles.update', $targetRole), validTargetRolePayload())->assertNotFound();
    $this->actingAs($intruder)->post(route('target-roles.confirm', $targetRole))->assertNotFound();
    $this->actingAs($intruder)->post(route('target-roles.restore', $targetRole))->assertNotFound();
    $this->actingAs($intruder)->delete(route('target-roles.destroy', $targetRole), ['confirmation' => true])->assertNotFound();
});

test('index and profile pages expose only owner safe Inertia props', function () {
    $user = User::factory()->create();
    $targetRole = TargetRole::factory()->for($user)->jobListingDraft()->create([
        'title' => 'Private role',
        'original_job_listing' => 'Private pasted listing',
    ]);
    RoleResponsibility::factory()->for($targetRole)->create(['sort_order' => 0]);
    RoleCompetency::factory()->for($targetRole)->create(['sort_order' => 0]);

    $this->actingAs($user)->get(route('target-roles.index'))->assertInertia(fn (Assert $page) => $page
        ->component('target-roles/index')
        ->has('targetRoles', 1)
        ->where('targetRoles.0.public_id', $targetRole->public_id)
        ->missing('targetRoles.0.user_id')
        ->missing('targetRoles.0.original_job_listing'));

    $this->actingAs($user)->get(route('target-roles.show', $targetRole))->assertInertia(fn (Assert $page) => $page
        ->component('target-roles/show')
        ->where('targetRole.public_id', $targetRole->public_id)
        ->has('targetRole.responsibilities', 1)
        ->has('targetRole.competencies', 1)
        ->missing('targetRole.user_id')
        ->missing('targetRole.original_job_listing')
        ->missing('targetRole.analyses'));
});

test('the familiar receives only minimal active target role context', function () {
    $user = User::factory()->create();
    $targetRole = TargetRole::factory()->for($user)->confirmed()->create(['title' => 'Active role']);

    $this->actingAs($user)->get(route('familiar'))->assertInertia(fn (Assert $page) => $page
        ->component('familiar')
        ->where('targetRole.public_id', $targetRole->public_id)
        ->where('targetRole.title', 'Active role')
        ->where('targetRole.status', 'confirmed')
        ->missing('targetRole.user_id')
        ->missing('targetRole.original_job_listing'));
});

test('the composer markup includes mobile safe layouts and keyboard operable row controls', function () {
    $composer = file_get_contents(resource_path('js/features/target-roles/components/target-role-composer.tsx'));

    expect($composer)->not->toBeFalse()
        ->and($composer)->toContain('min-w-0')
        ->and($composer)->toContain('sm:grid-cols-2')
        ->and($composer)->toContain('type="button"')
        ->and($composer)->toContain('aria-label={`Move ${label}')
        ->and($composer)->not->toContain('draggable');
});
