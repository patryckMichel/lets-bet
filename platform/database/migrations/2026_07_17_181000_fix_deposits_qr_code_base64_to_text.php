<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE deposits ALTER COLUMN qr_code_base64 TYPE TEXT');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE deposits MODIFY qr_code_base64 LONGTEXT NULL');
        } else {
            // sqlite / others: recreate via schema builder when possible
            Schema::table('deposits', function ($table) {
                $table->text('qr_code_base64')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // No-op: truncating back to varchar(255) would lose data.
    }
};
