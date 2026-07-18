<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sexo', 20)->nullable()->after('name');
            $table->date('data_nascimento')->nullable()->after('sexo');
            $table->string('estado', 2)->nullable()->after('data_nascimento');
            $table->string('cidade', 100)->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sexo', 'data_nascimento', 'estado', 'cidade']);
        });
    }
};
