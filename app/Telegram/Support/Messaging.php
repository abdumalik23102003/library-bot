<?php

namespace App\Telegram\Support;

use App\Enums\Role;
use App\Models\Message;
use App\Models\User;
use App\Telegram\Navigation\Kb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Throwable;

/**
 * Bot ichidagi xabarlar: foydalanuvchi <-> admin/moderator. Telegram username oshkor qilinmaydi (faqat ism va rol).
 * Administratsiyaga yozilgan xabar har bir xodimga ALOHIDA yoziladi (messages: bitta qabul qiluvchi = bitta qator).
 */
final class Messaging
{
    /** Xabarda yuborish mumkin bo'lgan fayl turlari (+ matn). */
    public const KINDS = ['text', 'photo', 'document', 'video', 'audio', 'voice'];

    public const MAX_TEXT = 3000;

    /** Xodim bo'lmagan foydalanuvchi bir soatda ko'pi bilan shuncha xabar yubora oladi. */
    public const HOURLY_LIMIT = 10;

    /** Kim kimga yoza oladi: qoidalar MessageTargets::pool() da. */
    public static function canWrite(User $from, User $to): bool
    {
        if ($from->id === $to->id || ! $from->isActive() || $from->registered_at === null || $to->registered_at === null) {
            return false;
        }

        return MessageTargets::allows($from, $to);
    }

    /** Murojaatlar boradigan xodimlar: admin va faol moderatorlar. */
    public static function staff(): Collection
    {
        return User::query()
            ->ofRole(Role::Admin, Role::Moderator)
            ->active()
            ->whereNotNull('registered_at')
            ->orderBy('id')
            ->get();
    }

    /** @return array{0: bool, 1: string} [to'g'rimi, tozalangan matn yoki xato matni] */
    public static function validateText(string $text, int $max = self::MAX_TEXT): array
    {
        $text = trim($text);
        $length = mb_strlen($text);

        if ($length < 1) {
            return [false, __('msg.err.empty')];
        }

        return $length > $max ? [false, __('msg.err.too_long', ['max' => $max])] : [true, $text];
    }

    // ---- Spamdan himoya (faqat xodim bo'lmaganlar uchun) ----------------------------------------

    /** Chegara oshgan bo'lsa, necha soniyadan keyin urinish mumkinligini qaytaradi, aks holda null. */
    public static function tooMany(User $from): ?int
    {
        if ($from->isStaff()) {
            return null;
        }

        $key = self::rateKey($from);

        return RateLimiter::tooManyAttempts($key, self::HOURLY_LIMIT) ? RateLimiter::availableIn($key) : null;
    }

    public static function hit(User $from): void
    {
        if (! $from->isStaff()) {
            RateLimiter::hit(self::rateKey($from), 3600);
        }
    }

    private static function rateKey(User $from): string
    {
        return "msg:send:{$from->id}";
    }

    // ---- Yuborish ---------------------------------------------------------------------------------

    /**
     * Xabarni saqlaydi va har bir qabul qiluvchiga kartochka (+ [Javob berish][Bloklash]) bilan yuboradi.
     * Yetkazib bo'lmaganining qatori o'chiriladi.
     *
     * @param  iterable<int, User>  $recipients
     * @return array{sent: int, failed: int}
     */
    public static function send(Nutgram $bot, User $from, iterable $recipients, string $kind, ?string $fileId, ?string $body, ?Message $parent = null): array
    {
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $to) {
            $message = Message::query()->create([
                'sender_id' => $from->id,
                'receiver_id' => $to->id,
                'parent_id' => $parent?->id,
                'kind' => $kind,
                'body' => $body,
                'telegram_file_id' => $fileId,
            ]);

            try {
                self::attempt(fn () => self::push(
                    $bot,
                    $to->telegram_id,
                    $kind,
                    $fileId,
                    self::card($message, $from, $parent),
                    self::keyboard($message, $from, $to),
                ));
                $sent++;
            } catch (Throwable $e) {
                Log::warning('Xabar yetkazilmadi', ['to' => $to->id, 'error' => $e->getMessage()]);
                $message->delete();
                $failed++;
            }
        }

        // Javob berilgan xabar "o'qilgan" deb belgilanadi.
        if ($parent !== null && $sent > 0 && $parent->read_at === null) {
            $parent->forceFill(['read_at' => now()])->save();
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    public static function card(Message $message, User $from, ?Message $parent): string
    {
        $lines = [__('msg.card.title', [
            'id' => $message->id,
            'name' => e($from->displayName()),
            'role' => e($from->role->label()),
        ])];

        if ($parent !== null) {
            $lines[] = __('msg.card.reply_to', ['id' => $parent->id]);
        }

        if (filled($message->body)) {
            $lines[] = '';
            $lines[] = e($message->body);
        }

        return implode("\n", $lines);
    }

    /** [Javob berish] har doim; [Bloklash] faqat xodim bo'lmagan yuboruvchini boshqara oladigan xodimga. */
    public static function keyboard(Message $message, User $from, User $to): InlineKeyboardMarkup
    {
        $row = [[__('msg.btn.reply'), "msg:reply:{$message->id}"]];

        if (! $from->isStaff() && UserAdmin::can($to, $from)) {
            $row[] = [__('msg.btn.block'), "msg:blk:{$message->id}"];
        }

        return Kb::inline([$row]);
    }

    /**
     * Turiga qarab yuboradi. Fayl bo'lsa, matn fayl ostidagi sarlavha (caption) bo'ladi (Telegram: 1024 belgigacha).
     * Xato bo'lsa istisno otadi.
     */
    public static function push(Nutgram $bot, int $chatId, string $kind, ?string $fileId, string $text, ?InlineKeyboardMarkup $markup = null): void
    {
        if ($fileId === null || $fileId === '') {
            $kind = 'text';
        }

        $caption = $text !== '' ? $text : null;

        match ($kind) {
            'photo' => $bot->sendPhoto(photo: (string) $fileId, chat_id: $chatId, caption: $caption, parse_mode: ParseMode::HTML, reply_markup: $markup),
            'document' => $bot->sendDocument(document: (string) $fileId, chat_id: $chatId, caption: $caption, parse_mode: ParseMode::HTML, reply_markup: $markup),
            'video' => $bot->sendVideo(video: (string) $fileId, chat_id: $chatId, caption: $caption, parse_mode: ParseMode::HTML, reply_markup: $markup),
            'audio' => $bot->sendAudio(audio: (string) $fileId, chat_id: $chatId, caption: $caption, parse_mode: ParseMode::HTML, reply_markup: $markup),
            'voice' => $bot->sendVoice(voice: (string) $fileId, chat_id: $chatId, caption: $caption, parse_mode: ParseMode::HTML, reply_markup: $markup),
            default => $bot->sendMessage(text: $text, chat_id: $chatId, parse_mode: ParseMode::HTML, reply_markup: $markup),
        };
    }

    /** "Too Many Requests: retry after N" bo'lsa kutib, 3 martagacha qayta uriniladi. */
    public static function attempt(callable $send): void
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $send();

                return;
            } catch (Throwable $e) {
                if ($attempt >= 3 || ! preg_match('/retry after (\d+)/i', $e->getMessage(), $m)) {
                    throw $e;
                }

                sleep(min((int) $m[1], 30) + 1);
            }
        }
    }
}
