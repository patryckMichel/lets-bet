<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Cap payouts during live testing so the house does not blow up.
        Setting::setValue('max_multiplier', '10');
        Setting::setValue('house_edge', '0.08');
    }

    public function down(): void
    {
        Setting::setValue('max_multiplier', '100');
        Setting::setValue('house_edge', '0.03');
    }
};
