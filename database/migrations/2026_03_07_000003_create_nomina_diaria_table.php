<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nomina_diaria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->date('fecha');
            $table->integer('semana');

            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias_nomina')->nullOnDelete();

            $table->decimal('horas_extra', 4, 1)->default(0);
            $table->foreignId('proyecto_he_id')->nullable()->constrained('proyectos')->nullOnDelete();

            $table->timestamps();

            $table->unique(['personal_id', 'fecha']);
            $table->index('semana');
            $table->index('fecha');
            $table->index('proyecto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_diaria');
    }
};
