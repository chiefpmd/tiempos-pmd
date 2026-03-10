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
            $table->decimal('costo_mueble', 12, 2)->nullable()->after('descripcion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('muebles', function (Blueprint $table) {
            $table->dropColumn('costo_mueble');
        });
    }
};
