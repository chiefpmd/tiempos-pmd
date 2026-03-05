<?php

namespace App\Exports;

use App\Models\Proyecto;
use App\Models\Personal;
use App\Models\Tiempo;
use Carbon\CarbonPeriod;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProyectoExport implements FromArray, WithTitle, WithStyles
{
    public function __construct(private Proyecto $proyecto) {}

    public function title(): string
    {
        return $this->proyecto->nombre;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function array(): array
    {
        $proyecto = $this->proyecto;
        $muebles = $proyecto->muebles()->orderBy('numero')->get();
        $fechaFin = $proyecto->fecha_inicio->copy()->addWeeks($proyecto->semanas);

        $diasHabiles = [];
        foreach (CarbonPeriod::create($proyecto->fecha_inicio, $fechaFin) as $dia) {
            if ($dia->isWeekday()) {
                $diasHabiles[] = $dia->copy();
            }
        }

        $tiempos = Tiempo::whereIn('mueble_id', $muebles->pluck('id'))
            ->whereBetween('fecha', [$proyecto->fecha_inicio, $fechaFin])
            ->get();

        $personal = Personal::where('activo', true)->get()->keyBy('id');
        $procesos = ['Carpintería', 'Barniz', 'Instalación'];

        $header = ['Mueble', 'Descripción', 'Proceso', 'Equipo'];
        foreach ($diasHabiles as $dia) {
            $header[] = $dia->format('d/M');
        }
        $header[] = 'Total';

        $rows = [$header];

        foreach ($muebles as $mueble) {
            foreach ($procesos as $proceso) {
                $tiemposMueble = $tiempos->where('mueble_id', $mueble->id)->where('proceso', $proceso);
                $personalIds = $tiemposMueble->pluck('personal_id')->unique();

                if ($personalIds->isEmpty()) {
                    $row = [$mueble->numero, $mueble->descripcion, $proceso, ''];
                    $total = 0;
                    foreach ($diasHabiles as $dia) {
                        $row[] = '';
                    }
                    $row[] = 0;
                    $rows[] = $row;
                } else {
                    foreach ($personalIds as $pid) {
                        $p = $personal->get($pid);
                        $row = [$mueble->numero, $mueble->descripcion, $proceso, $p ? $p->nombre : ''];
                        $total = 0;
                        foreach ($diasHabiles as $dia) {
                            $t = $tiemposMueble->first(fn($t) => $t->personal_id == $pid && $t->fecha->format('Y-m-d') === $dia->format('Y-m-d'));
                            $val = $t ? (float)$t->horas : 0;
                            $row[] = $val > 0 ? $val : '';
                            $total += $val;
                        }
                        $row[] = $total;
                        $rows[] = $row;
                    }
                }
            }
        }

        return $rows;
    }
}
