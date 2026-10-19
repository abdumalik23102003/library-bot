<?php

namespace App\Telegram\Screens;

use App\Enums\Access;
use App\Enums\MaterialType;
use App\Models\Category;
use App\Models\Material;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CategoryFields;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Incoming;
use App\Telegram\Support\MaterialCard;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Material yuklash. params: step (file|menu|title|desc|newcat|cat|access|done), d (qoralama), error, id.
 * Qoralama d: type, file_id, uid, file_name, mime, size, title, desc, url, body, cat (id|null), prev (kartochka xabari).
 */
class UpScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $user = CurrentUser::get();
        $step = $params['step'] ?? 'file';
        $d = $params['d'] ?? [];
        $error = ! empty($params['error']) ? "\n\n⚠️ " . e($params['error']) : '';

        $inputRows = [[[__('mat.btn.skip'), 'up:skip']], [[__('bot.cancel'), 'up:cancel']]];

        return match ($step) {
            'menu' => $this->menuPage($d, $error),
            'title' => Page::make(__('mat.up.ask_title') . $error, $inputRows),
            'desc' => Page::make(__('mat.up.ask_desc') . $error, $inputRows),
            'newcat' => Page::make(__('mat.up.ask_newcat') . $error, [[[__('bot.cancel'), 'up:cancel']]]),
            'cat' => $this->catPage($user),
            'access' => $this->accessPage($user, $d),
            'done' => Page::make(
                __('mat.up.done', ['id' => $params['id'] ?? '?']),
                [[[__('mat.btn.again'), 'up:again']]],
                back: true,
            ),
            default => Page::make(__('mat.up.intro', ['max' => Incoming::maxMb()]) . $error, back: true),
        };
    }

    /** Ekran ochilganda mos matn kiritishni kutishni yoqadi. */
    public function mounted(Nutgram $bot, array $params): void
    {
        $field = match ($params['step'] ?? 'file') {
            'file' => 'material',
            'title' => 'title',
            'desc' => 'desc',
            'newcat' => 'newcat',
            default => null,
        };

        if ($field !== null) {
            Navigator::awaitInput($bot, $field);
        }
    }

    // ---- Tugmalar ---------------------------------------------------------------------------

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $user = CurrentUser::get();
        $top = Navigator::current($bot);

        if (! $user->isTeacher() || ($top['screen'] ?? '') !== 'up') {
            Navigator::refresh($bot);

            return;
        }

        $params = $top['params'];
        $step = $params['step'] ?? 'file';
        $d = $params['d'] ?? [];

        if ($action === 'again' && $step === 'done') {
            Navigator::replace($bot, 'up', ['step' => 'file']);

            return;
        }

        if (empty($d['type'])) {
            Navigator::refresh($bot);

            return;
        }

        $to = fn (string $next) => Navigator::replace($bot, 'up', ['step' => $next, 'd' => $d]);

        match (true) {
            $action === 'title' && $step === 'menu' => $to('title'),
            $action === 'desc' && $step === 'menu' => $to('desc'),
            $action === 'skip' && in_array($step, ['title', 'desc'], true) => $to('menu'),
            $action === 'cancel' && in_array($step, ['title', 'desc'], true) => $to('menu'),
            $action === 'cancel' && $step === 'newcat' => $to('cat'),
            $action === 'next' && $step === 'menu' => $to('cat'),
            $action === 'tomenu' && $step === 'cat' => $to('menu'),
            $action === 'tocat' && $step === 'access' => $to('cat'),
            $action === 'newcat' && $step === 'cat' => $to('newcat'),
            $action === 'save' && $step === 'menu' => $this->save($bot, $user, $d, null),
            $action === 'cat' && $step === 'cat' => $this->pickCategory($bot, $user, $d, (string) ($args[0] ?? '')),
            $action === 'access' && $step === 'access' => $this->pickAccess($bot, $user, $d, (string) ($args[0] ?? '')),
            $action === 'discard' => $this->discard($bot, $d),
            default => Navigator::refresh($bot),
        };
    }

    // ---- Kiritish: fayl va matn ----------------------------------------------------------------

    /** @param array{type: string, file_id: string, uid: ?string, name: ?string, mime: ?string, size: ?int, caption: ?string} $file */
    public function file(Nutgram $bot, string $field, array $file, array $params): void
    {
        if (($params['step'] ?? 'file') !== 'file') {
            return;
        }

        $maxMb = Incoming::maxMb();

        if (! empty($file['size']) && $file['size'] > $maxMb * 1024 * 1024) {
            Navigator::replace($bot, 'up', ['step' => 'file', 'error' => __('mat.err.too_big', ['max' => $maxMb])]);

            return;
        }

        $this->begin($bot, [
            'type' => $file['type'],
            'file_id' => $file['file_id'],
            'uid' => $file['uid'],
            'file_name' => $file['name'],
            'mime' => $file['mime'],
            'size' => $file['size'],
            'title' => null,
            'desc' => $file['caption'],
            'url' => null,
            'body' => null,
            'cat' => null,
            'prev' => null,
        ]);
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $user = CurrentUser::get();
        $step = $params['step'] ?? 'file';
        $d = $params['d'] ?? [];
        $text = trim($text);

        if ($field === 'material' && $step === 'file') {
            $this->fromText($bot, $text);

            return;
        }

        if (empty($d['type']) || $field !== $step) {
            Navigator::refresh($bot);

            return;
        }

        $again = fn (string $error) => Navigator::replace($bot, 'up', ['step' => $step, 'd' => $d, 'error' => $error]);

        if (in_array($field, ['title', 'desc'], true)) {
            if ($text === '-') {
                Navigator::replace($bot, 'up', ['step' => 'menu', 'd' => $d]);

                return;
            }

            $value = trim((string) preg_replace('/\s+/u', ' ', $text));
            $length = mb_strlen($value);
            $ok = $field === 'title' ? ($length >= 2 && $length <= 100) : ($length >= 3 && $length <= 600);

            if (! $ok) {
                $again(__("mat.err.{$field}"));

                return;
            }

            $d[$field] = $value;
            MaterialCard::refresh($bot, $d, $this->chosenName($user, $d));
            Navigator::replace($bot, 'up', ['step' => 'menu', 'd' => $d]);

            return;
        }

        if ($field === 'newcat') {
            if (CategoryFields::limitReached($user)) {
                $again(__('cat.err.limit', ['max' => CategoryFields::MAX_PER_TEACHER]));

                return;
            }

            [$ok, $value] = CategoryFields::name($user, $text);

            if (! $ok) {
                $again($value);

                return;
            }

            $category = Category::create([
                'owner_id' => $user->id,
                'name' => $value,
                'default_access' => Access::Public->value,
                'is_default' => false,
            ]);

            $d['cat'] = $category->id;
            MaterialCard::refresh($bot, $d, $category->name);
            Navigator::replace($bot, 'up', ['step' => 'access', 'd' => $d]);

            return;
        }

        Navigator::refresh($bot);
    }

    private function fromText(Nutgram $bot, string $text): void
    {
        if ($text === '') {
            Navigator::refresh($bot);

            return;
        }

        if (mb_strlen($text) > 4000) {
            Navigator::replace($bot, 'up', ['step' => 'file', 'error' => __('mat.err.text_long')]);

            return;
        }

        $isLink = mb_strlen($text) <= 2000 && preg_match('~^https?://\S+$~iu', $text) === 1;

        $this->begin($bot, [
            'type' => ($isLink ? MaterialType::Link : MaterialType::Text)->value,
            'file_id' => null,
            'uid' => null,
            'file_name' => null,
            'mime' => null,
            'size' => null,
            'title' => null,
            'desc' => null,
            'url' => $isLink ? $text : null,
            'body' => $isLink ? null : $text,
            'cat' => null,
            'prev' => null,
        ]);
    }

    /** Kartochkani yuboradi, anchor xabarni uning ostida yangidan chizadi. */
    private function begin(Nutgram $bot, array $d): void
    {
        try {
            $d['prev'] = MaterialCard::send($bot, $d);
        } catch (Throwable $e) {
            report($e);
            Navigator::replace($bot, 'up', ['step' => 'file', 'error' => __('mat.err.send')]);

            return;
        }

        Navigator::replaceFresh($bot, 'up', ['step' => 'menu', 'd' => $d]);
    }

    // ---- Qadamlar ---------------------------------------------------------------------------

    private function pickCategory(Nutgram $bot, User $user, array $d, string $value): void
    {
        if ($value === 'def') {
            $d['cat'] = null;
        } else {
            $category = Category::query()->forTeacher($user)->find((int) $value);

            if ($category === null) {
                Navigator::refresh($bot);

                return;
            }

            $d['cat'] = $category->id;
        }

        MaterialCard::refresh($bot, $d, $this->chosenName($user, $d));
        Navigator::replace($bot, 'up', ['step' => 'access', 'd' => $d]);
    }

    private function pickAccess(Nutgram $bot, User $user, array $d, string $value): void
    {
        $access = Access::tryFrom($value);

        $access === null
            ? Navigator::refresh($bot)
            : $this->save($bot, $user, $d, $access);
    }

    private function save(Nutgram $bot, User $user, array $d, ?Access $access): void
    {
        $category = $this->category($user, $d);
        $access ??= $category?->default_access ?? Access::Public;
        $d['title'] = filled($d['title'] ?? null) ? $d['title'] : $this->fallbackTitle($d);

        $material = Material::create([
            'owner_id' => $user->id,
            'category_id' => $category?->id,
            'type' => $d['type'],
            'title' => $d['title'],
            'description' => $d['desc'] ?? null,
            'telegram_file_id' => $d['file_id'] ?? null,
            'telegram_file_unique_id' => $d['uid'] ?? null,
            'file_name' => $d['file_name'] ?? null,
            'mime_type' => $d['mime'] ?? null,
            'file_size' => $d['size'] ?? null,
            'url' => $d['url'] ?? null,
            'body' => $d['body'] ?? null,
            'access' => $access->value,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        MaterialCard::refresh($bot, $d, $category?->name, $access);
        Navigator::replace($bot, 'up', ['step' => 'done', 'id' => $material->id]);
    }

    private function discard(Nutgram $bot, array $d): void
    {
        MaterialCard::discard($bot, $d);
        Navigator::back($bot);
    }

    // ---- Sahifalar --------------------------------------------------------------------------

    private function menuPage(array $d, string $error): Page
    {
        $text = __('mat.up.ready', ['type' => MaterialType::from($d['type'])->label()]) . $error;

        $rows = [];
        $add = [];

        if (blank($d['title'] ?? null)) {
            $add[] = [__('mat.btn.title'), 'up:title'];
        }

        if (blank($d['desc'] ?? null)) {
            $add[] = [__('mat.btn.desc'), 'up:desc'];
        }

        if ($add !== []) {
            $rows[] = $add;
        }

        $rows[] = [[__('mat.btn.save'), 'up:save']];
        $rows[] = [[__('mat.btn.next'), 'up:next']];
        $rows[] = [[__('mat.btn.discard'), 'up:discard']];

        return Page::make($text, $rows);
    }

    private function catPage(User $user): Page
    {
        $rows = [[[__('mat.btn.cat_default'), 'up:cat:def']]];

        foreach ($user->categories()->orderBy('name')->get() as $category) {
            $rows[] = [[
                CategoryFields::icon($category->default_access) . ' ' . Str::limit($category->name, 40),
                "up:cat:{$category->id}",
            ]];
        }

        $rows[] = [[__('mat.btn.cat_new'), 'up:newcat']];
        $rows[] = [[__('mat.btn.back'), 'up:tomenu']];

        return Page::make(__('mat.up.pick_cat'), $rows);
    }

    private function accessPage(User $user, array $d): Page
    {
        $default = ($this->category($user, $d)?->default_access ?? Access::Public)->label();

        $rows = [];

        foreach (Access::cases() as $case) {
            $rows[] = [[$case->label(), 'up:access:' . $case->value]];
        }

        $rows[] = [[__('mat.btn.back'), 'up:tocat']];

        return Page::make(__('mat.up.pick_access', ['default' => e($default)]), $rows);
    }

    // ---- Yordamchilar -----------------------------------------------------------------------

    /** Tanlangan yoki (tanlanmagan bo'lsa) turiga mos standart kategoriya. */
    private function category(User $user, array $d): ?Category
    {
        if (! empty($d['cat'])) {
            return Category::query()->forTeacher($user)->find($d['cat']);
        }

        return Category::query()
            ->defaults()
            ->where('system_type', MaterialType::from($d['type'])->systemType()->value)
            ->first();
    }

    /** Faqat aniq tanlangan kategoriya nomi (tanlanmagan bo'lsa null). */
    private function chosenName(User $user, array $d): ?string
    {
        return empty($d['cat']) ? null : Category::query()->forTeacher($user)->find($d['cat'])?->name;
    }

    private function fallbackTitle(array $d): ?string
    {
        return match (MaterialType::from($d['type'])) {
            MaterialType::Document, MaterialType::Video, MaterialType::Audio => filled($d['file_name'] ?? null)
                ? Str::limit($d['file_name'], 100, '')
                : null,
            MaterialType::Link => Str::limit((string) ($d['url'] ?? ''), 100, ''),
            MaterialType::Text => Str::limit((string) preg_replace('/\s+/u', ' ', (string) ($d['body'] ?? '')), 60),
            default => null,
        };
    }
}
