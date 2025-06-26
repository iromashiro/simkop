<?php
// app/Http/Controllers/API/V1/NotificationController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Domain\Notification\Services\NotificationService;
use App\Domain\Notification\DTOs\NotificationDTO;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Http\Requests\API\V1\Notification\SendNotificationRequest;
use App\Http\Requests\API\V1\Notification\CreateTemplateRequest;
use App\Http\Requests\API\V1\Notification\UpdateTemplateRequest;
use App\Http\Resources\API\V1\NotificationResource;
use App\Http\Resources\API\V1\NotificationTemplateResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @group Notifications
 *
 * APIs for managing notifications and templates
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant.scope');
    }

    /**
     * Get user notifications
     *
     * @authenticated
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Number of items per page (max 50). Example: 20
     * @queryParam unread_only boolean Filter only unread notifications. Example: true
     * @queryParam type string Filter by notification type. Example: loan_approval
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'integer|min:1|max:50',
            'unread_only' => 'boolean',
            'type' => 'string|max:50',
        ]);

        $user = Auth::user();
        $perPage = $request->get('per_page', 20);
        $unreadOnly = $request->boolean('unread_only');
        $type = $request->get('type');

        $query = Notification::where('user_id', $user->id)
            ->where('cooperative_id', $user->cooperative_id)
            ->with(['user', 'notifiable'])
            ->orderBy('created_at', 'desc');

        if ($unreadOnly) {
            $query->unread();
        }

        if ($type) {
            $query->where('type', $type);
        }

        $notifications = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => NotificationResource::collection($notifications->items()),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $this->notificationService->getUnreadCount($user->id, $user->cooperative_id),
            ],
        ]);
    }

    /**
     * Send notification
     *
     * @authenticated
     * @bodyParam user_id integer required Target user ID. Example: 123
     * @bodyParam type string required Notification type. Example: loan_approval
     * @bodyParam title string required Notification title. Example: Loan Approved
     * @bodyParam message string required Notification message. Example: Your loan has been approved
     * @bodyParam channels array required Delivery channels. Example: ["email", "database"]
     * @bodyParam data object Additional notification data. Example: {"loan_id": 456}
     * @bodyParam priority string Notification priority. Example: high
     * @bodyParam scheduled_at string Schedule notification for later. Example: 2024-01-15 10:00:00
     */
    public function store(SendNotificationRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $notificationDTO = new NotificationDTO(
                cooperativeId: $user->cooperative_id,
                userId: $request->user_id,
                type: $request->type,
                title: $request->title,
                message: $request->message,
                data: $request->data ?? [],
                channels: $request->channels,
                notifiableType: $request->notifiable_type,
                notifiableId: $request->notifiable_id,
                priority: $request->priority ?? 'normal',
                scheduledAt: $request->scheduled_at ? \Carbon\Carbon::parse($request->scheduled_at) : null,
                expiresAt: $request->expires_at ? \Carbon\Carbon::parse($request->expires_at) : null
            );

            $notification = $this->notificationService->send($notificationDTO);

            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully',
                'data' => new NotificationResource($notification),
            ], 201);
        } catch (\App\Domain\Notification\Exceptions\RateLimitExceededException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded',
                'error' => $e->getMessage(),
            ], 429);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark notification as read
     *
     * @authenticated
     * @urlParam id integer required Notification ID. Example: 123
     */
    public function markAsRead(int $id): JsonResponse
    {
        $user = Auth::user();

        $success = $this->notificationService->markAsRead($id, $user->id);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found or already read',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read
     *
     * @authenticated
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();

        $count = Notification::where('user_id', $user->id)
            ->where('cooperative_id', $user->cooperative_id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "Marked {$count} notifications as read",
            'data' => ['marked_count' => $count],
        ]);
    }

    /**
     * Get notification statistics
     *
     * @authenticated
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        $stats = [
            'total' => Notification::where('user_id', $user->id)
                ->where('cooperative_id', $user->cooperative_id)
                ->count(),
            'unread' => $this->notificationService->getUnreadCount($user->id, $user->cooperative_id),
            'by_type' => Notification::where('user_id', $user->id)
                ->where('cooperative_id', $user->cooperative_id)
                ->groupBy('type')
                ->selectRaw('type, count(*) as count')
                ->pluck('count', 'type'),
            'by_channel' => Notification::where('user_id', $user->id)
                ->where('cooperative_id', $user->cooperative_id)
                ->selectRaw('channels, count(*) as count')
                ->groupBy('channels')
                ->get()
                ->flatMap(function ($item) {
                    $channels = json_decode($item->channels, true);
                    return collect($channels)->mapWithKeys(fn($channel) => [$channel => $item->count]);
                })
                ->groupBy(fn($count, $channel) => $channel)
                ->map(fn($counts) => $counts->sum()),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get rate limit status
     *
     * @authenticated
     */
    public function rateLimitStatus(): JsonResponse
    {
        $user = Auth::user();

        $status = $this->notificationService->getRateLimitStatus($user->id);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Send bulk notifications
     *
     * @authenticated
     * @bodyParam notifications array required Array of notification objects
     */
    public function sendBulk(Request $request): JsonResponse
    {
        $request->validate([
            'notifications' => 'required|array|min:1|max:100',
            'notifications.*.user_id' => 'required|integer|exists:users,id',
            'notifications.*.type' => 'required|string|max:50',
            'notifications.*.title' => 'required|string|max:255',
            'notifications.*.message' => 'required|string',
            'notifications.*.channels' => 'required|array|min:1',
            'notifications.*.channels.*' => 'string|in:email,sms,database,push',
        ]);

        $user = Auth::user();

        // Add cooperative_id to each notification
        $notifications = collect($request->notifications)->map(function ($notification) use ($user) {
            $notification['cooperative_id'] = $user->cooperative_id;
            return $notification;
        })->toArray();

        $results = $this->notificationService->sendBulkNotifications($notifications);

        return response()->json([
            'success' => true,
            'message' => 'Bulk notifications processed',
            'data' => $results,
        ]);
    }

    // Template Management APIs

    /**
     * Get notification templates
     *
     * @authenticated
     */
    public function templates(): JsonResponse
    {
        $user = Auth::user();

        $templates = NotificationTemplate::where('cooperative_id', $user->cooperative_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => NotificationTemplateResource::collection($templates),
        ]);
    }

    /**
     * Create notification template
     *
     * @authenticated
     */
    public function createTemplate(CreateTemplateRequest $request): JsonResponse
    {
        $user = Auth::user();

        $template = NotificationTemplate::create([
            'cooperative_id' => $user->cooperative_id,
            'name' => $request->name,
            'type' => $request->type,
            'subject' => $request->subject,
            'body_html' => $request->body_html,
            'body_text' => $request->body_text,
            'sms_template' => $request->sms_template,
            'variables' => $request->variables ?? [],
            'channels' => $request->channels,
            'is_active' => $request->is_active ?? true,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully',
            'data' => new NotificationTemplateResource($template),
        ], 201);
    }

    /**
     * Update notification template
     *
     * @authenticated
     */
    public function updateTemplate(int $id, UpdateTemplateRequest $request): JsonResponse
    {
        $user = Auth::user();

        $template = NotificationTemplate::where('cooperative_id', $user->cooperative_id)
            ->findOrFail($id);

        $template->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully',
            'data' => new NotificationTemplateResource($template),
        ]);
    }

    /**
     * Test notification template
     *
     * @authenticated
     */
    public function testTemplate(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'test_data' => 'array',
        ]);

        $user = Auth::user();

        $template = NotificationTemplate::where('cooperative_id', $user->cooperative_id)
            ->findOrFail($id);

        $testResult = $template->testTemplate($request->test_data ?? []);

        return response()->json([
            'success' => $testResult['success'],
            'data' => $testResult,
        ]);
    }
}
