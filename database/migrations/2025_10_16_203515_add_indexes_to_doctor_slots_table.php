<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_slots', function (Blueprint $table) {
            $table->index(['doctor_id', 'type', 'start_time'], 'idx_doctor_type_start');
            $table->index(['doctor_id', 'start_time'], 'idx_doctor_start');
            $table->index(['type', 'start_time'], 'idx_type_start');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_slots', function (Blueprint $table) {
            $table->dropIndex('idx_doctor_type_start');
            $table->dropIndex('idx_doctor_start');
            $table->dropIndex('idx_type_start');
        });
    }
};
