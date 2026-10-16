<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Models\DeviceToken;
use App\Modules\Notifications\Services\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function __construct(protected DeviceService $devices)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', Rule::in([DeviceToken::ANDROID, DeviceToken::IOS, DeviceToken::WEB])],
            'token' => 'required|string|max:4096',
            'device_id' => 'nullable|string|max:191',
            'app_version' => 'nullable|string|max:50',
            'web_keys' => 'nullable|array',
            'web_keys.p256dh' => 'required_with:web_keys|string',
            'web_keys.auth' => 'required_with:web_keys|string',
        ]);

        $device = $this->devices->register(
            $request->user()->id,
            $validated['platform'],
            $validated['token'],
            [
                'device_id' => $validated['device_id'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'web_keys' => $validated['web_keys'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Device registered.',
            'data' => ['id' => $device->id, 'platform' => $device->platform],
        ]);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $revoked = $this->devices->unregister($request->user()->id, $token);

        return response()->json([
            'success' => $revoked,
            'message' => $revoked ? 'Device removed.' : 'Device not found.',
        ]);
    }
}