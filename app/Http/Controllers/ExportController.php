<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Models\Personal;
use App\Models\Tiempo;
use App\Models\NominaDiaria;
use App\Models\CategoriaNomina;
use App\Models\DiaFestivo;
use App\Models\TiempoShift;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProyectoExport;

class ExportController extends Controller
{
    public function exportarProyecto(Proyecto $proyecto)
    {
        $filename = str_replace(' ', '_', $proyecto->nombre) . '_' . now()->format('Ymd') . '.xlsx';
        return Excel::download(new ProyectoExport($proyecto), $filename);
    }

    public function exportarGeneral()
    {
        return Excel::download(new \App\Exports\GeneralExport(), 'Vista_General_' . now()->format('Ymd') . '.xlsx');
    }

    public function exportarGeneralHtml(Request $request)
    {
        $controller = app()->make(\App\Http\Controllers\TiempoController::class);
        $view = $controller->vistaGeneral($request);
        $content = $view->render();

        $html = $this->wrapHtml('Vista General - ' . now()->format('d/m/Y'), $content);

        $filename = 'Vista_General_' . now()->format('Ymd') . '.html';
        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, ['Content-Type' => 'text/html']);
    }

    public function exportarDashboardHtml(Request $request)
    {
        $controller = app()->make(\App\Http\Controllers\TiempoController::class);
        $view = $controller->dashboard($request);
        $content = $view->render();

        $html = $this->wrapHtml('Dashboard - ' . now()->format('d/m/Y'), $content);

        $filename = 'Dashboard_' . now()->format('Ymd') . '.html';
        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, ['Content-Type' => 'text/html']);
    }

    public function exportarNominaExcel(Request $request)
    {
        $anio = $request->integer('anio', now()->year);
        $semana = $request->integer('semana', now()->weekOfYear);
        $semanaFin = $request->integer('semana_fin', $semana);
        $personalFiltro = $request->integer('personal_id', 0);

        if ($semanaFin < $semana) $semanaFin = $semana;

        $inicioSemana = Carbon::now()->setISODate($anio, $semana, 1);
        $finSemana = Carbon::now()->setISODate($anio, $semanaFin, 5);

        $dias = [];
        for ($d = $inicioSemana->copy(); $d->lte($finSemana); $d->addDay()) {
            if ($d->isWeekday()) $dias[] = $d->copy();
        }

        $empleados = Personal::where('activo', true)->orderBy('equipo')->orderBy('nombre')->get();
        if ($personalFiltro) {
            $empleados = $empleados->where('id', $personalFiltro);
        }

        $registros = NominaDiaria::whereBetween('fecha', [$inicioSemana->format('Y-m-d'), $finSemana->format('Y-m-d')]);
        if ($personalFiltro) {
            $registros = $registros->where('personal_id', $personalFiltro);
        }
        $registros = $registros->with(['proyecto', 'categoria'])->get()
            ->keyBy(fn($r) => $r->personal_id . '_' . $r->fecha->format('Y-m-d'));

        $proyectos = Proyecto::where('status', 'activo')->orderBy('nombre')->get();

        // Build Excel data
        $rows = [];
        $rows[] = ['Empleado', 'Equipo', 'Fecha', 'Dia', 'Asignacion', 'Horas Extra', 'Proyecto HE', 'Costo'];

        $diasNombre = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie'];

        foreach ($empleados as $emp) {
            foreach ($dias as $dia) {
                $key = $emp->id . '_' . $dia->format('Y-m-d');
                $reg = $registros[$key] ?? null;

                $asignacion = '';
                if ($reg) {
                    if ($reg->proyecto_id) {
                        $asignacion = $reg->proyecto->nombre ?? 'Proyecto #' . $reg->proyecto_id;
                    } elseif ($reg->categoria_id) {
                        $asignacion = $reg->categoria->nombre ?? 'Cat #' . $reg->categoria_id;
                    }
                }

                $rows[] = [
                    $emp->nombre,
                    $emp->equipo,
                    $dia->format('Y-m-d'),
                    $diasNombre[$dia->dayOfWeekIso - 1] ?? '',
                    $asignacion,
                    $reg ? floatval($reg->horas_extra) : 0,
                    $reg && $reg->proyecto_he_id ? ($proyectos->find($reg->proyecto_he_id)?->nombre ?? '') : '',
                    $reg ? floatval($reg->costo_total) : 0,
                ];
            }
        }

        $filename = 'Nomina_S' . $semana . ($semanaFin > $semana ? '-S' . $semanaFin : '') . '_' . $anio . '.xlsx';

        return Excel::download(new \App\Exports\ArrayExport($rows), $filename);
    }

    private function wrapHtml(string $title, string $content): string
    {
        // Extract just the body content and add Tailwind CDN for styling
        return '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>' . e($title) . '</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; padding: 16px; }
        @media print { body { padding: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
    <h1 style="font-size:18px;font-weight:bold;margin-bottom:8px;">' . e($title) . '</h1>
    ' . $content . '
    <script>
        // Remove interactive elements for the export
        document.querySelectorAll("button, select, input, form, .assign-popover, .shift-form, .add-mueble-form, .materiales-form").forEach(el => el.remove());
        document.querySelectorAll("a").forEach(a => { a.removeAttribute("href"); a.style.pointerEvents = "none"; });
    </script>
</body>
</html>';
    }
}
