<?php

namespace Database\Seeders;

use App\Enums\Access;
use App\Enums\MaterialType;
use App\Models\Category;
use Illuminate\Database\Seeder;

class DefaultCategorySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [MaterialType::Document, 'Hujjatlar', 'PDF, Word, Excel va boshqa hujjatlar'],
            [MaterialType::Video, 'Videolar', 'Video darslar va yozuvlar'],
            [MaterialType::Photo, 'Rasmlar', 'Rasm va suratlar'],
            [MaterialType::Audio, 'Audio', 'Audio va ovozli yozuvlar'],
            [MaterialType::Link, 'Havolalar', 'Foydali havolalar'],
            [MaterialType::Text, 'Matnlar', 'Matnli material va eslatmalar'],
        ];

        foreach ($defaults as [$type, $name, $description]) {
            Category::updateOrCreate(
                ['is_default' => true, 'system_type' => $type->value],
                ['name' => $name, 'description' => $description, 'default_access' => Access::Public->value, 'owner_id' => null],
            );
        }
    }
}
