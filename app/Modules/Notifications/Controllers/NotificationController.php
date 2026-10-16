<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Notifications\Repositories\UserNotificationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(protected UserNotificationRepository $inbox)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cursor' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:50',
            'unread' => 'nullable|boolean',
        ]);

        $limit = (int) ($validated['limit'] ?? 20);
        $beforeId = $this->decodeCursor($validated['cursor'] ?? null);

        $rows = $this->inbox->page(
            $request->user()->id,
            $beforeId,
            $limit,
            (bool) ($validated['unread'] ?? false)
        );

        $hasMore = $rows->count() > $limit;
        $page = $rows->take($limit)->values();

        return response()->json([
            'success' => true,
            'data' => $page->map(fn (UserNotification $n) => $this->present($n))->all(),
            'next_cursor' => $hasMore ? $this->encodeCursor($page->last()->id) : null,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['count' => $this->inbox->unreadCount($request->user()->id)],
        ]);
    }

    public function read(Request $request, string $uuid): JsonResponse
    {
        $userId = $request->user()->id;

        if ($this->inbox->findForUser($userId, $uuid) === null) {
            return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
        }

        $this->inbox->markRead($userId, $uuid); // already-read is fine: idempotent

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $this->inbox->unreadCount($userId)],
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $updated = $this->inbox->markAllRead($userId);

        return response()->json([
            'success' => true,
            'data' => ['updated' => $updated, 'unread_count' => 0],
        ]);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $deleted = $this->inbox->delete($request->user()->id, $uuid);

        return response()->json([
            'success' => $deleted,
            'message' => $deleted ? 'Notification deleted.' : 'Notification not found.',
        ], $deleted ? 200 : 404);
    }

    private function present(UserNotification $n): array
    {
        return [
            'id' => $n->uuid,
            'type' => $n->type,
            'reason' => $n->reason,
            'category' => $n->category,
            'title' => $n->title,
            'body' => $n->body,
            'deep_link' => $n->deep_link,
            'data' => $n->data ?? (object) [],
            'is_read' => $n->read_at !== null,
            'read_at' => $n->read_at?->copy()->utc()->toIso8601String(),
            'created_at' => $n->created_at?->copy()->utc()->toIso8601String(),
            'expires_at' => $n->expires_at?->copy()->utc()->toIso8601String(),
        ];
    }

    private function encodeCursor(int $id): string
    {
        return base64_encode((string) $id);
    }

    private function decodeCursor(?string $cursor): ?int
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $id = base64_decode($cursor, true);

        return ($id !== false && ctype_digit($id)) ? (int) $id : null;
    }
}