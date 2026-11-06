<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CheckAllUsersLoginTest extends TestCase
{
    public function test_all_users_exist_and_can_login(): void
    {
        $users = User::with('roles')->get();
        $this->assertNotEmpty($users, 'Users table should not be empty');

        echo "\n--- CHECKING ALL USERS IN DATABASE ---\n";
        foreach ($users as $user) {
            $role = $user->roles->first()?->slug ?? 'no-role';
            echo "ID: {$user->id} | Username: {$user->username} | Email: {$user->email} | Role: {$role}\n";

            // Test if Exyakuza28!? matches or password matches
            $matchesExyakuza = Hash::check('Exyakuza28!?', $user->password);
            $matchesPassword = Hash::check('password', $user->password);

            echo "  -> Check 'Exyakuza28!?': " . ($matchesExyakuza ? "MATCH" : "NO") . "\n";
            echo "  -> Check 'password': " . ($matchesPassword ? "MATCH" : "NO") . "\n";
        }
    }
}

