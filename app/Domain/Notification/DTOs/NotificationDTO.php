<?php
// app/Domain/Notification/DTOs/NotificationDTO.php
namespace App\Domain\Notification\DTOs;

use Carbon\Carbon;

class NotificationDTO
{
    public function __construct(
        public readonly int $cooperativeId,
        public readonly int $userId,
        public readonly string $type,
        public readonly string $title,
        public readonly string $message,
        public readonly array $data = [],
        public readonly array $channels = ['database'],
        public readonly ?string $notifiableType = null,
        public readonly ?int $notifiableId = null,
        public readonly string $priority = 'normal',
        public readonly ?Carbon $scheduledAt = null,
        public readonly ?Carbon $expiresAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            cooperativeId: $data['cooperative_id'],
            userId: $data['user_id'],
            type: $data['type'],
            title: $data['title'],
            message: $data['message'],
            data: $data['data'] ?? [],
            channels: $data['channels'] ?? ['database'],
            notifiableType: $data['notifiable_type'] ?? null,
            notifiableId: $data['notifiable_id'] ?? null,
            priority: $data['priority'] ?? 'normal',
            scheduledAt: isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null,
            expiresAt: isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'cooperative_id' => $this->cooperativeId,
            'user_id' => $this->userId,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'channels' => $this->channels,
            'notifiable_type' => $this->notifiableType,
            'notifiable_id' => $this->notifiableId,
            'priority' => $this->priority,
            'scheduled_at' => $this->scheduledAt?->toISOString(),
            'expires_at' => $this->expiresAt?->toISOString(),
        ];
    }
}
