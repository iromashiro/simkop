<?php
// app/Domain/Notification/Services/NotificationService.php
namespace App\Domain\Notification\Services;

use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Notification\DTOs\NotificationDTO;
use App\Domain\Notification\Events\NotificationSent;
use App\Domain\Notification\Events\NotificationFailed;
use App\Domain\Notification\Exceptions\RateLimitExceededException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly SMSService $smsService
    ) {}

    /**
     * Check rate limit for notifications to prevent spam
     */
    private function checkRateLimit(int $userId, string $type): bool
    {
        $cacheKey = "notification_rate_limit:{$userId}:{$type}:" . now()->format('Y-m-d-H');
        $currentCount = Cache::get($cacheKey, 0);

        $limits = [
            'email' => 50,      // 50 emails per hour
            'sms' => 10,        // 10 SMS per hour
            'push' => 100,      // 100 push notifications per hour
            'database' => 200,  // 200 database notifications per hour
        ];

        $limit = $limits[$type] ?? 50;

        if ($currentCount >= $limit) {
            Log::warning('Notification rate limit exceeded', [
                'user_id' => $userId,
                'type' => $type,
                'current_count' => $currentCount,
                'limit' => $limit,
            ]);

            throw new RateLimitExceededException("Rate limit exceeded for {$type} notifications. Limit: {$limit} per hour");
        }

        Cache::put($cacheKey, $currentCount + 1, 3600);
        return true;
    }

    /**
     * Get current rate limit status for user
     */
    public function getRateLimitStatus(int $userId): array
    {
        $status = [];
        $types = ['email', 'sms', 'push', 'database'];

        foreach ($types as $type) {
            $cacheKey = "notification_rate_limit:{$userId}:{$type}:" . now()->format('Y-m-d-H');
            $currentCount = Cache::get($cacheKey, 0);

            $limits = [
                'email' => 50,
                'sms' => 10,
                'push' => 100,
                'database' => 200,
            ];

            $limit = $limits[$type] ?? 50;

            $status[$type] = [
                'current' => $currentCount,
                'limit' => $limit,
                'remaining' => max(0, $limit - $currentCount),
                'percentage_used' => $limit > 0 ? round(($currentCount / $limit) * 100, 2) : 0,
            ];
        }

        return $status;
    }

    /**
     * Reset rate limit for specific user and type (admin function)
     */
    public function resetRateLimit(int $userId, ?string $type = null): void
    {
        if ($type) {
            $cacheKey = "notification_rate_limit:{$userId}:{$type}:" . now()->format('Y-m-d-H');
            Cache::forget($cacheKey);
        } else {
            $types = ['email', 'sms', 'push', 'database'];
            foreach ($types as $notificationType) {
                $cacheKey = "notification_rate_limit:{$userId}:{$notificationType}:" . now()->format('Y-m-d-H');
                Cache::forget($cacheKey);
            }
        }

        Log::info('Notification rate limit reset', [
            'user_id' => $userId,
            'type' => $type ?? 'all',
        ]);
    }

    public function send(NotificationDTO $notificationDTO): Notification
    {
        // ✅ FIXED: Add rate limiting check for each channel
        foreach ($notificationDTO->channels as $channel) {
            $this->checkRateLimit($notificationDTO->userId, $channel);
        }

        $notification = Notification::create([
            'cooperative_id' => $notificationDTO->cooperativeId,
            'user_id' => $notificationDTO->userId,
            'type' => $notificationDTO->type,
            'title' => $notificationDTO->title,
            'message' => $notificationDTO->message,
            'data' => $notificationDTO->data,
            'channels' => $notificationDTO->channels,
            'notifiable_type' => $notificationDTO->notifiableType,
            'notifiable_id' => $notificationDTO->notifiableId,
            'priority' => $notificationDTO->priority,
            'scheduled_at' => $notificationDTO->scheduledAt,
            'expires_at' => $notificationDTO->expiresAt,
        ]);

        if ($notificationDTO->scheduledAt && $notificationDTO->scheduledAt->isFuture()) {
            // Schedule for later
            \App\Jobs\SendScheduledNotificationJob::dispatch($notification)
                ->delay($notificationDTO->scheduledAt);
        } else {
            // Send immediately
            $this->sendNotification($notification);
        }

        return $notification;
    }

    public function sendFromTemplate(
        string $templateName,
        int $cooperativeId,
        int $userId,
        array $data = [],
        ?string $notifiableType = null,
        ?int $notifiableId = null
    ): Notification {
        $template = NotificationTemplate::where('cooperative_id', $cooperativeId)
            ->where('name', $templateName)
            ->where('is_active', true)
            ->firstOrFail();

        $notificationDTO = new NotificationDTO(
            cooperativeId: $cooperativeId,
            userId: $userId,
            type: $template->type,
            title: $template->renderSubject($data),
            message: $template->renderBodyText($data),
            data: $data,
            channels: $template->channels,
            notifiableType: $notifiableType,
            notifiableId: $notifiableId,
            priority: 'normal'
        );

        return $this->send($notificationDTO);
    }

    public function sendNotification(Notification $notification): void
    {
        try {
            foreach ($notification->channels as $channel) {
                switch ($channel) {
                    case 'email':
                        $this->sendEmail($notification);
                        break;
                    case 'sms':
                        $this->sendSMS($notification);
                        break;
                    case 'database':
                        // Already stored in database
                        break;
                    case 'push':
                        $this->sendPushNotification($notification);
                        break;
                }
            }

            $notification->markAsSent();
            Event::dispatch(new NotificationSent($notification));

            Log::info('Notification sent successfully', [
                'notification_id' => $notification->id,
                'type' => $notification->type,
                'channels' => $notification->channels,
                'user_id' => $notification->user_id,
            ]);
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            Event::dispatch(new NotificationFailed($notification, $e->getMessage()));

            Log::error('Notification sending failed', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
                'user_id' => $notification->user_id,
            ]);
        }
    }

    private function sendEmail(Notification $notification): void
    {
        if (!$notification->user->email) {
            throw new \Exception('User has no email address');
        }

        $this->emailService->send(
            to: $notification->user->email,
            subject: $notification->title,
            body: $notification->message,
            data: $notification->data,
            notification: $notification
        );
    }

    private function sendSMS(Notification $notification): void
    {
        if (!$notification->user->phone) {
            throw new \Exception('User has no phone number');
        }

        $this->smsService->send(
            to: $notification->user->phone,
            message: $notification->message,
            notification: $notification
        );
    }

    private function sendPushNotification(Notification $notification): void
    {
        // Implementation for push notifications
        // This would integrate with FCM, APNs, etc.
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return false;
        }

        $notification->markAsRead();
        return true;
    }

    public function getUnreadCount(int $userId, int $cooperativeId): int
    {
        return Notification::where('user_id', $userId)
            ->where('cooperative_id', $cooperativeId)
            ->unread()
            ->count();
    }

    public function getUserNotifications(
        int $userId,
        int $cooperativeId,
        int $limit = 20,
        bool $unreadOnly = false
    ) {
        $query = Notification::where('user_id', $userId)
            ->where('cooperative_id', $cooperativeId)
            ->orderBy('created_at', 'desc');

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->limit($limit)->get();
    }

    /**
     * Bulk send notifications with rate limiting
     */
    public function sendBulkNotifications(array $notifications): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'rate_limited' => 0, 'errors' => []];

        foreach ($notifications as $notificationData) {
            try {
                $dto = NotificationDTO::fromArray($notificationData);
                $this->send($dto);
                $results['sent']++;
            } catch (RateLimitExceededException $e) {
                $results['rate_limited']++;
                $results['errors'][] = [
                    'user_id' => $notificationData['user_id'],
                    'error' => $e->getMessage(),
                    'type' => 'rate_limit',
                ];
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'user_id' => $notificationData['user_id'],
                    'error' => $e->getMessage(),
                    'type' => 'general',
                ];
            }
        }

        return $results;
    }
}
