<?php

namespace App\Http\Controllers;

use App\Models\DiaFestivo;
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

        $fechaMin = $proyectos->min('fecha_inicio');
        $fechaMax = $proyectos->max(fn($p) => $p->fecha_fin);

        $festivos = DiaFestivo::whereBetween('fecha', [$fechaMin, $fechaMax])->pluck('nombre', 'fecha')->mapWithKeys(fn($n, $f) => [Carbon::parse($f)->format('Y-m-d') => $n]);

        $diasHabiles = [];
        $periodo = CarbonPeriod::create($fechaMin, $fechaMax);
        foreach ($periodo as $dia) {
            if ($dia->isWeekday()) {
                $diasHabiles[] = $dia->copy();
            }
        }

        $personal = Personal::where('activo', true)->orderBy('equipo')->orderBy('nombre')->get();

        $tiempos = Tiempo::whereIn('personal_id', $personal->pluck('id'))
            ->whereBetween('fecha', [$fechaMin, $fechaMax])
            ->where('horas', '>', 0)
            ->with('mueble.proyecto')
            ->get();

        $disponibilidad = [];
        foreach ($personal as $p) {
            foreach ($diasHabiles as $dia) {
                $fechaStr = $dia->format('Y-m-d');
                $registros = $tiempos->where('personal_id', $p->id)
                    ->filter(fn($t) => $t->fecha->format('Y-m-d') === $fechaStr);

                $proyectosEnDia = $registros->map(fn($t) => $t->mueble->proyecto->nombre)->unique()->values();
                $totalHoras = $registros->sum('horas');

                $disponibilidad[$p->id][$fechaStr] = [
                    'proyectos' => $proyectosEnDia->count(),
                    'nombres' => $proyectosEnDia->all(),
                    'horas' => $totalHoras,
                ];
            }
        }

        return view('tiempos.dashboard', compact('personal', 'diasHabiles', 'disponibilidad', 'proyectos', 'festivos'));
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

        // Guardar snapshot para poder revertir
        $snapshot = $tiempos->map(fn($t) => [
            'id' => $t->id,
            'fecha' => $t->fecha->format('Y-m-d'),
        ])->toArray();

        // Calcular nuevas fechas
        $nuevasFechas = [];
        foreach ($tiempos as $tiempo) {
            $nuevasFechas[$tiempo->id] = $this->moverDiasHabiles($tiempo->fecha, $diasHabiles);
        }

        try {
            DB::beginTransaction();

            $shift = TiempoShift::create([
                'proyecto_id' => $proyecto->id,
                'dias_habiles' => $diasHabiles,
                'snapshot' => $snapshot,
                'user_id' => auth()->id(),
            ]);

            // Poner fechas temporales para evitar colisiones con unique constraint
            $ids = $tiempos->pluck('id');
            Tiempo::whereIn('id', $ids)->update(['fecha' => '1900-01-01']);

            // Aplicar fechas nuevas
            foreach ($nuevasFechas as $id => $fecha) {
                Tiempo::where('id', $id)->update(['fecha' => $fecha]);
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

            // Primero poner fechas temporales para evitar colisiones
            $ids = collect($shift->snapshot)->pluck('id');
            Tiempo::whereIn('id', $ids)->update(['fecha' => '1900-01-01']);

            // Luego restaurar las fechas originales
            foreach ($shift->snapshot as $entry) {
                Tiempo::where('id', $entry['id'])->update(['fecha' => $entry['fecha']]);
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
            return view('tiempos.general', ['proyectos' => $proyectos, 'diasHabiles' => [], 'tiemposMap' => []]);
        }

        $fechaMin = $proyectos->min('fecha_inicio');
        $fechaMax = $proyectos->max(fn($p) => $p->fecha_fin);

        $festivos = DiaFestivo::whereBetween('fecha', [$fechaMin, $fechaMax])->pluck('nombre', 'fecha')->mapWithKeys(fn($n, $f) => [Carbon::parse($f)->format('Y-m-d') => $n]);

        $diasHabiles = [];
        $periodo = CarbonPeriod::create($fechaMin, $fechaMax);
        foreach ($periodo as $dia) {
            if ($dia->isWeekday()) {
                $diasHabiles[] = $dia->copy();
            }
        }

        $allMuebleIds = $proyectos->flatMap(fn($p) => $p->muebles->pluck('id'));
        $tiempos = Tiempo::whereIn('mueble_id', $allMuebleIds)
            ->whereBetween('fecha', [$fechaMin, $fechaMax])
            ->get();

        $tiemposMap = [];
        foreach ($tiempos as $t) {
            $key = "{$t->mueble_id}_{$t->proceso}_{$t->personal_id}_{$t->fecha->format('Y-m-d')}";
            $tiemposMap[$key] = $t->horas;
        }

        $personal = Personal::where('activo', true)->get()->keyBy('id');
        $procesos = ['Carpintería', 'Barniz', 'Instalación'];

        return view('tiempos.general', compact('proyectos', 'diasHabiles', 'tiemposMap', 'personal', 'procesos', 'festivos'));
    }
}
