<?php

namespace Database\Factories;

use App\Enums\RoleAnalysisStatus;
use App\Models\RoleAnalysis;
use App\Models\TargetRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleAnalysis>
 */
class RoleAnalysisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $source = fake()->paragraph();

        return [
            'target_role_id' => TargetRole::factory()->jobListingDraft(),
            'status' => RoleAnalysisStatus::Pending,
            'prompt_version' => 'job-listing-analysis-v1',
            'provider' => 'openai',
            'model' => 'test-model',
            'source_hash' => RoleAnalysis::sourceHash($source),
            'structured_result' => null,
            'assumptions' => null,
            'usage_metadata' => null,
            'failure_category' => null,
            'failure_summary' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => RoleAnalysisStatus::Pending,
            'structured_result' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => RoleAnalysisStatus::Completed,
            'structured_result' => [
                'normalized_title' => 'Software Engineer',
                'overall_extraction_confidence' => 90,
            ],
            'assumptions' => [],
            'usage_metadata' => ['input_tokens' => 100, 'output_tokens' => 50],
            'failure_category' => null,
            'failure_summary' => null,
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => RoleAnalysisStatus::Failed,
            'structured_result' => null,
            'assumptions' => null,
            'usage_metadata' => null,
            'failure_category' => 'provider_unavailable',
            'failure_summary' => 'The analysis provider was temporarily unavailable.',
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
        ]);
    }
}
