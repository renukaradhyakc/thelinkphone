<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Repositories\PreferenceRepository;
use App\Modules\Notifications\Services\NotificationRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    public function __construct(
        protected PreferenceRepository $preferences,
        protected NotificationRegistry $registry
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->list($request->user()->id),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => 'required|array|min:1',
            'preferences.*.category' => ['required', 'string', Rule::in(array_keys($this->registry->categories()))],
            'preferences.*.push_enabled' => 'required|boolean',
        ]);

        $userId = $request->user()->id;

        foreach ($validated['preferences'] as $item) {
            $this->preferences->set($userId, $item['category'], (bool) $item['push_enabled']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated.',
            'data' => $this->list($userId),
        ]);
    }

    private function list(int $userId): array
    {
        $saved = $this->preferences->forUser($userId);

        return collect($this->registry->categories())
            ->map(fn ($config, $category) => [
                'category' => $category,
                'push_enabled' => $saved[$category] ?? $this->registry->categoryDefaultEnabled($category),
            ])
            ->values()
            ->all();
    }
}