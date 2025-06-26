<?php
// app/Domain/Notification/Events/TransactionCreated.php
namespace App\Domain\Notification\Events;

use App\Domain\Financial\Models\JournalEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly JournalEntry $transaction
    ) {}
}
