<?php

namespace Database\Factories;

use App\Enums\DataSource;
use App\Enums\Priority;
use App\Models\RoleResponsibility;
use App\Models\TargetRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleResponsibility>
 */
class RoleResponsibilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'target_role_id' => TargetRole::factory(),
            'content' => fake()->sentence(),
            'priority' => Priority::Medium,
            'source' => DataSource::User,
            'source_evidence' => null,
            'sort_order' => 0,
            'user_confirmed_at' => null,
        ];
    }

    public function lowPriority(): static
    {
        return $this->state(['priority' => Priority::Low]);
    }

    public function mediumPriority(): static
    {
        return $this->state(['priority' => Priority::Medium]);
    }

    public function highPriority(): static
    {
        return $this->state(['priority' => Priority::High]);
    }

    public function criticalPriority(): static
    {
        return $this->state(['priority' => Priority::Critical]);
    }
}
