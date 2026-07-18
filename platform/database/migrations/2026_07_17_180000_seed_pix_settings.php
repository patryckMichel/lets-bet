<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            ['key' => 'pix_provider', 'value' => 'mercadopago'],
            ['key' => 'pix_key', 'value' => ''],
            ['key' => 'pix_merchant_name', 'value' => 'LESTBET 369'],
            ['key' => 'pix_merchant_city', 'value' => 'SAO PAULO'],
            ['key' => 'mercadopago_access_token', 'value' => ''],
            ['key' => 'mercadopago_public_key', 'value' => ''],
            ['key' => 'mercadopago_webhook_secret', 'value' => ''],
            ['key' => 'mercadopago_webhook_url', 'value' => ''],
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
            'pix_provider',
            'pix_key',
            'pix_merchant_name',
            'pix_merchant_city',
            'mercadopago_access_token',
            'mercadopago_public_key',
            'mercadopago_webhook_secret',
            'mercadopago_webhook_url',
        ])->delete();
    }
};
