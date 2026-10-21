<?php

namespace Database\Factories;

use App\Enums\Severity;
use App\Models\Advice;
use App\Models\AdviceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Advice>
 */
class AdviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'advice_category_id' => AdviceCategory::factory(),
            'title' => fake()->sentence(6),
            'summary' => fake()->sentence(),
            'content' => fake()->paragraphs(3, true),
            'risk_level' => Severity::Medium,
            'is_approved' => true,
            'moderation_status' => 'approved',
        ];
    }
}
