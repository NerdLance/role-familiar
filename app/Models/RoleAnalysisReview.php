<?php

namespace App\Models;

use Database\Factories\RoleAnalysisReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $role_analysis_id
 * @property array<string, mixed> $reviewed_payload
 * @property array<int, array<string, mixed>> $ambiguity_responses
 * @property array<int, array<string, mixed>> $assumption_decisions
 * @property Carbon $applied_at
 */
#[Fillable(['reviewed_payload', 'ambiguity_responses', 'assumption_decisions', 'applied_at'])]
class RoleAnalysisReview extends Model
{
    /** @use HasFactory<RoleAnalysisReviewFactory> */
    use HasFactory;

    /** @return BelongsTo<RoleAnalysis, $this> */
    public function roleAnalysis(): BelongsTo
    {
        return $this->belongsTo(RoleAnalysis::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reviewed_payload' => 'array',
            'ambiguity_responses' => 'array',
            'assumption_decisions' => 'array',
            'applied_at' => 'datetime',
        ];
    }
}
