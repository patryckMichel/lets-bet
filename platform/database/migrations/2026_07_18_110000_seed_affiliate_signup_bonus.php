<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('bonus_codes')
            ->where('kind', 'affiliate_signup')
            ->where('active', true)
            ->exists();

        if ($exists) {
            return;
        }

        $code = 'AFILICADASTRO';
        if (DB::table('bonus_codes')->where('code', $code)->exists()) {
            $code = 'AFILICADASTRO'.strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        }

        DB::table('bonus_codes')->insert([
            'code' => $code,
            'kind' => 'affiliate_signup',
            'type' => 'fixed',
            'affiliate_id' => null,
            'bonus_amount' => 50,
            'match_percent' => null,
            'max_bonus' => null,
            'inactive_days' => null,
            'max_uses' => null,
            'uses_count' => 0,
            'expires_at' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('bonus_codes')
            ->where('kind', 'affiliate_signup')
            ->where('code', 'like', 'AFILICADASTRO%')
            ->where('bonus_amount', 50)
            ->delete();
    }
};
