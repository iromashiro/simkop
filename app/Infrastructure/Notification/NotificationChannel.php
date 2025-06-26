<?php
// app/Infrastructure/Notification/NotificationChannel.php
namespace App\Infrastructure\Notification;

use Illuminate\Notifications\Notification;

abstract class NotificationChannel
{
    /**
     * Send the given notification.
     */
    abstract public function send($notifiable, Notification $notification): void;

    /**
     * Check if channel is available
     */
    abstract public function isAvailable(): bool;

    /**
     * Get channel configuration
     */
    abstract public function getConfig(): array;
}
