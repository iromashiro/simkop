<?php
// app/Domain/Notification/Events/NotificationSent.php
namespace App\Domain\Notification\Events;

use App\Domain\Notification\Models\Notification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Notification $notification
    ) {}
}
