<?php

namespace Database\Factories;

use App\Models\RoleAnalysis;
use App\Models\RoleAnalysisReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleAnalysisReview>
 */
class RoleAnalysisReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_analysis_id' => RoleAnalysis::factory()->completed(),
            'reviewed_payload' => [
                'title' => 'Software Engineer',
                'company_name' => null,
                'seniority' => 'mid',
                'role_summary' => 'Build reliable software.',
                'interview_at' => null,
                'biggest_concern' => null,
                'responsibilities' => [],
                'competencies' => [],
            ],
            'ambiguity_responses' => [],
            'assumption_decisions' => [],
            'applied_at' => now(),
        ];
    }
}
