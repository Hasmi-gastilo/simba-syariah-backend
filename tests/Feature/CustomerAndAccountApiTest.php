<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SavingsProduct;
use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalEntry;

class CustomerAndAccountApiTest extends TestCase
{
    protected $token;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::where('username', 'cs1')->first();
        $this->token = $this->user->createToken('test_token')->plainTextToken;
    }

    public function test_can_register_customer_and_auto_generate_cif()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/customers', [
                'full_name' => 'Ahmad Fulan',
                'identity_number' => '3201019908880001',
                'gender' => 'L',
                'birth_place' => 'Jakarta',
                'birth_date' => '1998-05-12',
                'address' => 'Jl. Syariah No. 45',
                'phone' => '081234567890',
                'email' => 'ahmad.fulan@example.com',
                'occupation' => 'Wiraswasta',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Data nasabah berhasil didaftarkan.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'customer_number',
                    'full_name',
                ],
            ]);

        $this->assertStringStartsWith('CIF', $response->json('data.customer_number'));
    }

    public function test_can_open_wadiah_account_with_initial_deposit_and_creates_journal()
    {
        // 1. Create a customer
        $custResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/customers', [
                'full_name' => 'Siti Aisyah',
                'phone' => '08987654321',
            ]);

        $customerId = $custResponse->json('data.id');
        $product = SavingsProduct::where('product_code', 'WAD-01')->first();

        // 2. Open Wadiah account with 150,000 initial deposit
        $accResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/accounts', [
                'customer_id' => $customerId,
                'savings_product_id' => $product->id,
                'initial_deposit' => 150000,
            ]);

        $accResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Rekening simpanan berhasil dibuka.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'account_number',
                    'current_balance',
                ],
            ]);

        $account = Account::find($accResponse->json('data.id'));
        $this->assertEquals(150000, (float) $account->current_balance);

        // 3. Verify Journal and Journal Entries exist and are balanced
        $journal = Journal::where('description', 'like', '%TRX%')->latest()->first();
        $this->assertNotNull($journal);

        $entries = JournalEntry::where('journal_id', $journal->id)->get();
        $totalDebit = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');

        $this->assertEquals(150000, (float) $totalDebit);
        $this->assertEquals(150000, (float) $totalCredit);
        $this->assertEquals($totalDebit, $totalCredit, 'Total debit must balance total credit');
    }
}

