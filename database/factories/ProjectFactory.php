<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return ['platform' => 'mock', 'external_project_id' => $this->faker->unique()->uuid(), 'title' => $this->faker->sentence(4), 'description' => $this->faker->paragraph(), 'budget' => null, 'currency' => 'IRR', 'deadline' => null, 'required_skills' => ['Laravel'], 'discovered_at' => now(), 'availability_status' => 'available', 'decision_status' => 'new', 'is_demo' => true];
    }
}
