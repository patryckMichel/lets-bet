<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crash_bets', function (Blueprint $table) {
            $table->decimal('from_balance', 12, 2)->default(0)->after('amount');
            $table->decimal('from_bonus', 12, 2)->default(0)->after('from_balance');
        });
    }

    public function down(): void
    {
        Schema::table('crash_bets', function (Blueprint $table) {
            $table->dropColumn(['from_balance', 'from_bonus']);
        });
    }
};
