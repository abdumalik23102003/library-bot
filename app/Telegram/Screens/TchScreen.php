<?php

namespace App\Telegram\Screens;

use App\Enums\Access;
use App\Models\Material;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\LinkNotifier;
use App\Telegram\Support\TeacherLinks;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/** O'quvchi uchun ustoz kartochkasi: qo'shilish va himoyalangan kirish so'rovlari. */
class TchScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $student = CurrentUser::get();
        $teacher = User::find((int) ($params['id'] ?? 0));

        if (! $student->isStudent() || ! $this->valid($teacher)) {
            return Page::make(__('links.err.not_found'), back: true);
        }

        $id = $teacher->id;
        $linkStatus = TeacherLinks::link($teacher, $student)?->status->value ?? 'none';

        $lines = [];

        if (! empty($params['flash'])) {
            $lines[] = __("links.flash.{$params['flash']}");
            $lines[] = '';
        }

        $lines[] = __('links.tch.card', [
            'name' => e($teacher->displayName()),
            'subject' => e($teacher->subject ?? '—'),
            'bio' => e(Str::limit((string) $teacher->bio, 400)),
            'public' => Material::query()->where('owner_id', $id)->where('access', Access::Public->value)->count(),
        ]);
        $lines[] = '';
        $lines[] = __("links.tch.status.{$linkStatus}");

        $rows = [];

        if ($linkStatus === 'pending') {
            $rows[] = [[__('links.tch.btn_cancel_join'), "tch:cjoin:{$id}"]];
        } elseif ($linkStatus === 'approved') {
            $protected = TeacherLinks::access($teacher, $student)?->status->value ?? 'none';
            $lines[] = __("links.tch.protected.{$protected}");

            if ($protected === 'pending') {
                $rows[] = [[__('links.tch.btn_cancel_pr'), "tch:cpr:{$id}"]];
            } elseif ($protected !== 'approved') {
                $rows[] = [[__('links.tch.btn_pr'), "tch:pr:{$id}"]];
            }
        } else {
            $rows[] = [[__('links.tch.btn_join'), "tch:join:{$id}"]];
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $student = CurrentUser::get();
        $id = (int) ($args[0] ?? 0);
        $teacher = User::find($id);

        if (! $student->isStudent() || ! $student->isActive() || ! $this->valid($teacher)) {
            $this->show($bot, $id, ['flash' => 'invalid']);

            return;
        }

        $flash = match ($action) {
            'join' => $this->join($bot, $teacher, $student),
            'cjoin' => TeacherLinks::cancelJoin($teacher, $student) ? 'join_cancelled' : 'invalid',
            'pr' => $this->requestProtected($bot, $teacher, $student),
            'cpr' => TeacherLinks::cancelProtected($teacher, $student) ? 'pr_cancelled' : 'invalid',
            default => null,
        };

        $this->show($bot, $id, $flash ? ['flash' => $flash] : []);
    }

    private function join(Nutgram $bot, User $teacher, User $student): string
    {
        $result = TeacherLinks::requestJoin($teacher, $student);

        if ($result === 'sent') {
            LinkNotifier::joinRequest($bot, $teacher, $student);
        }

        return match ($result) {
            'sent' => 'join_sent',
            'pending' => 'join_pending',
            'approved' => 'join_approved',
            default => 'invalid',
        };
    }

    private function requestProtected(Nutgram $bot, User $teacher, User $student): string
    {
        $result = TeacherLinks::requestProtected($teacher, $student);

        if ($result === 'sent') {
            LinkNotifier::protectedRequest($bot, $teacher, $student);
        }

        return match ($result) {
            'sent' => 'pr_sent',
            'pending' => 'pr_pending',
            'approved' => 'pr_approved',
            'not_linked' => 'not_linked',
            default => 'invalid',
        };
    }

    private function valid(?User $teacher): bool
    {
        return $teacher !== null && $teacher->isTeacher() && $teacher->isActive() && $teacher->registered_at !== null;
    }

    private function show(Nutgram $bot, int $id, array $extra = []): void
    {
        $params = ['id' => $id] + $extra;

        (Navigator::current($bot)['screen'] ?? null) === 'tch'
            ? Navigator::replace($bot, 'tch', $params)
            : Navigator::go($bot, 'tch', $params);
    }
}
