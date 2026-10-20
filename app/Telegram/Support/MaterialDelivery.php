<?php

namespace App\Telegram\Support;

use App\Enums\Access;
use App\Models\Download;
use App\Models\Material;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Materialni foydalanuvchiga yuboradi (qidiruv va "Yuklab olinganlar" uchun).
 * Kirish huquqi har safar Material::visibleTo() bilan qayta tekshiriladi.
 */
final class MaterialDelivery
{
    /** @return string  sent | gone | locked | failed */
    public static function deliver(Nutgram $bot, User $user, int $materialId): string
    {
        $material = Material::query()->visibleTo($user)->find($materialId);

        if ($material === null) {
            // O'quvchi himoyalangan materialni qidiruvda ko'rdi, lekin ustozdan kirish olmagan.
            $locked = $user->isStudent()
                && Material::query()->whereKey($materialId)->where('access', Access::Protected->value)->exists();

            return $locked ? 'locked' : 'gone';
        }

        try {
            MaterialSender::send($bot, $material, $user->telegram_id);
        } catch (Throwable $e) {
            report($e);

            return 'failed';
        }

        Download::create(['user_id' => $user->id, 'material_id' => $material->id]);

        // toBase(): updated_at o'zgarmasin ("Yangilangan" vaqti buzilmasin).
        DB::table('materials')->where('id', $material->id)->increment('downloads_count');

        return 'sent';
    }
}
