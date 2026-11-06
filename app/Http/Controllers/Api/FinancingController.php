<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Financing;
use App\Services\FinancingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

class FinancingController extends Controller
{
    protected FinancingService $financingService;

    public function __construct(FinancingService $financingService)
    {
        $this->financingService = $financingService;
    }

    /**
     * Daftar Pembiayaan
     */
    public function index(Request $request): JsonResponse
    {
        $query = Financing::with(['customer', 'product', 'submittedBy', 'approvedBy'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->status));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('akad_type')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('akad_type', strtoupper($request->akad_type));
            });
        }

        $financings = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $financings->items(),
            'meta' => [
                'current_page' => $financings->currentPage(),
                'last_page' => $financings->lastPage(),
                'total' => $financings->total(),
                'per_page' => $financings->perPage(),
            ],
        ]);
    }

    /**
     * Detail Pembiayaan lengkap dengan Jadwal Angsuran
     */
    public function show(int $id): JsonResponse
    {
        $financing = Financing::with([
            'customer',
            'product',
            'installments',
            'documents',
            'submittedBy',
            'approvedBy',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $financing,
        ]);
    }

    /**
     * Simulasi Kalkulator Pembiayaan
     */
    public function simulate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'akad_type' => 'required|in:MURABAHAH,MUDHARABAH,MUSYARAKAH,IJARAH',
            'principal_amount' => 'required|numeric|min:100000',
            'tenor' => 'required|integer|min:1|max:60',
            'margin_rate_percent' => 'nullable|numeric|min:0|max:100',
            'profit_sharing_ratio' => 'nullable|string',
            'estimated_monthly_profit' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi kalkulator simulasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->financingService->calculateSimulation($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Simulasi pembiayaan syariah berhasil dihitung.',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Pengajuan Pembiayaan Baru
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'financing_product_id' => 'required|exists:financing_products,id',
            'principal_amount' => 'required|numeric|min:100000',
            'tenor' => 'required|integer|min:1|max:60',
            'margin_rate_percent' => 'nullable|numeric|min:0|max:100',
            'profit_sharing_ratio' => 'nullable|string',
            'estimated_monthly_profit' => 'nullable|numeric|min:0',
            'purpose' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi pengajuan pembiayaan gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $financing = $this->financingService->applyFinancing(
                $validator->validated(),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => "Pengajuan pembiayaan {$financing->financing_number} berhasil dibuat.",
                'data' => $financing,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Otorisasi Persetujuan & Pencairan Pembiayaan (Supervisor/Dosen)
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'disbursement_account_id' => 'nullable|exists:accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi rekening pencairan gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $financing = $this->financingService->approveFinancing(
                $id,
                $request->user()->id,
                $request->disbursement_account_id
            );

            return response()->json([
                'success' => true,
                'message' => "Pembiayaan {$financing->financing_number} telah disetujui dan dana berhasil dicairkan.",
                'data' => $financing,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Penolakan Pengajuan Pembiayaan
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Alasan penolakan wajib diisi.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $financing = $this->financingService->rejectFinancing(
                $id,
                $request->user()->id,
                $request->reason
            );

            return response()->json([
                'success' => true,
                'message' => "Pengajuan pembiayaan {$financing->financing_number} telah ditolak.",
                'data' => $financing,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Pembayaran Angsuran Pembiayaan
     */
    public function payInstallment(Request $request, int $installmentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_account_id' => 'nullable|exists:accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Rekening sumber pembayaran tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $installment = $this->financingService->payInstallment(
                $installmentId,
                $request->user()->id,
                $request->source_account_id
            );

            return response()->json([
                'success' => true,
                'message' => "Pembayaran angsuran #{$installment->installment_number} berhasil diproses.",
                'data' => $installment,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

