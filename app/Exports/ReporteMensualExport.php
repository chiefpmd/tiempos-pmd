<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteMensualExport implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    private array $boldRows = [];
    private array $sectionRows = [];

    public function __construct(
        private array $data,
        private array $departamentos,
        private string $nombreMes,
        private float $totalProdAvanzada,
        private int $mueblesConAvance,
    ) {}

    public function title(): string
    {
        return 'Reporte Mensual';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 38,
            'C' => 12,
            'D' => 15,
            'E' => 15,
            'F' => 12,
            'G' => 15,
            'H' => 40,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];
        foreach ($this->boldRows as $r) {
            $styles[$r] = ['font' => ['bold' => true]];
        }
        foreach ($this->sectionRows as $r) {
            $styles[$r] = [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '374151']],
            ];
        }
        return $styles;
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ["Reporte Mensual - {$this->nombreMes}"];
        $this->sectionRows[] = count($rows);
        $rows[] = [];

        // ===== RESUMEN POR PROYECTO =====
        $proyectosResumen = [];
        $sumCT = 0;
        foreach ($this->departamentos as $depto) {
            $sumCT += $this->data[$depto]['totalCosto'];
            foreach ($this->data[$depto]['proyectos'] as $key => $proy) {
                if (!isset($proyectosResumen[$key])) {
                    $proyectosResumen[$key] = [
                        'nombre' => $proy['nombre'],
                        'abreviacion' => $proy['abreviacion'],
                        'jornales' => 0,
                        'costo' => 0,
                        'personal' => [],
                        'prod_avanzada' => 0,
                    ];
                }
                $proyectosResumen[$key]['jornales'] += $proy['jornales'];
                $proyectosResumen[$key]['costo'] += $proy['costo'];
                $proyectosResumen[$key]['personal'] += $proy['personal'];
                $proyectosResumen[$key]['prod_avanzada'] += ($proy['prod_avanzada'] ?? 0);
            }
        }

        $rows[] = ['RESUMEN POR PROYECTO'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['Proyecto', 'Jornales', 'Costo Nómina', 'Personas', 'Prod. Avanzada', 'Factor'];
        $this->boldRows[] = count($rows);

        $tJor = 0; $tCosto = 0; $tProd = 0;
        foreach ($proyectosResumen as $proy) {
            $tJor += $proy['jornales'];
            $tCosto += $proy['costo'];
            $tProd += $proy['prod_avanzada'];
            $factor = ($proy['prod_avanzada'] > 0 && $proy['costo'] > 0)
                ? $proy['costo'] / $proy['prod_avanzada']
                : null;

            $nombre = $proy['nombre'];
            if ($proy['abreviacion']) {
                $nombre .= ' (' . $proy['abreviacion'] . ')';
            }

            $rows[] = [
                $nombre,
                $proy['jornales'],
                round($proy['costo'], 2),
                count($proy['personal']),
                $proy['prod_avanzada'] > 0 ? round($proy['prod_avanzada'], 2) : 0,
                $factor !== null ? round($factor, 2) : '-',
            ];
        }

        $factorTotal = ($tProd > 0 && $tCosto > 0) ? $tCosto / $tProd : null;
        $rows[] = [
            'TOTAL',
            $tJor,
            round($tCosto, 2),
            '',
            round($tProd, 2),
            $factorTotal !== null ? round($factorTotal, 2) : '-',
        ];
        $this->boldRows[] = count($rows);

        $rows[] = [];
        $rows[] = ['Mueble Producido', round($this->totalProdAvanzada, 2), "({$this->mueblesConAvance} muebles con avance)"];
        $this->boldRows[] = count($rows);
        $rows[] = ['Costo Nómina Total', round($sumCT, 2)];
        $this->boldRows[] = count($rows);
        $rows[] = [];

        // ===== DESGLOSE POR DEPARTAMENTO =====
        foreach ($this->departamentos as $depto) {
            $info = $this->data[$depto];

            $rows[] = ["DEPARTAMENTO: {$depto}"];
            $this->sectionRows[] = count($rows);
            $rows[] = [
                "Total: {$info['totalJornales']} jornales · \$" . number_format($info['totalCosto'], 2),
            ];
            $rows[] = [];

            if (empty($info['proyectos']) && empty($info['categorias'])) {
                $rows[] = ['Sin datos para este departamento.'];
                $rows[] = [];
                continue;
            }

            foreach ($info['proyectos'] as $proy) {
                $nombreProy = $proy['nombre'];
                if ($proy['abreviacion']) {
                    $nombreProy .= ' (' . $proy['abreviacion'] . ')';
                }

                $factor = (($proy['prod_avanzada'] ?? 0) > 0 && $proy['costo'] > 0)
                    ? $proy['costo'] / $proy['prod_avanzada']
                    : null;

                $headerProy = "Proyecto: {$nombreProy} — {$proy['jornales']} jornales · \$"
                    . number_format($proy['costo'], 2)
                    . ' · ' . count($proy['personal']) . ' personas';

                if (($proy['prod_avanzada'] ?? 0) > 0) {
                    $headerProy .= ' · Prod: $' . number_format($proy['prod_avanzada'], 2);
                }
                if ($factor !== null) {
                    $headerProy .= ' · Factor: ' . number_format($factor, 2);
                }

                $rows[] = [$headerProy];
                $this->boldRows[] = count($rows);

                if (!empty($proy['muebles'])) {
                    $rows[] = [
                        'Mueble',
                        'Descripción',
                        'Jornales',
                        'Costo Nómina',
                        'Valor Mueble',
                        '% Avance',
                        'Prod. Avanzada',
                        'Personal',
                    ];
                    $this->boldRows[] = count($rows);

                    $campoAvance = $depto === 'Carpintería' ? 'avance_carpinteria' : 'avance_barniz';
                    $campoPrev = $depto === 'Carpintería' ? 'prev_carpinteria' : 'prev_barniz';

                    foreach ($proy['muebles'] as $mueble) {
                        $avance = $mueble[$campoAvance];
                        $prev = $mueble[$campoPrev] ?? 0;
                        $costoMueble = $mueble['costo_mueble'];
                        $delta = (float) ($avance ?? 0) - (float) $prev;
                        $prodAvanzada = ($delta > 0 && $costoMueble > 0)
                            ? $costoMueble * $delta / 100
                            : null;

                        $rows[] = [
                            $mueble['numero'],
                            $mueble['descripcion'],
                            $mueble['jornales'],
                            round($mueble['costo'], 2),
                            $costoMueble > 0 ? round($costoMueble, 2) : '',
                            $avance !== null ? round((float) $avance, 1) . '%' : '-',
                            $prodAvanzada !== null ? round($prodAvanzada, 2) : '-',
                            implode(', ', array_map(fn($n) => explode(' ', $n)[0], $mueble['personal'])),
                        ];
                    }

                    $sinMueble = $proy['jornales'] - collect($proy['muebles'])->sum('jornales');
                    if ($sinMueble > 0) {
                        $rows[] = ["({$sinMueble} jornales sin mueble asignado)"];
                    }
                }

                $rows[] = [];
            }

            if (!empty($info['categorias'])) {
                $rows[] = ['Ausencias / Otros'];
                $this->boldRows[] = count($rows);
                $rows[] = ['Categoría', 'Jornales', 'Personal'];
                $this->boldRows[] = count($rows);

                foreach ($info['categorias'] as $catNombre => $cat) {
                    $rows[] = [
                        $catNombre,
                        $cat['jornales'],
                        implode(', ', array_map(fn($n) => explode(' ', $n)[0], $cat['personal'])),
                    ];
                }
                $rows[] = [];
            }
        }

        return $rows;
    }
}
