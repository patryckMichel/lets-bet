<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crash_rounds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('round_number')->unique();
            $table->string('status', 20)->default('waiting'); // waiting|running|crashed
            $table->decimal('crash_point', 10, 2);
            $table->string('server_seed', 64);
            $table->string('server_seed_hash', 64);
            $table->timestamp('betting_ends_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('crashed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crash_rounds');
    }
};
