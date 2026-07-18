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
            $table->boolean('is_admin')->default(false)->after('bonus_balance');
            $table->boolean('is_blocked')->default(false)->after('is_admin');
            $table->timestamp('last_login_at')->nullable()->after('is_blocked');
        });

        DB::table('users')
            ->where('email', 'patryck.michel@gmail.com')
            ->update(['is_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'is_blocked', 'last_login_at']);
        });
    }
};
