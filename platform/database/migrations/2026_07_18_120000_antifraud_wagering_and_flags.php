<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('wagering_required', 14, 2)->default(0)->after('bonus_balance');
            $table->decimal('wagering_progress', 14, 2)->default(0)->after('wagering_required');
            $table->string('registration_ip', 45)->nullable()->after('affiliate_id');
            $table->string('last_ip', 45)->nullable()->after('registration_ip');
            $table->boolean('fraud_flag')->default(false)->after('is_blocked');
            $table->boolean('kyc_verified')->default(false)->after('fraud_flag');
            $table->text('fraud_note')->nullable()->after('kyc_verified');
        });

        $settings = [
            ['key' => 'wagering_multiplier', 'value' => '20'],
            ['key' => 'affiliate_signup_daily_cap', 'value' => '50'],
            ['key' => 'affiliate_block_same_ip', 'value' => '1'],
            ['key' => 'velocity_register_per_ip_day', 'value' => '3'],
            ['key' => 'velocity_deposit_per_ip_day', 'value' => '10'],
            ['key' => 'kyc_withdraw_threshold', 'value' => '500'],
        ];

        foreach ($settings as $row) {
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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'wagering_required',
                'wagering_progress',
                'registration_ip',
                'last_ip',
                'fraud_flag',
                'kyc_verified',
                'fraud_note',
            ]);
        });

        DB::table('settings')->whereIn('key', [
            'wagering_multiplier',
            'affiliate_signup_daily_cap',
            'affiliate_block_same_ip',
            'velocity_register_per_ip_day',
            'velocity_deposit_per_ip_day',
            'kyc_withdraw_threshold',
        ])->delete();
    }
};
