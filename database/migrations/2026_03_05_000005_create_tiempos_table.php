<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiempos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mueble_id')->constrained('muebles')->cascadeOnDelete();
            $table->enum('proceso', ['Carpintería', 'Barniz', 'Instalación']);
            $table->foreignId('personal_id')->constrained('personal');
            $table->date('fecha');
            $table->decimal('horas', 4, 1)->default(0);
            $table->timestamps();

            $table->unique(['mueble_id', 'proceso', 'personal_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiempos');
    }
};
