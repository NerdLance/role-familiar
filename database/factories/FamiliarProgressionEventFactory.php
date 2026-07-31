<?php

namespace Database\Factories;

use App\Enums\ProgressionEventType;
use App\Models\FamiliarProgressionEvent;
use App\Models\UserFamiliar;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FamiliarProgressionEvent> */
class FamiliarProgressionEventFactory extends Factory
{
    public function definition(): array
    {
        return ['user_familiar_id' => UserFamiliar::factory(), 'event_type' => ProgressionEventType::FamiliarSummoned, 'metadata' => ['species_key' => 'queue_wyrm'], 'occurred_at' => now()];
    }
}
