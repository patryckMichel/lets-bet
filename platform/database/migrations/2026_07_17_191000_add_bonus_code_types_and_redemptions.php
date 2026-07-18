<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonus_codes', function (Blueprint $table) {
            $table->string('type', 20)->default('fixed')->after('code');
            $table->decimal('match_percent', 8, 2)->nullable()->after('bonus_amount');
            $table->decimal('max_bonus', 12, 2)->nullable()->after('match_percent');
        });

        Schema::create('bonus_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bonus_code_id')->constrained('bonus_codes')->cascadeOnDelete();
            $table->foreignId('deposit_id')->constrained()->cascadeOnDelete();
            $table->decimal('bonus_credited', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'bonus_code_id']);
            $table->unique('deposit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_code_redemptions');

        Schema::table('bonus_codes', function (Blueprint $table) {
            $table->dropColumn(['type', 'match_percent', 'max_bonus']);
        });
    }
};
