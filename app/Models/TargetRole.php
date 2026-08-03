<?php

namespace App\Models;

use App\Enums\Seniority;
use App\Enums\TargetRoleSourceType;
use App\Enums\TargetRoleStatus;
use Database\Factories\TargetRoleFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property TargetRoleStatus $status
 * @property TargetRoleSourceType $source_type
 * @property string|null $title
 * @property string|null $company_name
 * @property Seniority|null $seniority
 * @property string|null $role_summary
 * @property string|null $original_job_listing
 * @property Carbon|null $interview_at
 * @property string|null $biggest_concern
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $deleted_at
 * @property-read UserFamiliar|null $familiar
 */
#[Fillable([
    'user_id',
    'status',
    'source_type',
    'title',
    'company_name',
    'seniority',
    'role_summary',
    'original_job_listing',
    'interview_at',
    'biggest_concern',
    'confirmed_at',
    'archived_at',
])]
#[Hidden(['original_job_listing'])]
class TargetRole extends Model
{
    /** @use HasFactory<TargetRoleFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $attributes = [
        'status' => TargetRoleStatus::Draft->value,
        'source_type' => TargetRoleSourceType::Manual->value,
    ];

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $user = request()->user();

        if (! $user instanceof User) {
            return null;
        }

        return $this->newQuery()
            ->ownedBy($user)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    public function resolveSoftDeletableRouteBinding($value, $field = null): ?Model
    {
        $user = request()->user();

        if (! $user instanceof User) {
            return null;
        }

        return $this->newQuery()
            ->withTrashed()
            ->ownedBy($user)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<RoleResponsibility, $this> */
    public function responsibilities(): HasMany
    {
        return $this->hasMany(RoleResponsibility::class)->orderBy('sort_order');
    }

    /** @return HasMany<RoleCompetency, $this> */
    public function competencies(): HasMany
    {
        return $this->hasMany(RoleCompetency::class)->orderBy('sort_order');
    }

    /** @return HasMany<RoleAnalysis, $this> */
    public function analyses(): HasMany
    {
        return $this->hasMany(RoleAnalysis::class);
    }

    /** @return HasOne<UserFamiliar, $this> */
    public function familiar(): HasOne
    {
        return $this->hasOne(UserFamiliar::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === TargetRoleStatus::Confirmed
            && $this->confirmed_at !== null;
    }

    public function isArchived(): bool
    {
        return $this->status === TargetRoleStatus::Archived
            && $this->archived_at !== null;
    }

    public function isActive(): bool
    {
        return $this->isConfirmed() && $this->archived_at === null;
    }

    /** @param Builder<TargetRole> $query */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->whereBelongsTo($user);
    }

    /** @param Builder<TargetRole> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', TargetRoleStatus::Confirmed)
            ->whereNotNull('confirmed_at')
            ->whereNull('archived_at');
    }

    /** @param Builder<TargetRole> $query */
    #[Scope]
    protected function confirmed(Builder $query): void
    {
        $query->where('status', TargetRoleStatus::Confirmed)
            ->whereNotNull('confirmed_at');
    }

    /** @param Builder<TargetRole> $query */
    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->where('status', TargetRoleStatus::Archived)
            ->whereNotNull('archived_at');
    }

    protected static function booted(): void
    {
        static::saving(function (TargetRole $targetRole): void {
            if ($targetRole->exists && $targetRole->isDirty('public_id')) {
                throw new LogicException('A target role public ID is immutable.');
            }

            if ($targetRole->status === TargetRoleStatus::Confirmed) {
                if ($targetRole->confirmed_at === null || $targetRole->archived_at !== null) {
                    throw new DomainException('A confirmed target role requires confirmed_at and cannot be archived.');
                }

                return;
            }

            if ($targetRole->status === TargetRoleStatus::Archived) {
                if ($targetRole->confirmed_at === null || $targetRole->archived_at === null) {
                    throw new DomainException('An archived target role requires confirmation and archival timestamps.');
                }

                return;
            }

            if ($targetRole->confirmed_at !== null || $targetRole->archived_at !== null) {
                throw new DomainException('An unconfirmed target role cannot carry confirmation or archival timestamps.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TargetRoleStatus::class,
            'source_type' => TargetRoleSourceType::class,
            'seniority' => Seniority::class,
            'original_job_listing' => 'encrypted',
            'interview_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
