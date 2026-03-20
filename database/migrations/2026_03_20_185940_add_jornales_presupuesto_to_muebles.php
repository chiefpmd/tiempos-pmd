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
        Schema::table('muebles', function (Blueprint $table) {
            $table->decimal('jornales_presupuesto', 8, 1)->nullable()->after('costo_mueble');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('muebles', function (Blueprint $table) {
            $table->dropColumn('jornales_presupuesto');
        });
    }
};
