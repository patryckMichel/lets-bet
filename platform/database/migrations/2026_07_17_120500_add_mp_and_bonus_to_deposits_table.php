<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->string('mp_payment_id')->nullable()->unique()->after('txid');
            $table->string('bonus_code', 40)->nullable()->after('mp_payment_id');
            $table->foreignId('bonus_code_id')->nullable()->after('bonus_code')->constrained('bonus_codes')->nullOnDelete();
            $table->text('qr_code_base64')->nullable()->after('pix_copy');
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bonus_code_id');
            $table->dropColumn(['mp_payment_id', 'bonus_code', 'qr_code_base64']);
        });
    }
};
