<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_materiales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->enum('tipo', ['pedido', 'entrega']);
            $table->date('fecha');
            $table->timestamps();

            $table->unique(['proyecto_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_materiales');
    }
};
