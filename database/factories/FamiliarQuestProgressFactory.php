<?php

namespace Database\Factories;

use App\Enums\PrototypeQuestKey;
use App\Models\FamiliarQuestProgress;
use App\Models\UserFamiliar;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FamiliarQuestProgress> */
class FamiliarQuestProgressFactory extends Factory
{
    public function definition(): array
    {
        return ['user_familiar_id' => UserFamiliar::factory(), 'quest_key' => fake()->randomElement(PrototypeQuestKey::cases()), 'completed_at' => now()];
    }
}
