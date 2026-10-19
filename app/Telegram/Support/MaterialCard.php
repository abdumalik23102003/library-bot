<?php

namespace App\Telegram\Support;

use App\Enums\Access;
use App\Enums\MaterialType;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/**
 * Material kartochkasi (qoralama va tayyor): matn yasash, yuborish, yangilash.
 * $d - qoralama massivi: type, file_id, title, desc, url, body, size, prev (kartochka xabari ID si).
 */
final class MaterialCard
{
    public static function text(array $d, ?string $category = null, ?Access $access = null): string
    {
        $type = MaterialType::from($d['type']);
        $title = filled($d['title'] ?? null) ? $d['title'] : __('mat.untitled');

        $lines = ['<b>' . e(Str::limit($title, 100)) . '</b>'];

        if (filled($d['desc'] ?? null)) {
            $lines[] = e(Str::limit($d['desc'], 600));
        }

        if ($type === MaterialType::Link) {
            $lines[] = '🔗 ' . e(Str::limit((string) ($d['url'] ?? ''), 300));
        }

        if ($type === MaterialType::Text) {
            $lines[] = '<blockquote>' . e(Str::limit((string) ($d['body'] ?? ''), 1500)) . '</blockquote>';
        }

        $lines[] = '';
        $lines[] = '📂 ' . __('mat.card.category') . ': ' . ($category !== null ? e($category) : __('mat.card.cat_auto'));
        $lines[] = '🔐 ' . __('mat.card.access') . ': ' . ($access ? e($access->label()) : __('mat.card.access_auto'));

        $meta = $type->label();

        if (! empty($d['size'])) {
            $meta .= ' · ' . Number::fileSize((int) $d['size'], precision: 1);
        }

        $lines[] = $meta;

        return implode("\n", $lines);
    }

    /** Kartochkani chatga yuboradi va xabar ID sini qaytaradi. */
    public static function send(Nutgram $bot, array $d, ?string $category = null, ?Access $access = null): ?int
    {
        $text = self::text($d, $category, $access);
        $chat = $bot->chatId();
        $html = ParseMode::HTML;

        $sent = match (MaterialType::from($d['type'])) {
            MaterialType::Document => $bot->sendDocument(document: $d['file_id'], chat_id: $chat, caption: $text, parse_mode: $html),
            MaterialType::Video => $bot->sendVideo(video: $d['file_id'], chat_id: $chat, caption: $text, parse_mode: $html),
            MaterialType::Photo => $bot->sendPhoto(photo: $d['file_id'], chat_id: $chat, caption: $text, parse_mode: $html),
            MaterialType::Audio => $bot->sendAudio(audio: $d['file_id'], chat_id: $chat, caption: $text, parse_mode: $html),
            MaterialType::Voice => $bot->sendVoice(voice: $d['file_id'], chat_id: $chat, caption: $text, parse_mode: $html),
            default => $bot->sendMessage(text: $text, chat_id: $chat, parse_mode: $html),
        };

        return $sent?->message_id;
    }

    /** Yuborilgan kartochkani yangilaydi (xatoliklarga e'tibor bermaydi). */
    public static function refresh(Nutgram $bot, array $d, ?string $category = null, ?Access $access = null): void
    {
        if (empty($d['prev'])) {
            return;
        }

        $text = self::text($d, $category, $access);

        try {
            if (MaterialType::from($d['type'])->hasFile()) {
                $bot->editMessageCaption(chat_id: $bot->chatId(), message_id: $d['prev'], caption: $text, parse_mode: ParseMode::HTML);
            } else {
                $bot->editMessageText(text: $text, chat_id: $bot->chatId(), message_id: $d['prev'], parse_mode: ParseMode::HTML);
            }
        } catch (Throwable) {
            // o'zgarmagan yoki eskirgan xabar
        }
    }

    public static function discard(Nutgram $bot, array $d): void
    {
        Chat::delete($bot, $d['prev'] ?? null);
    }
}
