<?php

namespace App\Enums;

enum MaterialType: string
{
    case Document = 'document';
    case Video = 'video';
    case Photo = 'photo';
    case Audio = 'audio';
    case Voice = 'voice';
    case Link = 'link';
    case Text = 'text';

    public function label(): string
    {
        return __("enums.type.{$this->value}");
    }

    /** Default kategoriyani topish uchun tur (ovozli xabar Audio kategoriyasiga tushadi). */
    public function systemType(): self
    {
        return $this === self::Voice ? self::Audio : $this;
    }

    /** Telegram file_id bilan saqlanadigan turlarmi (havola va matn emas). */
    public function hasFile(): bool
    {
        return ! in_array($this, [self::Link, self::Text], true);
    }
}
