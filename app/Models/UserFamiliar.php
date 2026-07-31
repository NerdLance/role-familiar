<?php

namespace App\Models;

use App\Enums\FamiliarStage;
use Database\Factories\UserFamiliarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $public_id
 * @property string $species_key
 * @property string $name
 * @property FamiliarStage $stage
 * @property Carbon $summoned_at
 * @property Carbon|null $attuned_at
 * @property Carbon|null $interview_ready_at
 */
#[Fillable(['user_id', 'species_key', 'name', 'stage', 'summoned_at', 'attuned_at', 'interview_ready_at'])]
class UserFamiliar extends Model
{
    /** @use HasFactory<UserFamiliarFactory> */
    use HasFactory, HasUlids;

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<FamiliarQuestProgress, $this> */
    public function questProgress(): HasMany
    {
        return $this->hasMany(FamiliarQuestProgress::class);
    }

    /** @return HasMany<FamiliarProgressionEvent, $this> */
    public function progressionEvents(): HasMany
    {
        return $this->hasMany(FamiliarProgressionEvent::class);
    }

    protected function casts(): array
    {
        return [
            'stage' => FamiliarStage::class,
            'summoned_at' => 'datetime',
            'attuned_at' => 'datetime',
            'interview_ready_at' => 'datetime',
        ];
    }
}
