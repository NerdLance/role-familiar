<?php

namespace App\Models;

use App\Enums\PrototypeQuestKey;
use Database\Factories\FamiliarQuestProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_familiar_id
 * @property PrototypeQuestKey $quest_key
 * @property Carbon|null $completed_at
 */
#[Fillable(['quest_key', 'completed_at'])]
class FamiliarQuestProgress extends Model
{
    /** @use HasFactory<FamiliarQuestProgressFactory> */
    use HasFactory;

    /** @return BelongsTo<UserFamiliar, $this> */
    public function familiar(): BelongsTo
    {
        return $this->belongsTo(UserFamiliar::class, 'user_familiar_id');
    }

    protected function casts(): array
    {
        return [
            'quest_key' => PrototypeQuestKey::class,
            'completed_at' => 'datetime',
        ];
    }
}
