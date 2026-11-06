<?php

namespace App\Services;

use App\Models\Financing;
use App\Models\FinancingProduct;
use App\Models\FinancingInstallment;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class FinancingService
{
    public function generateFinancingNumber(): string
    {
        do {
            $num = 'FIN-' . date('Ymd') . '-' . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Financing::where('financing_number', $num)->exists());

        return $num;
    }

    public function generateJournalNumber(): string
    {
        do {
            $ref = 'JRN-' . date('YmdHis') . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Journal::where('journal_number', $ref)->exists());

        return $ref;
    }

    public function generateReferenceNumber(string $type): string
    {
        $prefix = match ($type) {
            'FINANCING_DISBURSEMENT' => 'DSB',
            'FINANCING_PAYMENT' => 'FPM',
            default => 'TRX',
        };

        do {
            $ref = $prefix . date('YmdHis') . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Transaction::where('reference_number', $ref)->exists());

        return $ref;
    }

    /**
     * Kalkulasi Simulasi Pembiayaan berdasarkan Akad Syariah
     */
    public function calculateSimulation(array $data): array
    {
        $akad = strtoupper($data['akad_type'] ?? 'MURABAHAH');
        $principal = (float) ($data['principal_amount'] ?? 0);
        $tenor = (int) ($data['tenor'] ?? 12);
        if ($principal <= 0 || $tenor <= 0) {
            throw new Exception('Nominal pokok dan tenor harus bernilai lebih besar dari 0.');
        }

        $marginRatePercent = (float) ($data['margin_rate_percent'] ?? 10.0);
        $profitSharingRatio = $data['profit_sharing_ratio'] ?? '60:40';
        $estimatedMonthlyProfit = (float) ($data['estimated_monthly_profit'] ?? 0);

        $marginAmount = 0.0;
        $sellingPrice = $principal;
        $installmentAmount = 0.0;
        $monthlyPrincipal = round($principal / $tenor, 2);
        $monthlyMargin = 0.0;

        $schedule = [];

        if ($akad === 'MURABAHAH' || $akad === 'IJARAH') {
            // Margin = Pokok * (Rate/100) * (Tenor/12)
            $marginAmount = round($principal * ($marginRatePercent / 100) * ($tenor / 12), 2);
            $sellingPrice = round($principal + $marginAmount, 2);
            $installmentAmount = round($sellingPrice / $tenor, 2);
            $monthlyMargin = round($marginAmount / $tenor, 2);

            $accumulatedPrincipal = 0.0;
            $accumulatedMargin = 0.0;

            for ($i = 1; $i <= $tenor; $i++) {
                $p = ($i === $tenor) ? round($principal - $accumulatedPrincipal, 2) : $monthlyPrincipal;
                $m = ($i === $tenor) ? round($marginAmount - $accumulatedMargin, 2) : $monthlyMargin;
                $accumulatedPrincipal += $p;
                $accumulatedMargin += $m;

                $schedule[] = [
                    'installment_number' => $i,
                    'due_date' => Carbon::now()->addMonths($i)->format('Y-m-d'),
                    'principal_portion' => $p,
                    'margin_portion' => $m,
                    'amount_due' => round($p + $m, 2),
                ];
            }
        } elseif ($akad === 'MUDHARABAH' || $akad === 'MUSYARAKAH') {
            // Nisbah bagi hasil: Bank % : Nasabah %
            $parts = explode(':', $profitSharingRatio);
            $bankSharePercent = isset($parts[0]) ? (float) $parts[0] : 60.0;

            $monthlyBankProfit = round($estimatedMonthlyProfit * ($bankSharePercent / 100), 2);
            $installmentAmount = round($monthlyPrincipal + $monthlyBankProfit, 2);
            $sellingPrice = $principal;
            $marginAmount = round($monthlyBankProfit * $tenor, 2);

            $accumulatedPrincipal = 0.0;
            for ($i = 1; $i <= $tenor; $i++) {
                $p = ($i === $tenor) ? round($principal - $accumulatedPrincipal, 2) : $monthlyPrincipal;
                $accumulatedPrincipal += $p;

                $schedule[] = [
                    'installment_number' => $i,
                    'due_date' => Carbon::now()->addMonths($i)->format('Y-m-d'),
                    'principal_portion' => $p,
                    'margin_portion' => $monthlyBankProfit,
                    'amount_due' => round($p + $monthlyBankProfit, 2),
                ];
            }
        }

        return [
            'akad_type' => $akad,
            'principal_amount' => $principal,
            'tenor' => $tenor,
            'margin_rate_percent' => $marginRatePercent,
            'profit_sharing_ratio' => $profitSharingRatio,
            'estimated_monthly_profit' => $estimatedMonthlyProfit,
            'margin_amount' => $marginAmount,
            'selling_price' => $sellingPrice,
            'installment_amount' => $installmentAmount,
            'schedule_preview' => $schedule,
        ];
    }

    /**
     * Pengajuan Pembiayaan Baru (Apply Financing)
     */
    public function applyFinancing(array $data, int $userId): Financing
    {
        return DB::transaction(function () use ($data, $userId) {
            $customer = Customer::findOrFail($data['customer_id']);
            $product = FinancingProduct::findOrFail($data['financing_product_id']);

            $sim = $this->calculateSimulation([
                'akad_type' => $product->akad_type,
                'principal_amount' => $data['principal_amount'],
                'tenor' => $data['tenor'],
                'margin_rate_percent' => $data['margin_rate_percent'] ?? 10.0,
                'profit_sharing_ratio' => $data['profit_sharing_ratio'] ?? '60:40',
                'estimated_monthly_profit' => $data['estimated_monthly_profit'] ?? 0,
            ]);

            $financingNumber = $this->generateFinancingNumber();

            $financing = Financing::create([
                'financing_number' => $financingNumber,
                'customer_id' => $customer->id,
                'financing_product_id' => $product->id,
                'principal_amount' => $sim['principal_amount'],
                'margin_amount' => $sim['margin_amount'],
                'selling_price' => $sim['selling_price'],
                'profit_sharing_ratio' => $sim['profit_sharing_ratio'],
                'tenor' => $sim['tenor'],
                'installment_amount' => $sim['installment_amount'],
                'purpose' => $data['purpose'] ?? null,
                'status' => 'PENDING',
                'submitted_by' => $userId,
            ]);

            // Buat jadwal angsuran
            foreach ($sim['schedule_preview'] as $sch) {
                FinancingInstallment::create([
                    'financing_id' => $financing->id,
                    'installment_number' => $sch['installment_number'],
                    'due_date' => $sch['due_date'],
                    'amount_due' => $sch['amount_due'],
                    'amount_paid' => 0,
                    'status' => 'UNPAID',
                ]);
            }

            return $financing->load(['customer', 'product', 'installments', 'submittedBy']);
        });
    }

    /**
     * Persetujuan & Pencairan Pembiayaan (Approve & Disburse)
     */
    public function approveFinancing(int $financingId, int $approverId, ?int $disbursementAccountId = null): Financing
    {
        return DB::transaction(function () use ($financingId, $approverId, $disbursementAccountId) {
            $financing = Financing::with(['product', 'customer'])->lockForUpdate()->findOrFail($financingId);

            if ($financing->status !== 'PENDING') {
                throw new Exception("Pembiayaan ini tidak dalam status PENDING (status saat ini: {$financing->status}).");
            }

            // Tentukan rekening pencairan
            $account = null;
            if ($disbursementAccountId) {
                $account = Account::lockForUpdate()->find($disbursementAccountId);
            } else {
                $account = Account::where('customer_id', $financing->customer_id)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();
            }

            if (!$account) {
                throw new Exception('Nasabah belum memiliki rekening tabungan aktif untuk pencairan dana pembiayaan.');
            }

            $principal = (float) $financing->principal_amount;
            $margin = (float) $financing->margin_amount;
            $sellingPrice = (float) $financing->selling_price;
            $akad = $financing->product->akad_type;

            // 1. Tambahkan saldo pencairan ke rekening nasabah
            $account->increment('current_balance', $principal);

            // 2. Buat Transaksi Pencairan (FINANCING_DISBURSEMENT)
            $refNumber = $this->generateReferenceNumber('FINANCING_DISBURSEMENT');
            $trx = Transaction::create([
                'reference_number' => $refNumber,
                'transaction_type' => 'FINANCING_DISBURSEMENT',
                'source_account_id' => null,
                'destination_account_id' => $account->id,
                'amount' => $principal,
                'description' => "Pencairan {$financing->product->product_name} ({$financing->financing_number}) ke rekening {$account->account_number}",
                'status' => 'SUCCESS',
                'performed_by' => $approverId,
                'approved_by' => $approverId,
            ]);

            TransactionDetail::create([
                'transaction_id' => $trx->id,
                'account_id' => $account->id,
                'entry_type' => 'CREDIT',
                'amount' => $principal,
            ]);

            // 3. Buat Jurnal Akuntansi Berpasangan Sesuai Standar PSAK Syariah
            $journalNumber = $this->generateJournalNumber();
            $journal = Journal::create([
                'journal_number' => $journalNumber,
                'transaction_id' => $trx->id,
                'journal_date' => now(),
                'description' => "Pencairan Pembiayaan {$financing->financing_number} - Akad {$akad}",
                'status' => 'POSTED',
                'created_by' => $approverId,
            ]);

            // Cari COA
            $savingsCoa = ChartOfAccount::where('account_code', '20101')->first()
                ?? ChartOfAccount::where('account_category', 'LIABILITY')->first();

            if ($akad === 'MURABAHAH' || $akad === 'IJARAH') {
                $receivableCode = ($akad === 'MURABAHAH') ? '10301' : '10601'; // Piutang Murabahah / Ijarah
                $deferredCode = '10302'; // Margin Ditangguhkan

                $receivableCoa = ChartOfAccount::where('account_code', $receivableCode)->firstOrFail();
                $deferredCoa = ChartOfAccount::where('account_code', $deferredCode)->firstOrFail();

                // DEBIT Piutang Sebesar Harga Jual
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $receivableCoa->id,
                    'debit' => $sellingPrice,
                    'credit' => 0,
                    'description' => "Pengakuan {$receivableCoa->account_name} ({$financing->financing_number})",
                ]);

                // CREDIT Kewajiban Simpanan Tabungan Nasabah (sebesar pokok)
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $savingsCoa->id,
                    'debit' => 0,
                    'credit' => $principal,
                    'description' => "Pencairan dana pokok pembiayaan ke tabungan nasabah",
                ]);

                // CREDIT Margin Ditangguhkan (sebesar margin)
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $deferredCoa->id,
                    'debit' => 0,
                    'credit' => $margin,
                    'description' => "Pengakuan margin keuntungan ditangguhkan",
                ]);
            } else {
                // MUDHARABAH / MUSYARAKAH
                $financingCode = ($akad === 'MUDHARABAH') ? '10401' : '10501';
                $financingCoa = ChartOfAccount::where('account_code', $financingCode)->firstOrFail();

                // DEBIT Pembiayaan Sebesar Pokok
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $financingCoa->id,
                    'debit' => $principal,
                    'credit' => 0,
                    'description' => "Penyaluran dana modal {$financingCoa->account_name} ({$financing->financing_number})",
                ]);

                // CREDIT Rekening Tabungan Nasabah
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $savingsCoa->id,
                    'debit' => 0,
                    'credit' => $principal,
                    'description' => "Pencairan dana pembiayaan ke rekening nasabah",
                ]);
            }

            // 4. Update status financing menjadi ACTIVE
            $financing->update([
                'status' => 'ACTIVE',
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            return $financing->load(['customer', 'product', 'installments', 'approvedBy']);
        });
    }

    /**
     * Penolakan Pembiayaan (Reject Financing)
     */
    public function rejectFinancing(int $financingId, int $approverId, string $reason): Financing
    {
        return DB::transaction(function () use ($financingId, $approverId, $reason) {
            $financing = Financing::lockForUpdate()->findOrFail($financingId);

            if ($financing->status !== 'PENDING') {
                throw new Exception("Pembiayaan ini tidak dalam status PENDING.");
            }

            $financing->update([
                'status' => 'REJECTED',
                'approved_by' => $approverId,
                'approved_at' => now(),
                'purpose' => trim($financing->purpose . "\n[Ditolak: {$reason}]"),
            ]);

            return $financing->load(['customer', 'product', 'approvedBy']);
        });
    }

    /**
     * Pembayaran Angsuran Pembiayaan (Installment Payment)
     */
    public function payInstallment(int $installmentId, int $userId, ?int $sourceAccountId = null): FinancingInstallment
    {
        return DB::transaction(function () use ($installmentId, $userId, $sourceAccountId) {
            $installment = FinancingInstallment::with(['financing.product', 'financing.customer'])
                ->lockForUpdate()
                ->findOrFail($installmentId);

            if ($installment->status === 'PAID') {
                throw new Exception('Angsuran ini sudah berstatus LUNAS (PAID).');
            }

            $financing = $installment->financing;
            $amountDue = (float) $installment->amount_due;
            $akad = $financing->product->akad_type;

            // Sumber pembayaran: Autodebet rekening tabungan atau Setor Tunai
            $account = null;
            if ($sourceAccountId) {
                $account = Account::with('product')->lockForUpdate()->findOrFail($sourceAccountId);
                $minBalance = (float) $account->product->minimum_balance;
                $currentBalance = (float) $account->current_balance;

                if (($currentBalance - $amountDue) < $minBalance) {
                    throw new Exception("Saldo tabungan tidak mencukupi untuk pembayaran angsuran Rp " . number_format($amountDue, 0, ',', '.'));
                }

                $account->decrement('current_balance', $amountDue);

                // Buat Transaksi Pembayaran
                $refNumber = $this->generateReferenceNumber('FINANCING_PAYMENT');
                $trx = Transaction::create([
                    'reference_number' => $refNumber,
                    'transaction_type' => 'FINANCING_PAYMENT',
                    'source_account_id' => $account->id,
                    'destination_account_id' => null,
                    'amount' => $amountDue,
                    'description' => "Pembayaran angsuran ke-{$installment->installment_number} pembiayaan {$financing->financing_number}",
                    'status' => 'SUCCESS',
                    'performed_by' => $userId,
                ]);

                TransactionDetail::create([
                    'transaction_id' => $trx->id,
                    'account_id' => $account->id,
                    'entry_type' => 'DEBIT',
                    'amount' => $amountDue,
                ]);
            } else {
                // Setor Tunai langsung lewat Teller
                $refNumber = $this->generateReferenceNumber('FINANCING_PAYMENT');
                $trx = Transaction::create([
                    'reference_number' => $refNumber,
                    'transaction_type' => 'FINANCING_PAYMENT',
                    'source_account_id' => null,
                    'destination_account_id' => null,
                    'amount' => $amountDue,
                    'description' => "Pembayaran tunai angsuran ke-{$installment->installment_number} pembiayaan {$financing->financing_number}",
                    'status' => 'SUCCESS',
                    'performed_by' => $userId,
                ]);
            }

            // Update Installment
            $installment->update([
                'amount_paid' => $amountDue,
                'status' => 'PAID',
                'paid_at' => now(),
            ]);

            // Jurnal Akuntansi Pembayaran Angsuran
            $journalNumber = $this->generateJournalNumber();
            $journal = Journal::create([
                'journal_number' => $journalNumber,
                'transaction_id' => $trx->id,
                'journal_date' => now(),
                'description' => "Pembayaran Angsuran #{$installment->installment_number} {$financing->financing_number}",
                'status' => 'POSTED',
                'created_by' => $userId,
            ]);

            // Akun kas / tabungan masuk
            $paymentSourceCoa = $account
                ? (ChartOfAccount::where('account_code', '20101')->first() ?? ChartOfAccount::where('account_category', 'LIABILITY')->first())
                : ChartOfAccount::where('account_code', '10101')->firstOrFail();

            if ($akad === 'MURABAHAH' || $akad === 'IJARAH') {
                $receivableCode = ($akad === 'MURABAHAH') ? '10301' : '10601';
                $receivableCoa = ChartOfAccount::where('account_code', $receivableCode)->firstOrFail();
                $deferredCoa = ChartOfAccount::where('account_code', '10302')->firstOrFail();
                $revenueCode = ($akad === 'MURABAHAH') ? '40101' : '40104';
                $revenueCoa = ChartOfAccount::where('account_code', $revenueCode)->firstOrFail();

                $monthlyMargin = round($financing->margin_amount / $financing->tenor, 2);

                // DEBIT Kas Teller / Tabungan (pelunasan piutang angsuran)
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $paymentSourceCoa->id,
                    'debit' => $amountDue,
                    'credit' => 0,
                    'description' => "Penerimaan pembayaran angsuran #{$installment->installment_number}",
                ]);

                // CREDIT Piutang (berkurang)
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $receivableCoa->id,
                    'debit' => 0,
                    'credit' => $amountDue,
                    'description' => "Pengurangan piutang pembiayaan ({$financing->financing_number})",
                ]);

                // Pengakuan Pendapatan Margin:
                // DEBIT Margin Ditangguhkan
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $deferredCoa->id,
                    'debit' => $monthlyMargin,
                    'credit' => 0,
                    'description' => "Amortisasi margin ditangguhkan angsuran #{$installment->installment_number}",
                ]);

                // CREDIT Pendapatan Margin
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $revenueCoa->id,
                    'debit' => 0,
                    'credit' => $monthlyMargin,
                    'description' => "Pengakuan {$revenueCoa->account_name}",
                ]);
            } else {
                // MUDHARABAH / MUSYARAKAH
                $financingCode = ($akad === 'MUDHARABAH') ? '10401' : '10501';
                $financingCoa = ChartOfAccount::where('account_code', $financingCode)->firstOrFail();
                $revenueCode = ($akad === 'MUDHARABAH') ? '40102' : '40103';
                $revenueCoa = ChartOfAccount::where('account_code', $revenueCode)->firstOrFail();

                $monthlyPrincipal = round($financing->principal_amount / $financing->tenor, 2);
                $monthlyProfit = max(0, round($amountDue - $monthlyPrincipal, 2));

                // DEBIT Kas / Tabungan
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $paymentSourceCoa->id,
                    'debit' => $amountDue,
                    'credit' => 0,
                    'description' => "Penerimaan pengembalian modal & bagi hasil angsuran #{$installment->installment_number}",
                ]);

                // CREDIT Pokok Pembiayaan
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $financingCoa->id,
                    'debit' => 0,
                    'credit' => $monthlyPrincipal,
                    'description' => "Pengembalian pokok pembiayaan {$financingCoa->account_name}",
                ]);

                // CREDIT Pendapatan Bagi Hasil
                if ($monthlyProfit > 0) {
                    JournalEntry::create([
                        'journal_id' => $journal->id,
                        'chart_of_account_id' => $revenueCoa->id,
                        'debit' => 0,
                        'credit' => $monthlyProfit,
                        'description' => "Pendapatan bagi hasil bank ({$revenueCoa->account_name})",
                    ]);
                }
            }

            // Cek apakah seluruh angsuran telah LUNAS
            $unpaidCount = FinancingInstallment::where('financing_id', $financing->id)
                ->where('status', '!=', 'PAID')
                ->count();

            if ($unpaidCount === 0) {
                $financing->update(['status' => 'COMPLETED']);
            }

            return $installment->load('financing');
        });
    }
}

