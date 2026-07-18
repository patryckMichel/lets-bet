<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->string('referral_code', 40)->nullable()->unique()->after('user_id');
            $table->string('pix_key', 120)->nullable()->after('commission_percent');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('affiliate_id')
                ->nullable()
                ->after('is_blocked')
                ->constrained('affiliates')
                ->nullOnDelete();
        });

        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->foreignId('withdrawal_id')
                ->nullable()
                ->after('deposit_id')
                ->constrained('withdrawals')
                ->nullOnDelete();
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('source', 30)->default('player')->after('user_id');
            $table->foreignId('affiliate_id')
                ->nullable()
                ->after('source')
                ->constrained('affiliates')
                ->nullOnDelete();
        });

        // Normalize legacy commission statuses to the new vocabulary.
        DB::table('affiliate_commissions')
            ->where('status', 'pending')
            ->update(['status' => 'open']);
        DB::table('affiliate_commissions')
            ->where('status', 'credited')
            ->update(['status' => 'paid']);

        // Backfill referral codes for existing affiliates.
        $affiliates = DB::table('affiliates')->whereNull('referral_code')->get(['id']);
        foreach ($affiliates as $row) {
            $code = 'AFF'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            DB::table('affiliates')->where('id', $row->id)->update(['referral_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_id');
            $table->dropColumn('source');
        });

        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('withdrawal_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_id');
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropUnique(['referral_code']);
            $table->dropColumn(['referral_code', 'pix_key']);
        });
    }
};
