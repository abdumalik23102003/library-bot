<?php

namespace App\Telegram\Support;

use App\Models\Material;
use App\Models\User;
use App\Telegram\Navigation\Kb;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/** Boshqa kishi (admin/moderator) materialni o'zgartirsa, egasiga xabar beradi. */
final class MaterialNotifier
{
    public static function changed(Nutgram $bot, Material $material, User $actor, string $what): void
    {
        $owner = $material->owner;

        if ($owner === null || $owner->id === $actor->id) {
            return;
        }

        try {
            $bot->sendMessage(
                text: __('mats.notify.text', [
                    'role' => $actor->role->label(),
                    'actor' => e($actor->displayName()),
                    'what' => __("mats.notify.what.{$what}"),
                    'category' => e($material->category?->name ?? '—'),
                    'access' => $material->access->label(),
                ]),
                chat_id: $owner->telegram_id,
                parse_mode: ParseMode::HTML,
                reply_markup: Messaging::canWrite($owner, $actor)
                    ? Kb::inline([[[__('msg.btn.write_actor'), "msg:to:{$actor->id}"]]])
                    : null,
            );

            if ($what !== 'deleted') {
                MaterialSender::send($bot, $material, $owner->telegram_id);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
