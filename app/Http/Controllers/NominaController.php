<?php

namespace App\Http\Controllers;

use App\Models\CategoriaNomina;

use App\Models\NominaDiaria;
use App\Models\Personal;
use App\Models\Mueble;
use App\Models\Proyecto;
use App\Models\Tiempo;
use App\Models\DiaFestivo;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NominaController extends Controller
{
    public function semanal(Request $request)
    {
        $anio = $request->integer('anio', now()->year);
        $semana = $request->integer('semana', now()->weekOfYear);
        $semanaFin = $request->integer('semana_fin', $semana);
        $personalFiltro = $request->integer('personal_id', 0);

        // Ensure semana_fin >= semana
        if ($semanaFin < $semana) $semanaFin = $semana;

        $inicioSemana = Carbon::now()->setISODate($anio, $semana, 1); // Monday
        $finSemana = Carbon::now()->setISODate($anio, $semanaFin, 5); // Friday of last week

        // Get festivos in range
        $festivosEnRango = DiaFestivo::whereBetween('fecha', [$inicioSemana->format('Y-m-d'), $finSemana->format('Y-m-d')])
            ->pluck('nombre', 'fecha')
            ->mapWithKeys(fn($n, $f) => [Carbon::parse($f)->format('Y-m-d') => $n]);

        $dias = [];
        for ($d = $inicioSemana->copy(); $d->lte($finSemana); $d->addDay()) {
            if ($d->isWeekday()) {
                $dias[] = $d->copy();
            }
        }

        $todosEmpleados = Personal::where('activo', true)->orderBy('equipo')->orderBy('nombre')->get();

        if ($personalFiltro) {
            $empleados = $todosEmpleados->where('id', $personalFiltro);
        } else {
            $empleados = $todosEmpleados;
        }

        $registrosQuery = NominaDiaria::whereBetween('fecha', [$inicioSemana->format('Y-m-d'), $finSemana->format('Y-m-d')]);
        if ($personalFiltro) {
            $registrosQuery->where('personal_id', $personalFiltro);
        }
        $registros = $registrosQuery->get()
            ->keyBy(fn($r) => $r->personal_id . '_' . $r->fecha->format('Y-m-d'));

        $proyectos = Proyecto::where('status', 'activo')->orderBy('nombre')->get();
        $categorias = CategoriaNomina::where('activa', true)->orderBy('nombre')->get();

        $mueblesPorProyecto = Mueble::whereIn('proyecto_id', $proyectos->pluck('id'))
            ->orderBy('numero')
            ->get()
            ->groupBy('proyecto_id');

        return view('nomina.semanal', compact(
            'anio', 'semana', 'semanaFin', 'dias', 'empleados', 'registros',
            'proyectos', 'categorias', 'inicioSemana', 'finSemana',
            'todosEmpleados', 'personalFiltro', 'mueblesPorProyecto',
            'festivosEnRango'
        ));
    }

    public function guardar(Request $request)
    {
        $request->validate([
            'personal_id' => 'required|exists:personal,id',
            'fecha' => 'required|date',
            'asignacion_tipo' => 'nullable|in:proyecto,categoria',
            'asignacion_id' => 'nullable|integer',
            'mueble_id' => 'nullable|exists:muebles,id',
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
                'mueble_id' => $proyectoId ? $request->mueble_id : null,
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

    public function aplicarFestivos(Request $request)
    {
        $request->validate([
            'fechas' => 'required|array',
            'fechas.*' => 'date',
        ]);

        $categoriaFestivo = CategoriaNomina::where('nombre', 'Dia festivo')->first();
        if (!$categoriaFestivo) {
            return response()->json(['ok' => false, 'message' => 'Categoría "Dia festivo" no existe.'], 400);
        }

        $empleados = Personal::where('activo', true)->get();
        $count = 0;

        foreach ($request->fechas as $fechaStr) {
            $fecha = Carbon::parse($fechaStr);

            foreach ($empleados as $emp) {
                // Only create if no existing assignment
                $existe = NominaDiaria::where('personal_id', $emp->id)
                    ->where('fecha', $fecha->format('Y-m-d'))
                    ->where(function ($q) {
                        $q->whereNotNull('proyecto_id')
                          ->orWhereNotNull('categoria_id');
                    })
                    ->exists();

                if (!$existe) {
                    NominaDiaria::updateOrCreate(
                        ['personal_id' => $emp->id, 'fecha' => $fecha->format('Y-m-d')],
                        [
                            'semana' => $fecha->weekOfYear,
                            'proyecto_id' => null,
                            'mueble_id' => null,
                            'categoria_id' => $categoriaFestivo->id,
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
            'message' => "Se asignaron {$count} registros como día festivo.",
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

        $proyectosActivos = Proyecto::where('status', 'activo')->orderBy('nombre')
            ->with('muebles')
            ->get()->keyBy('nombre');

        // Presupuesto por proyecto: suma de costo_mueble
        $presupuestoPorProyecto = [];
        foreach ($proyectosActivos as $nombre => $proy) {
            $presupuestoPorProyecto[$nombre] = $proy->muebles->sum('costo_mueble') ?? 0;
        }

        return view('nomina.reporte', compact(
            'anio', 'semanaInicio', 'semanaFin', 'semanasConDatos',
            'costoProyectos', 'presupuestoPorProyecto', 'costoNoProd', 'costoHe', 'totalGeneral',
            'proyectosActivos'
        ));
    }

    public function eficiencia(Request $request)
    {
        $anio = $request->integer('anio', now()->year);
        $semanaInicio = $request->integer('semana_inicio', 1);
        $semanaFin = $request->integer('semana_fin', now()->weekOfYear);

        // 1. Get all nomina records for the period
        $registros = NominaDiaria::with(['personal', 'proyecto', 'mueble'])
            ->where('semana', '>=', $semanaInicio)
            ->where('semana', '<=', $semanaFin)
            ->whereHas('personal', fn($q) => $q->whereNotNull('nomina_bruta_semanal'))
            ->get();

        $semanasConDatos = $registros->pluck('semana')->unique()->sort()->values();

        // 2. Costo nómina per week
        $costoNominaPorSemana = [];
        foreach ($registros as $r) {
            $sem = $r->semana;
            $costoNominaPorSemana[$sem] = ($costoNominaPorSemana[$sem] ?? 0) + $r->costo_dia + $r->costo_he;
        }

        // 3. Valor producido per week: 25% del costo_mueble corresponde a nómina,
        //    repartido proporcionalmente por jornales trabajados en cada semana
        $PORCENTAJE_NOMINA = 0.25;
        $valorProducidoPorSemana = [];
        $inicioGeneral = Carbon::now()->setISODate($anio, $semanaInicio, 1);
        $finGeneral = Carbon::now()->setISODate($anio, $semanaFin, 7);

        // Jornales por mueble por semana (de nomina_diaria con proyecto asignado)
        // Registros sin mueble (vacaciones, capacitación, etc.) se contabilizan en costos pero no en valor producido
        $jornalesSinMueble = [];
        $jornalesPorMuebleSemana = [];
        $jornalesTotalPorMueble = [];
        foreach ($registros as $r) {
            if (!$r->proyecto_id) continue;
            if (!$r->mueble_id) {
                $sem = $r->semana;
                $jornalesSinMueble[$sem] = ($jornalesSinMueble[$sem] ?? 0) + 1;
                continue;
            }
            $mid = $r->mueble_id;
            $sem = $r->semana;
            $jornalesPorMuebleSemana[$mid][$sem] = ($jornalesPorMuebleSemana[$mid][$sem] ?? 0) + 1;
            $jornalesTotalPorMueble[$mid] = ($jornalesTotalPorMueble[$mid] ?? 0) + 1;
        }

        // Para cada mueble con costo, repartir el 25% entre semanas según jornales
        // También calcular nómina solo de muebles con valor (para eficiencia semanal)
        $muebleIdsConJornales = array_keys($jornalesTotalPorMueble);
        $costoNominaEficiencia = []; // solo nómina de muebles con costo_mueble
        $mueblesConCostoIds = [];
        if (!empty($muebleIdsConJornales)) {
            $mueblesConCosto = Mueble::whereIn('id', $muebleIdsConJornales)
                ->whereNotNull('costo_mueble')
                ->where('costo_mueble', '>', 0)
                ->get()
                ->keyBy('id');

            $mueblesConCostoIds = $mueblesConCosto->keys()->toArray();

            foreach ($mueblesConCosto as $mid => $mueble) {
                $valorNomina = $mueble->costo_mueble * $PORCENTAJE_NOMINA;
                $totalJornalesMueble = $jornalesTotalPorMueble[$mid];

                foreach ($jornalesPorMuebleSemana[$mid] as $sem => $jornales) {
                    $proporcion = $jornales / $totalJornalesMueble;
                    $valorProducidoPorSemana[$sem] = ($valorProducidoPorSemana[$sem] ?? 0) + ($valorNomina * $proporcion);
                }
            }

            // Nómina solo de registros con mueble que tiene costo_mueble
            foreach ($registros as $r) {
                if (!$r->mueble_id || !in_array($r->mueble_id, $mueblesConCostoIds)) continue;
                $sem = $r->semana;
                $costoNominaEficiencia[$sem] = ($costoNominaEficiencia[$sem] ?? 0) + $r->costo_dia + $r->costo_he;
            }
        }

        // Para barniz/costo por proyecto se sigue usando último barniz
        $ultimoBarniz = Tiempo::where('proceso', 'Barniz')
            ->whereBetween('fecha', [$inicioGeneral->format('Y-m-d'), $finGeneral->format('Y-m-d')])
            ->select('mueble_id', DB::raw('MAX(fecha) as ultima_fecha'))
            ->groupBy('mueble_id')
            ->get();

        // 4. Totals
        $totalNomina = array_sum($costoNominaPorSemana);
        $totalNominaEficiencia = array_sum($costoNominaEficiencia);
        $totalValor = array_sum($valorProducidoPorSemana);
        $totalMargen = $totalValor - $totalNominaEficiencia;
        $totalEficiencia = $totalNominaEficiencia > 0 ? ($totalValor / $totalNominaEficiencia) * 100 : 0;

        // 5. Costo por proceso por semana: jornales desde nomina_diaria
        //    agrupado por equipo del personal y semana
        $costoPorProceso = [];  // [equipo => [sem => ['jornales' => X, 'costo' => Y]]]
        foreach ($registros as $r) {
            if (!$r->proyecto_id) continue;
            $equipo = $r->personal?->equipo ?? 'Sin equipo';
            $sem = $r->semana;
            if (!isset($costoPorProceso[$equipo][$sem])) {
                $costoPorProceso[$equipo][$sem] = ['jornales' => 0, 'costo' => 0];
            }
            $costoPorProceso[$equipo][$sem]['jornales']++;
            $costoPorProceso[$equipo][$sem]['costo'] += $r->costo_dia + $r->costo_he;
        }
        ksort($costoPorProceso);

        // Totales por proceso (todas las semanas)
        $totalPorProceso = [];
        $totalCostoProceso = 0;
        $totalJornales = 0;
        foreach ($costoPorProceso as $equipo => $semanas) {
            $totalPorProceso[$equipo] = [
                'jornales' => array_sum(array_column($semanas, 'jornales')),
                'costo' => array_sum(array_column($semanas, 'costo')),
            ];
            $totalCostoProceso += $totalPorProceso[$equipo]['costo'];
            $totalJornales += $totalPorProceso[$equipo]['jornales'];
        }

        // 6. Costo por proyecto: from nomina_diaria grouped by proyecto
        $costoPorProyecto = [];
        foreach ($registros as $r) {
            if (!$r->proyecto_id) continue;
            $nombre = $r->proyecto?->nombre ?? 'Sin Proyecto';
            $proyId = $r->proyecto_id;

            if (!isset($costoPorProyecto[$nombre])) {
                $costoPorProyecto[$nombre] = ['nomina' => 0, 'valor_muebles' => 0, 'mueble_ids' => []];
            }
            $costoPorProyecto[$nombre]['nomina'] += $r->costo_dia + $r->costo_he;

            if ($r->mueble_id && !in_array($r->mueble_id, $costoPorProyecto[$nombre]['mueble_ids'])) {
                $costoPorProyecto[$nombre]['mueble_ids'][] = $r->mueble_id;
            }
        }

        // Get valor muebles per project (solo muebles que ya pasaron por barniz)
        $mueblesBarnizados = $ultimoBarniz->pluck('mueble_id')->toArray();

        // Single query: get costo_mueble for all barnized muebles at once
        $allBarnizIds = [];
        foreach ($costoPorProyecto as $nombre => $data) {
            $allBarnizIds = array_merge($allBarnizIds, array_intersect($data['mueble_ids'], $mueblesBarnizados));
        }
        $costosMuebles = !empty($allBarnizIds)
            ? Mueble::whereIn('id', array_unique($allBarnizIds))
                ->whereNotNull('costo_mueble')
                ->pluck('costo_mueble', 'id')
            : collect();

        foreach ($costoPorProyecto as $nombre => &$data) {
            $idsConBarniz = array_intersect($data['mueble_ids'], $mueblesBarnizados);
            $data['valor_muebles'] = $costosMuebles->only($idsConBarniz)->sum();
            unset($data['mueble_ids']);
        }
        unset($data);

        arsort($costoPorProyecto);

        // 7. Muebles en producción: todos los muebles de proyectos activos
        $enProduccionIds = Mueble::join('proyectos', 'muebles.proyecto_id', '=', 'proyectos.id')
            ->where('proyectos.status', 'activo')
            ->pluck('muebles.id');

        $mueblesEnProduccion = [];
        if ($enProduccionIds->isNotEmpty()) {
            $muebles = Mueble::with('proyecto')->whereIn('id', $enProduccionIds)->get()->keyBy('id');

            // Jornales y costo desde nomina_diaria (solo Carpintería y Barniz)
            $nominaPorMueble = NominaDiaria::with('personal')
                ->whereIn('mueble_id', $enProduccionIds)
                ->whereHas('personal', fn($q) => $q->whereNotNull('nomina_bruta_semanal')
                    ->whereIn('equipo', ['Carpintería', 'Barniz']))
                ->get()
                ->groupBy('mueble_id');

            // Procesos proyectados desde tiempos (Carpintería y Barniz - vista general)
            $horasProceso = Tiempo::whereIn('mueble_id', $enProduccionIds)
                ->whereIn('proceso', ['Carpintería', 'Barniz'])
                ->select('mueble_id', 'proceso', DB::raw('SUM(horas) as total_horas'))
                ->groupBy('mueble_id', 'proceso')
                ->get()
                ->groupBy('mueble_id');

            // "Otros" desde nomina_diaria: departamentos que no son Carpintería, Barniz ni Instalación
            $otrosJornales = NominaDiaria::with('personal')
                ->whereIn('mueble_id', $enProduccionIds)
                ->whereNotNull('proyecto_id')
                ->whereHas('personal', fn($q) => $q->whereNotNull('nomina_bruta_semanal')
                    ->whereNotIn('equipo', ['Carpintería', 'Barniz', 'Instalación']))
                ->get()
                ->groupBy('mueble_id')
                ->map(fn($regs) => [
                    'jornales' => $regs->count(),
                    'costo' => $regs->sum(fn($r) => $r->costo_dia + $r->costo_he),
                ]);

            foreach ($enProduccionIds as $mid) {
                $mueble = $muebles->get($mid);
                if (!$mueble) continue;

                $regs = $nominaPorMueble->get($mid, collect());
                $jornales = $regs->filter(fn($r) => $r->proyecto_id)->count();
                $costo = $regs->sum(fn($r) => $r->costo_dia + $r->costo_he);

                // Procesos proyectados (Carpintería y Barniz de tiempos)
                $procesos = $horasProceso->get($mid, collect());
                $partes = $procesos->map(fn($p) => $p->proceso . ' ' . intval($p->total_horas) . 'j')
                    ->toArray();

                // Agregar "Otros" de nomina si hay
                $otros = $otrosJornales->get($mid);
                $otrosJornalesCount = $otros['jornales'] ?? 0;
                $otrosCosto = $otros['costo'] ?? 0;
                if ($otrosJornalesCount > 0) {
                    $partes[] = 'Otros ' . $otrosJornalesCount . 'j';
                }
                $procesoStr = implode(' | ', $partes);

                // Sumar costo de "Otros" al costo total del mueble
                $costoTotal = (float) $costo + $otrosCosto;
                $jornalesTotal = $jornales + $otrosJornalesCount;

                $mueblesEnProduccion[] = [
                    'mueble' => $mueble->numero . ' - ' . $mueble->descripcion,
                    'proyecto' => $mueble->proyecto?->nombre ?? 'Sin Proyecto',
                    'jornales' => $jornalesTotal,
                    'procesos' => $procesoStr,
                    'costo_nomina' => $costoTotal,
                    'valor_mueble' => $mueble->costo_mueble ?? 0,
                    'jornales_presupuesto' => $mueble->jornales_presupuesto ?? 0,
                ];
            }

            // Ordenar por proyecto (alfabético) y luego por mueble
            usort($mueblesEnProduccion, fn($a, $b) => $a['proyecto'] <=> $b['proyecto'] ?: $a['mueble'] <=> $b['mueble']);
        }

        return view('nomina.eficiencia', compact(
            'anio', 'semanaInicio', 'semanaFin', 'semanasConDatos',
            'costoNominaPorSemana', 'costoNominaEficiencia', 'valorProducidoPorSemana',
            'totalNomina', 'totalNominaEficiencia', 'totalValor', 'totalMargen', 'totalEficiencia',
            'costoPorProceso', 'totalPorProceso', 'totalCostoProceso', 'totalJornales',
            'costoPorProyecto', 'mueblesEnProduccion'
        ));
    }

    public function costoMuebles(Request $request, Proyecto $proyecto)
    {
        $anio = $request->integer('anio', now()->year);
        $semanaInicio = $request->integer('semana_inicio', 1);
        $semanaFin = $request->integer('semana_fin', now()->weekOfYear);

        $muebles = $proyecto->muebles()->orderBy('numero')->get();
        $muebleIds = $muebles->pluck('id');

        $registros = NominaDiaria::with('personal')
            ->whereIn('mueble_id', $muebleIds)
            ->where('semana', '>=', $semanaInicio)
            ->where('semana', '<=', $semanaFin)
            ->whereHas('personal', fn($q) => $q->whereNotNull('nomina_bruta_semanal'))
            ->get();

        $semanasConDatos = $registros->pluck('semana')->unique()->sort()->values();

        // Build cost per mueble per week
        $costosPorMueble = [];
        foreach ($registros as $r) {
            $mid = $r->mueble_id;
            $sem = $r->semana;
            $costo = $r->costo_dia + $r->costo_he;
            $costosPorMueble[$mid][$sem] = ($costosPorMueble[$mid][$sem] ?? 0) + $costo;
        }

        return view('nomina.costo-muebles', compact(
            'proyecto', 'muebles', 'costosPorMueble', 'semanasConDatos',
            'anio', 'semanaInicio', 'semanaFin'
        ));
    }

    public function guardarCostoMueble(Request $request, Mueble $mueble)
    {
        $request->validate([
            'costo_mueble' => 'nullable|numeric|min:0',
            'jornales_presupuesto' => 'nullable|numeric|min:0',
        ]);

        $fields = [];
        if ($request->has('costo_mueble')) {
            $fields['costo_mueble'] = $request->costo_mueble;
        }
        if ($request->has('jornales_presupuesto')) {
            $fields['jornales_presupuesto'] = $request->jornales_presupuesto;
        }
        $mueble->update($fields);

        return response()->json(['ok' => true, 'costo_mueble' => $mueble->costo_mueble, 'jornales_presupuesto' => $mueble->jornales_presupuesto]);
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

    public function ganttNomina(Request $request)
    {
        $proyectos = Proyecto::where('status', 'activo')
            ->with(['muebles' => fn($q) => $q->orderBy('numero')])
            ->orderBy('fecha_inicio')
            ->get();

        if ($proyectos->isEmpty()) {
            return view('nomina.gantt-nomina', [
                'proyectos' => $proyectos,
                'diasHabiles' => [],
                'nominaMap' => [],
                'festivos' => collect(),
            ]);
        }

        // Window: 4 weeks back from today + forward to last project end
        $defaultStart = Carbon::now()->startOfWeek()->subWeeks(4);
        $ventanaInicio = $request->has('desde')
            ? Carbon::parse($request->input('desde'))->startOfWeek()
            : $defaultStart;

        $rangoMin = $proyectos->min('fecha_inicio');
        $ventanaFin = $proyectos->max(fn($p) => $p->fecha_fin);

        if ($ventanaInicio->lt($rangoMin)) {
            $ventanaInicio = $rangoMin->copy();
        }

        $festivos = DiaFestivo::whereBetween('fecha', [$ventanaInicio, $ventanaFin])
            ->pluck('nombre', 'fecha')
            ->mapWithKeys(fn($n, $f) => [Carbon::parse($f)->format('Y-m-d') => $n]);

        $diasHabiles = [];
        foreach (CarbonPeriod::create($ventanaInicio, $ventanaFin) as $dia) {
            if ($dia->isWeekday()) {
                $diasHabiles[] = $dia->copy();
            }
        }

        $allMuebleIds = $proyectos->flatMap(fn($p) => $p->muebles->pluck('id'));

        // Get all nomina entries for these muebles
        $nomina = NominaDiaria::with('personal')
            ->whereIn('mueble_id', $allMuebleIds)
            ->whereNotNull('proyecto_id')
            ->whereBetween('fecha', [$ventanaInicio, $ventanaFin])
            ->get();

        // Map: mueble_id => fecha => [ equipo => [ personas => [nombre, ...], count => N ] ]
        $nominaMap = [];
        foreach ($nomina as $nr) {
            $mid = $nr->mueble_id;
            $fecha = $nr->fecha->format('Y-m-d');
            $equipo = $nr->personal?->equipo ?? 'Otro';
            $nombre = $nr->personal?->nombre ?? '?';

            if (!isset($nominaMap[$mid][$fecha][$equipo])) {
                $nominaMap[$mid][$fecha][$equipo] = ['personas' => [], 'count' => 0];
            }
            $nominaMap[$mid][$fecha][$equipo]['personas'][] = $nombre;
            $nominaMap[$mid][$fecha][$equipo]['count']++;
        }

        // Navigation
        $canGoBack = $ventanaInicio->gt($rangoMin);
        $prevDesde = $ventanaInicio->copy()->subWeeks(2)->format('Y-m-d');
        $nextDesde = $ventanaInicio->copy()->addWeeks(2)->format('Y-m-d');
        $todayDesde = $defaultStart->format('Y-m-d');
        $allDesde = $rangoMin->format('Y-m-d');

        // Colors per project
        $colores = ['#3b82f6','#f59e0b','#10b981','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16'];
        $proyectoColores = [];
        foreach ($proyectos as $i => $proy) {
            $proyectoColores[$proy->id] = $colores[$i % count($colores)];
        }

        // Equipo colors
        $equipoColores = [
            'Carpintería' => '#f59e0b',
            'Barniz' => '#10b981',
            'Instalación' => '#3b82f6',
            'Armado' => '#8b5cf6',
            'Vidrio' => '#06b6d4',
            'Herrero' => '#6b7280',
            'Eléctrico' => '#ec4899',
        ];

        return view('nomina.gantt-nomina', compact(
            'proyectos', 'diasHabiles', 'nominaMap', 'festivos',
            'canGoBack', 'prevDesde', 'nextDesde', 'todayDesde', 'allDesde',
            'proyectoColores', 'equipoColores'
        ));
    }
}
