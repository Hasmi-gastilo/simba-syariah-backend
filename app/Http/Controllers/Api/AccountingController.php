<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AccountingController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function getChartOfAccounts(): JsonResponse
    {
        $coas = $this->accountingService->getChartOfAccounts();

        return response()->json([
            'success' => true,
            'data' => $coas,
        ]);
    }

    public function getJournals(Request $request): JsonResponse
    {
        $result = $this->accountingService->getJournals($request->all());

        return response()->json([
            'success' => true,
            'data' => $result['journals']->items(),
            'summary' => $result['summary'],
            'meta' => [
                'current_page' => $result['journals']->currentPage(),
                'last_page' => $result['journals']->lastPage(),
                'total' => $result['journals']->total(),
                'per_page' => $result['journals']->perPage(),
            ],
        ]);
    }

    public function getJournalDetail(int $id): JsonResponse
    {
        $journal = $this->accountingService->getJournalDetail($id);

        return response()->json([
            'success' => true,
            'data' => $journal,
        ]);
    }

    public function getLedger(Request $request): JsonResponse
    {
        $request->validate([
            'coa_id' => 'required|exists:chart_of_accounts,id',
        ]);

        $ledger = $this->accountingService->getLedger((int) $request->coa_id, $request->all());

        return response()->json([
            'success' => true,
            'data' => $ledger,
        ]);
    }

    public function getTrialBalance(Request $request): JsonResponse
    {
        $trialBalance = $this->accountingService->getTrialBalance($request->all());

        return response()->json([
            'success' => true,
            'data' => $trialBalance,
        ]);
    }

    public function getBalanceSheet(): JsonResponse
    {
        $balanceSheet = $this->accountingService->getBalanceSheet();

        return response()->json([
            'success' => true,
            'data' => $balanceSheet,
        ]);
    }

    public function getIncomeStatement(Request $request): JsonResponse
    {
        $incomeStatement = $this->accountingService->getIncomeStatement($request->all());

        return response()->json([
            'success' => true,
            'data' => $incomeStatement,
        ]);
    }

    public function getAuditLogs(Request $request): JsonResponse
    {
        $logs = $this->accountingService->getAuditLogs($request->all());

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}

