<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiempos', function (Blueprint $table) {
            $table->index(['personal_id', 'fecha']);
            $table->index(['mueble_id', 'proceso']);
        });

        Schema::table('nomina_diaria', function (Blueprint $table) {
            $table->index(['personal_id', 'fecha']);
            $table->index(['mueble_id', 'proyecto_id']);
        });

        Schema::table('muebles', function (Blueprint $table) {
            $table->index('proyecto_id');
            $table->index('fecha_entrega');
        });

        Schema::table('personal', function (Blueprint $table) {
            $table->index(['es_lider', 'activo']);
            $table->index(['lider_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::table('tiempos', function (Blueprint $table) {
            $table->dropIndex(['personal_id', 'fecha']);
            $table->dropIndex(['mueble_id', 'proceso']);
        });

        Schema::table('nomina_diaria', function (Blueprint $table) {
            $table->dropIndex(['personal_id', 'fecha']);
            $table->dropIndex(['mueble_id', 'proyecto_id']);
        });

        Schema::table('muebles', function (Blueprint $table) {
            $table->dropIndex(['proyecto_id']);
            $table->dropIndex(['fecha_entrega']);
        });

        Schema::table('personal', function (Blueprint $table) {
            $table->dropIndex(['es_lider', 'activo']);
            $table->dropIndex(['lider_id', 'activo']);
        });
    }
};
