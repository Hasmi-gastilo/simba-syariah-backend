<?php

namespace Tests\Feature;

use Tests\TestCase;

class EndToEndFeatureCheckTest extends TestCase
{
    private function loginAndGetToken(string $login, string $password = 'Exyakuza28!?'): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => $login,
            'password' => $password,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        return $response->json('data.token');
    }

    public function test_cs_user_can_access_cs_features(): void
    {
        $token = $this->loginAndGetToken('cs1');
        $headers = ['Authorization' => "Bearer $token"];

        // 1. Get customers
        $resCustomers = $this->getJson('/api/v1/customers', $headers);
        $resCustomers->assertStatus(200)->assertJson(['success' => true]);

        // 2. Get savings products
        $resSavings = $this->getJson('/api/v1/savings-products', $headers);
        $resSavings->assertStatus(200)->assertJson(['success' => true]);

        // 3. Get accounts
        $resAccounts = $this->getJson('/api/v1/accounts', $headers);
        $resAccounts->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_teller_user_can_access_teller_features(): void
    {
        $token = $this->loginAndGetToken('teller1');
        $headers = ['Authorization' => "Bearer $token"];

        // 1. Get accounts list
        $resAccounts = $this->getJson('/api/v1/accounts', $headers);
        $resAccounts->assertStatus(200)->assertJson(['success' => true]);

        // 2. Get transactions list
        $resTx = $this->getJson('/api/v1/transactions', $headers);
        $resTx->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_ao_user_can_access_financing_features(): void
    {
        $token = $this->loginAndGetToken('ao1');
        $headers = ['Authorization' => "Bearer $token"];

        // 1. Get financing products
        $resProds = $this->getJson('/api/v1/financing-products', $headers);
        $resProds->assertStatus(200)->assertJson(['success' => true]);

        // 2. Simulate Murabahah
        $resSim = $this->postJson('/api/v1/financings/simulate', [
            'akad_type' => 'MURABAHAH',
            'principal_amount' => 50000000,
            'tenor' => 12,
            'margin_rate_percent' => 10.0,
        ], $headers);
        $resSim->assertStatus(200)->assertJson(['success' => true]);

        // 3. Get financings list
        $resFin = $this->getJson('/api/v1/financings', $headers);
        $resFin->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_spv_user_can_access_approval_and_accounting(): void
    {
        $token = $this->loginAndGetToken('spv1');
        $headers = ['Authorization' => "Bearer $token"];

        // 1. Pending financings
        $resFin = $this->getJson('/api/v1/financings?status=PENDING', $headers);
        $resFin->assertStatus(200)->assertJson(['success' => true]);

        // 2. Trial balance
        $resTB = $this->getJson('/api/v1/accounting/trial-balance', $headers);
        $resTB->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_dosen_user_can_access_academic_and_reports(): void
    {
        $token = $this->loginAndGetToken('dosen');
        $headers = ['Authorization' => "Bearer $token"];

        // 1. Academic classes
        $resClasses = $this->getJson('/api/v1/academic/classes', $headers);
        $resClasses->assertStatus(200)->assertJson(['success' => true]);

        // 2. Academic students
        $resStudents = $this->getJson('/api/v1/academic/students', $headers);
        $resStudents->assertStatus(200)->assertJson(['success' => true]);

        // 3. Academic sessions
        $resSessions = $this->getJson('/api/v1/academic/sessions', $headers);
        $resSessions->assertStatus(200)->assertJson(['success' => true]);

        // 4. Balance sheet
        $resBS = $this->getJson('/api/v1/accounting/balance-sheet', $headers);
        $resBS->assertStatus(200)->assertJson(['success' => true]);

        // 5. Income statement
        $resIS = $this->getJson('/api/v1/accounting/income-statement', $headers);
        $resIS->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_admin_user_can_access_audit_logs_and_coas(): void
    {
        $token = $this->loginAndGetToken('admin');
        $headers = ['Authorization' => "Bearer $token"];

        // 1. Chart of accounts
        $resCOA = $this->getJson('/api/v1/accounting/coas', $headers);
        $resCOA->assertStatus(200)->assertJson(['success' => true]);

        // 2. Journals
        $resJ = $this->getJson('/api/v1/accounting/journals', $headers);
        $resJ->assertStatus(200)->assertJson(['success' => true]);

        // 3. Audit logs
        $resAudit = $this->getJson('/api/v1/accounting/audit-logs', $headers);
        $resAudit->assertStatus(200)->assertJson(['success' => true]);
    }
}

