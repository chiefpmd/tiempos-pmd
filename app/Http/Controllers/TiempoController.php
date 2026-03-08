<?php

namespace App\Http\Controllers;

use App\Models\DiaFestivo;
use App\Models\NominaDiaria;
use App\Models\Proyecto;
use App\Models\Mueble;
use App\Models\Personal;
use App\Models\Tiempo;
use App\Models\TiempoShift;
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
                DB::raw('MAX(horas) as personas'),
                DB::raw('(SELECT t2.personal_id FROM tiempos t2 WHERE t2.mueble_id = tiempos.mueble_id AND t2.proceso = tiempos.proceso AND t2.horas > 0 GROUP BY t2.personal_id ORDER BY COUNT(*) DESC LIMIT 1) as personal_id'))
            ->groupBy('mueble_id', 'proceso')
            ->get()
            ->keyBy(fn($r) => "{$r->mueble_id}_{$r->proceso}");

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

        if ((float)$data['horas'] === 0.0) {
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

        // All tiempos for all active personnel
        $allTiempos = Tiempo::whereIn('personal_id', $todosIds)
            ->whereBetween('fecha', [$fechaMin, $fechaMax])
            ->where('horas', '>', 0)
            ->with('mueble.proyecto')
            ->get();

        // All nomina for all active personnel
        $allNomina = NominaDiaria::whereIn('personal_id', $todosIds)
            ->whereBetween('fecha', [$fechaMin, $fechaMax])
            ->whereNotNull('proyecto_id')
            ->with('proyecto')
            ->get();

        // Build per-person per-day: project => personas count (from tiempos horas)
        // personId_date => [ projectName => personas ]
        $personDayProject = [];
        foreach ($allTiempos as $t) {
            $key = $t->personal_id . '_' . $t->fecha->format('Y-m-d');
            $projName = $t->mueble->proyecto->nombre;
            // Use max horas per person per project per day (horas = team size)
            $personDayProject[$key][$projName] = max(
                $personDayProject[$key][$projName] ?? 0,
                $t->horas
            );
        }
        foreach ($allNomina as $n) {
            $key = $n->personal_id . '_' . $n->fecha->format('Y-m-d');
            if (!isset($personDayProject[$key]) && $n->proyecto) {
                $personDayProject[$key][$n->proyecto->nombre] = 1; // nomina = 1 person
            }
        }

        // Group days by week
        $semanas = collect($diasHabiles)->groupBy(fn($d) => $d->weekOfYear);

        // Project capacity: total personas per project per week
        $proyectoCapacidad = []; // proyectoNombre => [semana => totalPersonas]
        // Department capacity: assigned vs total per dept per week
        $deptCapacidad = []; // equipo => [semana => ['asignados' => count, 'total' => count]]

        foreach ($semanas as $numSemana => $diasSemana) {
            // For project capacity: per leader, take their max personas for the week
            // Then sum across leaders
            $leaderMaxPerProject = []; // projName => [personalId => maxPersonas]
            $personasAsignadasPorDept = []; // equipo => [personIds]

            foreach ($diasSemana as $dia) {
                $fechaStr = $dia->format('Y-m-d');
                foreach ($todosActivos as $p) {
                    $key = $p->id . '_' . $fechaStr;
                    $proyectos_dia = $personDayProject[$key] ?? [];

                    foreach ($proyectos_dia as $projNombre => $personas) {
                        $leaderMaxPerProject[$projNombre][$p->id] = max(
                            $leaderMaxPerProject[$projNombre][$p->id] ?? 0,
                            $personas
                        );
                    }

                    if (!empty($proyectos_dia)) {
                        $personasAsignadasPorDept[$p->equipo][$p->id] = true;
                    }
                }
            }

            // Sum personas across all leaders for each project
            foreach ($leaderMaxPerProject as $projNombre => $leaders) {
                $proyectoCapacidad[$projNombre][$numSemana] = (int) array_sum($leaders);
            }

            foreach ($deptTotals as $equipo => $total) {
                $asignados = isset($personasAsignadasPorDept[$equipo]) ? count($personasAsignadasPorDept[$equipo]) : 0;
                $deptCapacidad[$equipo][$numSemana] = [
                    'asignados' => $asignados,
                    'total' => $total,
                    'libres' => $total - $asignados,
                ];
            }
        }

        ksort($proyectoCapacidad);
        ksort($deptCapacidad);

        $semanasNums = $semanas->keys()->sort()->values();

        return view('tiempos.dashboard', compact(
            'personal', 'diasHabiles', 'disponibilidad', 'proyectos', 'festivos',
            'teamCounts', 'proyectoCapacidad', 'deptCapacidad', 'deptTotals', 'semanasNums',
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

    public function vistaGeneral(Request $request)
    {
        $proyectos = Proyecto::where('status', 'activo')
            ->with(['muebles' => function($q) {
                $q->leftJoin('tiempos', function($join) {
                    $join->on('muebles.id', '=', 'tiempos.mueble_id')
                         ->where('tiempos.proceso', '=', 'Carpintería');
                })
                ->select('muebles.*', DB::raw('MIN(tiempos.fecha) as min_carpinteria'))
                ->groupBy('muebles.id')
                ->orderByRaw('min_carpinteria IS NULL, min_carpinteria ASC');
            }, 'materiales'])
            ->orderBy('fecha_inicio')
            ->get();

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

        return view('tiempos.general', compact(
            'proyectos', 'diasHabiles', 'tiemposMap', 'personal', 'procesos', 'festivos',
            'ventanaInicio', 'ventanaFin', 'canGoBack', 'prevDesde', 'nextDesde', 'todayDesde', 'allDesde'
        ));
    }
}
