<?php

namespace App\Telegram\Screens;

use App\Enums\RequestStatus;
use App\Models\ProtectedAccess;
use App\Models\TeacherStudent;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use SergiX44\Nutgram\Nutgram;

/** Ustoz uchun "O'quvchilarim" bosh ekrani: hisoblagichlar va ro'yxatlarga o'tish. */
class StusScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $teacher = CurrentUser::get();

        if (! $teacher->isTeacher()) {
            return Page::make(__('links.err.forbidden'), back: true);
        }

        $req = TeacherStudent::query()->where('teacher_id', $teacher->id)->where('status', RequestStatus::Pending->value)->count();
        $list = TeacherStudent::query()->where('teacher_id', $teacher->id)->where('status', RequestStatus::Approved->value)->count();
        $prot = ProtectedAccess::query()->where('teacher_id', $teacher->id)->where('status', RequestStatus::Pending->value)->count();

        return Page::make(__('links.stus.title', ['req' => $req, 'list' => $list, 'prot' => $prot]), [
            [[__('links.stus.btn_req', ['n' => $req]), 'stus:l:req']],
            [[__('links.stus.btn_list', ['n' => $list]), 'stus:l:list']],
            [[__('links.stus.btn_prot', ['n' => $prot]), 'stus:l:prot']],
        ], back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $kind = $args[0] ?? '';

        if ($action === 'l' && in_array($kind, ['req', 'list', 'prot'], true) && CurrentUser::get()->isTeacher()) {
            Navigator::go($bot, 'stul', ['kind' => $kind]);

            return;
        }

        Navigator::refresh($bot);
    }
}
