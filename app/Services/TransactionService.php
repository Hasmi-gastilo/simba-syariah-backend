<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Exception;

class TransactionService
{
    public function generateReferenceNumber(string $type): string
    {
        $prefix = match ($type) {
            'DEPOSIT' => 'DEP',
            'WITHDRAWAL' => 'WDR',
            'TRANSFER' => 'TRF',
            'REVERSAL' => 'REV',
            default => 'TRX',
        };

        do {
            $ref = $prefix . date('YmdHis') . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Transaction::where('reference_number', $ref)->exists());

        return $ref;
    }

    /**
     * Setoran Tunai (Cash Deposit)
     */
    public function deposit(array $data, int $userId): Transaction
    {
        return DB::transaction(function () use ($data, $userId) {
            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw new Exception('Nominal setoran harus lebih besar dari Rp 0.');
            }

            // Lock row for update
            $account = Account::with(['customer', 'product'])
                ->where('account_number', $data['account_number'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($account->status !== 'active') {
                throw new Exception('Rekening tujuan tidak aktif atau dalam status terblokir.');
            }

            $refNumber = $this->generateReferenceNumber('DEPOSIT');

            // 1. Update Account Balance
            $account->increment('current_balance', $amount);

            // 2. Create Transaction Header
            $trx = Transaction::create([
                'reference_number' => $refNumber,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'transaction_type' => 'DEPOSIT',
                'source_account_id' => null,
                'destination_account_id' => $account->id,
                'amount' => $amount,
                'description' => $data['description'] ?? ('Setoran tunai ke rekening ' . $account->account_number),
                'status' => 'SUCCESS',
                'performed_by' => $userId,
                'session_id' => $data['session_id'] ?? null,
            ]);

            // 3. Create Transaction Detail
            TransactionDetail::create([
                'transaction_id' => $trx->id,
                'account_id' => $account->id,
                'entry_type' => 'CREDIT',
                'amount' => $amount,
                'created_at' => now(),
            ]);

            // 4. Double-Entry Journal:
            // DEBIT: Kas Teller (10101)
            // CREDIT: Simpanan Nasabah Wadiah (20101) / Mudharabah (20102)
            $cashCoa = ChartOfAccount::where('account_code', '10101')->first();
            $savingsCode = ($account->product->akad_type === 'WADIAH') ? '20101' : '20102';
            $savingsCoa = ChartOfAccount::where('account_code', $savingsCode)->first();

            if ($cashCoa && $savingsCoa) {
                $journal = Journal::create([
                    'journal_number' => 'JRN' . date('YmdHis') . mt_rand(100, 999),
                    'transaction_id' => $trx->id,
                    'journal_date' => now()->toDateString(),
                    'description' => 'Jurnal setoran tunai ' . $trx->reference_number,
                    'status' => 'POSTED',
                    'created_by' => $userId,
                ]);

                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $cashCoa->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Penerimaan kas teller dari setoran tunai',
                ]);

                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $savingsCoa->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Penambahan kewajiban simpanan ' . $account->product->product_name,
                ]);
            }

            return $trx->load(['destinationAccount.customer', 'destinationAccount.product', 'performer']);
        });
    }

    /**
     * Penarikan Tunai (Cash Withdrawal)
     */
    public function withdraw(array $data, int $userId): Transaction
    {
        return DB::transaction(function () use ($data, $userId) {
            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw new Exception('Nominal penarikan harus lebih besar dari Rp 0.');
            }

            // Lock row for update
            $account = Account::with(['customer', 'product'])
                ->where('account_number', $data['account_number'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($account->status !== 'active') {
                throw new Exception('Rekening tidak aktif atau dalam status terblokir.');
            }

            $minBalance = (float) $account->product->minimum_balance;
            $currentBalance = (float) $account->current_balance;

            if (($currentBalance - $amount) < $minBalance) {
                $maxWithdraw = max(0, $currentBalance - $minBalance);
                throw new Exception("Saldo tidak mencukupi. Saldo minimum mengendap adalah Rp " . number_format($minBalance, 0, ',', '.') . ". Maksimal penarikan: Rp " . number_format($maxWithdraw, 0, ',', '.'));
            }

            $refNumber = $this->generateReferenceNumber('WITHDRAWAL');

            // 1. Update Account Balance
            $account->decrement('current_balance', $amount);

            // 2. Create Transaction Header
            $trx = Transaction::create([
                'reference_number' => $refNumber,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'transaction_type' => 'WITHDRAWAL',
                'source_account_id' => $account->id,
                'destination_account_id' => null,
                'amount' => $amount,
                'description' => $data['description'] ?? ('Penarikan tunai dari rekening ' . $account->account_number),
                'status' => 'SUCCESS',
                'performed_by' => $userId,
                'session_id' => $data['session_id'] ?? null,
            ]);

            // 3. Create Transaction Detail
            TransactionDetail::create([
                'transaction_id' => $trx->id,
                'account_id' => $account->id,
                'entry_type' => 'DEBIT',
                'amount' => $amount,
                'created_at' => now(),
            ]);

            // 4. Double-Entry Journal:
            // DEBIT: Simpanan Nasabah Wadiah (20101) / Mudharabah (20102)
            // CREDIT: Kas Teller (10101)
            $cashCoa = ChartOfAccount::where('account_code', '10101')->first();
            $savingsCode = ($account->product->akad_type === 'WADIAH') ? '20101' : '20102';
            $savingsCoa = ChartOfAccount::where('account_code', $savingsCode)->first();

            if ($cashCoa && $savingsCoa) {
                $journal = Journal::create([
                    'journal_number' => 'JRN' . date('YmdHis') . mt_rand(100, 999),
                    'transaction_id' => $trx->id,
                    'journal_date' => now()->toDateString(),
                    'description' => 'Jurnal penarikan tunai ' . $trx->reference_number,
                    'status' => 'POSTED',
                    'created_by' => $userId,
                ]);

                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $savingsCoa->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Pengurangan kewajiban simpanan ' . $account->product->product_name,
                ]);

                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $cashCoa->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Pengeluaran kas teller untuk penarikan tunai',
                ]);
            }

            return $trx->load(['sourceAccount.customer', 'sourceAccount.product', 'performer']);
        });
    }

    /**
     * Transfer Internal (Internal Account Transfer)
     */
    public function transfer(array $data, int $userId): Transaction
    {
        return DB::transaction(function () use ($data, $userId) {
            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw new Exception('Nominal transfer harus lebih besar dari Rp 0.');
            }

            if ($data['source_account_number'] === $data['destination_account_number']) {
                throw new Exception('Rekening pengirim dan penerima tidak boleh sama.');
            }

            // Lock accounts in order of IDs to strictly avoid database deadlocks
            $accounts = Account::with(['customer', 'product'])
                ->whereIn('account_number', [$data['source_account_number'], $data['destination_account_number']])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $sourceAccount = $accounts->firstWhere('account_number', $data['source_account_number']);
            $destAccount = $accounts->firstWhere('account_number', $data['destination_account_number']);

            if (!$sourceAccount) {
                throw new Exception('Rekening pengirim tidak ditemukan.');
            }
            if (!$destAccount) {
                throw new Exception('Rekening tujuan tidak ditemukan.');
            }

            if ($sourceAccount->status !== 'active') {
                throw new Exception('Rekening pengirim tidak aktif.');
            }
            if ($destAccount->status !== 'active') {
                throw new Exception('Rekening tujuan tidak aktif.');
            }

            $minBalance = (float) $sourceAccount->product->minimum_balance;
            $currentBalance = (float) $sourceAccount->current_balance;

            if (($currentBalance - $amount) < $minBalance) {
                $maxTransfer = max(0, $currentBalance - $minBalance);
                throw new Exception("Saldo rekening pengirim tidak mencukupi. Saldo minimum mengendap adalah Rp " . number_format($minBalance, 0, ',', '.') . ". Maksimal transfer: Rp " . number_format($maxTransfer, 0, ',', '.'));
            }

            $refNumber = $this->generateReferenceNumber('TRANSFER');

            // 1. Update balances
            $sourceAccount->decrement('current_balance', $amount);
            $destAccount->increment('current_balance', $amount);

            // 2. Create Transaction Header
            $trx = Transaction::create([
                'reference_number' => $refNumber,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'transaction_type' => 'TRANSFER',
                'source_account_id' => $sourceAccount->id,
                'destination_account_id' => $destAccount->id,
                'amount' => $amount,
                'description' => $data['description'] ?? ("Transfer dari {$sourceAccount->account_number} ke {$destAccount->account_number}"),
                'status' => 'SUCCESS',
                'performed_by' => $userId,
                'session_id' => $data['session_id'] ?? null,
            ]);

            // 3. Create Transaction Details
            TransactionDetail::create([
                'transaction_id' => $trx->id,
                'account_id' => $sourceAccount->id,
                'entry_type' => 'DEBIT',
                'amount' => $amount,
                'created_at' => now(),
            ]);

            TransactionDetail::create([
                'transaction_id' => $trx->id,
                'account_id' => $destAccount->id,
                'entry_type' => 'CREDIT',
                'amount' => $amount,
                'created_at' => now(),
            ]);

            // 4. Double-Entry Journal:
            // DEBIT: Simpanan Pengirim
            // CREDIT: Simpanan Penerima
            $sourceCode = ($sourceAccount->product->akad_type === 'WADIAH') ? '20101' : '20102';
            $destCode = ($destAccount->product->akad_type === 'WADIAH') ? '20101' : '20102';

            $sourceCoa = ChartOfAccount::where('account_code', $sourceCode)->first();
            $destCoa = ChartOfAccount::where('account_code', $destCode)->first();

            if ($sourceCoa && $destCoa) {
                $journal = Journal::create([
                    'journal_number' => 'JRN' . date('YmdHis') . mt_rand(100, 999),
                    'transaction_id' => $trx->id,
                    'journal_date' => now()->toDateString(),
                    'description' => 'Jurnal transfer internal ' . $trx->reference_number,
                    'status' => 'POSTED',
                    'created_by' => $userId,
                ]);

                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $sourceCoa->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "Pengurangan simpanan pengirim ({$sourceAccount->account_number})",
                ]);

                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $destCoa->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "Penambahan simpanan penerima ({$destAccount->account_number})",
                ]);
            }

            return $trx->load([
                'sourceAccount.customer',
                'sourceAccount.product',
                'destinationAccount.customer',
                'destinationAccount.product',
                'performer'
            ]);
        });
    }

    /**
     * Reversal Transaksi (Mekanisme Koreksi Jurnal Pembalik)
     */
    public function reverse(int $transactionId, int $userId, string $reason): Transaction
    {
        return DB::transaction(function () use ($transactionId, $userId, $reason) {
            $original = Transaction::with(['sourceAccount', 'destinationAccount'])->findOrFail($transactionId);

            if ($original->status === 'REVERSED') {
                throw new Exception('Transaksi ini sudah pernah dibatalkan (reversed).');
            }

            $amount = (float) $original->amount;
            $revRef = $this->generateReferenceNumber('REVERSAL');

            // Revert balances depending on transaction type
            if ($original->transaction_type === 'DEPOSIT' || $original->transaction_type === 'INITIAL_DEPOSIT') {
                $account = Account::findOrFail($original->destination_account_id);
                $account->decrement('current_balance', $amount);
            } elseif ($original->transaction_type === 'WITHDRAWAL') {
                $account = Account::findOrFail($original->source_account_id);
                $account->increment('current_balance', $amount);
            } elseif ($original->transaction_type === 'TRANSFER') {
                $source = Account::findOrFail($original->source_account_id);
                $dest = Account::findOrFail($original->destination_account_id);
                $dest->decrement('current_balance', $amount);
                $source->increment('current_balance', $amount);
            }

            $original->update(['status' => 'REVERSED']);

            $revTrx = Transaction::create([
                'reference_number' => $revRef,
                'transaction_type' => 'REVERSAL',
                'source_account_id' => $original->destination_account_id,
                'destination_account_id' => $original->source_account_id,
                'amount' => $amount,
                'description' => "Reversal transaksi {$original->reference_number}: {$reason}",
                'status' => 'SUCCESS',
                'performed_by' => $userId,
                'session_id' => $original->session_id,
            ]);

            return $revTrx;
        });
    }
}

