<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Repositories\ScheduleRepository;
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
{
    public function __construct(
        protected ScheduleRepository $scheduleRepository
    ) {}

    /**
     * Get all schedule names.
     */
    public function index(): JsonResponse
    {
        $schedules = $this->scheduleRepository->getScheduleNames();

        $data = collect($schedules)
            ->map(function ($name, $id) {
                return [
                    'id' => $id,
                    'schedule_name' => $name,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get a schedule with all of its slots.
     */
    public function show(int $id): JsonResponse
    {
        $schedule = $this->scheduleRepository->getScheduleWithSlots($id);

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }
}