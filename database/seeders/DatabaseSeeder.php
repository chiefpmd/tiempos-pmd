<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Personal;
use App\Models\Proyecto;
use App\Models\Mueble;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['email' => 'admin@pmd.com'], [
            'name' => 'Administrador',
            'role' => 'admin',
            'password' => Hash::make('admin123'),
        ]);
        User::firstOrCreate(['email' => 'viewer@pmd.com'], [
            'name' => 'Consulta',
            'role' => 'viewer',
            'password' => Hash::make('viewer123'),
        ]);

        $equipos = [
            ['nombre' => 'Anibal', 'equipo' => 'Carpintería', 'color_hex' => '#4A90D9'],
            ['nombre' => 'Carlos', 'equipo' => 'Barniz', 'color_hex' => '#5CB85C'],
            ['nombre' => 'Extra', 'equipo' => 'Carpintería', 'color_hex' => '#95A5A6'],
            ['nombre' => 'Fernando', 'equipo' => 'Carpintería', 'color_hex' => '#ED7D31'],
            ['nombre' => 'Garrido', 'equipo' => 'Carpintería', 'color_hex' => '#9B59B6'],
            ['nombre' => 'Juan Carlos', 'equipo' => 'Carpintería', 'color_hex' => '#27AE60'],
            ['nombre' => 'Miguel', 'equipo' => 'Barniz', 'color_hex' => '#E74C3C'],
            ['nombre' => 'Santana', 'equipo' => 'Instalación', 'color_hex' => '#3498DB'],
            ['nombre' => 'Santiago', 'equipo' => 'Instalación', 'color_hex' => '#E67E22'],
            ['nombre' => 'Tochi', 'equipo' => 'Carpintería', 'color_hex' => '#1ABC9C'],
            ['nombre' => 'Lalo', 'equipo' => 'Carpintería', 'color_hex' => '#F39C12'],
        ];

        foreach ($equipos as $eq) {
            Personal::firstOrCreate(['nombre' => $eq['nombre']], $eq);
        }

        $proyecto = Proyecto::firstOrCreate(['nombre' => 'Juan Cano Casa 3'], [
            'cliente' => 'Juan Cano',
            'fecha_inicio' => '2026-03-02',
            'semanas' => 12,
            'status' => 'activo',
        ]);

        $muebles = [
            ['numero' => 'CAR-13', 'descripcion' => 'Puerta abatible'],
            ['numero' => 'CAR-19', 'descripcion' => 'Closet Alina'],
            ['numero' => 'CAR-05', 'descripcion' => 'Puerta baño Alina'],
            ['numero' => 'CAR-28', 'descripcion' => 'Cabecera Alina'],
            ['numero' => 'CAR-03', 'descripcion' => 'Puerta corrediza Alina'],
            ['numero' => 'CAR-16', 'descripcion' => 'Closet rec. principal'],
            ['numero' => 'CAR-17', 'descripcion' => 'Closet rec. principal 2'],
            ['numero' => 'CAR-18', 'descripcion' => 'Closet rec. principal 3'],
            ['numero' => 'CAR-02', 'descripcion' => 'Puerta corrediza RP'],
        ];

        foreach ($muebles as $m) {
            Mueble::firstOrCreate(
                ['proyecto_id' => $proyecto->id, 'numero' => $m['numero']],
                $m
            );
        }

        Proyecto::firstOrCreate(['nombre' => 'Juan Cano Casa 1'], [
            'cliente' => 'Juan Cano',
            'fecha_inicio' => '2026-03-02',
            'semanas' => 12,
            'status' => 'activo',
        ]);

        Proyecto::firstOrCreate(['nombre' => 'Panerai Puebla'], [
            'cliente' => 'Panerai',
            'fecha_inicio' => '2026-03-16',
            'semanas' => 10,
            'status' => 'activo',
        ]);

        Proyecto::firstOrCreate(['nombre' => 'Panerai Veracruz'], [
            'cliente' => 'Panerai',
            'fecha_inicio' => '2026-03-23',
            'semanas' => 10,
            'status' => 'activo',
        ]);

        Proyecto::firstOrCreate(['nombre' => 'Zenith St Maarten'], [
            'cliente' => 'Zenith',
            'fecha_inicio' => '2026-04-06',
            'semanas' => 8,
            'status' => 'activo',
        ]);
    }
}
