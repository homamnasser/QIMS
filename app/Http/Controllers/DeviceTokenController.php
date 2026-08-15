<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $account = $request->user();

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'tokenable_type' => $account::class,
                'tokenable_id' => $account->getKey(),
                'device_name' => $data['device_name'] ?? null,
            ],
        );

        return response()->json([
            'code' => 200,
            'message' => 'تم تسجيل الجهاز.',
            'data' => null,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        // مقيّد بصاحب الرمز: بغيره يصير المسار وسيلة لإسكات إشعارات أي جهاز
        // لمن يعرف رمزه فقط.
        $account = $request->user();

        DeviceToken::query()
            ->where('token', $data['token'])
            ->where('tokenable_type', $account::class)
            ->where('tokenable_id', $account->getKey())
            ->delete();

        return response()->json([
            'code' => 200,
            'message' => 'تم إلغاء تسجيل الجهاز.',
            'data' => null,
        ]);
    }
}
