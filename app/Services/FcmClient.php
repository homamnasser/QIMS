<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * عميل FCM HTTP v1. مفتاح الخادم القديم أُوقف في 2024، وواجهة v1 تطلب رمز
 * OAuth2 موقّعًا من حساب الخدمة — وهو ما يوقّعه openssl المدمج في PHP، بلا حزمة.
 */
class FcmClient
{
    private ?array $credentials = null;

    /**
     * يُرجع false فقط إذا رفض FCM الرمز نهائيًا (حُذف التطبيق أو تلف الرمز)،
     * فيحذفه المستدعي. الأخطاء العابرة تُرجع true حتى لا نفقد رمزًا صالحًا.
     */
    public function send(
        string $deviceToken,
        string $title,
        string $body,
        array $data = [],
        array $androidNotification = [],
    ): bool {
        $project = $this->credentials()['project_id'];

        $response = Http::withToken($this->accessToken())
            ->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => ['title' => $title, 'body' => $body],
                    // FCM يقبل نصوصًا فقط في data؛ أي عدد يجب تحويله.
                    'data' => array_map(strval(...), $data),
                    'android' => array_filter([
                        'priority' => 'high',
                        // ما يرسمه النظام حين يكون التطبيق في الخلفية أو مغلقاً.
                        'notification' => $androidNotification,
                    ]),
                ],
            ]);

        if ($response->successful()) {
            return true;
        }

        // 404 UNREGISTERED و 400 INVALID_ARGUMENT: الرمز ميت، لا فائدة من إبقائه.
        if (in_array($response->status(), [400, 404], true)) {
            return false;
        }

        Log::warning('FCM send failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return true;
    }

    /**
     * يُقرأ عند أول إرسال لا في الباني: الباني يعمل مع كل حاوية تُحَل حتى في
     * بيئة بلا مفتاح (اختبارات، أوامر artisan)، فالتحميل الكسول يمنع انفجارها.
     */
    private function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = config('services.fcm.credentials');

        if (! is_string($path) || ! is_readable($path)) {
            throw new RuntimeException("ملف اعتماد FCM غير موجود أو غير مقروء: {$path}");
        }

        return $this->credentials = json_decode(
            file_get_contents($path),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /** رمز الوصول صالح ساعة؛ نخزّنه 55 دقيقة فنوقّع JWT مرة كل ساعة لا كل إشعار. */
    private function accessToken(): string
    {
        return Cache::remember('fcm.access_token', now()->addMinutes(55), function (): string {
            $credentials = $this->credentials();
            $now = time();

            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];

            openssl_sign(
                implode('.', $segments),
                $signature,
                $credentials['private_key'],
                OPENSSL_ALGO_SHA256,
            );
            $segments[] = $this->base64Url($signature);

            return Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw()->json('access_token');
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
