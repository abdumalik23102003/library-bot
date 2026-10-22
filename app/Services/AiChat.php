<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Oddiy AI suhbat (Gemini). Har bir foydalanuvchi uchun oxirgi xabarlar tarixi Cache da saqlanadi. */
final class AiChat
{
    private const MAX_ITEMS = 12;

    public static function forget(User $user): void
    {
        Cache::forget("ai:hist:{$user->id}");
        Cache::forget("ai:last:{$user->id}");
    }

    public function enabled(): bool
    {
        return filled(config('services.gemini.key'));
    }

    public function reply(User $user, string $text): string
    {
        $key = "ai:hist:{$user->id}";
        $history = Cache::get($key, []);
        $history[] = ['role' => 'user', 'text' => $text];

        $system = "Sen Telegram kutubxona botidagi AI yordamchisan. Foydalanuvchi roli: {$user->role->value}. "
            . "Foydalanuvchi qaysi tilda yozsa, shu tilda javob ber (odatda o'zbekcha). "
            . "Qisqa va aniq yoz, 1500 belgidan oshirma. Oddiy matn yoz, markdown belgilarini (*, #, `) ishlatma. "
            . "Bilmasang, o'ylab topma. Boshqa foydalanuvchilarning shaxsiy ma'lumotlarini bilmaysan.";

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . config('services.gemini.model', 'gemini-3.8-flash') . ':generateContent';

        $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
            ->timeout(60)
            ->post($url, [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => array_map(fn ($h) => ['role' => $h['role'], 'parts' => [['text' => $h['text']]]], $history),
                'generationConfig' => ['temperature' => 0.5],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('HTTP ' . $response->status() . ': ' . mb_substr((string) data_get($response->json(), 'error.message', $response->body()), 0, 160));
        }

        $answer = trim(collect(data_get($response->json(), 'candidates.0.content.parts', []))->pluck('text')->filter()->implode("\n"));

        if ($answer === '') {
            throw new RuntimeException('AI bo\'sh javob qaytardi');
        }

        $history[] = ['role' => 'model', 'text' => $answer];
        Cache::put($key, array_slice($history, -self::MAX_ITEMS), now()->addHours(6));

        return $answer;
    }
}
