<?php
// app/Infrastructure/Notification/Channels/EmailChannel.php
namespace App\Infrastructure\Notification\Channels;

use App\Infrastructure\Notification\NotificationChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

class EmailChannel extends NotificationChannel
{
    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification): void
    {
        if (!$notifiable->email) {
            throw new \Exception('User does not have an email address');
        }

        $mailData = $notification->toMail($notifiable);

        Mail::to($notifiable->email)->send($mailData);
    }

    /**
     * Check if channel is available
     */
    public function isAvailable(): bool
    {
        return config('mail.default') !== null;
    }

    /**
     * Get channel configuration
     */
    public function getConfig(): array
    {
        return [
            'name' => 'Email',
            'enabled' => config('mail.default') !== null,
            'priority' => 2,
        ];
    }
}
