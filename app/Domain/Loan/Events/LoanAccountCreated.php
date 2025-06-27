<?php
// LoanAccountCreated.php
namespace App\Domain\Loan\Events;

use App\Domain\Loan\Models\LoanAccount;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanAccountCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LoanAccount $loanAccount,
        public readonly int $createdBy
    ) {}
}

// LoanPaymentProcessed.php
namespace App\Domain\Loan\Events;

use App\Domain\Loan\Models\LoanPayment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanPaymentProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LoanPayment $payment,
        public readonly int $processedBy
    ) {}
}

// LoanPaidOff.php
namespace App\Domain\Loan\Events;

use App\Domain\Loan\Models\LoanAccount;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanPaidOff
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LoanAccount $loanAccount,
        public readonly int $processedBy
    ) {}
}
