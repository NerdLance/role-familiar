<?php

namespace App\Models;

use App\Enums\DataSource;
use App\Enums\Priority;
use Database\Factories\RoleResponsibilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $target_role_id
 * @property string $content
 * @property Priority $priority
 * @property DataSource $source
 * @property string|null $source_evidence
 * @property int $sort_order
 * @property Carbon|null $user_confirmed_at
 */
#[Fillable(['content', 'priority', 'source', 'source_evidence', 'sort_order', 'user_confirmed_at'])]
class RoleResponsibility extends Model
{
    /** @use HasFactory<RoleResponsibilityFactory> */
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
            'priority' => Priority::class,
            'source' => DataSource::class,
            'sort_order' => 'integer',
            'user_confirmed_at' => 'datetime',
        ];
    }
}
