<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mueble_avance_mensual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mueble_id')->constrained('muebles')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->decimal('avance_carpinteria', 5, 1)->nullable();
            $table->decimal('avance_barniz', 5, 1)->nullable();
            $table->timestamps();

            $table->unique(['mueble_id', 'anio', 'mes']);
        });

        // Migrar avance actual de muebles al mes actual (abril 2026)
        $muebles = DB::table('muebles')
            ->whereNotNull('avance_carpinteria')
            ->orWhereNotNull('avance_barniz')
            ->get();

        foreach ($muebles as $m) {
            if ($m->avance_carpinteria || $m->avance_barniz) {
                DB::table('mueble_avance_mensual')->insert([
                    'mueble_id' => $m->id,
                    'anio' => 2026,
                    'mes' => 4,
                    'avance_carpinteria' => $m->avance_carpinteria,
                    'avance_barniz' => $m->avance_barniz,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mueble_avance_mensual');
    }
};
