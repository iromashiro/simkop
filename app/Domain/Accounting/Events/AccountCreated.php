<?php
// AccountCreated.php
namespace App\Domain\Accounting\Events;

use App\Domain\Accounting\Models\Account;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccountCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Account $account,
        public readonly int $createdBy
    ) {}
}

// JournalEntryCreated.php
namespace App\Domain\Accounting\Events;

use App\Domain\Accounting\Models\JournalEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JournalEntryCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly JournalEntry $journalEntry,
        public readonly int $createdBy
    ) {}
}

// FiscalPeriodCreated.php
namespace App\Domain\Accounting\Events;

use App\Domain\Accounting\Models\FiscalPeriod;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FiscalPeriodCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly FiscalPeriod $fiscalPeriod,
        public readonly int $createdBy
    ) {}
}

// FiscalPeriodClosed.php
namespace App\Domain\Accounting\Events;

use App\Domain\Accounting\Models\FiscalPeriod;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FiscalPeriodClosed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly FiscalPeriod $fiscalPeriod,
        public readonly int $closedBy
    ) {}
}
