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
            $table->decimal('avance_porcentaje', 5, 1)->nullable()->after('jornales_presupuesto');
        });
    }

    public function down(): void
    {
        Schema::table('muebles', function (Blueprint $table) {
            $table->dropColumn('avance_porcentaje');
        });
    }
};
