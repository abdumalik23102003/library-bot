<?php

namespace App\Services;

use App\Models\Homework;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Uy ishini Google Gemini orqali tekshiradi (rasm, PDF va matnni to'g'ridan-to'g'ri o'qiydi).
 * "Parts" - Gemini formatidagi bo'laklar: ['text' => ...] yoki ['inline_data' => ['mime_type' => ..., 'data' => base64]].
 */
class HomeworkGrader
{
    private const MIMES = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif',
        'text/plain', 'text/csv', 'text/markdown', 'text/html',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private const EXTENSIONS = [
        'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'txt' => 'text/plain', 'md' => 'text/markdown', 'csv' => 'text/csv',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** Qayta urinishdan oldingi kutish (soniya). Testlarda nolga tushiriladi. */
    public array $backoff = [8, 20, 45];

    public function enabled(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /** AI o'qiy oladigan manbami (matn, rasm, PDF, oddiy matn fayli). */
    public static function readable(array $source): bool
    {
        $kind = (string) ($source['kind'] ?? '');

        if ($kind === 'text') {
            return filled($source['text'] ?? null);
        }

        if ($kind === 'photo') {
            return true;
        }

        return $kind === 'document' && self::mime($source) !== null;
    }

    /** Gemini uchun mime turi (null = o'qib bo'lmaydi). */
    public static function mime(array $source): ?string
    {
        $mime = strtolower((string) ($source['mime'] ?? ''));

        if ($mime === 'image/jpg') {
            $mime = 'image/jpeg';
        }

        if (in_array($mime, self::MIMES, true)) {
            return $mime;
        }

        $ext = strtolower(pathinfo((string) ($source['name'] ?? ''), PATHINFO_EXTENSION));

        return self::EXTENSIONS[$ext] ?? null;
    }

    /** @return array{readable: bool, score: int, feedback: string, mistakes: array<int, string>, needs_teacher: bool, reason: string} */
    public function grade(Homework $homework, array $taskParts, array $keyParts, array $studentParts): array
    {
        $max = (int) $homework->max_score;

        $parts = [['text' => $this->instruction($max)], ['text' => "=== UY ISHI SHARTI ==="]];
        $parts = array_merge($parts, $taskParts);

        if (filled($homework->note)) {
            $parts[] = ['text' => "Ustozning izohi: " . $homework->note];
        }

        if ($keyParts !== []) {
            $parts[] = ['text' => "=== TO'G'RI JAVOBLAR KALITI (faqat ustoz uchun) ==="];
            $parts = array_merge($parts, $keyParts);
        }

        $parts[] = ['text' => "=== O'QUVCHI YECHIMI ==="];
        $parts = array_merge($parts, $studentParts);

        $data = $this->request($parts, true);

        return [
            'readable' => (bool) ($data['readable'] ?? false),
            'score' => max(0, min($max, (int) ($data['score'] ?? 0))),
            'feedback' => trim((string) ($data['feedback'] ?? '')),
            'mistakes' => collect($data['mistakes'] ?? [])->map(fn ($m) => trim((string) $m))->filter()->take(12)->values()->all(),
            'needs_teacher' => (bool) ($data['needs_teacher'] ?? false),
            'reason' => trim((string) ($data['reason'] ?? '')),
        ];
    }

    /** O'quvchilarning xatolaridan umumiy tahlil (matn). Xato bo'lsa null. */
    public function summarize(Homework $homework, array $mistakeLists): ?string
    {
        if ($mistakeLists === []) {
            return null;
        }

        $lines = collect($mistakeLists)->map(fn (array $list, int $i) => ($i + 1) . ') ' . implode('; ', $list))->implode("\n");

        try {
            $text = $this->requestText(
                "Sen ustoz yordamchisisan. Quyida '{$homework->title}' uy ishini tekshirishda har bir o'quvchida topilgan xatolar ro'yxati (o'quvchi ismlarisiz). "
                . "Sinf umuman qaysi savol/mavzularda ko'proq qiynalganini va ustoz keyingi darsda nimani takrorlashi kerakligini "
                . "o'zbek tilida 4-6 qisqa gapda yoz. Ro'yxat bo'sh joylarini o'ylab topma.\n\n" . $lines,
            );
        } catch (RuntimeException) {
            return null;
        }

        return $text !== '' ? $text : null;
    }

    // ---- Gemini so'rovi -------------------------------------------------------------------------

    private function instruction(int $max): string
    {
        return "Sen tajribali va adolatli ustoz yordamchisisan (AI Co-teacher). Quyida: (1) uy ishi sharti, (2) ba'zan to'g'ri javoblar kaliti, (3) o'quvchining yechimi beriladi.\n"
            . "Vazifang: o'quvchi yechimini shartga ko'ra tekshir va 0 dan {$max} gacha ball qo'y.\n"
            . "Qoidalar:\n"
            . "- Faqat yechimda haqiqatan ko'ringan narsaga tayan, o'zingdan to'qima.\n"
            . "- Yechimni o'qib bo'lmasa (xira rasm, mavzuga aloqasiz fayl, bo'sh) readable=false qil.\n"
            . "- Ishonching komil bo'lmasa yoki baholash qiyin bo'lsa needs_teacher=true qil va reason da sababini yoz.\n"
            . "- feedback: o'quvchiga mos 2-3 gap, o'zbek tilida, mehribon va aniq.\n"
            . "- mistakes: qaysi savol/mavzularda xato qilgani, har biri qisqa (masalan \"3-savol: kvadrat tenglama diskriminanti\"). Xato bo'lmasa bo'sh massiv.\n"
            . "Javobni faqat JSON ko'rinishida qaytar.";
    }

    /** @return array<string, mixed> */
    private function request(array $parts, bool $json): array
    {
        $config = ['temperature' => 0.2];

        if ($json) {
            $config['responseMimeType'] = 'application/json';
            $config['responseSchema'] = [
                'type' => 'OBJECT',
                'properties' => [
                    'readable' => ['type' => 'BOOLEAN'],
                    'score' => ['type' => 'INTEGER'],
                    'feedback' => ['type' => 'STRING'],
                    'mistakes' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    'needs_teacher' => ['type' => 'BOOLEAN'],
                    'reason' => ['type' => 'STRING'],
                ],
                'required' => ['readable', 'score', 'feedback', 'mistakes', 'needs_teacher', 'reason'],
            ];
        }

        $text = $this->call(['contents' => [['role' => 'user', 'parts' => $parts]], 'generationConfig' => $config]);
        $data = json_decode($text, true);

        if (! is_array($data)) {
            throw new RuntimeException('AI javobi JSON emas');
        }

        return $data;
    }

    private function requestText(string $prompt): string
    {
        return trim($this->call([
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.3],
        ]));
    }

    private function call(array $body): string
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . config('services.gemini.model', 'gemini-3.8-flash') . ':generateContent';
        $last = 'noma\'lum xato';

        for ($attempt = 0; $attempt <= count($this->backoff); $attempt++) {
            $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                ->timeout(150)
                ->post($url, $body);

            if ($response->successful()) {
                $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');

                if ($text === '') {
                    throw new RuntimeException('AI bo\'sh javob qaytardi (' . (string) data_get($response->json(), 'candidates.0.finishReason', 'sabab noma\'lum') . ')');
                }

                return $text;
            }

            $last = 'HTTP ' . $response->status() . ': ' . mb_substr((string) data_get($response->json(), 'error.message', $response->body()), 0, 200);

            // Vaqtinchalik xatolar (limit, band server) - kutib qayta uriniladi.
            if (! in_array($response->status(), [429, 500, 502, 503, 504], true) || ! isset($this->backoff[$attempt])) {
                break;
            }

            sleep($this->backoff[$attempt]);
        }

        throw new RuntimeException($last);
    }
}
