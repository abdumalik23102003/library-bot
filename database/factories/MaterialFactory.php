<?php

namespace Database\Factories;

use App\Enums\Access;
use App\Enums\MaterialType;
use App\Models\Material;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Material> */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->teacher(),
            'type' => MaterialType::Document,
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(8),
            'telegram_file_id' => 'FAKE_' . Str::random(40), // haqiqiy yuborib bo'lmaydi, faqat testlar uchun
            'telegram_file_unique_id' => Str::random(16),
            'file_name' => fake()->word() . '.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 5_000_000),
            'access' => Access::Public,
        ];
    }
}
