<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            ['key' => 'house_edge', 'value' => '0.08'],
            ['key' => 'max_multiplier', 'value' => '10'],
            ['key' => 'deposit_min', 'value' => '5'],
            ['key' => 'deposit_max', 'value' => '5000'],
            ['key' => 'min_bet', 'value' => '1'],
            ['key' => 'max_bet', 'value' => '500'],
        ];

        foreach ($rows as $row) {
            DB::table('settings')->updateOrInsert(
                ['key' => $row['key']],
                ['value' => $row['value'], 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'house_edge',
            'max_multiplier',
            'deposit_min',
            'deposit_max',
            'min_bet',
            'max_bet',
        ])->delete();
    }
};
