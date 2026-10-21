<?php

namespace App\Telegram\Screens;

use App\Models\Message;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\UserAdmin;
use SergiX44\Nutgram\Nutgram;

/**
 * "✉ Xabarlar" markazi: yozish, kelganlar, (xodimlar uchun) broadcast.
 * Callback: msg:new, msg:inb, msg:bc (menyu); msg:reply:{xabarId}, msg:to:{userId}, msg:blk:{xabarId} (xabar kartochkalaridan).
 * Kartochkadagi tugmalar yangi xabar ochadi (Navigator::start), shunda asl xabar matni yo'qolmaydi.
 */
class MsgScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $actor = CurrentUser::get();
        $unread = $this->unread($actor);

        $rows = [
            [[__('msg.hub.btn_write'), 'msg:new']],
            [[__('msg.hub.btn_inbox', ['n' => $unread]), 'msg:inb']],
        ];

        if ($actor->isStaff()) {
            $rows[] = [[__('msg.bc.btn_panel'), 'msg:bc']];
        }

        return Page::make(__('msg.hub.title', ['n' => $unread]), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        $id = (int) ($args[0] ?? 0);
        $onHub = (Navigator::current($bot)['screen'] ?? '') === 'msg';

        match (true) {
            $action === 'reply' => Navigator::start($bot, 'wr', ['reply' => $id], ['home']),
            $action === 'to' => Navigator::start($bot, 'wr', ['to' => $id], ['home']),
            $action === 'blk' => $this->block($bot, $actor, $id),
            ! $onHub => Navigator::refresh($bot),
            $action === 'new' => $this->write($bot, $actor),
            $action === 'inb' => Navigator::go($bot, 'inb', ['f' => 'all', 'page' => 1]),
            $action === 'bc' && $actor->isStaff() => Navigator::go($bot, 'bc'),
            default => Navigator::refresh($bot),
        };
    }

    private function write(Nutgram $bot, User $actor): void
    {
        WrScreen::forget($actor);
        Navigator::go($bot, 'wr');
    }

    private function unread(User $actor): int
    {
        return Message::query()->where('receiver_id', $actor->id)->whereNull('read_at')->count();
    }

    /** Kartochkadagi [Bloklash]: foydalanuvchi kartochkasini ochadi (sabab so'raladi). */
    private function block(Nutgram $bot, User $actor, int $messageId): void
    {
        $sender = Message::query()->where('receiver_id', $actor->id)->find($messageId)?->sender;

        if ($sender === null || ! UserAdmin::can($actor, $sender)) {
            $bot->sendMessage(__('msg.err.cannot_block'));

            return;
        }

        Navigator::start($bot, 'usr', ['id' => $sender->id], ['home']);
    }
}
