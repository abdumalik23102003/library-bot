<?php

namespace App\Telegram\Screens;

use App\Enums\Access;
use App\Enums\MaterialType;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Material;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\MaterialNotifier;
use App\Telegram\Support\MaterialSender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Bitta material: kartochka va amallar (callback: mat:{amal}:{materialId}[:{arg}]).
 * Holatlar params orqali: ask (matn/fayl kutish), pick (kategoriya/ruxsat tanlash), confirm (o'chirish), flash, error.
 */
class MatScreen extends Screen
{
    private const TEXT_FIELDS = ['title', 'description', 'url', 'body'];

    // ---- Ko'rinish --------------------------------------------------------------------------

    public function render(Nutgram $bot, array $params): Page
    {
        $actor = CurrentUser::get();
        $material = Material::with('category')->find((int) ($params['id'] ?? 0));

        if ($material === null || ! $this->can($actor, $material)) {
            return Page::make(__('mats.err.not_found'), back: true);
        }

        $id = $material->id;
        $card = $this->card($material);

        if (! empty($params['flash'])) {
            $card = __("mats.flash.{$params['flash']}") . "\n\n" . $card;
        }

        // Matn yoki fayl kutish
        if (($ask = $params['ask'] ?? null) !== null) {
            $lines = [$card, ''];

            if (! empty($params['error'])) {
                $lines[] = '⚠️ ' . e($params['error']);
            }

            $lines[] = __("mats.ask.{$ask}", ['max' => config('library.max_upload_mb')]);

            return Page::make(implode("\n", $lines), [[[__('bot.cancel'), "mat:cancel:{$id}"]]]);
        }

        // Kategoriya tanlash
        if (($params['pick'] ?? null) === 'category') {
            $rows = [];

            foreach ($this->categories($material, $actor)->get() as $category) {
                $mark = $category->id === $material->category_id ? '✅ ' : '';
                $rows[] = [[$mark . Str::limit($category->name, 40), "mat:setcat:{$id}:{$category->id}"]];
            }

            $rows[] = [[__('bot.cancel'), "mat:cancel:{$id}"]];

            return Page::make($card . "\n\n" . __('mats.pick.category'), $rows);
        }

        // Ruxsat turini tanlash
        if (($params['pick'] ?? null) === 'access') {
            $rows = [];

            foreach (Access::cases() as $access) {
                $mark = $access === $material->access ? '✅ ' : '';
                $rows[] = [[$mark . $access->label(), "mat:setacc:{$id}:{$access->value}"]];
            }

            $rows[] = [[__('bot.cancel'), "mat:cancel:{$id}"]];

            return Page::make($card . "\n\n" . __('mats.pick.access'), $rows);
        }

        // O'chirishni tasdiqlash
        if (! empty($params['confirm'])) {
            return Page::make($card . "\n\n" . __('mats.delete_confirm'), [[
                [__('mats.btn.delete_yes'), "mat:delyes:{$id}"],
                [__('mats.btn.delete_no'), "mat:cancel:{$id}"],
            ]]);
        }

        // Asosiy ko'rinish
        $replace = match ($material->type) {
            MaterialType::Link => [__('mats.btn.replace_url'), "mat:ask:{$id}:url"],
            MaterialType::Text => [__('mats.btn.replace_body'), "mat:ask:{$id}:body"],
            default => [__('mats.btn.replace_file'), "mat:ask:{$id}:file"],
        };

        return Page::make($card, [
            [[__('mats.btn.send'), "mat:send:{$id}"]],
            [
                [__('mats.btn.title'), "mat:ask:{$id}:title"],
                [__('mats.btn.description'), "mat:ask:{$id}:description"],
            ],
            [
                [__('mats.btn.category'), "mat:pick:{$id}:category"],
                [__('mats.btn.access'), "mat:pick:{$id}:access"],
            ],
            [$replace],
            [[__('mats.btn.delete'), "mat:del:{$id}"]],
        ], back: true);
    }

    /** Ekran chizilgach, kerak bo'lsa matn/fayl kutishni yoqadi. */
    public function mounted(Nutgram $bot, array $params): void
    {
        $ask = $params['ask'] ?? null;

        if ($ask === 'file') {
            Navigator::awaitInput($bot, 'material');
        } elseif (in_array($ask, self::TEXT_FIELDS, true)) {
            Navigator::awaitInput($bot, $ask);
        }
    }

    // ---- Tugmalar ---------------------------------------------------------------------------

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        $id = (int) ($args[0] ?? 0);
        $material = Material::find($id);

        if ($material === null || ! $this->can($actor, $material)) {
            $this->show($bot, $id);

            return;
        }

        switch ($action) {
            case 'send':
                try {
                    MaterialSender::send($bot, $material, $bot->chatId());
                } catch (Throwable $e) {
                    report($e);
                    $bot->sendMessage(__('mats.err.send'));
                }
                break;

            case 'ask':
                $field = (string) ($args[1] ?? '');
                $valid = in_array($field, self::TEXT_FIELDS, true) || $field === 'file';

                if ($valid && $this->fieldAllowed($material, $field)) {
                    $this->show($bot, $id, ['ask' => $field]);
                } else {
                    $this->show($bot, $id);
                }
                break;

            case 'pick':
                $what = (string) ($args[1] ?? '');
                $this->show($bot, $id, in_array($what, ['category', 'access'], true) ? ['pick' => $what] : []);
                break;

            case 'setcat':
                $category = $this->categories($material, $actor)->whereKey((int) ($args[1] ?? 0))->first();

                if ($category === null) {
                    $this->show($bot, $id);
                    break;
                }

                $material->category_id = $category->id;
                $this->changed($bot, $material, $actor, 'category');
                $this->show($bot, $id, ['flash' => 'saved']);
                break;

            case 'setacc':
                $access = Access::tryFrom((string) ($args[1] ?? ''));

                // Moderator faqat public materiallarni boshqaradi, ularni yopib qo'ymasligi kerak.
                if ($access === null || ($actor->isModerator() && $access !== Access::Public)) {
                    $this->show($bot, $id);
                    break;
                }

                $material->access = $access;
                $this->changed($bot, $material, $actor, 'access');
                $this->show($bot, $id, ['flash' => 'saved']);
                break;

            case 'del':
                $this->show($bot, $id, ['confirm' => true]);
                break;

            case 'delyes':
                $material->updated_by = $actor->id;
                $material->save();
                $material->delete();

                if ($material->owner_id !== $actor->id) {
                    AuditLog::record($actor, 'material.deleted', $material);
                    MaterialNotifier::changed($bot, $material, $actor, 'deleted');
                }

                // Ro'yxatga qaytamiz (u bazadan qayta o'qiladi)
                Navigator::back($bot);
                break;

            default: // cancel va boshqalar
                $this->show($bot, $id);
        }
    }

    // ---- Matn kiritish ----------------------------------------------------------------------

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $actor = CurrentUser::get();
        $id = (int) ($params['id'] ?? 0);
        $material = Material::find($id);

        if ($material === null || ! $this->can($actor, $material) || ! in_array($field, self::TEXT_FIELDS, true)) {
            $this->show($bot, $id);

            return;
        }

        [$ok, $value] = $this->validate($field, $text);

        if (! $ok) {
            $this->show($bot, $id, ['ask' => $field, 'error' => $value]);

            return;
        }

        $material->{$field} = $value;
        $this->changed($bot, $material, $actor, $field);
        $this->show($bot, $id, ['flash' => 'saved']);
    }

    // ---- Faylni almashtirish ----------------------------------------------------------------

    /**
     * @param  array{type: string, file_id: string, uid: ?string, name: ?string, mime: ?string, size: ?int, caption: ?string}  $file
     */
    public function file(Nutgram $bot, string $field, array $file, array $params): void
    {
        $actor = CurrentUser::get();
        $id = (int) ($params['id'] ?? 0);
        $material = Material::find($id);

        if ($material === null || ! $this->can($actor, $material) || ! $this->fieldAllowed($material, 'file')) {
            $this->show($bot, $id);

            return;
        }

        $type = MaterialType::tryFrom((string) ($file['type'] ?? ''));

        if ($type === null || ! $type->hasFile()) {
            $this->show($bot, $id, ['ask' => 'file', 'error' => __('mats.err.file_type')]);

            return;
        }

        $maxMb = (int) config('library.max_upload_mb');

        if ((int) ($file['size'] ?? 0) > $maxMb * 1024 * 1024) {
            $this->show($bot, $id, ['ask' => 'file', 'error' => __('mats.err.too_big', ['max' => $maxMb])]);

            return;
        }

        $material->type = $type;
        $material->telegram_file_id = $file['file_id'];
        $material->telegram_file_unique_id = $file['uid'] ?? null;
        $material->file_name = $file['name'] ?? null;
        $material->mime_type = $file['mime'] ?? null;
        $material->file_size = $file['size'] ?? null;

        $this->changed($bot, $material, $actor, 'file');
        $this->show($bot, $id, ['flash' => 'file']);
    }

    // ---- Ichki ------------------------------------------------------------------------------

    /** Material kim tomonidan boshqarilishi mumkin. */
    private function can(User $actor, Material $material): bool
    {
        return match (true) {
            $actor->isAdmin() => true,
            $actor->isModerator() => $material->access === Access::Public,
            $actor->isTeacher() => $material->owner_id === $actor->id,
            default => false,
        };
    }

    /** Tur bo'yicha qaysi maydonlar tahrirlanadi: havola uchun url, matn uchun body, fayllar uchun file. */
    private function fieldAllowed(Material $material, string $field): bool
    {
        return match ($field) {
            'url' => $material->type === MaterialType::Link,
            'body' => $material->type === MaterialType::Text,
            'file' => $material->type->hasFile(),
            default => true,
        };
    }

    /** Material egasi tanlay oladigan kategoriyalar (egasi bo'lmasa faqat default). */
    private function categories(Material $material, User $actor): Builder
    {
        $owner = $material->owner ?? ($actor->isTeacher() ? $actor : null);

        $query = $owner ? Category::forTeacher($owner) : Category::defaults();

        return $query->orderByDesc('is_default')->orderBy('name');
    }

    /** O'zgarishni saqlaydi, auditga yozadi va (boshqa kishi qilgan bo'lsa) egasiga xabar beradi. */
    private function changed(Nutgram $bot, Material $material, User $actor, string $what): void
    {
        $material->updated_by = $actor->id;
        $material->save();
        $material->load(['category', 'owner']);

        if ($material->owner_id !== $actor->id) {
            AuditLog::record($actor, 'material.updated', $material, ['what' => $what]);
            MaterialNotifier::changed($bot, $material, $actor, $what);
        }
    }

    /** Shu ekranni (id bo'yicha) ko'rsatadi: stekda "mat" tepada bo'lsa almashtiradi, aks holda o'tadi. */
    private function show(Nutgram $bot, int $id, array $extra = []): void
    {
        $params = ['id' => $id] + $extra;

        (Navigator::current($bot)['screen'] ?? null) === 'mat'
            ? Navigator::replace($bot, 'mat', $params)
            : Navigator::go($bot, 'mat', $params);
    }

    /** @return array{0: bool, 1: string} */
    private function validate(string $field, string $text): array
    {
        $text = trim($text);

        if ($field === 'title') {
            $title = trim((string) preg_replace('/\s+/u', ' ', $text));
            $length = mb_strlen($title);

            return $length >= 2 && $length <= 100 ? [true, $title] : [false, __('mats.err.title')];
        }

        if ($field === 'description') {
            $length = mb_strlen($text);

            return $length >= 3 && $length <= 600 ? [true, $text] : [false, __('mats.err.description')];
        }

        if ($field === 'url') {
            $valid = mb_strlen($text) <= 500
                && preg_match('~^https?://~i', $text)
                && filter_var($text, FILTER_VALIDATE_URL);

            return $valid ? [true, $text] : [false, __('mats.err.url')];
        }

        $length = mb_strlen($text);

        return $length >= 1 && $length <= 4000 ? [true, $text] : [false, __('mats.err.body')];
    }

    private function card(Material $material): string
    {
        $lines = [
            '📌 <b>' . e($material->title ?: __('mats.untitled')) . '</b>',
            $material->type->label(),
            __('mats.card.category', ['value' => e($material->category?->name ?? '—')]),
            __('mats.card.access', ['value' => $material->access->label()]),
        ];

        if ($size = $material->sizeForHumans()) {
            $lines[] = __('mats.card.size', ['value' => $size]);
        }

        $lines[] = __('mats.card.downloads', ['value' => $material->downloads_count]);
        $lines[] = __('mats.card.updated', ['value' => $material->updated_at?->format('d.m.Y H:i')]);

        if ($material->type === MaterialType::Link) {
            $lines[] = __('mats.card.url', ['value' => e(Str::limit((string) $material->url, 200))]);
        }

        if (filled($material->description)) {
            $lines[] = "\n" . __('mats.card.desc', ['value' => e(Str::limit($material->description, 500))]);
        }

        if ($material->type === MaterialType::Text && filled($material->body)) {
            $lines[] = "\n" . __('mats.card.body', ['value' => e(Str::limit($material->body, 300))]);
        }

        return implode("\n", $lines);
    }
}
