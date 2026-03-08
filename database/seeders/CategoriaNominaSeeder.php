<?php

namespace Database\Seeders;

use App\Models\CategoriaNomina;
use Illuminate\Database\Seeder;

class CategoriaNominaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            'Vacaciones', 'Incapacidad', 'Supervisión', 'No Aplica',
            'Almacen', 'Administración', 'Mantenimiento', 'Otros',
        ];

        foreach ($categorias as $nombre) {
            CategoriaNomina::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
