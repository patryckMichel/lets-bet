<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('last_login_at');
            $table->index('last_seen_at');
        });

        Schema::create('player_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_key', 64)->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('end_reason', 40)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ended_at']);
            $table->index(['started_at']);
            $table->index(['last_seen_at']);
        });

        Schema::create('ops_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 60)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('player_session_id')->nullable()->constrained('player_sessions')->nullOnDelete();
            $table->unsignedBigInteger('round_id')->nullable()->index();
            $table->unsignedBigInteger('bet_id')->nullable()->index();
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('multiplier', 10, 2)->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['event', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('ops_hourly_stats', function (Blueprint $table) {
            $table->id();
            $table->timestamp('hour_start')->unique();
            $table->unsignedInteger('logins')->default(0);
            $table->unsignedInteger('logouts')->default(0);
            $table->unsignedInteger('unique_players')->default(0);
            $table->unsignedInteger('online_peak')->default(0);
            $table->unsignedInteger('heartbeats')->default(0);
            $table->unsignedInteger('bets_count')->default(0);
            $table->decimal('bets_amount', 14, 2)->default(0);
            $table->unsignedInteger('cashouts_count')->default(0);
            $table->decimal('cashouts_amount', 14, 2)->default(0);
            $table->unsignedInteger('losses_count')->default(0);
            $table->decimal('losses_amount', 14, 2)->default(0);
            $table->unsignedInteger('rounds_count')->default(0);
            $table->decimal('crash_point_sum', 14, 2)->default(0);
            $table->decimal('crash_point_max', 10, 2)->default(0);
            $table->decimal('round_wagered', 14, 2)->default(0);
            $table->decimal('round_paid', 14, 2)->default(0);
            $table->decimal('ggr', 14, 2)->default(0);
            $table->unsignedInteger('deposits_count')->default(0);
            $table->decimal('deposits_amount', 14, 2)->default(0);
            $table->unsignedInteger('withdrawals_count')->default(0);
            $table->decimal('withdrawals_amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_hourly_stats');
        Schema::dropIfExists('ops_events');
        Schema::dropIfExists('player_sessions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_seen_at']);
            $table->dropColumn('last_seen_at');
        });
    }
};
