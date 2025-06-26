<?php
// app/Domain/Notification/Events/ReportGenerated.php
namespace App\Domain\Notification\Events;

use App\Domain\Reporting\Models\GeneratedReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly GeneratedReport $report
    ) {}
}
