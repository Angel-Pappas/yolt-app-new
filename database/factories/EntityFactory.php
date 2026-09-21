<?php

namespace Database\Factories;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entity>
 */
class EntityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => null,
            'vat_number' => fake()->optional()->numerify('EL#########'),
        ];
    }

    public function customer(): static
    {
        return $this->state(['type' => 'customer']);
    }

    public function supplier(): static
    {
        return $this->state(['type' => 'supplier']);
    }

    public function contractor(): static
    {
        return $this->state(['type' => 'contractor']);
    }

    public function employee(): static
    {
        return $this->state(['type' => 'employee']);
    }

    public function stateType(): static
    {
        return $this->state(['type' => 'state']);
    }

    public function shareholder(): static
    {
        return $this->state(['type' => 'shareholder']);
    }
}
