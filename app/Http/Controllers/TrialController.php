<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TrialService;
use Illuminate\Http\Request;
use DomainException;
use Illuminate\Support\Facades\Log;

class TrialController extends Controller
{
    public function __construct(private TrialService $trialService) {}

    /**
     * Start free trial
     */
    public function start(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        Log::info('Trial start requested', ['email' => $user->email]);

        try {
            $trial = $this->trialService->start($user);

            Log::info('Trial started successfully', ['email' => $user->email]);

            return response()->json([
                'success' => true,
                'message' => 'Trial started successfully',

                // ✅ Aligned flags
                'has_trial' => true,
                'active' => true,
                'expired' => false,
                'eligible_for_trial' => false,

                // Metadata
                'started_at' => $trial->started_at,
                'expires_at' => $trial->expires_at,
                'days_remaining' => now()->diffInDays($trial->expires_at, false),
            ]);

        } catch (DomainException $e) {
            Log::warning('Trial already consumed', ['email' => $user->email]);

            return response()->json([
                'success' => false,
                'code' => 'TRIAL_ALREADY_USED',
                'message' => 'You have already used your free trial'
            ], 422);
        }
    }

    /**
     * Get trial status
     */
    public function status(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $trial = $this->trialService->get($user);

        Log::info('Trial status checked', [
            'email' => $user->email,
            'has_trial' => $trial !== null,
            'active' => $trial ? $trial->isActive() : false
        ]);

        /**
         * Case 1: User NEVER had a trial
         */
        if (! $trial) {
            return response()->json([
                'success' => true,

                'has_trial' => false,
                'active' => false,
                'expired' => false,
                'eligible_for_trial' => true,

                'days_remaining' => 0
            ]);
        }

        /**
         * Case 2: Trial exists (active or expired)
         */
        return response()->json([
            'success' => true,

            // Structural truth
            'has_trial' => true,
            'active' => $trial->isActive(),

            // ✅ Clear, client-safe semantics
            'expired' => ! $trial->isActive(),
            'eligible_for_trial' => ! $trial->isActive() && ! $trial->consumed,

            // Metadata
            'started_at' => $trial->started_at,
            'expires_at' => $trial->expires_at,
            'days_remaining' => $trial->isActive()
                ? now()->diffInDays($trial->expires_at, false)
                : 0,
        ]);
    }
}
