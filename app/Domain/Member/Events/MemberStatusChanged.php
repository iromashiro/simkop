<?php

namespace App\Domain\Member\Events;

use App\Domain\Member\Models\Member;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Member Status Changed Event
 *
 * Dispatched when member status changes (active, suspended, terminated)
 * Triggers business processes like account freezing/unfreezing
 *
 * @package App\Domain\Member\Events
 * @author Mateen (Senior Software Engineer)
 */
class MemberStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Member $member,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly int $changedBy,
        public readonly ?string $reason = null
    ) {}

    /**
     * Get the channels the event should broadcast on
     */
    public function broadcastOn(): array
    {
        return [
            new \Illuminate\Broadcasting\PrivateChannel("cooperative.{$this->member->cooperative_id}"),
        ];
    }

    /**
     * Check if status change requires account action
     */
    public function requiresAccountAction(): bool
    {
        return in_array($this->newStatus, ['suspended', 'terminated']);
    }
}
