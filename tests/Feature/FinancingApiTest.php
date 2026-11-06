<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Account;
use App\Models\SavingsProduct;
use App\Models\FinancingProduct;
use App\Models\Financing;
use App\Models\FinancingInstallment;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class FinancingApiTest extends TestCase
{
    private function authenticateUser(string $roleSlug = 'account_officer'): User
    {
        $user = User::whereHas('roles', function ($q) use ($roleSlug) {
            $q->where('slug', $roleSlug);
        })->first();

        if (!$user) {
            $user = User::first();
        }

        return $user;
    }

    public function test_can_list_financing_products(): void
    {
        $user = $this->authenticateUser();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/financing-products');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_can_simulate_murabahah_financing(): void
    {
        $user = $this->authenticateUser();

        $payload = [
            'akad_type' => 'MURABAHAH',
            'principal_amount' => 12000000,
            'tenor' => 12,
            'margin_rate_percent' => 10.0,
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/v1/financings/simulate', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.principal_amount', 12000000)
            ->assertJsonPath('data.margin_amount', 1200000)
            ->assertJsonPath('data.selling_price', 13200000)
            ->assertJsonPath('data.installment_amount', 1100000);

        $this->assertCount(12, $response->json('data.schedule_preview'));
    }

    public function test_can_apply_financing_and_generate_installments(): void
    {
        $user = $this->authenticateUser('account_officer');

        $customer = Customer::first();
        $product = FinancingProduct::where('akad_type', 'MURABAHAH')->first();

        $payload = [
            'customer_id' => $customer->id,
            'financing_product_id' => $product->id,
            'principal_amount' => 6000000,
            'tenor' => 6,
            'margin_rate_percent' => 10.0,
            'purpose' => 'Pembelian Laptop untuk Praktikum Laboratorium',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/v1/financings', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'PENDING');

        $financingId = $response->json('data.id');
        $this->assertNotNull($financingId);

        $installmentsCount = FinancingInstallment::where('financing_id', $financingId)->count();
        $this->assertEquals(6, $installmentsCount);
    }

    public function test_can_approve_financing_disburse_to_account_and_create_journal(): void
    {
        $user = $this->authenticateUser('supervisor');

        $customer = Customer::first();
        $product = FinancingProduct::where('akad_type', 'MURABAHAH')->first();

        // Cari atau buat rekening tabungan aktif untuk nasabah
        $savingsProduct = SavingsProduct::first();
        $account = Account::firstOrCreate([
            'customer_id' => $customer->id,
            'savings_product_id' => $savingsProduct->id,
        ], [
            'account_number' => 'ACC' . rand(1000000, 9999999),
            'opening_balance' => 100000,
            'current_balance' => 100000,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $initialBalance = (float) $account->current_balance;

        // Buat pengajuan pembiayaan
        $financing = Financing::create([
            'financing_number' => 'FIN-' . date('Ymd') . '-' . rand(1000, 9999),
            'customer_id' => $customer->id,
            'financing_product_id' => $product->id,
            'principal_amount' => 5000000,
            'margin_amount' => 500000,
            'selling_price' => 5500000,
            'tenor' => 5,
            'installment_amount' => 1100000,
            'purpose' => 'Uji Coba Approval Pembiayaan',
            'status' => 'PENDING',
            'submitted_by' => $user->id,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            FinancingInstallment::create([
                'financing_id' => $financing->id,
                'installment_number' => $i,
                'due_date' => now()->addMonths($i),
                'amount_due' => 1100000,
                'amount_paid' => 0,
                'status' => 'UNPAID',
            ]);
        }

        // Jalankan Approve
        $response = $this->actingAs($user)
            ->postJson("/api/v1/financings/{$financing->id}/approve", [
                'disbursement_account_id' => $account->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ACTIVE');

        // Verifikasi saldo bertambah sebesar pokok (5.000.000)
        $account->refresh();
        $this->assertEquals($initialBalance + 5000000, (float) $account->current_balance);

        // Verifikasi jurnal akuntansi berpasangan DEBIT == KREDIT
        $journal = Journal::where('description', 'like', "%{$financing->financing_number}%")->latest()->first();
        $this->assertNotNull($journal);

        $totalDebit = JournalEntry::where('journal_id', $journal->id)->sum('debit');
        $totalCredit = JournalEntry::where('journal_id', $journal->id)->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(5500000, $totalDebit); // Sebesar selling price
    }

    public function test_can_pay_installment_and_update_status(): void
    {
        $user = $this->authenticateUser('teller');

        $customer = Customer::first();
        $product = FinancingProduct::where('akad_type', 'MURABAHAH')->first();

        $financing = Financing::create([
            'financing_number' => 'FIN-' . date('Ymd') . '-' . rand(1000, 9999),
            'customer_id' => $customer->id,
            'financing_product_id' => $product->id,
            'principal_amount' => 1000000,
            'margin_amount' => 100000,
            'selling_price' => 1100000,
            'tenor' => 1,
            'installment_amount' => 1100000,
            'status' => 'ACTIVE',
            'submitted_by' => $user->id,
        ]);

        $installment = FinancingInstallment::create([
            'financing_id' => $financing->id,
            'installment_number' => 1,
            'due_date' => now()->addMonth(),
            'amount_due' => 1100000,
            'amount_paid' => 0,
            'status' => 'UNPAID',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/financings/installments/{$installment->id}/pay");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'PAID');

        // Karena tenor 1 dan sudah lunas, status pembiayaan harus otomatis jadi COMPLETED
        $financing->refresh();
        $this->assertEquals('COMPLETED', $financing->status);
    }
}

