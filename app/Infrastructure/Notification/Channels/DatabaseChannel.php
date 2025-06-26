<?php
// app/Infrastructure/Notification/Channels/DatabaseChannel.php
namespace App\Infrastructure\Notification\Channels;

use App\Infrastructure\Notification\NotificationChannel;
use Illuminate\Notifications\Notification;
use App\Domain\Notification\Models\Notification as NotificationModel;

class DatabaseChannel extends NotificationChannel
{
    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification): void
    {
        $data = $notification->toDatabase($notifiable);

        NotificationModel::create([
            'cooperative_id' => $notifiable->cooperative_id,
            'user_id' => $notifiable->id,
            'type' => get_class($notification),
            'title' => $data['title'] ?? 'Notification',
            'message' => $data['message'],
            'data' => $data['data'] ?? [],
            'channels' => ['database'],
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Check if channel is available
     */
    public function isAvailable(): bool
    {
        return true; // Database channel is always available
    }

    /**
     * Get channel configuration
     */
    public function getConfig(): array
    {
        return [
            'name' => 'Database',
            'enabled' => true,
            'priority' => 1,
        ];
    }
}
