<?php

namespace App\Exports;

use App\Models\NominaDiaria;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NominaReporteExport implements FromArray, WithTitle, WithStyles
{
    public function __construct(
        private int $semanaInicio,
        private int $semanaFin,
        private int $anio,
    ) {}

    public function title(): string
    {
        return 'Costo por Proyecto';
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function array(): array
    {
        $registros = NominaDiaria::with(['personal', 'proyecto', 'categoria', 'proyectoHe'])
            ->where('semana', '>=', $this->semanaInicio)
            ->where('semana', '<=', $this->semanaFin)
            ->whereHas('personal', fn($q) => $q->whereNotNull('nomina_bruta_semanal'))
            ->get();

        $semanasConDatos = $registros->pluck('semana')->unique()->sort()->values();

        $costoProyectos = [];
        $costoNoProd = [];
        $costoHe = [];

        foreach ($registros as $r) {
            $sem = $r->semana;
            if ($r->proyecto_id && $r->costo_dia > 0) {
                $nombre = $r->proyecto?->nombre ?? 'Sin Proyecto';
                $costoProyectos[$nombre][$sem] = ($costoProyectos[$nombre][$sem] ?? 0) + $r->costo_dia;
            }
            if ($r->categoria_id && $r->costo_dia > 0) {
                $nombre = $r->categoria?->nombre ?? 'Sin Categoría';
                $costoNoProd[$nombre][$sem] = ($costoNoProd[$nombre][$sem] ?? 0) + $r->costo_dia;
            }
            if ($r->costo_he > 0) {
                $nombreHe = $r->proyectoHe?->nombre ?? $r->proyecto?->nombre ?? 'Sin Proyecto HE';
                $costoHe[$nombreHe][$sem] = ($costoHe[$nombreHe][$sem] ?? 0) + $r->costo_he;
            }
        }

        // Build header
        $header = ['Concepto'];
        foreach ($semanasConDatos as $sem) {
            $header[] = "Sem {$sem}";
        }
        $header[] = 'Total';
        $rows = [$header];

        // Helper to add section
        $addSection = function ($title, $data) use (&$rows, $semanasConDatos) {
            $rows[] = [$title];
            foreach ($data as $nombre => $semanas) {
                $row = [$nombre];
                $total = 0;
                foreach ($semanasConDatos as $sem) {
                    $val = $semanas[$sem] ?? 0;
                    $row[] = $val > 0 ? round($val, 2) : '';
                    $total += $val;
                }
                $row[] = round($total, 2);
                $rows[] = $row;
            }
        };

        $addSection('--- PROYECTOS ---', $costoProyectos);
        $rows[] = [];
        $addSection('--- NO PRODUCTIVO ---', $costoNoProd);
        $rows[] = [];
        $addSection('--- HORAS EXTRA ---', $costoHe);

        return $rows;
    }
}
