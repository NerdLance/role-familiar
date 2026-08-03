<?php

namespace Database\Factories;

use App\Enums\Seniority;
use App\Enums\TargetRoleSourceType;
use App\Enums\TargetRoleStatus;
use App\Models\TargetRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TargetRole>
 */
class TargetRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => TargetRoleStatus::Draft,
            'source_type' => TargetRoleSourceType::Manual,
            'title' => fake()->jobTitle(),
            'company_name' => fake()->company(),
            'seniority' => Seniority::Mid,
            'role_summary' => fake()->paragraph(),
            'original_job_listing' => null,
            'interview_at' => null,
            'biggest_concern' => null,
            'confirmed_at' => null,
            'archived_at' => null,
        ];
    }

    public function manualDraft(): static
    {
        return $this->state([
            'status' => TargetRoleStatus::Draft,
            'source_type' => TargetRoleSourceType::Manual,
            'original_job_listing' => null,
            'confirmed_at' => null,
            'archived_at' => null,
        ]);
    }

    public function jobListingDraft(): static
    {
        return $this->state([
            'status' => TargetRoleStatus::Draft,
            'source_type' => TargetRoleSourceType::JobListing,
            'title' => null,
            'company_name' => null,
            'seniority' => null,
            'role_summary' => null,
            'original_job_listing' => fake()->paragraphs(3, true),
            'confirmed_at' => null,
            'archived_at' => null,
        ]);
    }

    public function analysisPending(): static
    {
        return $this->jobListingDraft()->state([
            'status' => TargetRoleStatus::AnalysisPending,
        ]);
    }

    public function reviewRequired(): static
    {
        return $this->state([
            'status' => TargetRoleStatus::ReviewRequired,
            'confirmed_at' => null,
            'archived_at' => null,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state([
            'status' => TargetRoleStatus::Confirmed,
            'confirmed_at' => now(),
            'archived_at' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state([
            'status' => TargetRoleStatus::Archived,
            'confirmed_at' => now()->subDay(),
            'archived_at' => now(),
        ]);
    }
}
