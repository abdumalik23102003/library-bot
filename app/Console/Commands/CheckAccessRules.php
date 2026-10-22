<?php

namespace App\Console\Commands;

use App\Enums\Access;
use App\Enums\RequestStatus;
use App\Models\Material;
use App\Models\ProtectedAccess;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckAccessRules extends Command
{
    protected $signature = 'bot:check-access';

    protected $description = 'Material kirish qoidalarini (Material::visibleTo) tekshiradi. Bazada iz qoldirmaydi.';

    public function handle(): int
    {
        if (! class_exists(\Faker\Factory::class)) {
            $this->error('Faker (require-dev) o\'rnatilmagan: bu buyruq faqat dev muhitda ishlaydi.');

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $teacher = User::factory()->teacher()->create();
            $otherTeacher = User::factory()->teacher()->create();
            $paid = User::factory()->create();        // protected-kirishi bor o'quvchi
            $free = User::factory()->create();        // oddiy o'quvchi
            $moderator = User::factory()->moderator()->create();
            $admin = User::factory()->admin()->create();

            ProtectedAccess::create([
                'teacher_id' => $teacher->id,
                'student_id' => $paid->id,
                'status' => RequestStatus::Approved,
            ]);

            $public = Material::factory()->create(['owner_id' => $teacher->id, 'access' => Access::Public]);
            $protected = Material::factory()->create(['owner_id' => $teacher->id, 'access' => Access::Protected]);
            $private = Material::factory()->create(['owner_id' => $teacher->id, 'access' => Access::Private]);

            $ids = [$public->id, $protected->id, $private->id];

            $cases = [
                'ustoz (ega)' => [$teacher, [$public->id, $protected->id, $private->id]],
                'boshqa ustoz' => [$otherTeacher, [$public->id]],
                "o'quvchi (protected kirishi bor)" => [$paid, [$public->id, $protected->id]],
                "o'quvchi (kirishi yo'q)" => [$free, [$public->id]],
                'moderator' => [$moderator, [$public->id]],
                'admin' => [$admin, [$public->id, $protected->id, $private->id]],
            ];

            $failed = false;

            foreach ($cases as $label => [$viewer, $expected]) {
                $actual = Material::visibleTo($viewer)->whereIn('id', $ids)->pluck('id')->sort()->values()->all();
                sort($expected);
                $ok = $actual === $expected;
                $failed = $failed || ! $ok;

                $this->line(sprintf('%s %-36s kutilgan=[%s] natija=[%s]', $ok ? 'OK  ' : 'FAIL', $label, implode(',', $expected), implode(',', $actual)));
            }

            $found = Material::search($public->title)->whereIn('id', $ids)->exists();
            $failed = $failed || ! $found;
            $this->line(sprintf('%s qidiruv (sarlavha bo\'yicha)', $found ? 'OK  ' : 'FAIL'));

            return $failed ? self::FAILURE : self::SUCCESS;
        } finally {
            DB::rollBack();
        }
    }
}
