<?php
// SavingsAccountCreated.php
namespace App\Domain\Savings\Events;

use App\Domain\Savings\Models\SavingsAccount;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SavingsAccountCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SavingsAccount $savingsAccount,
        public readonly int $createdBy
    ) {}
}

// SavingsTransactionProcessed.php
namespace App\Domain\Savings\Events;

use App\Domain\Savings\Models\SavingsTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SavingsTransactionProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SavingsTransaction $transaction,
        public readonly int $processedBy
    ) {}
}
