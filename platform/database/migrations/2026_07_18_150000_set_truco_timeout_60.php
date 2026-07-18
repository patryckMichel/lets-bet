<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('settings')->where('key', 'truco_turn_timeout_seconds')->exists();
        if ($exists) {
            DB::table('settings')
                ->where('key', 'truco_turn_timeout_seconds')
                ->update([
                    'value' => '60',
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('settings')->insert([
                'key' => 'truco_turn_timeout_seconds',
                'value' => '60',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'truco_turn_timeout_seconds')
            ->update([
                'value' => '20',
                'updated_at' => now(),
            ]);
    }
};
