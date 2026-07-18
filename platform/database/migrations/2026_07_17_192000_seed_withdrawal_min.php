<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Setting::query()->where('key', 'withdrawal_min')->doesntExist()) {
            Setting::setValue('withdrawal_min', '200');
        }
    }

    public function down(): void
    {
        Setting::query()->where('key', 'withdrawal_min')->delete();
    }
};
