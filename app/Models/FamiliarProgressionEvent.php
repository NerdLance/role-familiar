<?php

namespace App\Models;

use App\Enums\ProgressionEventType;
use Database\Factories\FamiliarProgressionEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_familiar_id
 * @property ProgressionEventType $event_type
 * @property array<string, string> $metadata
 * @property Carbon $occurred_at
 */
#[Fillable(['event_type', 'metadata', 'occurred_at'])]
class FamiliarProgressionEvent extends Model
{
    /** @use HasFactory<FamiliarProgressionEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<UserFamiliar, $this> */
    public function familiar(): BelongsTo
    {
        return $this->belongsTo(UserFamiliar::class, 'user_familiar_id');
    }

    protected function casts(): array
    {
        return [
            'event_type' => ProgressionEventType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
