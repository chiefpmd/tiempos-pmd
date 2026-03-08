<?php

namespace App\Http\Controllers;

use App\Models\CategoriaNomina;

use App\Models\NominaDiaria;
use App\Models\Personal;
use App\Models\Proyecto;
use App\Models\Tiempo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NominaController extends Controller
{
    public function semanal(Request $request)
    {
        $anio = $request->integer('anio', now()->year);
        $semana = $request->integer('semana', now()->weekOfYear);

        $inicioSemana = Carbon::now()->setISODate($anio, $semana, 1); // Monday
        $finSemana = $inicioSemana->copy()->addDays(4); // Friday

        $dias = [];
        for ($d = $inicioSemana->copy(); $d->lte($finSemana); $d->addDay()) {
            $dias[] = $d->copy();
        }

        $empleados = Personal::where('activo', true)->orderBy('equipo')->orderBy('nombre')->get();

        $registros = NominaDiaria::whereBetween('fecha', [$inicioSemana->format('Y-m-d'), $finSemana->format('Y-m-d')])
            ->get()
            ->keyBy(fn($r) => $r->personal_id . '_' . $r->fecha->format('Y-m-d'));

        $proyectos = Proyecto::where('status', 'activo')->orderBy('nombre')->get();
        $categorias = CategoriaNomina::where('activa', true)->orderBy('nombre')->get();

        return view('nomina.semanal', compact(
            'anio', 'semana', 'dias', 'empleados', 'registros',
            'proyectos', 'categorias', 'inicioSemana', 'finSemana'
        ));
    }

    public function guardar(Request $request)
    {
        $request->validate([
            'personal_id' => 'required|exists:personal,id',
            'fecha' => 'required|date',
            'asignacion_tipo' => 'nullable|in:proyecto,categoria',
            'asignacion_id' => 'nullable|integer',
            'horas_extra' => 'nullable|numeric|min:0|max:24',
            'proyecto_he_id' => 'nullable|exists:proyectos,id',
        ]);

        $fecha = Carbon::parse($request->fecha);
        $proyectoId = null;
        $categoriaId = null;

        if ($request->asignacion_tipo === 'proyecto' && $request->asignacion_id) {
            $proyectoId = $request->asignacion_id;
        } elseif ($request->asignacion_tipo === 'categoria' && $request->asignacion_id) {
            $categoriaId = $request->asignacion_id;
        }

        $registro = NominaDiaria::updateOrCreate(
            [
                'personal_id' => $request->personal_id,
                'fecha' => $fecha->format('Y-m-d'),
            ],
            [
                'semana' => $fecha->weekOfYear,
                'proyecto_id' => $proyectoId,
                'categoria_id' => $categoriaId,
                'horas_extra' => $request->horas_extra ?? 0,
                'proyecto_he_id' => $request->proyecto_he_id,
            ]
        );

        $registro->load('personal', 'proyecto', 'categoria');

        return response()->json([
            'ok' => true,
            'registro' => $registro,
            'costo_dia' => $registro->costo_dia,
            'costo_he' => $registro->costo_he,
            'costo_total' => $registro->costo_total,
        ]);
    }

    public function prellenar(Request $request)
    {
        $anio = $request->integer('anio', now()->year);
        $semana = $request->integer('semana', now()->weekOfYear);

        $inicioSemana = Carbon::now()->setISODate($anio, $semana, 1);
        $finSemana = $inicioSemana->copy()->addDays(4);
        $fechaInicio = $inicioSemana->format('Y-m-d');
        $fechaFin = $finSemana->format('Y-m-d');

        $trabajadores = Personal::where('activo', true)->whereNotNull('lider_id')->get();
        $trabajadorIds = $trabajadores->pluck('id');

        // Existing nomina entries with assignments (skip these)
        $nominaExistentes = NominaDiaria::whereIn('personal_id', $trabajadorIds)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->where(function ($q) {
                $q->whereNotNull('proyecto_id')
                  ->orWhereNotNull('categoria_id');
            })
            ->get()
            ->groupBy(fn($r) => $r->personal_id . '_' . $r->fecha->format('Y-m-d'));

        // Leader time entries for the week (top project per leader per day)
        $liderIds = $trabajadores->pluck('lider_id')->unique();

        // Also pre-fill leaders themselves from their own time entries
        $lideres = Personal::where('activo', true)->where('es_lider', true)->get();
        $liderIds = $liderIds->merge($lideres->pluck('id'))->unique();

        $tiemposLideres = Tiempo::whereIn('tiempos.personal_id', $liderIds)
            ->whereBetween('tiempos.fecha', [$fechaInicio, $fechaFin])
            ->join('muebles', 'tiempos.mueble_id', '=', 'muebles.id')
            ->select('tiempos.personal_id', 'tiempos.fecha', 'muebles.proyecto_id', DB::raw('SUM(tiempos.horas) as total_horas'))
            ->groupBy('tiempos.personal_id', 'tiempos.fecha', 'muebles.proyecto_id')
            ->orderByDesc('total_horas')
            ->get()
            ->groupBy(fn($t) => $t->personal_id . '_' . $t->fecha->format('Y-m-d'));

        $count = 0;
        $allPersonnel = $trabajadores->merge($lideres)->unique('id');

        // Check existing nomina for leaders too
        $nominaLideres = NominaDiaria::whereIn('personal_id', $lideres->pluck('id'))
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->where(function ($q) {
                $q->whereNotNull('proyecto_id')
                  ->orWhereNotNull('categoria_id');
            })
            ->get()
            ->groupBy(fn($r) => $r->personal_id . '_' . $r->fecha->format('Y-m-d'));

        $allExistentes = $nominaExistentes->merge($nominaLideres);

        foreach ($allPersonnel as $persona) {
            // For workers, use their lider_id; for leaders, use their own id
            $liderIdParaBuscar = $persona->es_lider ? $persona->id : $persona->lider_id;

            for ($d = $inicioSemana->copy(); $d->lte($finSemana); $d->addDay()) {
                $fechaStr = $d->format('Y-m-d');
                $key = $persona->id . '_' . $fechaStr;

                if ($allExistentes->has($key)) continue;

                $liderKey = $liderIdParaBuscar . '_' . $fechaStr;
                $topProyecto = $tiemposLideres->get($liderKey)?->first();

                if ($topProyecto) {
                    NominaDiaria::updateOrCreate(
                        ['personal_id' => $persona->id, 'fecha' => $fechaStr],
                        [
                            'semana' => $d->weekOfYear,
                            'proyecto_id' => $topProyecto->proyecto_id,
                            'categoria_id' => null,
                            'horas_extra' => 0,
                            'proyecto_he_id' => null,
                        ]
                    );
                    $count++;
                }
            }
        }

        return response()->json([
            'ok' => true,
            'count' => $count,
            'message' => "Se pre-llenaron {$count} registros desde tiempos.",
        ]);
    }

    public function reporte(Request $request)
    {
        $anio = $request->integer('anio', now()->year);
        $semanaInicio = $request->integer('semana_inicio', 1);
        $semanaFin = $request->integer('semana_fin', now()->weekOfYear);

        $registros = NominaDiaria::with(['personal', 'proyecto', 'categoria', 'proyectoHe'])
            ->where('semana', '>=', $semanaInicio)
            ->where('semana', '<=', $semanaFin)
            ->whereHas('personal', fn($q) => $q->whereNotNull('nomina_bruta_semanal'))
            ->get();

        $semanasConDatos = $registros->pluck('semana')->unique()->sort()->values();

        $costoProyectos = [];
        $costoNoProd = [];
        $costoHe = [];
        $totalGeneral = 0;

        foreach ($registros as $r) {
            $sem = $r->semana;

            if ($r->proyecto_id && $r->costo_dia > 0) {
                $nombre = $r->proyecto?->nombre ?? 'Sin Proyecto';
                $costoProyectos[$nombre][$sem] = ($costoProyectos[$nombre][$sem] ?? 0) + $r->costo_dia;
                $totalGeneral += $r->costo_dia;
            }

            if ($r->categoria_id && $r->costo_dia > 0) {
                $nombre = $r->categoria?->nombre ?? 'Sin Categoría';
                $costoNoProd[$nombre][$sem] = ($costoNoProd[$nombre][$sem] ?? 0) + $r->costo_dia;
                $totalGeneral += $r->costo_dia;
            }

            if ($r->costo_he > 0) {
                $nombreHe = $r->proyectoHe?->nombre ?? $r->proyecto?->nombre ?? 'Sin Proyecto HE';
                $costoHe[$nombreHe][$sem] = ($costoHe[$nombreHe][$sem] ?? 0) + $r->costo_he;
                $totalGeneral += $r->costo_he;
            }
        }

        ksort($costoProyectos);
        ksort($costoNoProd);
        ksort($costoHe);

        return view('nomina.reporte', compact(
            'anio', 'semanaInicio', 'semanaFin', 'semanasConDatos',
            'costoProyectos', 'costoNoProd', 'costoHe', 'totalGeneral'
        ));
    }

    public function exportarReporte(Request $request)
    {
        $anio = $request->integer('anio', now()->year);
        $semanaInicio = $request->integer('semana_inicio', 1);
        $semanaFin = $request->integer('semana_fin', now()->weekOfYear);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\NominaReporteExport($semanaInicio, $semanaFin, $anio),
            "reporte_nomina_sem{$semanaInicio}-{$semanaFin}_{$anio}.xlsx"
        );
    }
}
