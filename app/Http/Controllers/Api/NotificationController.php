<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:notifications');
    }

    /**
     * Get user notifications with pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 10), 50); // Max 50 notifications
            $unreadOnly = $request->boolean('unread_only', false);

            $query = Notification::where('user_id', auth()->id())
                ->with('cooperative:id,name')
                ->orderBy('created_at', 'desc');

            if ($unreadOnly) {
                $query->unread();
            }

            $notifications = $query->limit($limit)->get();

            return response()->json([
                'data' => $notifications->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'is_read' => $notification->is_read,
                        'created_at' => $notification->created_at->diffForHumans(),
                        'created_at_iso' => $notification->created_at->toISOString(),
                        'icon_class' => $notification->getIconClass(),
                        'cooperative' => $notification->cooperative ? [
                            'id' => $notification->cooperative->id,
                            'name' => $notification->cooperative->name,
                        ] : null,
                        'data' => $notification->data,
                    ];
                }),
                'meta' => [
                    'total' => $notifications->count(),
                    'unread_count' => $this->notificationService->getUnreadCount(auth()->id()),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching notifications', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Gagal memuat notifikasi'
            ], 500);
        }
    }

    /**
     * Get unread notifications count
     */
    public function count(): JsonResponse
    {
        try {
            $count = $this->notificationService->getUnreadCount(auth()->id());

            return response()->json([
                'count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting notification count', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Gagal memuat jumlah notifikasi'
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        try {
            $notification = Notification::where('id', $id)
                ->where('user_id', auth()->id())
                ->first();

            if (!$notification) {
                return response()->json([
                    'error' => 'Notifikasi tidak ditemukan'
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi berhasil ditandai sebagai dibaca'
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking notification as read', [
                'user_id' => auth()->id(),
                'notification_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Gagal menandai notifikasi sebagai dibaca'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $count = $this->notificationService->markAllAsRead(auth()->id());

            return response()->json([
                'success' => true,
                'message' => "Berhasil menandai {$count} notifikasi sebagai dibaca",
                'marked_count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Gagal menandai semua notifikasi sebagai dibaca'
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->notificationService->deleteNotification($id, auth()->id());

            if (!$deleted) {
                return response()->json([
                    'error' => 'Notifikasi tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting notification', [
                'user_id' => auth()->id(),
                'notification_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Gagal menghapus notifikasi'
            ], 500);
        }
    }
}
