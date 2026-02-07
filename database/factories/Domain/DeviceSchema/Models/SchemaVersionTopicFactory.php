<?php

declare(strict_types=1);

namespace Database\Factories\Domain\DeviceSchema\Models;

use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\DeviceSchema\Models\SchemaVersionTopic>
 */
class SchemaVersionTopicFactory extends Factory
{
    protected $model = SchemaVersionTopic::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = $this->faker->unique()->slug(2);

        return [
            'device_schema_version_id' => DeviceSchemaVersion::factory(),
            'key' => $key,
            'label' => $this->faker->words(2, true),
            'direction' => $this->faker->randomElement(TopicDirection::cases()),
            'suffix' => $key,
            'description' => $this->faker->optional()->sentence,
            'qos' => $this->faker->randomElement([0, 1, 2]),
            'retain' => $this->faker->boolean(20),
            'sequence' => $this->faker->numberBetween(0, 10),
        ];
    }

    public function publish(): static
    {
        return $this->state(fn () => [
            'direction' => TopicDirection::Publish,
        ]);
    }

    public function subscribe(): static
    {
        return $this->state(fn () => [
            'direction' => TopicDirection::Subscribe,
        ]);
    }
}
