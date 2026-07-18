<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            ['key' => 'asaas_api_key', 'value' => ''],
            ['key' => 'asaas_webhook_token', 'value' => ''],
            ['key' => 'asaas_webhook_url', 'value' => ''],
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
            'asaas_api_key',
            'asaas_webhook_token',
            'asaas_webhook_url',
        ])->delete();
    }
};
