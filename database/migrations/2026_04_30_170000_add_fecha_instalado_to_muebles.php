<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('muebles', function (Blueprint $table) {
            $table->date('fecha_instalado')->nullable()->after('fecha_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('muebles', function (Blueprint $table) {
            $table->dropColumn('fecha_instalado');
        });
    }
};
