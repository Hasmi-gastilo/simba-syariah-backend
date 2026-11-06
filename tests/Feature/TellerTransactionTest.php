<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SavingsProduct;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Transaction;

class TellerTransactionTest extends TestCase
{
    protected $token;
    protected $teller;
    protected $account1;
    protected $account2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teller = User::where('username', 'teller1')->first();
        $this->token = $this->teller->createToken('test_teller_token')->plainTextToken;

        // Ensure customer & 2 accounts exist for testing
        $customer = Customer::firstOrCreate(
            ['full_name' => 'Testing Teller Nasabah'],
            ['customer_number' => 'CIF999901', 'created_by' => $this->teller->id]
        );

        $wadiah = SavingsProduct::where('product_code', 'WAD-01')->first();

        $this->account1 = Account::firstOrCreate(
            ['account_number' => '110990000001'],
            [
                'customer_id' => $customer->id,
                'savings_product_id' => $wadiah->id,
                'opening_balance' => 500000,
                'current_balance' => 500000,
                'status' => 'active',
                'created_by' => $this->teller->id,
            ]
        );

        $this->account2 = Account::firstOrCreate(
            ['account_number' => '110990000002'],
            [
                'customer_id' => $customer->id,
                'savings_product_id' => $wadiah->id,
                'opening_balance' => 200000,
                'current_balance' => 200000,
                'status' => 'active',
                'created_by' => $this->teller->id,
            ]
        );
    }

    public function test_teller_can_deposit_cash_and_increase_balance()
    {
        $initialBalance = (float) $this->account1->fresh()->current_balance;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/transactions/deposit', [
                'account_number' => $this->account1->account_number,
                'amount' => 100000,
                'description' => 'Setoran tunai praktikum teller',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Setoran tunai berhasil diproses.',
            ]);

        $newBalance = (float) $this->account1->fresh()->current_balance;
        $this->assertEquals($initialBalance + 100000, $newBalance);
    }

    public function test_teller_withdrawal_fails_if_exceeds_minimum_balance()
    {
        $currentBalance = (float) $this->account1->fresh()->current_balance;

        // Attempting to withdraw more than available
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/transactions/withdrawal', [
                'account_number' => $this->account1->account_number,
                'amount' => $currentBalance + 50000,
                'description' => 'Penarikan melebihi saldo',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_teller_can_withdraw_cash_when_balance_sufficient()
    {
        $initialBalance = (float) $this->account1->fresh()->current_balance;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/transactions/withdrawal', [
                'account_number' => $this->account1->account_number,
                'amount' => 50000,
                'description' => 'Penarikan tunai sah',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Penarikan tunai berhasil diproses.',
            ]);

        $newBalance = (float) $this->account1->fresh()->current_balance;
        $this->assertEquals($initialBalance - 50000, $newBalance);
    }

    public function test_teller_can_transfer_between_two_accounts()
    {
        $init1 = (float) $this->account1->fresh()->current_balance;
        $init2 = (float) $this->account2->fresh()->current_balance;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/transactions/transfer', [
                'source_account_number' => $this->account1->account_number,
                'destination_account_number' => $this->account2->account_number,
                'amount' => 50000,
                'description' => 'Transfer internal simulasi',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Transfer internal berhasil diproses.',
            ]);

        $this->assertEquals($init1 - 50000, (float) $this->account1->fresh()->current_balance);
        $this->assertEquals($init2 + 50000, (float) $this->account2->fresh()->current_balance);
    }
}

