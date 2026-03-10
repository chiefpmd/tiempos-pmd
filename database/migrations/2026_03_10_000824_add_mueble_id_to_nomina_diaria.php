<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('nomina_diaria', function (Blueprint $table) {
            $table->foreignId('mueble_id')->nullable()->after('proyecto_id')
                  ->constrained('muebles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nomina_diaria', function (Blueprint $table) {
            $table->dropForeign(['mueble_id']);
            $table->dropColumn('mueble_id');
        });
    }
};
