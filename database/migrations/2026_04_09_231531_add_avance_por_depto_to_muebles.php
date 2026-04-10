<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('muebles', function (Blueprint $table) {
            $table->decimal('avance_carpinteria', 5, 1)->nullable()->after('avance_porcentaje');
            $table->decimal('avance_barniz', 5, 1)->nullable()->after('avance_carpinteria');
        });

        // Migrate existing avance_porcentaje to avance_carpinteria
        DB::statement('UPDATE muebles SET avance_carpinteria = avance_porcentaje WHERE avance_porcentaje IS NOT NULL');

        Schema::table('muebles', function (Blueprint $table) {
            $table->dropColumn('avance_porcentaje');
        });
    }

    public function down(): void
    {
        Schema::table('muebles', function (Blueprint $table) {
            $table->decimal('avance_porcentaje', 5, 1)->nullable()->after('jornales_presupuesto');
        });

        DB::statement('UPDATE muebles SET avance_porcentaje = avance_carpinteria WHERE avance_carpinteria IS NOT NULL');

        Schema::table('muebles', function (Blueprint $table) {
            $table->dropColumn(['avance_carpinteria', 'avance_barniz']);
        });
    }
};
