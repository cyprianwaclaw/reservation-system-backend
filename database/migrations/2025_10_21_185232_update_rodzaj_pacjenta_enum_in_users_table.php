<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
 public function up(): void
    {
        // Zmieniamy typ ENUM, dodając nową opcję
        DB::statement("ALTER TABLE users MODIFY rodzaj_pacjenta ENUM(
            'Prywatny',
            'Klub gimnastyki',
            'AWF',
            'WKS',
            'Od Grzegorza',
            'Od Asi',
            'DK',
            'DT',
            'Klub lekkoatletyczny'
        ) NULL");
    }

    public function down(): void
    {
        // Przywracamy poprzednią wersję bez nowej opcji
        DB::statement("ALTER TABLE users MODIFY rodzaj_pacjenta ENUM(
            'Prywatny',
            'Klub gimnastyki',
            'AWF',
            'WKS',
            'Od Grzegorza',
            'Od Asi',
            'DK',
            'DT'
        ) NULL");
    }
};