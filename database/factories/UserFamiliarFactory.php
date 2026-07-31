<?php

namespace Database\Factories;

use App\Enums\FamiliarStage;
use App\Models\User;
use App\Models\UserFamiliar;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserFamiliar> */
class UserFamiliarFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'species_key' => 'queue_wyrm', 'name' => 'Queue Wyrm', 'stage' => FamiliarStage::Summoned, 'summoned_at' => now()];
    }

    public function summoned(): static
    {
        return $this->state(['stage' => FamiliarStage::Summoned]);
    }

    public function attuned(): static
    {
        return $this->state(['stage' => FamiliarStage::Attuned, 'attuned_at' => now()]);
    }

    public function interviewReady(): static
    {
        return $this->state(['stage' => FamiliarStage::InterviewReady, 'attuned_at' => now(), 'interview_ready_at' => now()]);
    }
}
