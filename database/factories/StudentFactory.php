<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'dni' => fake()->unique()->numerify('########'), 'guardian' => fake()->name(), 'phone' => '987654321', 'status' => 'Activo', 'color' => '#3b82f6'];
    }
}
