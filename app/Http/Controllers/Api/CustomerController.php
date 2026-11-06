<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    protected $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    public function index(Request $request)
    {
        $query = Customer::withCount('accounts')->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                  ->orWhere('customer_number', 'ilike', "%{$search}%")
                  ->orWhere('identity_number', 'ilike', "%{$search}%")
                  ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $customers = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Daftar nasabah berhasil diambil.',
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'identity_number' => 'nullable|string|max:50',
            'gender' => 'nullable|in:L,P',
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'occupation' => 'nullable|string|max:100',
        ]);

        $customer = $this->customerService->createCustomer($validated, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Data nasabah berhasil didaftarkan.',
            'data' => $customer,
            'meta' => null,
        ], 201);
    }

    public function show($id)
    {
        $customer = Customer::with(['accounts.product', 'creator'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail nasabah berhasil diambil.',
            'data' => $customer,
            'meta' => null,
        ]);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'full_name' => 'sometimes|required|string|max:255',
            'identity_number' => 'nullable|string|max:50',
            'gender' => 'nullable|in:L,P',
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'occupation' => 'nullable|string|max:100',
            'status' => 'sometimes|in:active,inactive,closed',
        ]);

        $updated = $this->customerService->updateCustomer($customer, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Data nasabah berhasil diperbarui.',
            'data' => $updated,
            'meta' => null,
        ]);
    }
}

