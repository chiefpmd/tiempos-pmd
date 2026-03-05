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

class GeneralExport implements FromArray, WithTitle, WithStyles
{
    public function title(): string
    {
        return 'Vista General';
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function array(): array
    {
        $proyectos = Proyecto::where('status', 'activo')
            ->with(['muebles' => fn($q) => $q->orderBy('numero')])
            ->orderBy('fecha_inicio')->get();

        if ($proyectos->isEmpty()) return [['Sin proyectos activos']];

        $fechaMin = $proyectos->min('fecha_inicio');
        $fechaMax = $proyectos->max(fn($p) => $p->fecha_fin);

        $diasHabiles = [];
        foreach (CarbonPeriod::create($fechaMin, $fechaMax) as $dia) {
            if ($dia->isWeekday()) $diasHabiles[] = $dia->copy();
        }

        $allMuebleIds = $proyectos->flatMap(fn($p) => $p->muebles->pluck('id'));
        $tiempos = Tiempo::whereIn('mueble_id', $allMuebleIds)->get();
        $personal = Personal::where('activo', true)->get()->keyBy('id');
        $procesos = ['Carpintería', 'Barniz', 'Instalación'];

        $header = ['Proyecto', 'Mueble', 'Descripción', 'Proceso', 'Equipo'];
        foreach ($diasHabiles as $dia) {
            $header[] = $dia->format('d/M');
        }
        $header[] = 'Total';
        $rows = [$header];

        foreach ($proyectos as $proyecto) {
            foreach ($proyecto->muebles as $mueble) {
                foreach ($procesos as $proceso) {
                    $tiemposMueble = $tiempos->where('mueble_id', $mueble->id)->where('proceso', $proceso);
                    $personalIds = $tiemposMueble->pluck('personal_id')->unique();

                    if ($personalIds->isEmpty()) {
                        $row = [$proyecto->nombre, $mueble->numero, $mueble->descripcion, $proceso, ''];
                        foreach ($diasHabiles as $dia) { $row[] = ''; }
                        $row[] = 0;
                        $rows[] = $row;
                    } else {
                        foreach ($personalIds as $pid) {
                            $p = $personal->get($pid);
                            $row = [$proyecto->nombre, $mueble->numero, $mueble->descripcion, $proceso, $p?->nombre ?? ''];
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
        }

        return $rows;
    }
}
