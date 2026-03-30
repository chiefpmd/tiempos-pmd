<?php

namespace App\Http\Controllers;

use App\Models\DiaFestivo;
use App\Models\NominaDiaria;
use App\Models\Proyecto;
use App\Models\Mueble;
use App\Models\Personal;
use App\Models\Tiempo;
use App\Models\TiempoShift;
use App\Models\GanttAnual;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TiempoController extends Controller
{
    public function captura(Proyecto $proyecto, Request $request)
    {
        $muebles = $proyecto->muebles()->orderBy('numero')->get();
        $personal = Personal::where('activo', true)->orderBy('equipo')->orderBy('nombre')->get();
        $procesos = ['Carpintería', 'Barniz', 'Instalación'];

        // Get existing ranges per mueble+proceso
        $rangos = Tiempo::whereIn('mueble_id', $muebles->pluck('id'))
            ->where('horas', '>', 0)
            ->select('mueble_id', 'proceso',
                DB::raw('MIN(fecha) as fecha_inicio'),
                DB::raw('MAX(fecha) as fecha_fin'),
                DB::raw('MAX(horas) as personas'))
            ->groupBy('mueble_id', 'proceso')
            ->get()
            ->keyBy(fn($r) => "{$r->mueble_id}_{$r->proceso}");

        // Get most frequent personal per mueble+proceso in a single query
        $topPersonal = Tiempo::whereIn('mueble_id', $muebles->pluck('id'))
            ->where('horas', '>', 0)
            ->select('mueble_id', 'proceso', 'personal_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('mueble_id', 'proceso', 'personal_id')
            ->orderByDesc('cnt')
            ->get()
            ->groupBy(fn($r) => "{$r->mueble_id}_{$r->proceso}");

        foreach ($rangos as $key => $rango) {
            $rango->personal_id = $topPersonal->get($key)?->first()?->personal_id;
        }

        return view('tiempos.captura', compact(
            'proyecto', 'muebles', 'personal', 'procesos', 'rangos'
        ));
    }

    public function guardar(Request $request)
    {
        $data = $request->validate([
            'mueble_id' => 'required|exists:muebles,id',
            'proceso' => 'required|in:Carpintería,Barniz,Instalación',
            'personal_id' => 'required|exists:personal,id',
            'fecha' => 'required|date',
            'horas' => 'required|numeric|min:0|max:24',
        ]);

        if ((float)$data['horas'] < 0.01) {
            Tiempo::where([
                'mueble_id' => $data['mueble_id'],
                'proceso' => $data['proceso'],
                'personal_id' => $data['personal_id'],
                'fecha' => $data['fecha'],
            ])->delete();
            return response()->json(['ok' => true, 'deleted' => true]);
        }

        $tiempo = Tiempo::updateOrCreate(
            [
                'mueble_id' => $data['mueble_id'],
                'proceso' => $data['proceso'],
                'personal_id' => $data['personal_id'],
                'fecha' => $data['fecha'],
            ],
            ['horas' => $data['horas']]
        );

        return response()->json(['ok' => true, 'id' => $tiempo->id]);
    }

    public function reasignarEquipo(Request $request)
    {
        $data = $request->validate([
            'mueble_id' => 'required|exists:muebles,id',
            'proceso' => 'required|in:Carpintería,Barniz,Instalación',
            'personal_id' => 'required|exists:personal,id',
            'personal_anterior_id' => 'nullable|integer',
        ]);

        $updated = 0;
        if ($data['personal_anterior_id']) {
            $updated = Tiempo::where('mueble_id', $data['mueble_id'])
                ->where('proceso', $data['proceso'])
                ->where('personal_id', $data['personal_anterior_id'])
                ->update(['personal_id' => $data['personal_id']]);
        }

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    public function guardarRango(Request $request)
    {
        $data = $request->validate([
            'mueble_id' => 'required|exists:muebles,id',
            'proceso' => 'required|in:Carpintería,Barniz,Instalación',
            'personal_id' => 'required|exists:personal,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'personas' => 'required|numeric|min:0.5|max:24',
        ]);

        // Delete existing entries for this mueble+proceso
        Tiempo::where('mueble_id', $data['mueble_id'])
            ->where('proceso', $data['proceso'])
            ->delete();

        // Create entries for each working day in the range (skip weekends and holidays)
        $periodo = CarbonPeriod::create($data['fecha_inicio'], $data['fecha_fin']);
        $created = 0;
        foreach ($periodo as $dia) {
            if (DiaFestivo::esDiaLaborable($dia)) {
                Tiempo::create([
                    'mueble_id' => $data['mueble_id'],
                    'proceso' => $data['proceso'],
                    'personal_id' => $data['personal_id'],
                    'fecha' => $dia->format('Y-m-d'),
                    'horas' => $data['personas'],
                ]);
                $created++;
            }
        }

        return response()->json(['ok' => true, 'dias_creados' => $created]);
    }

    public function borrarRango(Request $request)
    {
        $data = $request->validate([
            'mueble_id' => 'required|exists:muebles,id',
            'proceso' => 'required|in:Carpintería,Barniz,Instalación',
        ]);

        Tiempo::where('mueble_id', $data['mueble_id'])
            ->where('proceso', $data['proceso'])
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function dashboard(Request $request)
    {
        $proyectos = Proyecto::where('status', 'activo')->orderBy('fecha_inicio')->get();

        if ($proyectos->isEmpty()) {
            return view('tiempos.dashboard', [
                'personal' => collect(),
                'diasHabiles' => [],
                'disponibilidad' => [],
                'proyectos' => $proyectos,
            ]);
        }

        $rangoMin = $proyectos->min('fecha_inicio');
        $rangoMax = $proyectos->max(fn($p) => $p->fecha_fin);

        // Rolling window: 2 weeks back from today to furthest project end
        $defaultStart = Carbon::now()->startOfWeek()->subWeeks(2);
        $fechaMin = $request->has('desde')
            ? Carbon::parse($request->input('desde'))->startOfWeek()
            : $defaultStart;
        $fechaMax = $rangoMax;

        if ($fechaMin->lt($rangoMin)) {
            $fechaMin = $rangoMin->copy();
        }

        $canGoBack = $fechaMin->gt($rangoMin);
        $prevDesde = $fechaMin->copy()->subWeeks(2)->format('Y-m-d');
        $nextDesde = $fechaMin->copy()->addWeeks(2)->format('Y-m-d');
        $todayDesde = $defaultStart->format('Y-m-d');
        $allDesde = $rangoMin->format('Y-m-d');

        $festivos = DiaFestivo::whereBetween('fecha', [$fechaMin, $fechaMax])->pluck('nombre', 'fecha')->mapWithKeys(fn($n, $f) => [Carbon::parse($f)->format('Y-m-d') => $n]);

        $diasHabiles = [];
        $periodo = CarbonPeriod::create($fechaMin, $fechaMax);
        foreach ($periodo as $dia) {
            if ($dia->isWeekday()) {
                $diasHabiles[] = $dia->copy();
            }
        }

        // Only show leaders (each represents their team)
        $lideres = Personal::where('activo', true)->where('es_lider', true)
            ->orderBy('equipo')->orderBy('nombre')->get();

        // Also include departments with no leader (solo workers)
        $deptsSinLider = Personal::where('activo', true)
            ->whereNotIn('equipo', $lideres->pluck('equipo')->unique())
            ->orderBy('equipo')->orderBy('nombre')->get();

        $personal = $lideres->merge($deptsSinLider);

        $tiempos = Tiempo::whereIn('personal_id', $personal->pluck('id'))
            ->whereBetween('fecha', [$fechaMin, $fechaMax])
            ->where('horas', '>', 0)
            ->with('mueble.proyecto')
            ->get();

        // Pre-index tiempos by personal_id + fecha for O(1) lookup
        $tiemposIndex = [];
        foreach ($tiempos as $t) {
            $key = $t->personal_id . '_' . $t->fecha->format('Y-m-d');
            $tiemposIndex[$key][] = $t;
        }

        // Load nomina data for people without tiempos (non-Vista-General departments)
        $nominaEntries = NominaDiaria::whereIn('personal_id', $personal->pluck('id'))
            ->whereBetween('fecha', [$fechaMin, $fechaMax])
            ->whereNotNull('proyecto_id')
            ->with('proyecto')
            ->get();

        $nominaIndex = [];
        foreach ($nominaEntries as $n) {
            $key = $n->personal_id . '_' . $n->fecha->format('Y-m-d');
            $nominaIndex[$key] = $n;
        }

        $disponibilidad = [];
        foreach ($personal as $p) {
            foreach ($diasHabiles as $dia) {
                $fechaStr = $dia->format('Y-m-d');
                $registros = $tiemposIndex[$p->id . '_' . $fechaStr] ?? [];

                $nombres = [];
                $totalHoras = 0;

                $source = 'tiempos';

                if (!empty($registros)) {
                    foreach ($registros as $t) {
                        $nombres[] = $t->mueble->proyecto->nombre;
                        $totalHoras += $t->horas;
                    }
                } else {
                    $nomina = $nominaIndex[$p->id . '_' . $fechaStr] ?? null;
                    if ($nomina && $nomina->proyecto) {
                        $nombres[] = $nomina->proyecto->nombre;
                        $source = 'nomina';
                    }
                }

                $nombres = array_values(array_unique($nombres));

                $disponibilidad[$p->id][$fechaStr] = [
                    'proyectos' => count($nombres),
                    'nombres' => $nombres,
                    'horas' => $totalHoras,
                    'source' => $source,
                ];
            }
        }

        // Team member counts per leader
        $teamCounts = Personal::where('activo', true)->whereNotNull('lider_id')
            ->selectRaw('lider_id, count(*) as total')
            ->groupBy('lider_id')
            ->pluck('total', 'lider_id');

        // === CAPACITY DATA: all active personnel (not just leaders) ===
        $todosActivos = Personal::where('activo', true)->get();
        $todosIds = $todosActivos->pluck('id');

        // Department totals
        $deptTotals = $todosActivos->groupBy('equipo')->map->count();

        // Load only tiempos/nomina for non-leader personnel (leaders already loaded above)
        $leaderIds = $personal->pluck('id');
        $nonLeaderIds = $todosIds->diff($leaderIds);

        $extraTiempos = $nonLeaderIds->isNotEmpty()
            ? Tiempo::whereIn('personal_id', $nonLeaderIds)
                ->whereBetween('fecha', [$fechaMin, $fechaMax])
                ->where('horas', '>', 0)
                ->with('mueble.proyecto')
                ->get()
            : collect();

        $extraNomina = $nonLeaderIds->isNotEmpty()
            ? NominaDiaria::whereIn('personal_id', $nonLeaderIds)
                ->whereBetween('fecha', [$fechaMin, $fechaMax])
                ->whereNotNull('proyecto_id')
                ->with('proyecto')
                ->get()
            : collect();

        // Merge with already-loaded leader data
        $allTiempos = $tiempos->merge($extraTiempos);
        $allNomina = $nominaEntries->merge($extraNomina);

        // Build per-person per-day: project/process => personas count
        // Sum horas across muebles (1 persona per mueble = N personas total)
        $personDayProject = [];
        $personDayProjectProceso = [];
        $muebleHoras = []; // [personalId][date][projName][proceso][muebleId] => max horas
        foreach ($allTiempos as $t) {
            $fechaStr = $t->fecha->format('Y-m-d');
            $projName = $t->mueble->proyecto->nombre;
            $muebleHoras[$t->personal_id][$fechaStr][$projName][$t->proceso][$t->mueble_id] = max(
                $muebleHoras[$t->personal_id][$fechaStr][$projName][$t->proceso][$t->mueble_id] ?? 0,
                $t->horas
            );
        }
        foreach ($muebleHoras as $personalId => $fechas) {
            foreach ($fechas as $fechaStr => $proyectos_data) {
                $key = $personalId . '_' . $fechaStr;
                foreach ($proyectos_data as $projName => $procesos_data) {
                    $totalProj = 0;
                    foreach ($procesos_data as $proceso => $muebles) {
                        $sumProceso = array_sum($muebles);
                        $personDayProjectProceso[$key][$projName][$proceso] = $sumProceso;
                        $totalProj += $sumProceso;
                    }
                    $personDayProject[$key][$projName] = $totalProj;
                }
            }
        }
        foreach ($allNomina as $n) {
            $key = $n->personal_id . '_' . $n->fecha->format('Y-m-d');
            if (!isset($personDayProject[$key]) && $n->proyecto) {
                $personDayProject[$key][$n->proyecto->nombre] = 1; // nomina = 1 person
            }
        }

        // Group days by week
        $semanas = collect($diasHabiles)->groupBy(fn($d) => $d->weekOfYear);

        // Project capacity per process: projName => proceso => [semana => totalPersonas]
        $proyectoCapacidadProceso = [];
        $procesos = ['Carpintería', 'Barniz', 'Instalación'];

        foreach ($semanas as $numSemana => $diasSemana) {
            $leaderMaxPerProjectProceso = []; // projName => proceso => [personalId => maxPersonas]
            $personasAsignadasPorDept = []; // equipo => [personIds]

            foreach ($diasSemana as $dia) {
                $fechaStr = $dia->format('Y-m-d');
                foreach ($todosActivos as $p) {
                    $key = $p->id . '_' . $fechaStr;

                    // Per-process tracking
                    $proyectosProceso = $personDayProjectProceso[$key] ?? [];
                    foreach ($proyectosProceso as $projNombre => $procData) {
                        foreach ($procData as $proc => $personas) {
                            $leaderMaxPerProjectProceso[$projNombre][$proc][$p->id] = max(
                                $leaderMaxPerProjectProceso[$projNombre][$proc][$p->id] ?? 0,
                                $personas
                            );
                        }
                    }

                    $proyectos_dia = $personDayProject[$key] ?? [];
                    if (!empty($proyectos_dia)) {
                        $personasAsignadasPorDept[$p->equipo][$p->id] = true;
                    }
                }
            }

            foreach ($leaderMaxPerProjectProceso as $projNombre => $procData) {
                foreach ($procData as $proc => $leaders) {
                    $proyectoCapacidadProceso[$projNombre][$proc][$numSemana] = (int) array_sum($leaders);
                }
            }
        }

        ksort($proyectoCapacidadProceso);

        $semanasNums = $semanas->keys()->sort()->values();

        return view('tiempos.dashboard', compact(
            'personal', 'diasHabiles', 'disponibilidad', 'proyectos', 'festivos',
            'teamCounts', 'proyectoCapacidadProceso', 'deptTotals', 'semanasNums',
            'canGoBack', 'prevDesde', 'nextDesde', 'todayDesde', 'allDesde'
        ));
    }

    public function recorrerFechas(Request $request, Proyecto $proyecto)
    {
        $data = $request->validate([
            'dias_habiles' => 'required|integer|min:-60|max:60',
            'procesos' => 'required|array|min:1',
            'procesos.*' => 'in:Carpintería,Barniz,Instalación',
            'mueble_ids' => 'nullable|array',
            'mueble_ids.*' => 'integer|exists:muebles,id',
        ]);

        $diasHabiles = (int) $data['dias_habiles'];
        if ($diasHabiles === 0) {
            return response()->json(['ok' => false, 'error' => 'Debe ser diferente de 0']);
        }

        $muebleIds = !empty($data['mueble_ids'])
            ? $proyecto->muebles()->whereIn('id', $data['mueble_ids'])->pluck('id')
            : $proyecto->muebles()->pluck('id');
        $tiempos = Tiempo::whereIn('mueble_id', $muebleIds)
            ->whereIn('proceso', $data['procesos'])
            ->get();

        if ($tiempos->isEmpty()) {
            return response()->json(['ok' => false, 'error' => 'No hay tiempos para recorrer']);
        }

        // Guardar snapshot completo para poder revertir
        $snapshot = $tiempos->map(fn($t) => [
            'mueble_id' => $t->mueble_id,
            'proceso' => $t->proceso,
            'personal_id' => $t->personal_id,
            'fecha' => $t->fecha->format('Y-m-d'),
            'horas' => $t->horas,
        ])->values()->toArray();

        // Calcular nuevas fechas
        $newRecords = [];
        foreach ($tiempos as $tiempo) {
            $newRecords[] = [
                'mueble_id' => $tiempo->mueble_id,
                'proceso' => $tiempo->proceso,
                'personal_id' => $tiempo->personal_id,
                'fecha' => $this->moverDiasHabiles($tiempo->fecha, $diasHabiles)->format('Y-m-d'),
                'horas' => $tiempo->horas,
            ];
        }

        try {
            DB::beginTransaction();

            $shift = TiempoShift::create([
                'proyecto_id' => $proyecto->id,
                'dias_habiles' => $diasHabiles,
                'snapshot' => $snapshot,
                'user_id' => auth()->id(),
            ]);

            // Delete old records and re-insert with new dates to avoid unique constraint collisions
            Tiempo::whereIn('id', $tiempos->pluck('id'))->delete();

            foreach ($newRecords as $rec) {
                Tiempo::create($rec);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'error' => 'Error al recorrer fechas: ' . $e->getMessage()]);
        }

        return response()->json([
            'ok' => true,
            'shift_id' => $shift->id,
            'registros' => $tiempos->count(),
        ]);
    }

    public function revertirRecorrido(TiempoShift $shift)
    {
        if ($shift->reverted) {
            return response()->json(['ok' => false, 'error' => 'Ya fue revertido']);
        }

        try {
            DB::beginTransaction();

            // Delete current records for this mueble+proceso combination and recreate from snapshot
            $snapshot = collect($shift->snapshot);
            $groups = $snapshot->groupBy(fn($e) => $e['mueble_id'] . '_' . $e['proceso']);

            foreach ($groups as $key => $entries) {
                $first = $entries->first();
                Tiempo::where('mueble_id', $first['mueble_id'])
                    ->where('proceso', $first['proceso'])
                    ->delete();
            }

            foreach ($snapshot as $entry) {
                Tiempo::create([
                    'mueble_id' => $entry['mueble_id'],
                    'proceso' => $entry['proceso'],
                    'personal_id' => $entry['personal_id'],
                    'fecha' => $entry['fecha'],
                    'horas' => $entry['horas'],
                ]);
            }

            $shift->update(['reverted' => true]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'error' => 'Error al revertir: ' . $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }

    private function moverDiasHabiles(Carbon $fecha, int $dias): Carbon
    {
        $resultado = $fecha->copy();
        $direccion = $dias > 0 ? 1 : -1;
        $restantes = abs($dias);

        while ($restantes > 0) {
            $resultado->addDays($direccion);
            if (DiaFestivo::esDiaLaborable($resultado)) {
                $restantes--;
            }
        }

        return $resultado;
    }

    public function ganttAnual(Request $request)
    {
        $anio = (int) $request->input('anio', Carbon::now()->year);

        $proyectos = Proyecto::whereIn('status', ['activo', 'completado', 'pausado'])
            ->with('ganttAnual')
            ->orderBy('fecha_inicio')
            ->get();

        // Build gantt data map: proyecto_id => {fecha_inicio, fecha_fin}
        $ganttData = [];
        foreach ($proyectos as $p) {
            if ($p->ganttAnual) {
                $ganttData[$p->id] = [
                    'fecha_inicio' => $p->ganttAnual->fecha_inicio,
                    'fecha_fin' => $p->ganttAnual->fecha_fin,
                ];
            } else {
                $ganttData[$p->id] = [
                    'fecha_inicio' => null,
                    'fecha_fin' => null,
                ];
            }
        }

        // Build weeks structure for the year
        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $mesInicio = Carbon::create($anio, $m, 1);
            $mesFin = $mesInicio->copy()->endOfMonth();
            $semanas = [];
            $semanaInicio = $mesInicio->copy()->startOfWeek(Carbon::MONDAY);
            while ($semanaInicio->lte($mesFin)) {
                $semanaFin = $semanaInicio->copy()->endOfWeek(Carbon::FRIDAY);
                if ($semanaFin->gte($mesInicio) && $semanaInicio->lte($mesFin)) {
                    $semanas[] = [
                        'inicio' => $semanaInicio->copy(),
                        'fin' => $semanaFin->copy(),
                        'label' => $semanaInicio->day . '-' . min($semanaFin->day, $mesFin->day),
                    ];
                }
                $semanaInicio->addWeek();
            }
            $meses[] = [
                'nombre' => ucfirst($mesInicio->translatedFormat('M')),
                'num' => $m,
                'semanas' => $semanas,
            ];
        }

        $totalSemanas = collect($meses)->sum(fn($m) => count($m['semanas']));

        $colores = ['#3b82f6','#ef4444','#10b981','#f59e0b','#8b5cf6','#ec4899','#06b6d4','#84cc16','#f97316','#6366f1','#14b8a6','#e11d48'];

        $isAdmin = auth()->user()->isAdmin();

        return view('tiempos.gantt-anual', compact('proyectos', 'meses', 'totalSemanas', 'anio', 'colores', 'ganttData', 'isAdmin'));
    }

    public function ganttAnualGuardar(Request $request)
    {
        $data = $request->validate([
            'proyecto_id' => 'required|exists:proyectos,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        GanttAnual::updateOrCreate(
            ['proyecto_id' => $data['proyecto_id']],
            ['fecha_inicio' => $data['fecha_inicio'], 'fecha_fin' => $data['fecha_fin']]
        );

        return response()->json(['ok' => true]);
    }

    public function vistaGeneral(Request $request)
    {
        $query = Proyecto::where('status', 'activo')
            ->with(['muebles' => function($q) {
                $q->leftJoin('tiempos', function($join) {
                    $join->on('muebles.id', '=', 'tiempos.mueble_id')
                         ->where('tiempos.proceso', '=', 'Carpintería');
                })
                ->select('muebles.*', DB::raw('MIN(tiempos.fecha) as min_carpinteria'))
                ->groupBy('muebles.id')
                ->orderByRaw('min_carpinteria IS NULL, min_carpinteria ASC');
            }, 'materiales'])
            ->orderBy('fecha_inicio');

        if ($request->filled('proyecto')) {
            $query->where('id', $request->input('proyecto'));
        }

        $proyectos = $query->get();

        if ($proyectos->isEmpty()) {
            return view('tiempos.general', ['proyectos' => $proyectos, 'diasHabiles' => [], 'tiemposMap' => [], 'ventanaInicio' => null, 'ventanaFin' => null]);
        }

        // Full project range (for data queries)
        $rangoMin = $proyectos->min('fecha_inicio');
        $rangoMax = $proyectos->max(fn($p) => $p->fecha_fin);

        // Rolling window: default = 2 weeks back from today, extends to furthest project end
        // User can override with ?desde= parameter to navigate
        $defaultStart = Carbon::now()->startOfWeek()->subWeeks(2);
        $ventanaInicio = $request->has('desde')
            ? Carbon::parse($request->input('desde'))->startOfWeek()
            : $defaultStart;

        // Always extend to the furthest active project end
        $ventanaFin = $rangoMax;

        // Clamp to not go before earliest project
        if ($ventanaInicio->lt($rangoMin)) {
            $ventanaInicio = $rangoMin->copy();
        }

        $festivos = DiaFestivo::whereBetween('fecha', [$ventanaInicio, $ventanaFin])->pluck('nombre', 'fecha')->mapWithKeys(fn($n, $f) => [Carbon::parse($f)->format('Y-m-d') => $n]);

        $diasHabiles = [];
        $periodo = CarbonPeriod::create($ventanaInicio, $ventanaFin);
        foreach ($periodo as $dia) {
            if ($dia->isWeekday()) {
                $diasHabiles[] = $dia->copy();
            }
        }

        $allMuebleIds = $proyectos->flatMap(fn($p) => $p->muebles->pluck('id'));
        $tiempos = Tiempo::whereIn('mueble_id', $allMuebleIds)
            ->whereBetween('fecha', [$ventanaInicio, $ventanaFin])
            ->get();

        $tiemposMap = [];
        foreach ($tiempos as $t) {
            $key = "{$t->mueble_id}_{$t->proceso}_{$t->personal_id}_{$t->fecha->format('Y-m-d')}";
            $tiemposMap[$key] = $t->horas;
        }

        $personal = Personal::where('activo', true)->get()->keyBy('id');
        $procesos = ['Carpintería', 'Barniz', 'Instalación'];

        // Navigation data
        $canGoBack = $ventanaInicio->gt($rangoMin);
        $prevDesde = $ventanaInicio->copy()->subWeeks(2)->format('Y-m-d');
        $nextDesde = $ventanaInicio->copy()->addWeeks(2)->format('Y-m-d');
        $todayDesde = $defaultStart->format('Y-m-d');
        $allDesde = $rangoMin->format('Y-m-d');

        // Real: jornales de nómina por mueble/día/equipo (excluye Instalación)
        $nominaReal = NominaDiaria::with('personal')
            ->whereIn('mueble_id', $allMuebleIds)
            ->whereNotNull('proyecto_id')
            ->whereBetween('fecha', [$ventanaInicio, $ventanaFin])
            ->whereHas('personal', fn($q) => $q->whereNotIn('equipo', ['Instalación']))
            ->get();

        // Map: mueble_id => fecha => [equipo => count]
        $realMap = [];
        foreach ($nominaReal as $nr) {
            $mid = $nr->mueble_id;
            $fecha = $nr->fecha->format('Y-m-d');
            $equipo = $nr->personal?->equipo ?? 'Otro';
            $realMap[$mid][$fecha][$equipo] = ($realMap[$mid][$fecha][$equipo] ?? 0) + 1;
        }

        return view('tiempos.general', compact(
            'proyectos', 'diasHabiles', 'tiemposMap', 'personal', 'procesos', 'festivos',
            'ventanaInicio', 'ventanaFin', 'canGoBack', 'prevDesde', 'nextDesde', 'todayDesde', 'allDesde',
            'realMap'
        ));
    }
}
