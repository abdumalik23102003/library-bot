<?php

namespace App\Telegram\Screens;

use App\Enums\RequestStatus;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\LinkNotifier;
use App\Telegram\Support\TeacherLinks;
use SergiX44\Nutgram\Nutgram;

/** Ustoz uchun o'quvchi kartochkasi va boshqaruv tugmalari (stu:{amal}:{o'quvchiId}). */
class StuScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $teacher = CurrentUser::get();
        $student = User::find((int) ($params['id'] ?? 0));
        $link = ($teacher->isTeacher() && $student) ? TeacherLinks::link($teacher, $student) : null;

        if ($student === null || $link === null) {
            return Page::make(__('links.err.not_found'), back: true);
        }

        $id = $student->id;
        $access = TeacherLinks::access($teacher, $student);
        $accessStatus = $access?->status->value ?? 'none';

        $lines = [];

        if (! empty($params['flash'])) {
            $lines[] = __("links.flash.{$params['flash']}");
            $lines[] = '';
        }

        $lines[] = $this->card($student);
        $lines[] = '';
        $lines[] = __("links.stu.link.{$link->status->value}");

        if ($link->status === RequestStatus::Approved) {
            $lines[] = __("links.stu.protected.{$accessStatus}");

            if (! empty($params['confirm'])) {
                return Page::make(implode("\n", $lines) . "\n\n" . __('links.stu.remove_confirm'), [[
                    [__('links.stu.btn_remove_yes'), "stu:rmyes:{$id}"],
                    [__('links.stu.btn_remove_no'), "stu:cancel:{$id}"],
                ]]);
            }
        }

        $rows = [];

        if ($link->status === RequestStatus::Pending) {
            $rows[] = [
                [__('links.btn.accept'), "stu:jacc:{$id}"],
                [__('links.btn.reject'), "stu:jrej:{$id}"],
            ];
        } elseif ($link->status === RequestStatus::Approved) {
            if ($accessStatus === 'approved') {
                $rows[] = [[__('links.stu.btn_revoke'), "stu:prev:{$id}"]];
            } elseif ($accessStatus === 'pending') {
                $rows[] = [
                    [__('links.btn.accept'), "stu:pacc:{$id}"],
                    [__('links.btn.reject'), "stu:prej:{$id}"],
                ];
            } else {
                $rows[] = [[__('links.stu.btn_grant'), "stu:pgr:{$id}"]];
            }

            $rows[] = [[__('links.stu.btn_remove'), "stu:rm:{$id}"]];
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $teacher = CurrentUser::get();
        $id = (int) ($args[0] ?? 0);
        $student = User::find($id);

        if (! $teacher->isTeacher() || ! $teacher->isActive() || $student === null || TeacherLinks::link($teacher, $student) === null) {
            $this->show($bot, $id);

            return;
        }

        $extra = match ($action) {
            'jacc' => $this->join($bot, $teacher, $student, true),
            'jrej' => $this->join($bot, $teacher, $student, false),
            'pacc' => $this->decideProtected($bot, $teacher, $student, true),
            'prej' => $this->decideProtected($bot, $teacher, $student, false),
            'pgr' => $this->grant($bot, $teacher, $student),
            'prev' => $this->revoke($bot, $teacher, $student),
            'rm' => ['confirm' => true],
            'rmyes' => $this->remove($bot, $teacher, $student),
            default => [],
        };

        $this->show($bot, $id, $extra);
    }

    private function join(Nutgram $bot, User $teacher, User $student, bool $accept): array
    {
        if (! TeacherLinks::decideJoin($teacher, $student, $accept)) {
            return ['flash' => 'already'];
        }

        LinkNotifier::joinDecision($bot, $teacher, $student, $accept);

        return ['flash' => $accept ? 'accepted' : 'rejected'];
    }

    private function decideProtected(Nutgram $bot, User $teacher, User $student, bool $grant): array
    {
        if (! TeacherLinks::decideProtected($teacher, $student, $grant)) {
            return ['flash' => 'already'];
        }

        LinkNotifier::protectedDecision($bot, $teacher, $student, $grant ? 'granted' : 'rejected');

        return ['flash' => $grant ? 'prot_granted' : 'prot_rejected'];
    }

    private function grant(Nutgram $bot, User $teacher, User $student): array
    {
        if (! TeacherLinks::grantProtected($teacher, $student)) {
            return ['flash' => 'invalid'];
        }

        LinkNotifier::protectedDecision($bot, $teacher, $student, 'granted');

        return ['flash' => 'prot_granted'];
    }

    private function revoke(Nutgram $bot, User $teacher, User $student): array
    {
        if (! TeacherLinks::revokeProtected($teacher, $student)) {
            return ['flash' => 'invalid'];
        }

        LinkNotifier::protectedDecision($bot, $teacher, $student, 'revoked');

        return ['flash' => 'revoked'];
    }

    private function remove(Nutgram $bot, User $teacher, User $student): array
    {
        TeacherLinks::removeStudent($teacher, $student);
        LinkNotifier::removed($bot, $teacher, $student);

        return ['flash' => 'removed'];
    }

    private function card(User $student): string
    {
        $lines = [
            '👤 <b>' . e($student->displayName()) . '</b>',
            __('links.stu.tg', ['value' => LinkNotifier::tg($student)]),
        ];

        if (filled($student->phone)) {
            $lines[] = __('links.stu.phone', ['value' => e($student->phone)]);
        }

        return implode("\n", $lines);
    }

    private function show(Nutgram $bot, int $id, array $extra = []): void
    {
        $params = ['id' => $id] + $extra;

        (Navigator::current($bot)['screen'] ?? null) === 'stu'
            ? Navigator::replace($bot, 'stu', $params)
            : Navigator::go($bot, 'stu', $params);
    }
}
