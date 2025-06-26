<?php
// app/Domain/Notification/Services/EmailService.php
namespace App\Domain\Notification\Services;

use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    public function send(
        string $to,
        string $subject,
        string $body,
        array $data = [],
        ?Notification $notification = null
    ): void {
        $log = null;

        if ($notification) {
            $log = NotificationLog::create([
                'cooperative_id' => $notification->cooperative_id,
                'notification_id' => $notification->id,
                'channel' => 'email',
                'recipient' => $to,
                'status' => 'pending',
                'retry_count' => 0,
            ]);
        }

        try {
            Mail::send([], [], function ($message) use ($to, $subject, $body) {
                $message->to($to)
                    ->subject($subject)
                    ->html($body);
            });

            if ($log) {
                $log->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }

            Log::info('Email sent successfully', [
                'to' => $to,
                'subject' => $subject,
                'notification_id' => $notification?->id,
            ]);
        } catch (\Exception $e) {
            if ($log) {
                $log->markAsFailed($e->getMessage());
            }

            Log::error('Email sending failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'notification_id' => $notification?->id,
            ]);

            throw $e;
        }
    }

    public function sendBulk(array $recipients, string $subject, string $body): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

        foreach ($recipients as $recipient) {
            try {
                $this->send($recipient, $subject, $body);
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
