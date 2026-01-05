<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => 'pending',
            'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
            'courier_notes' => $this->faker->optional()->sentence(),
            'scheduled_delivery_datetime' => $this->faker->optional()->dateTimeBetween('now', '+7 days'),
            'created_by' => User::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function assigned()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'assigned',
                'assigned_driver_id' => User::factory(),
            ];
        });
    }

    public function inProgress()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'in_progress',
            ];
        });
    }

    public function completed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'completed',
                'completed_at' => now(),
            ];
        });
    }
}
