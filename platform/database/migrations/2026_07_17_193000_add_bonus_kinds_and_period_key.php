<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonus_codes', function (Blueprint $table) {
            $table->string('kind', 30)->default('fixed')->after('code');
            $table->unsignedInteger('inactive_days')->nullable()->after('max_bonus');
        });

        if (Schema::hasColumn('bonus_codes', 'type')) {
            DB::table('bonus_codes')->orderBy('id')->each(function ($row) {
                $kind = in_array($row->type ?? '', ['fixed', 'match'], true)
                    ? $row->type
                    : 'fixed';
                DB::table('bonus_codes')->where('id', $row->id)->update(['kind' => $kind]);
            });
        }

        Schema::table('bonus_code_redemptions', function (Blueprint $table) {
            $table->string('period_key', 40)->default('once')->after('deposit_id');
        });

        Schema::table('bonus_code_redemptions', function (Blueprint $table) {
            $table->dropForeign(['deposit_id']);
            $table->dropUnique(['deposit_id']);
            $table->dropUnique(['user_id', 'bonus_code_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE bonus_code_redemptions ALTER COLUMN deposit_id DROP NOT NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE bonus_code_redemptions MODIFY deposit_id BIGINT UNSIGNED NULL');
        }

        Schema::table('bonus_code_redemptions', function (Blueprint $table) {
            $table->foreign('deposit_id')->references('id')->on('deposits')->nullOnDelete();
            $table->unique(['user_id', 'bonus_code_id', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::table('bonus_code_redemptions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'bonus_code_id', 'period_key']);
            $table->dropForeign(['deposit_id']);
            $table->dropColumn('period_key');
        });

        Schema::table('bonus_code_redemptions', function (Blueprint $table) {
            $table->foreign('deposit_id')->references('id')->on('deposits')->cascadeOnDelete();
            $table->unique('deposit_id');
            $table->unique(['user_id', 'bonus_code_id']);
        });

        Schema::table('bonus_codes', function (Blueprint $table) {
            $table->dropColumn(['kind', 'inactive_days']);
        });
    }
};
