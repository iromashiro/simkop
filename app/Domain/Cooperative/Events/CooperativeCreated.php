<?php
// CooperativeCreated.php
namespace App\Domain\Cooperative\Events;

use App\Domain\Cooperative\Models\Cooperative;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CooperativeCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Cooperative $cooperative,
        public readonly int $createdBy
    ) {}

    public function broadcastOn(): array
    {
        return [
            new \Illuminate\Broadcasting\PrivateChannel('admin'),
        ];
    }
}

// CooperativeUpdated.php
namespace App\Domain\Cooperative\Events;

use App\Domain\Cooperative\Models\Cooperative;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CooperativeUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Cooperative $cooperative,
        public readonly array $originalData,
        public readonly array $updatedData,
        public readonly int $updatedBy
    ) {}
}
