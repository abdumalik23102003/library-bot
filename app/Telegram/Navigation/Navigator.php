<?php

namespace App\Telegram\Navigation;

use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/**
 * Foydalanuvchi uchun ekranlar steki va "anchor" xabar (tahrirlanadigan asosiy xabar).
 * Holat Laravel keshida (database) saqlanadi.
 */
final class Navigator
{
    private const TTL_DAYS = 30;

    /** Yangi seans: stekni tozalaydi va yangi anchor xabar yuboradi. $parents - "Orqaga" qaytadigan ekranlar. */
    public static function start(Nutgram $bot, string $screen = 'home', array $params = [], array $parents = []): void
    {
        self::detachOldAnchor($bot);

        $stack = array_map(fn (string $key) => ['screen' => $key, 'params' => []], $parents);
        $stack[] = ['screen' => $screen, 'params' => $params];

        self::saveStack($bot, $stack);
        self::clearInput($bot);
        self::render($bot, forceNew: true);
    }

    /** Yangi ekranga o'tadi (stekka qo'shiladi). */
    public static function go(Nutgram $bot, string $screen, array $params = []): void
    {
        $stack = self::stack($bot);
        $stack[] = ['screen' => $screen, 'params' => $params];

        self::saveStack($bot, $stack);
        self::clearInput($bot);
        self::render($bot);
    }

    /** Joriy ekranni almashtiradi (steki balandligi o'zgarmaydi). */
    public static function replace(Nutgram $bot, string $screen, array $params = []): void
    {
        $stack = self::stack($bot);
        array_pop($stack);
        $stack[] = ['screen' => $screen, 'params' => $params];

        self::saveStack($bot, $stack);
        self::clearInput($bot);
        self::render($bot);
    }

    /** Aynan oldingi ekranga qaytadi. */
    public static function back(Nutgram $bot): void
    {
        $stack = self::stack($bot);

        if (count($stack) > 1) {
            array_pop($stack);
            self::saveStack($bot, $stack);
        }

        self::clearInput($bot);
        self::render($bot);
    }

    /** Joriy ekranni qayta chizadi. */
    public static function refresh(Nutgram $bot): void
    {
        self::render($bot);
    }

    /** @return array{screen: string, params: array}|null */
    public static function current(Nutgram $bot): ?array
    {
        $stack = self::stack($bot);

        return $stack === [] ? null : end($stack);
    }

    // ---- Matn kiritish holati ---------------------------------------------------------------

    /** Joriy ekran uchun keyingi matn xabarini kutadi. */
    public static function awaitInput(Nutgram $bot, string $field): void
    {
        $top = self::current($bot);

        if ($top === null) {
            return;
        }

        Cache::put(self::key($bot, 'input'), [
            'screen' => $top['screen'],
            'field' => $field,
            'params' => $top['params'],
        ], now()->addDays(self::TTL_DAYS));
    }

    /** @return array{screen: string, field: string, params: array}|null */
    public static function pendingInput(Nutgram $bot): ?array
    {
        return Cache::get(self::key($bot, 'input'));
    }

    public static function clearInput(Nutgram $bot): void
    {
        Cache::forget(self::key($bot, 'input'));
    }

    /** Boshqa foydalanuvchining (telegram_id) navigatsiya holatini tozalaydi (masalan, qaror qabul qilingach). */
    public static function resetFor(int $telegramId): void
    {
        Cache::forget("tg:{$telegramId}:stack");
        Cache::forget("tg:{$telegramId}:input");
    }

    /** Boshqa foydalanuvchining anchor xabar ID si. */
    public static function anchorOf(int $telegramId): ?int
    {
        return Cache::get("tg:{$telegramId}:anchor");
    }

    // ---- Ichki ----------------------------------------------------------------------------------

    private static function render(Nutgram $bot, bool $forceNew = false): void
    {
        $stack = self::stack($bot);

        if ($stack === []) {
            $stack = [['screen' => 'home', 'params' => []]];
            self::saveStack($bot, $stack);
        }

        $top = end($stack);
        $screen = ScreenRegistry::make($top['screen']);

        if ($screen === null) {
            $top = ['screen' => 'home', 'params' => []];
            self::saveStack($bot, [$top]);
            $screen = ScreenRegistry::make('home');
        }

        self::deliver($bot, $screen->render($bot, $top['params']), $forceNew);
    }

    /** Anchor xabarni tahrirlaydi; iloji bo'lmasa yangisini yuboradi. */
    private static function deliver(Nutgram $bot, Page $page, bool $forceNew): void
    {
        $chatId = $bot->chatId();
        $messageId = $forceNew
            ? null
            : ($bot->callbackQuery()?->message?->message_id ?? Cache::get(self::key($bot, 'anchor')));

        if ($messageId) {
            try {
                $bot->editMessageText(
                    text: $page->text,
                    chat_id: $chatId,
                    message_id: $messageId,
                    parse_mode: ParseMode::HTML,
                    reply_markup: $page->keyboard,
                );
                self::setAnchor($bot, $messageId);

                return;
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'message is not modified')) {
                    self::setAnchor($bot, $messageId);

                    return;
                }
                // Tahrirlab bo'lmadi (eski xabar, media va h.k.) - yangisini yuboramiz.
            }
        }

        $sent = $bot->sendMessage(
            text: $page->text,
            chat_id: $chatId,
            parse_mode: ParseMode::HTML,
            reply_markup: $page->keyboard,
        );

        if ($sent) {
            self::setAnchor($bot, $sent->message_id);
        }
    }

    /** Eski anchor xabardagi tugmalarni olib tashlaydi (eskirgan ekran bosilmasin). */
    private static function detachOldAnchor(Nutgram $bot): void
    {
        $old = Cache::get(self::key($bot, 'anchor'));

        if (! $old) {
            return;
        }

        try {
            $bot->editMessageReplyMarkup(chat_id: $bot->chatId(), message_id: $old);
        } catch (Throwable) {
            // e'tibor bermaymiz
        }
    }

    private static function setAnchor(Nutgram $bot, int $messageId): void
    {
        Cache::put(self::key($bot, 'anchor'), $messageId, now()->addDays(self::TTL_DAYS));
    }

    /** @return array<int, array{screen: string, params: array}> */
    private static function stack(Nutgram $bot): array
    {
        return Cache::get(self::key($bot, 'stack'), []);
    }

    private static function saveStack(Nutgram $bot, array $stack): void
    {
        Cache::put(self::key($bot, 'stack'), array_values($stack), now()->addDays(self::TTL_DAYS));
    }

    private static function key(Nutgram $bot, string $name): string
    {
        return "tg:{$bot->userId()}:{$name}";
    }
}
