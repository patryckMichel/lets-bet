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
            $table->decimal('bonus_balance', 12, 2)->default(0)->after('balance');
        });

        // Keep existing credited funds as normal balance; bonus starts at 0.
        DB::table('users')->whereNull('bonus_balance')->update(['bonus_balance' => 0]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bonus_balance');
        });
    }
};
