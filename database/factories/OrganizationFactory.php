<?php

namespace Database\Factories;

use App\Domain\Shared\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // $url = 'https://picsum.photos/400/400';
        // $contents = file_get_contents($url);
        // $name = Str::random(10);
        // $location = 'logos/'.$name.'.jpg';
        // Storage::disk('public')->put("logos/{$name}.jpg", $contents);

        return [
            'name' => $name = $this->faker->company,
            'slug' => Str::slug($name),
            // 'logo' => $location,
        ];
    }
}
