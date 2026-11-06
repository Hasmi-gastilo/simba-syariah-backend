<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class AccountingService
{
    public function getChartOfAccounts()
    {
        return ChartOfAccount::orderBy('account_code')->get();
    }

    /**
     * Jurnal Umum (General Journal)
     */
    public function getJournals(array $filters = [])
    {
        $query = Journal::with(['entries.account', 'creator', 'transaction'])
            ->orderByDesc('journal_date')
            ->orderByDesc('id');

        if (!empty($filters['start_date'])) {
            $query->whereDate('journal_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('journal_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', strtoupper($filters['status']));
        }

        $perPage = $filters['per_page'] ?? 15;
        $journals = $query->paginate($perPage);

        // Hitung total debit dan kredit pada data terfilter
        $journalIds = (clone $query)->pluck('id');
        $totalDebit = JournalEntry::whereIn('journal_id', $journalIds)->sum('debit');
        $totalCredit = JournalEntry::whereIn('journal_id', $journalIds)->sum('credit');

        return [
            'journals' => $journals,
            'summary' => [
                'total_debit' => (float) $totalDebit,
                'total_credit' => (float) $totalCredit,
                'is_balanced' => round((float) $totalDebit, 2) === round((float) $totalCredit, 2),
            ],
        ];
    }

    public function getJournalDetail(int $id): Journal
    {
        return Journal::with(['entries.account', 'creator', 'transaction'])->findOrFail($id);
    }

    /**
     * Buku Besar (General Ledger per Akun)
     */
    public function getLedger(int $coaId, array $filters = []): array
    {
        $coa = ChartOfAccount::findOrFail($coaId);
        $isNormalDebit = $coa->normal_balance === 'DEBIT';

        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        // 1. Saldo Awal sebelum start_date
        $initialBalance = 0.0;
        if ($startDate) {
            $priorEntries = JournalEntry::where('chart_of_account_id', $coa->id)
                ->whereHas('journal', function ($q) use ($startDate) {
                    $q->whereDate('journal_date', '<', $startDate);
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();

            $pDebit = (float) ($priorEntries->total_debit ?? 0);
            $pCredit = (float) ($priorEntries->total_credit ?? 0);
            $initialBalance = $isNormalDebit ? ($pDebit - $pCredit) : ($pCredit - $pDebit);
        }

        // 2. Entri dalam periode
        $entriesQuery = JournalEntry::with('journal')
            ->where('chart_of_account_id', $coa->id)
            ->whereHas('journal', function ($q) use ($startDate, $endDate) {
                if ($startDate) $q->whereDate('journal_date', '>=', $startDate);
                if ($endDate) $q->whereDate('journal_date', '<=', $endDate);
            })
            ->join('journals', 'journal_entries.journal_id', '=', 'journals.id')
            ->orderBy('journals.journal_date')
            ->orderBy('journals.id')
            ->select('journal_entries.*');

        $entries = $entriesQuery->get();

        // 3. Hitung running balance
        $runningBalance = $initialBalance;
        $ledgerEntries = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($entries as $entry) {
            $debit = (float) $entry->debit;
            $credit = (float) $entry->credit;
            $totalDebit += $debit;
            $totalCredit += $credit;

            if ($isNormalDebit) {
                $runningBalance += ($debit - $credit);
            } else {
                $runningBalance += ($credit - $debit);
            }

            $ledgerEntries[] = [
                'id' => $entry->id,
                'journal_id' => $entry->journal_id,
                'journal_number' => $entry->journal->journal_number,
                'date' => $entry->journal->journal_date,
                'description' => $entry->description ?? $entry->journal->description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
            ];
        }

        return [
            'account' => $coa,
            'initial_balance' => $initialBalance,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'ending_balance' => $runningBalance,
            'entries' => $ledgerEntries,
        ];
    }

    /**
     * Neraca Saldo (Trial Balance)
     */
    public function getTrialBalance(array $filters = []): array
    {
        $coas = ChartOfAccount::orderBy('account_code')->get();
        $items = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        $endDate = $filters['end_date'] ?? null;

        foreach ($coas as $coa) {
            $query = JournalEntry::where('chart_of_account_id', $coa->id);
            if ($endDate) {
                $query->whereHas('journal', function ($q) use ($endDate) {
                    $q->whereDate('journal_date', '<=', $endDate);
                });
            }

            $totals = $query->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();
            $d = (float) ($totals->total_debit ?? 0);
            $c = (float) ($totals->total_credit ?? 0);

            $debitBalance = 0.0;
            $creditBalance = 0.0;

            if ($coa->normal_balance === 'DEBIT') {
                $net = $d - $c;
                if ($net >= 0) {
                    $debitBalance = $net;
                } else {
                    $creditBalance = abs($net);
                }
            } else {
                $net = $c - $d;
                if ($net >= 0) {
                    $creditBalance = $net;
                } else {
                    $debitBalance = abs($net);
                }
            }

            $totalDebit += $debitBalance;
            $totalCredit += $creditBalance;

            $items[] = [
                'account_id' => $coa->id,
                'account_code' => $coa->account_code,
                'account_name' => $coa->account_name,
                'account_category' => $coa->account_category,
                'normal_balance' => $coa->normal_balance,
                'total_mutation_debit' => $d,
                'total_mutation_credit' => $c,
                'debit_balance' => $debitBalance,
                'credit_balance' => $creditBalance,
            ];
        }

        return [
            'period_end' => $endDate ?? now()->toDateString(),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => round($totalDebit, 2) === round($totalCredit, 2),
            'items' => $items,
        ];
    }

    /**
     * Laporan Posisi Keuangan (Neraca / Balance Sheet)
     */
    public function getBalanceSheet(): array
    {
        $trialBalance = $this->getTrialBalance();
        $items = $trialBalance['items'];

        $assets = [];
        $liabilities = [];
        $equity = [];

        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;

        foreach ($items as $item) {
            $cat = $item['account_category'];
            $balance = ($item['normal_balance'] === 'DEBIT') ? $item['debit_balance'] : $item['credit_balance'];

            // Contra asset (seperti Margin Ditangguhkan 10302 yang normal_balance CREDIT)
            if ($cat === 'ASSET') {
                if ($item['normal_balance'] === 'CREDIT') {
                    $balance = -$item['credit_balance'];
                }
                $totalAssets += $balance;
                $assets[] = [
                    'account_code' => $item['account_code'],
                    'account_name' => $item['account_name'],
                    'balance' => $balance,
                ];
            } elseif ($cat === 'LIABILITY') {
                $totalLiabilities += $balance;
                $liabilities[] = [
                    'account_code' => $item['account_code'],
                    'account_name' => $item['account_name'],
                    'balance' => $balance,
                ];
            } elseif ($cat === 'EQUITY') {
                $totalEquity += $balance;
                $equity[] = [
                    'account_code' => $item['account_code'],
                    'account_name' => $item['account_name'],
                    'balance' => $balance,
                ];
            }
        }

        // Hitung Laba Bersih Tahun Berjalan (Net Income)
        $incomeStatement = $this->getIncomeStatement();
        $netIncome = $incomeStatement['net_income'];

        // Masukkan laba berjalan ke ekuitas
        $equity[] = [
            'account_code' => '30999',
            'account_name' => 'Laba / (Rugi) Periode Berjalan',
            'balance' => $netIncome,
        ];
        $totalEquity += $netIncome;

        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;

        return [
            'report_name' => 'Laporan Posisi Keuangan (Neraca Syariah)',
            'as_of_date' => now()->toDateString(),
            'assets' => [
                'items' => $assets,
                'total' => $totalAssets,
            ],
            'liabilities' => [
                'items' => $liabilities,
                'total' => $totalLiabilities,
            ],
            'equity' => [
                'items' => $equity,
                'total' => $totalEquity,
            ],
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'is_balanced' => round($totalAssets, 2) === round($totalLiabilitiesAndEquity, 2),
        ];
    }

    /**
     * Laporan Laba Rugi (Income Statement)
     */
    public function getIncomeStatement(array $filters = []): array
    {
        $trialBalance = $this->getTrialBalance($filters);
        $items = $trialBalance['items'];

        $revenues = [];
        $expenses = [];

        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($items as $item) {
            $cat = $item['account_category'];
            if ($cat === 'REVENUE') {
                $val = $item['credit_balance'];
                $totalRevenue += $val;
                $revenues[] = [
                    'account_code' => $item['account_code'],
                    'account_name' => $item['account_name'],
                    'amount' => $val,
                ];
            } elseif ($cat === 'EXPENSE') {
                $val = $item['debit_balance'];
                $totalExpense += $val;
                $expenses[] = [
                    'account_code' => $item['account_code'],
                    'account_name' => $item['account_name'],
                    'amount' => $val,
                ];
            }
        }

        $netIncome = $totalRevenue - $totalExpense;

        return [
            'report_name' => 'Laporan Laba Rugi Komprehensif Syariah',
            'period' => now()->format('F Y'),
            'revenues' => [
                'items' => $revenues,
                'total' => $totalRevenue,
            ],
            'expenses' => [
                'items' => $expenses,
                'total' => $totalExpense,
            ],
            'net_income' => $netIncome,
        ];
    }

    /**
     * Audit Trail (Log Aktivitas Keamanan)
     */
    public function getAuditLogs(array $filters = [])
    {
        $query = AuditLog::with('user')->orderByDesc('created_at');

        if (!empty($filters['module'])) {
            $query->where('module', strtoupper($filters['module']));
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', strtoupper($filters['action']));
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }
}

