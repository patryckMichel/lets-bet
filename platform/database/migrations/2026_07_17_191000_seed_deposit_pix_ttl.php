<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('settings')->updateOrInsert(
            ['key' => 'deposit_pix_ttl_seconds'],
            ['value' => '60', 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'deposit_pix_ttl_seconds')->delete();
    }
};
