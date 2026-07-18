<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('truco_matches', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 10); // 1v1 | 2v2
            $table->decimal('stake', 12, 2);
            $table->string('status', 20)->default('playing'); // waiting|playing|finished|cancelled
            $table->string('code', 12)->nullable()->unique();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('score_us')->default(0);
            $table->unsignedTinyInteger('score_them')->default(0);
            $table->unsignedTinyInteger('hand_value')->default(1);
            $table->string('target_winner', 20); // us | them
            $table->decimal('edge_roll', 8, 6)->nullable();
            $table->decimal('house_edge', 8, 4)->default(0.05);
            $table->json('state')->nullable();
            $table->decimal('from_balance', 12, 2)->default(0);
            $table->decimal('from_bonus', 12, 2)->default(0);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'mode']);
            $table->index(['host_user_id', 'status']);
        });

        Schema::create('truco_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truco_match_id')->constrained('truco_matches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_ghost')->default(false);
            $table->string('display_name', 60)->nullable();
            $table->string('team', 10); // us | them
            $table->unsignedTinyInteger('seat_index'); // 0 bottom, 1 left, 2 top, 3 right for 2v2; 0 us, 1 them for 1v1
            $table->timestamps();

            $table->unique(['truco_match_id', 'seat_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truco_seats');
        Schema::dropIfExists('truco_matches');
    }
};
