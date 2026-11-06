<?php

namespace App\Services;

use App\Models\Account;
use App\Models\SavingsProduct;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Exception;

class AccountService
{
    public function generateAccountNumber(SavingsProduct $product): string
    {
        $prefix = ($product->akad_type === 'WADIAH') ? '110' : '120';
        do {
            $accNum = $prefix . date('y') . str_pad((string) mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (Account::where('account_number', $accNum)->exists());

        return $accNum;
    }

    public function openAccount(array $data, int $userId): Account
    {
        return DB::transaction(function () use ($data, $userId) {
            $product = SavingsProduct::findOrFail($data['savings_product_id']);
            $initialDeposit = (float) ($data['initial_deposit'] ?? 0);

            if ($initialDeposit < (float) $product->initial_deposit) {
                throw new Exception("Setoran awal minimum untuk produk ini adalah Rp " . number_format($product->initial_deposit, 0, ',', '.'));
            }

            $accountNumber = $this->generateAccountNumber($product);

            $account = Account::create([
                'account_number' => $accountNumber,
                'customer_id' => $data['customer_id'],
                'savings_product_id' => $product->id,
                'opening_balance' => $initialDeposit,
                'current_balance' => $initialDeposit,
                'status' => 'active',
                'opened_at' => now(),
                'created_by' => $userId,
            ]);

            // If initial deposit > 0, record transaction & accounting journal
            if ($initialDeposit > 0) {
                $refNumber = 'TRX' . date('YmdHis') . mt_rand(100, 999);

                $trx = Transaction::create([
                    'reference_number' => $refNumber,
                    'transaction_type' => 'INITIAL_DEPOSIT',
                    'source_account_id' => null,
                    'destination_account_id' => $account->id,
                    'amount' => $initialDeposit,
                    'description' => 'Setoran awal pembukaan rekening ' . $account->account_number,
                    'status' => 'SUCCESS',
                    'performed_by' => $userId,
                    'session_id' => $data['session_id'] ?? null,
                ]);

                // Transaction Detail (Credit for customer account)
                TransactionDetail::create([
                    'transaction_id' => $trx->id,
                    'account_id' => $account->id,
                    'entry_type' => 'CREDIT',
                    'amount' => $initialDeposit,
                    'created_at' => now(),
                ]);

                // Automatic Double-Entry Journal
                $this->createInitialDepositJournal($trx, $product, $initialDeposit, $userId);
            }

            return $account->load(['customer', 'product']);
        });
    }

    protected function createInitialDepositJournal(Transaction $trx, SavingsProduct $product, float $amount, int $userId): void
    {
        // Kas Teller COA (10101)
        $cashCoa = ChartOfAccount::where('account_code', '10101')->first();

        // Liability COA depending on Akad
        $savingsCode = ($product->akad_type === 'WADIAH') ? '20101' : '20102';
        $savingsCoa = ChartOfAccount::where('account_code', $savingsCode)->first();

        if ($cashCoa && $savingsCoa) {
            $journal = Journal::create([
                'journal_number' => 'JRN' . date('YmdHis') . mt_rand(100, 999),
                'transaction_id' => $trx->id,
                'journal_date' => now()->toDateString(),
                'description' => 'Jurnal setoran awal transaksi ' . $trx->reference_number,
                'status' => 'POSTED',
                'created_by' => $userId,
            ]);

            // Debit: Kas Teller
            JournalEntry::create([
                'journal_id' => $journal->id,
                'chart_of_account_id' => $cashCoa->id,
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Penerimaan kas setoran awal',
            ]);

            // Credit: Tabungan Wadiah/Mudharabah
            JournalEntry::create([
                'journal_id' => $journal->id,
                'chart_of_account_id' => $savingsCoa->id,
                'debit' => 0,
                'credit' => $amount,
                'description' => 'Kewajiban simpanan nasabah (' . $product->akad_type . ')',
            ]);
        }
    }
}

