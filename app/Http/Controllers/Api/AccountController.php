<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\AccountService;
use Illuminate\Http\Request;
use Exception;

class AccountController extends Controller
{
    protected $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    public function index(Request $request)
    {
        $query = Account::with(['customer', 'product'])->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('account_number', 'ilike', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('full_name', 'ilike', "%{$search}%")
                         ->orWhere('customer_number', 'ilike', "%{$search}%");
                  });
            });
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $accounts = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Daftar rekening berhasil diambil.',
            'data' => $accounts->items(),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'per_page' => $accounts->perPage(),
                'total' => $accounts->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'savings_product_id' => 'required|exists:savings_products,id',
            'initial_deposit' => 'required|numeric|min:0',
            'session_id' => 'nullable|integer',
        ]);

        try {
            $account = $this->accountService->openAccount($validated, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Rekening simpanan berhasil dibuka.',
                'data' => $account,
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

    public function show($id)
    {
        $account = Account::with(['customer', 'product', 'creator'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail rekening berhasil diambil.',
            'data' => $account,
            'meta' => null,
        ]);
    }

    public function balance($id)
    {
        $account = Account::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Informasi saldo berhasil diambil.',
            'data' => [
                'account_number' => $account->account_number,
                'current_balance' => (float) $account->current_balance,
                'status' => $account->status,
            ],
            'meta' => null,
        ]);
    }
}

