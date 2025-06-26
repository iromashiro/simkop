<?php
// app/Domain/Notification/Events/NotificationFailed.php
namespace App\Domain\Notification\Events;

use App\Domain\Notification\Models\Notification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Notification $notification,
        public readonly string $reason
    ) {}
}
