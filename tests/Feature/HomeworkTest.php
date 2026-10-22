<?php

namespace Tests\Feature;

use App\Models\Homework;
use App\Models\HomeworkAssignment;
use App\Models\Material;
use App\Models\User;
use App\Services\HomeworkGrader;
use App\Telegram\Support\HomeworkReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Uy ishi: AI o'qiy oladigan formatlar, Gemini so'rovi/javobi va ustozga hisobot (haqiqiy Gemini/Telegramga chiqmasdan). */
class HomeworkTest extends TestCase
{
    use RefreshDatabase;

    private function homework(User $teacher): Homework
    {
        return Homework::query()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Algebra 5',
            'max_score' => 100,
            'task' => ['kind' => 'document', 'file_id' => 'F1', 'name' => 'task.pdf', 'mime' => 'application/pdf', 'size' => 1000, 'text' => null],
        ]);
    }

    /** Gemini javobi shakli. */
    private function gemini(array $result): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($result)]]]]]];
    }

    private function assignment(Homework $hw, array $attrs = []): HomeworkAssignment
    {
        return HomeworkAssignment::query()->create(['homework_id' => $hw->id, 'student_id' => User::factory()->create()->id] + $attrs);
    }

    public function test_readable_formats(): void
    {
        $this->assertTrue(HomeworkGrader::readable(['kind' => 'photo']));
        $this->assertTrue(HomeworkGrader::readable(['kind' => 'text', 'text' => '2x=10']));
        $this->assertFalse(HomeworkGrader::readable(['kind' => 'text', 'text' => '']));
        $this->assertTrue(HomeworkGrader::readable(['kind' => 'document', 'mime' => 'application/pdf']));
        $this->assertTrue(HomeworkGrader::readable(['kind' => 'document', 'mime' => 'application/octet-stream', 'name' => 'javob.TXT']));
        $this->assertFalse(HomeworkGrader::readable(['kind' => 'document', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'name' => 'a.docx']));
        $this->assertFalse(HomeworkGrader::readable(['kind' => 'video']));
        $this->assertFalse(HomeworkGrader::readable(['kind' => 'link', 'text' => 'https://x.uz']));
    }

    public function test_source_is_copied_from_material(): void
    {
        $material = new Material(['type' => 'document', 'title' => 'T', 'telegram_file_id' => 'ABC', 'file_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'file_size' => 5]);

        $this->assertSame(
            ['kind' => 'document', 'file_id' => 'ABC', 'name' => 'a.pdf', 'mime' => 'application/pdf', 'size' => 5, 'text' => null],
            Homework::sourceFrom($material),
        );
    }

    public function test_grader_sends_task_key_and_solution_and_normalizes_result(): void
    {
        config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'gemini-test']);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->gemini([
            'readable' => true, 'score' => 250, 'feedback' => 'Yaxshi', 'mistakes' => ['3-savol: diskriminant', '5-savol'], 'needs_teacher' => false, 'reason' => '',
        ]))]);

        $hw = $this->homework(User::factory()->teacher()->create());
        $hw->note = 'Faqat 1-5 savollar';

        $r = (new HomeworkGrader)->grade(
            $hw,
            [['inline_data' => ['mime_type' => 'application/pdf', 'data' => 'AAA']]],
            [['text' => 'Kalit: 1-a']],
            [['text' => "O'quvchi matni:\n2x=10"]],
        );

        $this->assertSame(100, $r['score'], 'Ball max_score dan oshmaydi');
        $this->assertSame('Yaxshi', $r['feedback']);
        $this->assertSame(['3-savol: diskriminant', '5-savol'], $r['mistakes']);

        Http::assertSent(function (Request $request) {
            $body = json_encode($request->data());

            return $request->hasHeader('x-goog-api-key', 'test-key')
                && str_contains($request->url(), 'gemini-test:generateContent')
                && str_contains($body, 'UY ISHI SHARTI')
                && str_contains($body, 'Faqat 1-5 savollar')
                && str_contains($body, 'KALITI')
                && str_contains($body, 'YECHIMI')
                && str_contains($body, 'AAA');
        });
    }

    public function test_grader_retries_temporary_errors_then_gives_up(): void
    {
        config(['services.gemini.key' => 'test-key']);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'quota']], 429)
            ->push($this->gemini(['readable' => false, 'score' => 0, 'feedback' => '', 'mistakes' => [], 'needs_teacher' => true, 'reason' => 'xira']))]);

        $grader = new HomeworkGrader;
        $grader->backoff = [0];

        $r = $grader->grade($this->homework(User::factory()->teacher()->create()), [], [], [['text' => 'x']]);

        $this->assertFalse($r['readable']);
        $this->assertSame('xira', $r['reason']);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'bad key']], 400)]);

        $this->expectException(\RuntimeException::class);
        $grader->grade($this->homework(User::factory()->teacher()->create()), [], [], [['text' => 'x']]);
    }

    public function test_report_lists_grades_topics_attention_and_non_submitters(): void
    {
        $teacher = User::factory()->teacher()->create();
        $hw = $this->homework($teacher);

        $a = $this->assignment($hw, ['status' => 'checked', 'score' => 90, 'feedback' => 'Zo\'r', 'mistakes' => ['3-savol: diskriminant']]);
        $b = $this->assignment($hw, ['status' => 'checked', 'score' => 60, 'feedback' => 'Qoniqarli', 'mistakes' => ['3-savol: Diskriminant', '5-savol']]);
        $c = $this->assignment($hw, ['status' => 'unreadable', 'needs_teacher' => true, 'reason' => 'Xira rasm']);
        $d = $this->assignment($hw, ['status' => 'pending']);

        $text = implode("\n", HomeworkReport::build($hw, 'Diskriminantni takrorlang'));

        $this->assertStringContainsString('Algebra 5', $text);
        $this->assertStringContainsString('75', $text, "O'rtacha ball");
        $this->assertStringContainsString($c->student->displayName(), $text);
        $this->assertStringContainsString('Xira rasm', $text);
        $this->assertStringContainsString($d->student->displayName(), $text);
        $this->assertStringContainsString('Diskriminantni takrorlang', $text);
        $this->assertSame([['3-savol: diskriminant', 2], ['5-savol', 1]], HomeworkReport::topics(collect([$a, $b])));
    }

    public function test_long_report_is_split_into_telegram_sized_chunks(): void
    {
        $hw = $this->homework(User::factory()->teacher()->create());

        for ($i = 0; $i < 60; $i++) {
            $this->assignment($hw, ['status' => 'checked', 'score' => 50, 'feedback' => str_repeat('x', 100)]);
        }

        $chunks = HomeworkReport::build($hw);

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertLessThan(4096, mb_strlen($chunk));
        }
    }

    public function test_language_keys_resolve(): void
    {
        foreach (['hw.menu', 'hw.new.pick_title', 'hw.btn.check', 'hw.status.checked', 'hw.flash.queued', 'hw.report.avg', 'hw.check.no_key'] as $key) {
            $this->assertNotSame($key, __($key), "Til kaliti topilmadi: {$key}");
        }
    }
}
