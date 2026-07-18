<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['key' => 'truco_house_edge', 'value' => '0.05'],
            ['key' => 'truco_turn_timeout_seconds', 'value' => '60'],
            ['key' => 'truco_fair_debug', 'value' => '0'],
        ];

        foreach ($rows as $row) {
            if (! DB::table('settings')->where('key', $row['key'])->exists()) {
                DB::table('settings')->insert([
                    'key' => $row['key'],
                    'value' => $row['value'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'truco_house_edge',
            'truco_turn_timeout_seconds',
            'truco_fair_debug',
        ])->delete();
    }
};
