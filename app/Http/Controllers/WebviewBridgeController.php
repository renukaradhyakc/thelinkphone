<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Cache};
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\User;

class WebviewBridgeController extends Controller
{
    public function issue(Request $request)
    {
        $user = $request->user();
        // Hard paywall: refuse here if the subscription is not active.

        $ticket = Str::random(64);
        Cache::put('webview_ticket:'.hash('sha256', $ticket), [
            'user_id'  => $user->id,
            'token_id' => $user->currentAccessToken()->id,
        ], now()->addSeconds(60));

        return response()->json([
            'url' => route('native.bridge', [
                'ticket' => $ticket,
                'to'     => $request->input('to', '/dashboard'),
            ]),
            'expires_in' => 60,
        ]);
    }

    public function consume(Request $request)
    {
        $data = Cache::pull('webview_ticket:'.hash('sha256', (string) $request->query('ticket')));
        abort_unless($data, 403);
        abort_unless(PersonalAccessToken::find($data['token_id']), 403); // token still valid

        Auth::guard('web')->login(User::findOrFail($data['user_id']));
        $request->session()->regenerate();
        $request->session()->put('native_token_id', $data['token_id']);

        $to = (string) $request->query('to', '/dashboard');
        if (!str_starts_with($to, '/') || str_starts_with($to, '//') || str_contains($to, '\\')) {
            $to = '/dashboard';
        }
        return redirect($to);
    }
}