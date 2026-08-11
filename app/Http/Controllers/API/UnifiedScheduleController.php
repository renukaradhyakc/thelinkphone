<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UnifiedScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnifiedScheduleController extends Controller
{
    public function __construct(protected UnifiedScheduleService $service)
    {
    }

    public function index(): JsonResponse
    {

        $data = $this->service->getUnifiedSchedules();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}