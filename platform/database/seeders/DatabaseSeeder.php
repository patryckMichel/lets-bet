<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'vip@lestbet.com'],
            [
                'name' => 'VIP Lestbet',
                'password' => Hash::make('lestbet369'),
            ]
        );

        User::query()
            ->whereIn('email', ['patryck.michel@gmail.com', 'vip@lestbet.com'])
            ->update(['is_admin' => true]);

        $this->call(GameSeeder::class);
    }
}
