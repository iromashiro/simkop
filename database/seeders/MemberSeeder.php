<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Account;
use App\Models\Cooperative;
use App\Models\SavingsTransaction;
use App\Models\LoanPayment;
use Faker\Factory as Faker;

class JournalEntrySeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $cooperatives = Cooperative::all();

        foreach ($cooperatives as $cooperative) {
            $accounts = Account::where('cooperative_id', $cooperative->id)->get()->keyBy('code');

            // 1. Journal Entry untuk Setoran Modal Awal
            $this->createInitialCapitalEntry($cooperative, $accounts, $faker);

            // 2. Journal Entry untuk Transaksi Simpanan (dari SavingsTransaction)
            $this->createSavingsJournalEntries($cooperative, $accounts);

            // 3. Journal Entry untuk Pembayaran Pinjaman (dari LoanPayment)
            $this->createLoanJournalEntries($cooperative, $accounts);

            // 4. Journal Entry untuk Beban Operasional
            $this->createOperationalExpenseEntries($cooperative, $accounts, $faker);

            // 5. Journal Entry untuk Pendapatan Lain
            $this->createOtherIncomeEntries($cooperative, $accounts, $faker);
        }
    }

    private function createInitialCapitalEntry($cooperative, $accounts, $faker)
    {
        $entry = JournalEntry::create([
            'cooperative_id' => $cooperative->id,
            'entry_number' => 'JE' . date('Ym') . '001',
            'transaction_date' => $cooperative->created_at,
            'description' => 'Setoran modal awal koperasi',
            'reference_type' => 'manual',
            'reference_id' => null,
            'total_debit' => 50000000,
            'total_credit' => 50000000,
            'status' => 'posted',
            'created_by' => 1,
            'posted_by' => 1,
            'posted_at' => $cooperative->created_at,
            'created_at' => $cooperative->created_at,
            'updated_at' => $cooperative->created_at,
        ]);

        // Debit: Kas
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $accounts['1120']->id, // Kas Besar
            'debit_amount' => 50000000,
            'credit_amount' => 0,
            'description' => 'Penerimaan modal awal',
        ]);

        // Credit: Modal Awal
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $accounts['3110']->id, // Modal Awal
            'debit_amount' => 0,
            'credit_amount' => 50000000,
            'description' => 'Modal awal koperasi',
        ]);
    }

    private function createSavingsJournalEntries($cooperative, $accounts)
    {
        $savingsTransactions = SavingsTransaction::whereHas('savings.member', function ($query) use ($cooperative) {
            $query->where('cooperative_id', $cooperative->id);
        })->get();

        foreach ($savingsTransactions as $transaction) {
            $entryNumber = 'JE' . $transaction->transaction_date->format('Ym') . str_pad($transaction->id, 4, '0', STR_PAD_LEFT);

            $entry = JournalEntry::create([
                'cooperative_id' => $cooperative->id,
                'entry_number' => $entryNumber,
                'transaction_date' => $transaction->transaction_date,
                'description' => $transaction->description,
                'reference_type' => 'savings_transaction',
                'reference_id' => $transaction->id,
                'total_debit' => $transaction->amount,
                'total_credit' => $transaction->amount,
                'status' => 'posted',
                'created_by' => $transaction->processed_by,
                'posted_by' => $transaction->processed_by,
                'posted_at' => $transaction->created_at,
                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->created_at,
            ]);

            if ($transaction->transaction_type === 'deposit') {
                // Debit: Kas
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accounts['1120']->id, // Kas Besar
                    'debit_amount' => $transaction->amount,
                    'credit_amount' => 0,
                    'description' => 'Penerimaan setoran simpanan',
                ]);

                // Credit: Simpanan sesuai jenis
                $savingsAccountCode = match ($transaction->savings->type) {
                    'pokok' => '2210',
                    'wajib' => '2220',
                    'khusus' => '2230',
                    'sukarela' => '2240',
                };

                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accounts[$savingsAccountCode]->id,
                    'debit_amount' => 0,
                    'credit_amount' => $transaction->amount,
                    'description' => 'Simpanan ' . $transaction->savings->type,
                ]);
            } else { // withdrawal
                // Debit: Simpanan
                $savingsAccountCode = match ($transaction->savings->type) {
                    'pokok' => '2210',
                    'wajib' => '2220',
                    'khusus' => '2230',
                    'sukarela' => '2240',
                };

                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accounts[$savingsAccountCode]->id,
                    'debit_amount' => $transaction->amount,
                    'credit_amount' => 0,
                    'description' => 'Penarikan simpanan ' . $transaction->savings->type,
                ]);

                // Credit: Kas
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accounts['1120']->id, // Kas Besar
                    'debit_amount' => 0,
                    'credit_amount' => $transaction->amount,
                    'description' => 'Pembayaran penarikan simpanan',
                ]);
            }
        }
    }

    private function createLoanJournalEntries($cooperative, $accounts)
    {
        $loanPayments = LoanPayment::whereHas('loan.member', function ($query) use ($cooperative) {
            $query->where('cooperative_id', $cooperative->id);
        })->get();

        foreach ($loanPayments as $payment) {
            $entryNumber = 'JE' . $payment->payment_date->format('Ym') . str_pad($payment->id + 1000, 4, '0', STR_PAD_LEFT);

            $entry = JournalEntry::create([
                'cooperative_id' => $cooperative->id,
                'entry_number' => $entryNumber,
                'transaction_date' => $payment->payment_date,
                'description' => "Pembayaran pinjaman #{$payment->loan->loan_number} angsuran ke-{$payment->payment_number}",
                'reference_type' => 'loan_payment',
                'reference_id' => $payment->id,
                'total_debit' => $payment->amount_paid,
                'total_credit' => $payment->amount_paid,
                'status' => 'posted',
                'created_by' => $payment->processed_by,
                'posted_by' => $payment->processed_by,
                'posted_at' => $payment->created_at,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->created_at,
            ]);

            // Debit: Kas
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accounts['1120']->id, // Kas Besar
                'debit_amount' => $payment->amount_paid,
                'credit_amount' => 0,
                'description' => 'Penerimaan angsuran pinjaman',
            ]);

            // Credit: Piutang Pinjaman (pokok)
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accounts['1310']->id, // Piutang Pinjaman Anggota
                'debit_amount' => 0,
                'credit_amount' => $payment->principal_amount,
                'description' => 'Pelunasan pokok pinjaman',
            ]);

            // Credit: Pendapatan Bunga (jika ada)
            if ($payment->interest_amount > 0) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accounts['4110']->id, // Pendapatan Bunga Pinjaman
                    'debit_amount' => 0,
                    'credit_amount' => $payment->interest_amount,
                    'description' => 'Pendapatan bunga pinjaman',
                ]);
            }
        }

        // Create journal entries for loan disbursements
        $loans = \App\Models\Loan::whereHas('member', function ($query) use ($cooperative) {
            $query->where('cooperative_id', $cooperative->id);
        })->whereNotNull('disbursement_date')->get();

        foreach ($loans as $loan) {
            $entryNumber = 'JE' . $loan->disbursement_date->format('Ym') . 'L' . str_pad($loan->id, 3, '0', STR_PAD_LEFT);

            $entry = JournalEntry::create([
                'cooperative_id' => $cooperative->id,
                'entry_number' => $entryNumber,
                'transaction_date' => $loan->disbursement_date,
                'description' => "Pencairan pinjaman #{$loan->loan_number}",
                'reference_type' => 'loan_disbursement',
                'reference_id' => $loan->id,
                'total_debit' => $loan->principal_amount,
                'total_credit' => $loan->principal_amount,
                'status' => 'posted',
                'created_by' => $loan->approved_by,
                'posted_by' => $loan->approved_by,
                'posted_at' => $loan->disbursement_date,
                'created_at' => $loan->disbursement_date,
                'updated_at' => $loan->disbursement_date,
            ]);

            // Debit: Piutang Pinjaman
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accounts['1310']->id, // Piutang Pinjaman Anggota
                'debit_amount' => $loan->principal_amount,
                'credit_amount' => 0,
                'description' => 'Piutang pinjaman anggota',
            ]);

            // Credit: Kas
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accounts['1120']->id, // Kas Besar
                'debit_amount' => 0,
                'credit_amount' => $loan->principal_amount,
                'description' => 'Pencairan dana pinjaman',
            ]);
        }
    }

    private function createOperationalExpenseEntries($cooperative, $accounts, $faker)
    {
        $startDate = $cooperative->created_at;
        $currentDate = clone $startDate;

        while ($currentDate <= now()->subMonth()) {
            // Beban Gaji (bulanan)
            $this->createExpenseEntry(
                $cooperative,
                $accounts,
                $currentDate,
                '5110',
                $faker->numberBetween(15000000, 25000000),
                'Beban gaji karyawan bulan ' . $currentDate->format('F Y'),
                $faker
            );

            // Beban Listrik (bulanan)
            $this->createExpenseEntry(
                $cooperative,
                $accounts,
                $currentDate,
                '5120',
                $faker->numberBetween(2000000, 4000000),
                'Beban listrik bulan ' . $currentDate->format('F Y'),
                $faker
            );

            // Beban Telepon (bulanan)
            $this->createExpenseEntry(
                $cooperative,
                $accounts,
                $currentDate,
                '5130',
                $faker->numberBetween(500000, 1500000),
                'Beban telepon bulan ' . $currentDate->format('F Y'),
                $faker
            );

            // Beban ATK (kadang-kadang)
            if ($faker->boolean(30)) {
                $this->createExpenseEntry(
                    $cooperative,
                    $accounts,
                    $currentDate,
                    '5140',
                    $faker->numberBetween(500000, 2000000),
                    'Pembelian ATK',
                    $faker
                );
            }

            $currentDate->addMonth();
        }

        // Beban Penyusutan (tahunan)
        $yearStart = $cooperative->created_at->startOfYear();
        while ($yearStart <= now()->subYear()) {
            $this->createDepreciationEntry($cooperative, $accounts, $yearStart->endOfYear(), $faker);
            $yearStart->addYear();
        }
    }

    private function createExpenseEntry($cooperative, $accounts, $date, $expenseAccountCode, $amount, $description, $faker)
    {
        $entryNumber = 'JE' . $date->format('Ym') . 'E' . $faker->numberBetween(100, 999);

        $entry = JournalEntry::create([
            'cooperative_id' => $cooperative->id,
            'entry_number' => $entryNumber,
            'transaction_date' => $date,
            'description' => $description,
            'reference_type' => 'expense',
            'reference_id' => null,
            'total_debit' => $amount,
            'total_credit' => $amount,
            'status' => 'posted',
            'created_by' => 1,
            'posted_by' => 1,
            'posted_at' => $date,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        // Debit: Beban
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $accounts[$expenseAccountCode]->id,
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'description' => $description,
        ]);

        // Credit: Kas
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $accounts['1120']->id, // Kas Besar
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'description' => 'Pembayaran ' . strtolower($description),
        ]);
    }

    private function createDepreciationEntry($cooperative, $accounts, $date, $faker)
    {
        $entryNumber = 'JE' . $date->format('Ym') . 'DEP';

        // Penyusutan Bangunan (5% per tahun)
        $buildingDepreciation = 50000000 * 0.05; // Asumsi nilai bangunan 50jt

        // Penyusutan Kendaraan (20% per tahun)
        $vehicleDepreciation = 150000000 * 0.20; // Asumsi nilai kendaraan 150jt

        // Penyusutan Peralatan (10% per tahun)
        $equipmentDepreciation = 20000000 * 0.10; // Asumsi nilai peralatan 20jt

        $totalDepreciation = $buildingDepreciation + $vehicleDepreciation + $equipmentDepreciation;

        $entry = JournalEntry::create([
            'cooperative_id' => $cooperative->id,
            'entry_number' => $entryNumber,
            'transaction_date' => $date,
            'description' => 'Beban penyusutan tahun ' . $date->format('Y'),
            'reference_type' => 'depreciation',
            'reference_id' => null,
            'total_debit' => $totalDepreciation,
            'total_credit' => $totalDepreciation,
            'status' => 'posted',
            'created_by' => 1,
            'posted_by' => 1,
            'posted_at' => $date,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        // Debit: Beban Penyusutan
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $accounts['5150']->id,
            'debit_amount' => $totalDepreciation,
            'credit_amount' => 0,
            'description' => 'Beban penyusutan aset tetap',
        ]);

        // Credit: Akumulasi Penyusutan Bangunan
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $accounts['1521']->id,
            'debit_amount' => 0,
            'credit_amount' => $buildingDepreciation,
            'description' => 'Akumulasi penyusutan bangunan',
        ]);

        // Credit: Akumulasi Penyusutan Kendaraan
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $accounts['1531']->id,
            'debit_amount' => 0,
            'credit_amount' => $vehicleDepreciation,
            'description' => 'Akumulasi penyusutan kendaraan',
        ]);

        // Credit: Akumulasi Penyusutan Peralatan
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $accounts['1541']->id,
            'debit_amount' => 0,
            'credit_amount' => $equipmentDepreciation,
            'description' => 'Akumulasi penyusutan peralatan',
        ]);
    }

    private function createOtherIncomeEntries($cooperative, $accounts, $faker)
    {
        $startDate = $cooperative->created_at;
        $currentDate = clone $startDate;

        while ($currentDate <= now()->subMonth()) {
            // Pendapatan administrasi (kadang-kadang)
            if ($faker->boolean(40)) {
                $amount = $faker->numberBetween(500000, 2000000);
                $entryNumber = 'JE' . $currentDate->format('Ym') . 'ADM' . $faker->numberBetween(100, 999);

                $entry = JournalEntry::create([
                    'cooperative_id' => $cooperative->id,
                    'entry_number' => $entryNumber,
                    'transaction_date' => $currentDate,
                    'description' => 'Pendapatan administrasi',
                    'reference_type' => 'income',
                    'reference_id' => null,
                    'total_debit' => $amount,
                    'total_credit' => $amount,
                    'status' => 'posted',
                    'created_by' => 1,
                    'posted_by' => 1,
                    'posted_at' => $currentDate,
                    'created_at' => $currentDate,
                    'updated_at' => $currentDate,
                ]);

                // Debit: Kas
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accounts['1120']->id,
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'description' => 'Penerimaan pendapatan administrasi',
                ]);

                // Credit: Pendapatan Administrasi
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accounts['4120']->id,
                    'debit_amount' => 0,
                    'credit_amount' => $amount,
                    'description' => 'Pendapatan administrasi',
                ]);
            }

            $currentDate->addMonth();
        }
    }
}
