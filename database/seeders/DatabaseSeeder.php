<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Akses penuh sistem bank simulasi'],
            ['name' => 'Dosen', 'slug' => 'dosen', 'description' => 'Dosen pengampu praktikum dan penilai'],
            ['name' => 'Customer Service', 'slug' => 'customer_service', 'description' => 'Pelayanan nasabah dan pembukaan rekening'],
            ['name' => 'Teller', 'slug' => 'teller', 'description' => 'Operasional kas, setoran, penarikan, transfer'],
            ['name' => 'Account Officer', 'slug' => 'account_officer', 'description' => 'Pengajuan dan analisis pembiayaan syariah'],
            ['name' => 'Supervisor', 'slug' => 'supervisor', 'description' => 'Otorisasi transaksi dan persetujuan pembiayaan'],
            ['name' => 'Mahasiswa', 'slug' => 'mahasiswa', 'description' => 'Peserta praktikum bank mini syariah'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(['slug' => $role['slug']], $role);
        }

        // 2. Chart of Accounts (COA) Syariah
        $coas = [
            ['account_code' => '10101', 'account_name' => 'Kas Teller', 'account_category' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['account_code' => '10102', 'account_name' => 'Kas Khazanah / Vault', 'account_category' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['account_code' => '10201', 'account_name' => 'Penempatan pada Bank Indonesia', 'account_category' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['account_code' => '10301', 'account_name' => 'Piutang Murabahah', 'account_category' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['account_code' => '10302', 'account_name' => 'Margin Murabahah Ditangguhkan', 'account_category' => 'ASSET', 'normal_balance' => 'CREDIT'],
            ['account_code' => '10401', 'account_name' => 'Pembiayaan Mudharabah', 'account_category' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['account_code' => '10501', 'account_name' => 'Pembiayaan Musyarakah', 'account_category' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['account_code' => '10601', 'account_name' => 'Piutang Ijarah', 'account_category' => 'ASSET', 'normal_balance' => 'DEBIT'],
            ['account_code' => '20101', 'account_name' => 'Tabungan Wadiah', 'account_category' => 'LIABILITY', 'normal_balance' => 'CREDIT'],
            ['account_code' => '20102', 'account_name' => 'Tabungan Mudharabah', 'account_category' => 'LIABILITY', 'normal_balance' => 'CREDIT'],
            ['account_code' => '20103', 'account_name' => 'Deposito Mudharabah', 'account_category' => 'LIABILITY', 'normal_balance' => 'CREDIT'],
            ['account_code' => '30101', 'account_name' => 'Modal Virtual Laboratorium', 'account_category' => 'EQUITY', 'normal_balance' => 'CREDIT'],
            ['account_code' => '40101', 'account_name' => 'Pendapatan Margin Murabahah', 'account_category' => 'REVENUE', 'normal_balance' => 'CREDIT'],
            ['account_code' => '40102', 'account_name' => 'Pendapatan Bagi Hasil Mudharabah', 'account_category' => 'REVENUE', 'normal_balance' => 'CREDIT'],
            ['account_code' => '40103', 'account_name' => 'Pendapatan Bagi Hasil Musyarakah', 'account_category' => 'REVENUE', 'normal_balance' => 'CREDIT'],
            ['account_code' => '40104', 'account_name' => 'Pendapatan Sewa Ijarah', 'account_category' => 'REVENUE', 'normal_balance' => 'CREDIT'],
            ['account_code' => '50101', 'account_name' => 'Beban Bagi Hasil Tabungan', 'account_category' => 'EXPENSE', 'normal_balance' => 'DEBIT'],
        ];

        foreach ($coas as $coa) {
            DB::table('chart_of_accounts')->updateOrInsert(['account_code' => $coa['account_code']], array_merge($coa, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // 3. Savings Products
        $products = [
            [
                'product_code' => 'WAD-01',
                'product_name' => 'Tabungan SIMBA Wadiah',
                'akad_type' => 'WADIAH',
                'minimum_balance' => 10000.00,
                'initial_deposit' => 50000.00,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_code' => 'MUD-01',
                'product_name' => 'Tabungan SIMBA Mudharabah',
                'akad_type' => 'MUDHARABAH',
                'minimum_balance' => 20000.00,
                'initial_deposit' => 100000.00,
                'nisbah_customer' => 70.00,
                'nisbah_bank' => 30.00,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_code' => 'DEP-01',
                'product_name' => 'Deposito SIMBA Berjangka',
                'akad_type' => 'MUDHARABAH',
                'minimum_balance' => 1000000.00,
                'initial_deposit' => 1000000.00,
                'nisbah_customer' => 60.00,
                'nisbah_bank' => 40.00,
                'tenor_options' => json_encode([1, 3, 6, 12]),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($products as $prod) {
            DB::table('savings_products')->updateOrInsert(['product_code' => $prod['product_code']], $prod);
        }

        // 4. Financing Products Syariah
        $financingProducts = [
            [
                'product_name' => 'Pembiayaan Murabahah Multiguna',
                'akad_type' => 'MURABAHAH',
                'description' => 'Akad jual beli barang dengan penegasan harga perolehan dan margin keuntungan yang disepakati bersama.',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_name' => 'Pembiayaan Modal Kerja Mudharabah',
                'akad_type' => 'MUDHARABAH',
                'description' => 'Akad kerjasama usaha dimana bank menyediakan 100% modal dan nasabah sebagai pengelola (mudharib).',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_name' => 'Pembiayaan Kemitraan Musyarakah',
                'akad_type' => 'MUSYARAKAH',
                'description' => 'Akad kerjasama modal antara bank dan nasabah untuk membiayai usaha bersama dengan porsi bagi hasil proporsional.',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_name' => 'Pembiayaan Sewa Manfaat Ijarah',
                'akad_type' => 'IJARAH',
                'description' => 'Akad pemindahan hak guna (manfaat) atas suatu barang atau jasa dalam waktu tertentu dengan pembayaran ujrah (sewa).',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($financingProducts as $fp) {
            DB::table('financing_products')->updateOrInsert(['product_name' => $fp['product_name']], $fp);
        }

        // 5. Initial Users & Roles Assignment
        $initialUsers = [
            [
                'name' => 'Super Administrator',
                'username' => 'admin',
                'email' => 'admin@simba.ac.id',
                'password' => Hash::make('Exyakuza28!?'),
                'role' => 'super_admin',
            ],
            [
                'name' => 'Dosen Pembimbing, M.E.I',
                'username' => 'dosen',
                'email' => 'dosen@simba.ac.id',
                'password' => Hash::make('Exyakuza28!?'),
                'role' => 'dosen',
            ],
            [
                'name' => 'Supervisor Operasional',
                'username' => 'spv1',
                'email' => 'spv1@simba.ac.id',
                'password' => Hash::make('Exyakuza28!?'),
                'role' => 'supervisor',
            ],
            [
                'name' => 'Account Officer 1',
                'username' => 'ao1',
                'email' => 'ao1@simba.ac.id',
                'password' => Hash::make('Exyakuza28!?'),
                'role' => 'account_officer',
            ],
            [
                'name' => 'Customer Service 1',
                'username' => 'cs1',
                'email' => 'cs1@simba.ac.id',
                'password' => Hash::make('Exyakuza28!?'),
                'role' => 'customer_service',
            ],
            [
                'name' => 'Teller Kas 1',
                'username' => 'teller1',
                'email' => 'teller1@simba.ac.id',
                'password' => Hash::make('Exyakuza28!?'),
                'role' => 'teller',
            ],
            [
                'name' => 'Mahasiswa Praktikan',
                'username' => 'mahasiswa1',
                'email' => 'mahasiswa1@simba.ac.id',
                'password' => Hash::make('Exyakuza28!?'),
                'role' => 'mahasiswa',
            ],
        ];

        foreach ($initialUsers as $u) {
            $roleSlug = $u['role'];
            unset($u['role']);

            $user = User::updateOrCreate(
                ['username' => $u['username']],
                array_merge($u, ['status' => 'active'])
            );

            $role = DB::table('roles')->where('slug', $roleSlug)->first();
            if ($role) {
                DB::table('role_user')->updateOrInsert([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                ], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 6. Akademik & Praktikum
        $dosenUser = User::where('username', 'dosen')->first();
        $mhsUser = User::where('username', 'mahasiswa1')->first();

        if ($dosenUser) {
            DB::table('lecturers')->updateOrInsert(
                ['user_id' => $dosenUser->id],
                [
                    'employee_number' => 'NIDN-0012058801',
                    'academic_status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $lecturer = DB::table('lecturers')->where('user_id', $dosenUser?->id)->first();

        $classId = DB::table('classes')->insertGetId([
            'class_name' => 'Perbankan Syariah 6A',
            'lecturer_id' => $lecturer?->id,
            'academic_year' => '2026/2027',
            'semester' => 'Genap',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentId = null;
        if ($mhsUser) {
            $studentId = DB::table('students')->insertGetId([
                'user_id' => $mhsUser->id,
                'student_number' => 'NIM-2026081001',
                'class_id' => $classId,
                'academic_status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $groupId = DB::table('practicum_groups')->insertGetId([
            'class_id' => $classId,
            'group_name' => 'Kelompok 1 - Bank Mini Syariah Amanah',
            'description' => 'Simulasi operasional kantor cabang mini syariah',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($studentId) {
            DB::table('practicum_group_members')->updateOrInsert([
                'group_id' => $groupId,
                'student_id' => $studentId,
            ], [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $practicumId = DB::table('practicums')->insertGetId([
            'practicum_name' => 'Praktikum Operasional & Pembiayaan Bank Syariah',
            'class_id' => $classId,
            'description' => 'Simulasi komprehensif CS, Teller, Account Officer, dan Supervisor',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(1)->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sessionId = DB::table('practicum_sessions')->insertGetId([
            'practicum_id' => $practicumId,
            'group_id' => $groupId,
            'session_name' => 'Sesi 1: Pembukaan Rekening & Pelayanan Teller',
            'start_time' => now(),
            'end_time' => null,
            'virtual_initial_balance' => 50000000.00,
            'status' => 'OPEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($studentId) {
            $tellerRole = DB::table('roles')->where('slug', 'teller')->first();
            if ($tellerRole) {
                DB::table('practicum_assignments')->insert([
                    'session_id' => $sessionId,
                    'student_id' => $studentId,
                    'role_id' => $tellerRole->id,
                    'assigned_by' => $dosenUser?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
