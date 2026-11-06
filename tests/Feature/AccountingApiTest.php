<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;

class AccountingApiTest extends TestCase
{
    private function authenticateUser(): User
    {
        return User::where('username', 'admin')->first() ?? User::first();
    }

    public function test_can_list_chart_of_accounts(): void
    {
        $user = $this->authenticateUser();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/accounting/coas');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_can_get_journals_and_verify_balance(): void
    {
        $user = $this->authenticateUser();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/accounting/journals');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.is_balanced', true);
    }

    public function test_can_get_ledger_for_cash_account(): void
    {
        $user = $this->authenticateUser();

        $cashCoa = ChartOfAccount::where('account_code', '10101')->first();
        $this->assertNotNull($cashCoa);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounting/ledger?coa_id={$cashCoa->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.account_code', '10101');
    }

    public function test_can_get_trial_balance_and_verify_balanced(): void
    {
        $user = $this->authenticateUser();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/accounting/trial-balance');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_balanced', true);
    }

    public function test_can_get_balance_sheet(): void
    {
        $user = $this->authenticateUser();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/accounting/balance-sheet');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'assets',
                    'liabilities',
                    'equity',
                    'total_liabilities_and_equity',
                    'is_balanced',
                ],
            ]);
    }

    public function test_can_get_income_statement(): void
    {
        $user = $this->authenticateUser();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/accounting/income-statement');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'revenues',
                    'expenses',
                    'net_income',
                ],
            ]);
    }
}

