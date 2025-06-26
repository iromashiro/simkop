<?php
// app/Domain/Notification/Services/SMSService.php
namespace App\Domain\Notification\Services;

use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SMSService
{
    private string $provider;
    private array $config;

    public function __construct()
    {
        $this->provider = config('notification.sms.default_provider', 'twilio');
        $this->config = config("notification.sms.providers.{$this->provider}");
    }

    public function send(
        string $to,
        string $message,
        ?Notification $notification = null
    ): void {
        $log = null;

        if ($notification) {
            $log = NotificationLog::create([
                'cooperative_id' => $notification->cooperative_id,
                'notification_id' => $notification->id,
                'channel' => 'sms',
                'recipient' => $to,
                'status' => 'pending',
                'retry_count' => 0,
            ]);
        }

        try {
            $response = $this->sendViaTwilio($to, $message);

            if ($log) {
                $log->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'provider_response' => $response,
                    'cost' => $response['cost'] ?? 0,
                ]);
            }

            Log::info('SMS sent successfully', [
                'to' => $to,
                'message_length' => strlen($message),
                'notification_id' => $notification?->id,
                'provider' => $this->provider,
            ]);
        } catch (\Exception $e) {
            if ($log) {
                $log->markAsFailed($e->getMessage());
            }

            Log::error('SMS sending failed', [
                'to' => $to,
                'error' => $e->getMessage(),
                'notification_id' => $notification?->id,
                'provider' => $this->provider,
            ]);

            throw $e;
        }
    }

    private function sendViaTwilio(string $to, string $message): array
    {
        $response = Http::withBasicAuth(
            $this->config['account_sid'],
            $this->config['auth_token']
        )->asForm()->post(
            "https://api.twilio.com/2010-04-01/Accounts/{$this->config['account_sid']}/Messages.json",
            [
                'From' => $this->config['from_number'],
                'To' => $to,
                'Body' => $message,
            ]
        );

        if (!$response->successful()) {
            throw new \Exception('Twilio API error: ' . $response->body());
        }

        return $response->json();
    }

    public function sendBulk(array $recipients, string $message): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

        foreach ($recipients as $recipient) {
            try {
                $this->send($recipient, $message);
                $results['sent']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
