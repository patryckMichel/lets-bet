<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crash_bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crash_round_id')->constrained('crash_rounds')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot')->default(1); // 1 or 2
            $table->decimal('amount', 12, 2);
            $table->decimal('auto_cashout_at', 10, 2)->nullable();
            $table->decimal('cashout_multiplier', 10, 2)->nullable();
            $table->decimal('payout', 12, 2)->nullable();
            $table->string('status', 20)->default('active'); // active|cashed_out|lost
            $table->timestamp('cashed_out_at')->nullable();
            $table->timestamps();

            $table->unique(['crash_round_id', 'user_id', 'slot']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crash_bets');
    }
};
