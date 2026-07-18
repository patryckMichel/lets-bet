<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('truco_matches', function (Blueprint $table) {
            $table->timestamp('turn_deadline')->nullable()->after('settled_at');
            $table->json('stake_splits')->nullable()->after('from_bonus');
        });

        Schema::table('truco_seats', function (Blueprint $table) {
            $table->decimal('from_balance', 12, 2)->nullable()->after('seat_index');
            $table->decimal('from_bonus', 12, 2)->nullable()->after('from_balance');
        });
    }

    public function down(): void
    {
        Schema::table('truco_matches', function (Blueprint $table) {
            $table->dropColumn(['turn_deadline', 'stake_splits']);
        });

        Schema::table('truco_seats', function (Blueprint $table) {
            $table->dropColumn(['from_balance', 'from_bonus']);
        });
    }
};
