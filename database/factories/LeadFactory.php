<?php

namespace Database\Factories;

use App\Modules\Leads\Enums\LeadCaptureStep;
use App\Modules\Leads\Enums\LeadStatus;
use App\Modules\Leads\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tg_user_id' => fake()->unique()->numberBetween(100_000, 999_999_999),
            'tg_chat_id' => fake()->numberBetween(100_000, 999_999_999),
            'tg_username' => fake()->optional()->userName(),
            'name' => fake()->name(),
            'phone' => '+996'.fake()->numerify('#########'),
            'phone_raw' => fake()->numerify('996#########'),
            'company' => fake()->company(),
            'source' => 'telegram',
            'status' => LeadStatus::New,
            'capture_step' => LeadCaptureStep::Completed,
            'meta' => [],
        ];
    }
}
