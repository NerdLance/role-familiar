<?php

namespace Database\Factories;

use App\Enums\CompetencyCategory;
use App\Enums\DataSource;
use App\Enums\Priority;
use App\Models\RoleCompetency;
use App\Models\TargetRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleCompetency>
 */
class RoleCompetencyFactory extends Factory
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
            'name' => fake()->words(2, true),
            'category' => CompetencyCategory::Technical,
            'priority' => Priority::Medium,
            'source_evidence' => null,
            'source' => DataSource::User,
            'user_confidence' => null,
            'sort_order' => 0,
            'user_confirmed_at' => null,
        ];
    }

    public function technical(): static
    {
        return $this->state(['category' => CompetencyCategory::Technical]);
    }

    public function behavioral(): static
    {
        return $this->state(['category' => CompetencyCategory::Behavioral]);
    }

    public function leadership(): static
    {
        return $this->state(['category' => CompetencyCategory::Leadership]);
    }

    public function domain(): static
    {
        return $this->state(['category' => CompetencyCategory::Domain]);
    }

    public function tooling(): static
    {
        return $this->state(['category' => CompetencyCategory::Tooling]);
    }

    public function communication(): static
    {
        return $this->state(['category' => CompetencyCategory::Communication]);
    }

    public function delivery(): static
    {
        return $this->state(['category' => CompetencyCategory::Delivery]);
    }
}
