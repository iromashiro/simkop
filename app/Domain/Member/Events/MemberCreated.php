<?php

namespace App\Domain\Member\Events;

use App\Domain\Member\Models\Member;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Member Created Event
 *
 * Dispatched when a new member is successfully created
 * Triggers related business processes like account setup
 *
 * @package App\Domain\Member\Events
 * @author Mateen (Senior Software Engineer)
 */
class MemberCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Member $member,
        public readonly int $createdBy,
        public readonly array $metadata = []
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
     * Get event data for broadcasting
     */
    public function broadcastWith(): array
    {
        return [
            'member_id' => $this->member->id,
            'member_number' => $this->member->member_number,
            'member_name' => $this->member->name,
            'cooperative_id' => $this->member->cooperative_id,
            'created_by' => $this->createdBy,
            'created_at' => $this->member->created_at->toISOString(),
        ];
    }
}
