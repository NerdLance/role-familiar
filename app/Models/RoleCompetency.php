<?php

namespace App\Models;

use App\Enums\CompetencyCategory;
use App\Enums\DataSource;
use App\Enums\Priority;
use Database\Factories\RoleCompetencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $target_role_id
 * @property string $name
 * @property CompetencyCategory $category
 * @property Priority $priority
 * @property string|null $source_evidence
 * @property DataSource $source
 * @property int|null $user_confidence
 * @property int $sort_order
 * @property Carbon|null $user_confirmed_at
 */
#[Fillable(['name', 'category', 'priority', 'source_evidence', 'source', 'user_confidence', 'sort_order', 'user_confirmed_at'])]
class RoleCompetency extends Model
{
    /** @use HasFactory<RoleCompetencyFactory> */
    use HasFactory;

    /** @return BelongsTo<TargetRole, $this> */
    public function targetRole(): BelongsTo
    {
        return $this->belongsTo(TargetRole::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => CompetencyCategory::class,
            'priority' => Priority::class,
            'source' => DataSource::class,
            'user_confidence' => 'integer',
            'sort_order' => 'integer',
            'user_confirmed_at' => 'datetime',
        ];
    }
}
