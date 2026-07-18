<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->decimal('fee_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('net_amount', 12, 2)->nullable()->after('fee_amount');
        });

        $now = now();
        $rows = [
            // PIX dinâmico Asaas — promocional até 17/10/2026
            ['key' => 'fee_asaas_pix_fixed', 'value' => '0.99'],
            ['key' => 'fee_asaas_pix_percent', 'value' => '0'],
            ['key' => 'fee_asaas_boleto_fixed', 'value' => '0.99'],
            ['key' => 'fee_asaas_card_percent', 'value' => '1.99'],
            ['key' => 'fee_asaas_card_fixed', 'value' => '0.49'],
            ['key' => 'fee_asaas_debit_percent', 'value' => '1.89'],
            ['key' => 'fee_asaas_debit_fixed', 'value' => '0.35'],
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
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn(['fee_amount', 'net_amount']);
        });

        DB::table('settings')->whereIn('key', [
            'fee_asaas_pix_fixed',
            'fee_asaas_pix_percent',
            'fee_asaas_boleto_fixed',
            'fee_asaas_card_percent',
            'fee_asaas_card_fixed',
            'fee_asaas_debit_percent',
            'fee_asaas_debit_fixed',
        ])->delete();
    }
};
