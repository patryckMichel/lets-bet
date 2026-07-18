<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the oldest row for each lowercase email, remove duplicates
        DB::statement("
            DELETE FROM users u
            USING users d
            WHERE LOWER(u.email) = LOWER(d.email)
              AND u.id > d.id
        ");

        DB::statement('UPDATE users SET email = LOWER(email)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_email_lower_unique ON users (LOWER(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
    }
};
