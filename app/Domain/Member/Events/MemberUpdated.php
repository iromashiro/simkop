<?php

namespace App\Domain\Member\Events;

use App\Domain\Member\Models\Member;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Member Updated Event
 *
 * Dispatched when member information is updated
 * Triggers audit logging and related updates
 *
 * @package App\Domain\Member\Events
 * @author Mateen (Senior Software Engineer)
 */
class MemberUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Member $member,
        public readonly array $originalData,
        public readonly array $updatedData,
        public readonly int $updatedBy
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
     * Get changed fields
     */
    public function getChangedFields(): array
    {
        return array_keys($this->updatedData);
    }
}
