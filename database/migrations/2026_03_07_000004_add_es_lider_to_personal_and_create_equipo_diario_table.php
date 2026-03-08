<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add es_lider flag to personal
        Schema::table('personal', function (Blueprint $table) {
            $table->boolean('es_lider')->default(false)->after('activo');
            $table->foreignId('lider_id')->nullable()->after('es_lider');
        });

        // Mark all existing personnel as leaders
        DB::table('personal')->update(['es_lider' => true]);

        // Add foreign key after data update
        Schema::table('personal', function (Blueprint $table) {
            $table->foreign('lider_id')->references('id')->on('personal')->nullOnDelete();
        });

        // Daily team composition
        Schema::create('equipo_diario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->foreignId('lider_id')->constrained('personal')->cascadeOnDelete();
            $table->date('fecha');
            $table->timestamps();

            $table->unique(['personal_id', 'fecha']); // one person can only be in one team per day
            $table->index(['lider_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipo_diario');

        Schema::table('personal', function (Blueprint $table) {
            $table->dropForeign(['lider_id']);
            $table->dropColumn(['es_lider', 'lider_id']);
        });
    }
};
