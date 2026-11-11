<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\RevenueCatEventHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POST /api/revenuecat/webhook
 * RevenueCat sends the Authorization header value you set in its dashboard.
 * A non-2xx answer makes RevenueCat retry, so errors are answered with 500 on purpose.
 */
class RevenueCatWebhookController extends Controller
{
    public function handle(Request $request, RevenueCatEventHandler $handler): JsonResponse
    {
        $secret = (string) config('services.revenuecat.webhook_secret');

        if ($secret === '') {
            Log::error('notifications.revenuecat.secret_missing');

            return response()->json(['success' => false, 'message' => 'Webhook is not configured.'], 503);
        }

        $given = trim((string) $request->header('Authorization'));

        if (! hash_equals($secret, $given) && ! hash_equals('Bearer '.$secret, $given)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $event = $request->input('event');

        if (! is_array($event)) {
            return response()->json(['success' => false, 'message' => 'Invalid payload.'], 400);
        }

        try {
            $result = $handler->handle($event);
        } catch (Throwable $e) {
            Log::error('notifications.revenuecat.failed', [
                'event_id' => $event['id'] ?? null,
                'type' => $event['type'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Could not process the event.'], 500);
        }

        return response()->json(['success' => true, 'result' => $result]);
    }
}