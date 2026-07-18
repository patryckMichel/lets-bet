<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('asaas_transfer_id', 80)->nullable()->after('pix_key')->index();
            $table->string('pix_key_type', 20)->nullable()->after('asaas_transfer_id');
            $table->string('provider_status', 40)->nullable()->after('status');
            $table->json('provider_payload')->nullable()->after('admin_note');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['asaas_transfer_id', 'pix_key_type', 'provider_status', 'provider_payload']);
        });
    }
};
