<?php

namespace App\Telegram\Support;

use App\Enums\MaterialType;
use App\Models\Material;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * Materialni turiga qarab chatga yuboradi (file_id bo'yicha, serverdan yuklab olinmaydi).
 * Xato bo'lsa (masalan, yaroqsiz file_id) istisno otadi - chaqiruvchi ushlaydi.
 */
final class MaterialSender
{
    public static function caption(Material $material): string
    {
        $parts = [];

        if (filled($material->title)) {
            $parts[] = '<b>' . e(Str::limit($material->title, 150)) . '</b>';
        }

        if (filled($material->description)) {
            $parts[] = e(Str::limit($material->description, 600));
        }

        return implode("\n\n", $parts);
    }

    public static function send(Nutgram $bot, Material $material, int $chatId, ?InlineKeyboardMarkup $keyboard = null): void
    {
        $caption = self::caption($material);
        $fileId = (string) $material->telegram_file_id;

        match ($material->type) {
            MaterialType::Document => $bot->sendDocument(document: $fileId, chat_id: $chatId, caption: $caption ?: null, parse_mode: ParseMode::HTML, reply_markup: $keyboard),
            MaterialType::Video => $bot->sendVideo(video: $fileId, chat_id: $chatId, caption: $caption ?: null, parse_mode: ParseMode::HTML, reply_markup: $keyboard),
            MaterialType::Photo => $bot->sendPhoto(photo: $fileId, chat_id: $chatId, caption: $caption ?: null, parse_mode: ParseMode::HTML, reply_markup: $keyboard),
            MaterialType::Audio => $bot->sendAudio(audio: $fileId, chat_id: $chatId, caption: $caption ?: null, parse_mode: ParseMode::HTML, reply_markup: $keyboard),
            MaterialType::Voice => $bot->sendVoice(voice: $fileId, chat_id: $chatId, caption: $caption ?: null, parse_mode: ParseMode::HTML, reply_markup: $keyboard),
            MaterialType::Link => $bot->sendMessage(
                text: trim($caption . "\n\n🔗 " . e((string) $material->url)),
                chat_id: $chatId,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard,
            ),
            MaterialType::Text => $bot->sendMessage(
                text: trim($caption . "\n\n" . e(Str::limit((string) $material->body, 3500, '…'))),
                chat_id: $chatId,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard,
            ),
        };
    }
}
