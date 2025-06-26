<?php
// app/Domain/Notification/Events/MemberRegistered.php
namespace App\Domain\Notification\Events;

use App\Domain\Member\Models\Member;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Member $member
    ) {}
}
