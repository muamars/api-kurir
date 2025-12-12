<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShipmentPhoto>
 */
class ShipmentPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => \App\Models\Shipment::factory(),
            'type' => $this->faker->randomElement(['admin_upload', 'pickup', 'delivery']),
            'photo_url' => 'shipments/'.$this->faker->numberBetween(1, 100).'/originals/'.$this->faker->uuid().'.jpg',
            'photo_thumbnail' => 'shipments/'.$this->faker->numberBetween(1, 100).'/thumbnails/'.$this->faker->uuid().'.jpg',
            'uploaded_by' => \App\Models\User::factory(),
            'notes' => $this->faker->optional()->sentence(),
            'uploaded_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function adminUpload()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'admin_upload',
            ];
        });
    }

    public function pickup()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'pickup',
            ];
        });
    }

    public function delivery()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'delivery',
            ];
        });
    }
}
