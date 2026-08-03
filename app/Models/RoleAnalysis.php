<?php

namespace App\Models;

use App\Enums\RoleAnalysisStatus;
use Database\Factories\RoleAnalysisFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property string $public_id
 * @property int $target_role_id
 * @property RoleAnalysisStatus $status
 * @property string $prompt_version
 * @property string $provider
 * @property string $model
 * @property string $source_hash
 * @property array<string, mixed>|null $structured_result
 * @property array<int, array<string, mixed>>|null $assumptions
 * @property array<string, int|string>|null $usage_metadata
 * @property string|null $failure_category
 * @property string|null $failure_summary
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property-read RoleAnalysisReview|null $review
 */
#[Fillable([
    'status',
    'prompt_version',
    'provider',
    'model',
    'source_hash',
    'structured_result',
    'assumptions',
    'usage_metadata',
    'failure_category',
    'failure_summary',
    'started_at',
    'completed_at',
])]
class RoleAnalysis extends Model
{
    /** @use HasFactory<RoleAnalysisFactory> */
    use HasFactory, HasUlids;

    protected $attributes = [
        'status' => RoleAnalysisStatus::Pending->value,
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

    public static function sourceHash(string $source): string
    {
        $normalizedSource = Str::of($source)
            ->replace(["\r\n", "\r"], "\n")
            ->trim()
            ->toString();

        return hash('sha256', $normalizedSource);
    }

    /** @return BelongsTo<TargetRole, $this> */
    public function targetRole(): BelongsTo
    {
        return $this->belongsTo(TargetRole::class);
    }

    /** @return HasOne<RoleAnalysisReview, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(RoleAnalysisReview::class);
    }

    public function isPending(): bool
    {
        return $this->status === RoleAnalysisStatus::Pending;
    }

    public function isCompleted(): bool
    {
        return $this->status === RoleAnalysisStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === RoleAnalysisStatus::Failed;
    }

    public function isTerminal(): bool
    {
        return $this->isCompleted() || $this->isFailed();
    }

    /** @param Builder<RoleAnalysis> $query */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', RoleAnalysisStatus::Pending);
    }

    /** @param Builder<RoleAnalysis> $query */
    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->where('status', RoleAnalysisStatus::Completed);
    }

    /** @param Builder<RoleAnalysis> $query */
    #[Scope]
    protected function failed(Builder $query): void
    {
        $query->where('status', RoleAnalysisStatus::Failed);
    }

    protected static function booted(): void
    {
        static::saving(function (RoleAnalysis $analysis): void {
            if ($analysis->exists && $analysis->isDirty('public_id')) {
                throw new LogicException('A role analysis public ID is immutable.');
            }

            $originalStatus = $analysis->getRawOriginal('status');

            if ($analysis->exists
                && in_array($originalStatus, [RoleAnalysisStatus::Completed->value, RoleAnalysisStatus::Failed->value], true)
                && $analysis->isDirty()) {
                throw new LogicException('A terminal role analysis is immutable.');
            }

            if ($analysis->status === RoleAnalysisStatus::Pending
                && ($analysis->started_at !== null || $analysis->completed_at !== null)) {
                throw new DomainException('A pending analysis cannot have lifecycle timestamps.');
            }

            if ($analysis->status === RoleAnalysisStatus::Processing
                && ($analysis->started_at === null || $analysis->completed_at !== null)) {
                throw new DomainException('A processing analysis requires started_at and cannot be completed.');
            }

            if ($analysis->status === RoleAnalysisStatus::Completed
                && ($analysis->started_at === null || $analysis->completed_at === null || $analysis->structured_result === null)) {
                throw new DomainException('A completed analysis requires lifecycle timestamps and structured output.');
            }

            if ($analysis->status === RoleAnalysisStatus::Failed && $analysis->completed_at === null) {
                throw new DomainException('A failed analysis requires completed_at.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RoleAnalysisStatus::class,
            'structured_result' => 'array',
            'assumptions' => 'array',
            'usage_metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
