<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\DTOs\CreateJournalEntryDTO;
use App\Domain\Accounting\Contracts\JournalEntryRepositoryInterface;
use App\Domain\Accounting\Exceptions\JournalEntryValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JournalEntryService
{
    public function __construct(
        private JournalEntryRepositoryInterface $journalEntryRepository,
        private AccountService $accountService,
        private FiscalPeriodService $fiscalPeriodService
    ) {}

    public function createJournalEntry(CreateJournalEntryDTO $dto): JournalEntry
    {
        return DB::transaction(function () use ($dto) {
            $this->validateJournalEntry($dto);

            $journalEntry = $this->journalEntryRepository->create([
                'cooperative_id' => $dto->cooperative_id,
                'fiscal_period_id' => $dto->fiscal_period_id,
                'reference_number' => $this->generateReferenceNumber($dto->cooperative_id),
                'transaction_date' => $dto->transaction_date->format('Y-m-d'),
                'description' => $dto->description,
                'total_debit' => $dto->getTotalDebit(),
                'total_credit' => $dto->getTotalCredit(),
                'created_by' => auth()->id(),
            ]);

            foreach ($dto->lines as $line) {
                $journalEntry->lines()->create([
                    'account_id' => $line->account_id,
                    'description' => $line->description,
                    'debit_amount' => $line->debit_amount,
                    'credit_amount' => $line->credit_amount,
                ]);
            }

            Log::info('Journal entry created', [
                'journal_entry_id' => $journalEntry->id,
                'reference_number' => $journalEntry->reference_number,
                'total_amount' => $journalEntry->total_debit
            ]);

            return $journalEntry;
        });
    }

    private function validateJournalEntry(CreateJournalEntryDTO $dto): void
    {
        if ($dto->getTotalDebit() !== $dto->getTotalCredit()) {
            throw JournalEntryValidationException::unbalancedEntry($dto->getTotalDebit(), $dto->getTotalCredit());
        }

        $activePeriod = $this->fiscalPeriodService->getActivePeriod($dto->cooperative_id);
        if (!$activePeriod || $activePeriod->id !== $dto->fiscal_period_id) {
            throw JournalEntryValidationException::invalidFiscalPeriod($dto->fiscal_period_id);
        }

        foreach ($dto->lines as $line) {
            $account = $this->accountService->findById($line->account_id);
            if (!$account || $account->cooperative_id !== $dto->cooperative_id) {
                throw JournalEntryValidationException::invalidAccount($line->account_id);
            }
        }
    }

    private function generateReferenceNumber(int $cooperativeId): string
    {
        $lastEntry = $this->journalEntryRepository->getLastEntry($cooperativeId);
        $nextNumber = $lastEntry ? (int)substr($lastEntry->reference_number, -6) + 1 : 1;

        return 'JE' . date('Ym') . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
