<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_does_not_require_factories_and_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('letters', 6);
        $this->assertDatabaseHas('users', [
            'name' => 'DearYou Admin',
            'email' => 'admin@dearyou.test',
        ]);

        $this->assertTrue(
            Hash::check('ChangeMe123!', (string) User::query()->sole()->password),
        );
    }
}
