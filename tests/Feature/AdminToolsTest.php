<?php

namespace Tests\Feature;

use App\Enums\Access;
use App\Enums\MaterialType;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Material;
use App\Models\User;
use App\Telegram\Support\DefaultCategories;
use App\Telegram\Support\Incoming;
use App\Telegram\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Standart kategoriyalar CRUD qoidalari va admin sozlamalari. */
class AdminToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_category_name_must_be_unique_among_defaults(): void
    {
        Category::create(['name' => 'Testlar', 'default_access' => Access::Public->value, 'is_default' => true]);

        [$ok] = DefaultCategories::name('  testlar ');
        $this->assertFalse($ok, 'Katta-kichik harfga e\'tibor bermay, takror nom bo\'lmaydi');

        [$ok, $value] = DefaultCategories::name('Yangi   nom');
        $this->assertTrue($ok);
        $this->assertSame('Yangi nom', $value);

        [$ok] = DefaultCategories::name('A');
        $this->assertFalse($ok);
    }

    public function test_create_update_and_delete_default_category(): void
    {
        $mod = User::factory()->moderator()->create();

        $category = DefaultCategories::create($mod, 'Imtihonlar', null, Access::Protected);
        $this->assertTrue($category->is_default);
        $this->assertNull($category->owner_id);
        $this->assertSame(Access::Protected, $category->default_access);

        DefaultCategories::update($mod, $category, 'name', 'Imtihon savollari');
        $this->assertSame('Imtihon savollari', $category->refresh()->name);

        $material = Material::factory()->create(['category_id' => $category->id]);

        $this->assertTrue(DefaultCategories::delete($mod, $category));
        $this->assertNull($material->refresh()->category_id, 'Material o\'chmaydi, kategoriyasiz qoladi');
        $this->assertNull(Category::query()->find($category->id));

        $this->assertSame(
            ['category.created', 'category.updated', 'category.deleted'],
            AuditLog::query()->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_system_category_cannot_be_deleted(): void
    {
        $mod = User::factory()->moderator()->create();
        $system = Category::create([
            'name' => 'Hujjatlar',
            'default_access' => Access::Public->value,
            'is_default' => true,
            'system_type' => MaterialType::Document->value,
        ]);

        $this->assertFalse(DefaultCategories::canDelete($system));
        $this->assertFalse(DefaultCategories::delete($mod, $system));
        $this->assertNotNull(Category::query()->find($system->id));
    }

    public function test_max_upload_setting(): void
    {
        $admin = User::factory()->admin()->create();
        $default = Incoming::maxMb();

        $this->assertFalse(Settings::parseMaxMb('0')[0]);
        $this->assertFalse(Settings::parseMaxMb('2001')[0]);
        $this->assertFalse(Settings::parseMaxMb('12.5')[0]);
        $this->assertFalse(Settings::parseMaxMb('abc')[0]);
        $this->assertSame([true, 500], Settings::parseMaxMb(' 500 '));

        Settings::setMaxMb($admin, 500);
        $this->assertSame(500, Incoming::maxMb());
        $this->assertTrue(Settings::maxMbIsCustom());

        Settings::setMaxMb($admin, null);
        $this->assertSame($default, Incoming::maxMb());
        $this->assertFalse(Settings::maxMbIsCustom());
        $this->assertSame(2, AuditLog::query()->where('action', 'setting.updated')->count());
    }
}
