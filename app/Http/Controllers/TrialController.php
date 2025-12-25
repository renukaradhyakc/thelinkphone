<?php

namespace App\Http\Controllers;

use App\Services\TrialService;
use Illuminate\Http\Request;
use DomainException;

class TrialController extends Controller
{
    public function __construct(private TrialService $trialService) {}

    public function start(Request $request)
    {
        try {
            $trial = $this->trialService->start($request->user());

            return response()->json([
                'message' => 'Trial started successfully',
                'trial' => [
                    'started_at' => $trial->started_at,
                    'expires_at' => $trial->expires_at,
                    'days_remaining' => now()->diffInDays($trial->expires_at, false)
                ]
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'code' => $e->getMessage(),
                'message' => 'You have already used your free trial'
            ], 422);
        }
    }

    public function status(Request $request)
    {
        $user = $request->user();
        $trial = $this->trialService->get($user);

        if (!$trial) {
            return response()->json([
                'has_trial' => false,
                'consumed' => false,
                'active' => false
            ]);
        }

        return response()->json([
            'has_trial' => true,
            'consumed' => $trial->consumed,
            'active' => $trial->isActive(),
            'started_at' => $trial->started_at,
            'expires_at' => $trial->expires_at,
            'days_remaining' => $trial->isActive() ? now()->diffInDays($trial->expires_at, false) : 0
        ]);
    }
}
