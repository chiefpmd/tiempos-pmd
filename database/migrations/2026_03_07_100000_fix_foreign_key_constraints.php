<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix tiempos.personal_id: change from RESTRICT to CASCADE
        Schema::table('tiempos', function (Blueprint $table) {
            $table->dropForeign(['personal_id']);
            $table->foreign('personal_id')->references('id')->on('personal')->cascadeOnDelete();
        });

        // Fix tiempo_shifts.user_id: change from RESTRICT to SET NULL
        Schema::table('tiempo_shifts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tiempos', function (Blueprint $table) {
            $table->dropForeign(['personal_id']);
            $table->foreign('personal_id')->references('id')->on('personal');
        });

        Schema::table('tiempo_shifts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
};
