<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\SavingsProductController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\FinancingProductController;
use App\Http\Controllers\Api\FinancingController;
use App\Http\Controllers\Api\AcademicController;
use App\Http\Controllers\Api\AccountingController;

Route::prefix('v1')->group(function () {
    // Authentication
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Customer Service - Nasabah
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{id}', [CustomerController::class, 'show']);
        Route::put('/customers/{id}', [CustomerController::class, 'update']);

        // Produk Simpanan
        Route::get('/savings-products', [SavingsProductController::class, 'index']);

        // Rekening Simpanan
        Route::get('/accounts', [AccountController::class, 'index']);
        Route::post('/accounts', [AccountController::class, 'store']);
        Route::get('/accounts/lookup/{account_number}', [TransactionController::class, 'lookupAccount']);
        Route::get('/accounts/{id}', [AccountController::class, 'show']);
        Route::get('/accounts/{id}/balance', [AccountController::class, 'balance']);

        // Teller Transaksi & Mutasi Kas
        Route::post('/transactions/deposit', [TransactionController::class, 'deposit']);
        Route::post('/transactions/withdrawal', [TransactionController::class, 'withdrawal']);
        Route::post('/transactions/transfer', [TransactionController::class, 'transfer']);
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/{id}', [TransactionController::class, 'show']);
        Route::post('/transactions/{id}/reverse', [TransactionController::class, 'reverse']);

        // Pembiayaan Syariah (Financing)
        Route::get('/financing-products', [FinancingProductController::class, 'index']);
        Route::post('/financings/simulate', [FinancingController::class, 'simulate']);
        Route::get('/financings', [FinancingController::class, 'index']);
        Route::post('/financings', [FinancingController::class, 'store']);
        Route::get('/financings/{id}', [FinancingController::class, 'show']);
        Route::post('/financings/{id}/approve', [FinancingController::class, 'approve']);
        Route::post('/financings/{id}/reject', [FinancingController::class, 'reject']);
        Route::post('/financings/installments/{installmentId}/pay', [FinancingController::class, 'payInstallment']);

        // Akademik & Manajemen Praktikum
        Route::get('/academic/classes', [AcademicController::class, 'getClasses']);
        Route::post('/academic/classes', [AcademicController::class, 'storeClass']);
        Route::get('/academic/students', [AcademicController::class, 'getStudents']);
        Route::get('/academic/groups', [AcademicController::class, 'getGroups']);
        Route::post('/academic/groups', [AcademicController::class, 'storeGroup']);
        Route::get('/academic/sessions', [AcademicController::class, 'getSessions']);
        Route::post('/academic/sessions', [AcademicController::class, 'storeSession']);
        Route::post('/academic/sessions/{id}/open', [AcademicController::class, 'openSession']);
        Route::post('/academic/sessions/{id}/close', [AcademicController::class, 'closeSession']);
        Route::post('/academic/sessions/{id}/assign', [AcademicController::class, 'assignRole']);
        Route::post('/academic/grades', [AcademicController::class, 'gradeStudent']);

        // Akuntansi & Laporan Keuangan Syariah
        Route::get('/accounting/coas', [AccountingController::class, 'getChartOfAccounts']);
        Route::get('/accounting/journals', [AccountingController::class, 'getJournals']);
        Route::get('/accounting/journals/{id}', [AccountingController::class, 'getJournalDetail']);
        Route::get('/accounting/ledger', [AccountingController::class, 'getLedger']);
        Route::get('/accounting/trial-balance', [AccountingController::class, 'getTrialBalance']);
        Route::get('/accounting/balance-sheet', [AccountingController::class, 'getBalanceSheet']);
        Route::get('/accounting/income-statement', [AccountingController::class, 'getIncomeStatement']);
        Route::get('/accounting/audit-logs', [AccountingController::class, 'getAuditLogs']);
    });
});
