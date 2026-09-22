<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClassroomFactory extends Factory
{
    public function definition(): array
    {
        return ['year' => 2026, 'grade' => fake()->numberBetween(1, 6), 'section' => fake()->unique()->lexify('??'), 'capacity' => 30];
    }
}
