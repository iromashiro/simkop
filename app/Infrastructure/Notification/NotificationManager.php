<?php
// app/Infrastructure/Notification/NotificationManager.php
namespace App\Infrastructure\Notification;

use App\Infrastructure\Notification\Channels\DatabaseChannel;
use App\Infrastructure\Notification\Channels\EmailChannel;
use Illuminate\Support\Facades\Log;

class NotificationManager
{
    private array $channels = [];

    public function __construct()
    {
        $this->registerChannels();
    }

    /**
     * Register notification channels
     */
    private function registerChannels(): void
    {
        $this->channels = [
            'database' => new DatabaseChannel(),
            'email' => new EmailChannel(),
        ];
    }

    /**
     * Send notification through multiple channels
     */
    public function send($notifiable, $notification, array $channels = ['database']): array
    {
        $results = [];

        foreach ($channels as $channelName) {
            try {
                $channel = $this->getChannel($channelName);

                if ($channel->isAvailable()) {
                    $channel->send($notifiable, $notification);
                    $results[$channelName] = 'sent';
                } else {
                    $results[$channelName] = 'unavailable';
                }
            } catch (\Exception $e) {
                Log::error("Failed to send notification via {$channelName}: " . $e->getMessage());
                $results[$channelName] = 'failed';
            }
        }

        return $results;
    }

    /**
     * Get notification channel
     */
    public function getChannel(string $name): NotificationChannel
    {
        if (!isset($this->channels[$name])) {
            throw new \InvalidArgumentException("Notification channel '{$name}' not found");
        }

        return $this->channels[$name];
    }

    /**
     * Get available channels
     */
    public function getAvailableChannels(): array
    {
        $available = [];

        foreach ($this->channels as $name => $channel) {
            if ($channel->isAvailable()) {
                $available[$name] = $channel->getConfig();
            }
        }

        return $available;
    }
}
