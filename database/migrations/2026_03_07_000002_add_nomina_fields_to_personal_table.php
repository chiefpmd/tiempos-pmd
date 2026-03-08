<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->string('clave_empleado')->nullable()->after('activo');
            $table->decimal('nomina_bruta_semanal', 10, 2)->nullable()->after('clave_empleado');
            $table->integer('dias_semana')->default(5)->after('nomina_bruta_semanal');
            $table->decimal('factor_he', 4, 2)->default(0.20)->after('dias_semana');
        });
    }

    public function down(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->dropColumn(['clave_empleado', 'nomina_bruta_semanal', 'dias_semana', 'factor_he']);
        });
    }
};
