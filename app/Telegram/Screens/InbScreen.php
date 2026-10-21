<?php

namespace App\Telegram\Screens;

use App\Models\Message;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Messaging;
use App\Telegram\Support\Pager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Kelgan xabarlar. Callback: inb:f:{all|unread}, inb:p:{sahifa}, inb:o:{xabarId}, inb:rall.
 * params: f (filtr), page. Xabar ochilganda kartochka chatga qayta yuboriladi ([Javob][Bloklash] bilan) va o'qilgan deb belgilanadi.
 */
class InbScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $actor = CurrentUser::get();
        $filter = ($params['f'] ?? 'all') === 'unread' ? 'unread' : 'all';
        $unread = $this->base($actor)->whereNull('read_at')->count();

        $query = $this->base($actor)->with('sender')->orderByDesc('id');

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        [$items, $page, $pages, $total] = Pager::slice($query, (int) ($params['page'] ?? 1));

        $rows = [[
            [($filter === 'all' ? '• ' : '') . __('msg.inb.f_all'), 'inb:f:all'],
            [($filter === 'unread' ? '• ' : '') . __('msg.inb.f_unread', ['n' => $unread]), 'inb:f:unread'],
        ]];

        foreach ($items as $message) {
            $name = $message->sender?->displayName() ?? __('msg.inb.deleted');
            $mark = $message->read_at === null ? '🔵' : '⚪';
            $rows[] = [[
                $mark . ' ' . Str::limit($name, 22) . ' · ' . $message->created_at->format('d.m H:i'),
                "inb:o:{$message->id}",
            ]];
        }

        if ($nav = Pager::navRow('inb:p', $page, $pages)) {
            $rows[] = $nav;
        }

        if ($unread > 0) {
            $rows[] = [[__('msg.inb.btn_read_all'), 'inb:rall']];
        }

        $text = $total === 0
            ? __($filter === 'unread' ? 'msg.inb.empty_unread' : 'msg.inb.empty')
            : __('msg.inb.title', ['total' => $total, 'page' => $page, 'pages' => $pages]);

        return Page::make($text, $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        $top = Navigator::current($bot);

        if (($top['screen'] ?? '') !== 'inb') {
            Navigator::refresh($bot);

            return;
        }

        $params = $top['params'];
        $filter = (string) ($params['f'] ?? 'all');

        match ($action) {
            'f' => Navigator::replace($bot, 'inb', ['f' => ($args[0] ?? '') === 'unread' ? 'unread' : 'all', 'page' => 1]),
            'p' => Navigator::replace($bot, 'inb', ['f' => $filter, 'page' => max(1, (int) ($args[0] ?? 1))]),
            'o' => $this->open($bot, $actor, (int) ($args[0] ?? 0), $params),
            'rall' => $this->readAll($bot, $actor, $params),
            default => Navigator::refresh($bot),
        };
    }

    /** @return Builder<Message> */
    private function base(User $actor): Builder
    {
        return Message::query()->where('receiver_id', $actor->id);
    }

    private function open(Nutgram $bot, User $actor, int $id, array $params): void
    {
        $message = $this->base($actor)->with(['sender', 'parent'])->find($id);

        if ($message !== null && $message->sender !== null) {
            try {
                Messaging::push(
                    $bot,
                    $actor->telegram_id,
                    $message->kind,
                    $message->telegram_file_id,
                    Messaging::card($message, $message->sender, $message->parent),
                    Messaging::keyboard($message, $message->sender, $actor),
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($message !== null && $message->read_at === null) {
            $message->forceFill(['read_at' => now()])->save();
        }

        Navigator::replace($bot, 'inb', ['f' => $params['f'] ?? 'all', 'page' => $params['page'] ?? 1]);
    }

    private function readAll(Nutgram $bot, User $actor, array $params): void
    {
        $this->base($actor)->whereNull('read_at')->update(['read_at' => now()]);

        Navigator::replace($bot, 'inb', ['f' => $params['f'] ?? 'all', 'page' => 1]);
    }
}
