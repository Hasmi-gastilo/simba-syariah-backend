<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Account;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Exception;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Cari info rekening & saldo untuk preview teller
     */
    public function lookupAccount(string $accountNumber)
    {
        $account = Account::with(['customer', 'product'])
            ->where('account_number', $accountNumber)
            ->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor rekening tidak ditemukan.',
                'errors' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data rekening ditemukan.',
            'data' => [
                'id' => $account->id,
                'account_number' => $account->account_number,
                'customer_name' => $account->customer->full_name,
                'customer_cif' => $account->customer->customer_number,
                'product_name' => $account->product->product_name,
                'akad_type' => $account->product->akad_type,
                'current_balance' => (float) $account->current_balance,
                'minimum_balance' => (float) $account->product->minimum_balance,
                'status' => $account->status,
            ],
            'meta' => null,
        ]);
    }

    public function deposit(Request $request)
    {
        $validated = $request->validate([
            'account_number' => 'required|string|exists:accounts,account_number',
            'amount' => 'required|numeric|min:1000',
            'description' => 'nullable|string|max:255',
            'session_id' => 'nullable|integer',
        ]);

        try {
            $trx = $this->transactionService->deposit($validated, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Setoran tunai berhasil diproses.',
                'data' => $trx,
                'meta' => null,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => null,
            ], 422);
        }
    }

    public function withdrawal(Request $request)
    {
        $validated = $request->validate([
            'account_number' => 'required|string|exists:accounts,account_number',
            'amount' => 'required|numeric|min:1000',
            'description' => 'nullable|string|max:255',
            'session_id' => 'nullable|integer',
        ]);

        try {
            $trx = $this->transactionService->withdraw($validated, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Penarikan tunai berhasil diproses.',
                'data' => $trx,
                'meta' => null,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => null,
            ], 422);
        }
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'source_account_number' => 'required|string|exists:accounts,account_number',
            'destination_account_number' => 'required|string|exists:accounts,account_number|different:source_account_number',
            'amount' => 'required|numeric|min:1000',
            'description' => 'nullable|string|max:255',
            'session_id' => 'nullable|integer',
        ]);

        try {
            $trx = $this->transactionService->transfer($validated, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Transfer internal berhasil diproses.',
                'data' => $trx,
                'meta' => null,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => null,
            ], 422);
        }
    }

    public function index(Request $request)
    {
        $query = Transaction::with([
            'sourceAccount.customer',
            'destinationAccount.customer',
            'performer'
        ])->latest();

        if ($request->filled('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->filled('account_number')) {
            $accNum = $request->account_number;
            $query->where(function ($q) use ($accNum) {
                $q->whereHas('sourceAccount', function ($sq) use ($accNum) {
                    $sq->where('account_number', $accNum);
                })->orWhereHas('destinationAccount', function ($dq) use ($accNum) {
                    $dq->where('account_number', $accNum);
                });
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $transactions = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Riwayat transaksi berhasil diambil.',
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $trx = Transaction::with([
            'sourceAccount.customer',
            'sourceAccount.product',
            'destinationAccount.customer',
            'destinationAccount.product',
            'details',
            'performer'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail transaksi berhasil diambil.',
            'data' => $trx,
            'meta' => null,
        ]);
    }

    public function reverse(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $rev = $this->transactionService->reverse($id, $request->user()->id, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dibatalkan (reversed).',
                'data' => $rev,
                'meta' => null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => null,
            ], 422);
        }
    }
}

