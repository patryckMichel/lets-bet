<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'truco_house_edge'],
            ['value' => '0.12']
        );
    }

    public function down(): void
    {
        Setting::query()->where('key', 'truco_house_edge')->update(['value' => '0.05']);
    }
};
