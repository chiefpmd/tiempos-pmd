<?php

namespace App\Http\Controllers;

use App\Models\DiaFestivo;
use App\Models\NominaDiaria;
use App\Models\Personal;
use App\Models\Tiempo;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsignacionController extends Controller
{
    private const HORAS_DIA = 8;

    public function disponibilidad(Request $request)
    {
        $semanaInicio = Carbon::parse($request->input('semana'))->startOfWeek();
        $semanaFin = $semanaInicio->copy()->endOfWeek();

        $diasLab = $this->diasLaborables($semanaInicio, $semanaFin);

        $personal = Personal::where('activo', true)->orderBy('equipo')->orderBy('nombre')->get();

        $tiemposEnSemana = Tiempo::with('mueble.proyecto')
            ->whereBetween('fecha', [$semanaInicio, $semanaFin])
            ->get()
            ->groupBy('personal_id');

        // Cualquier registro en nómina cuenta como ocupado: cubre ausencias, "tienda completa"
        // y equipos que no se siembran (Vidrio, Herrero, Eléctrico, Tapicero, Mantenimiento)
        // pero que sí se imputan a muebles en nómina.
        $nominaEnSemana = NominaDiaria::whereBetween('fecha', [$semanaInicio, $semanaFin])
            ->get(['personal_id', 'fecha'])
            ->groupBy('personal_id');

        $resultado = [];
        foreach ($personal as $p) {
            $tiempos = $tiemposEnSemana->get($p->id, collect());
            $diasDesdeTiempos = $tiempos->map(fn($t) => $t->fecha->format('Y-m-d'));
            $diasDesdeNomina = ($nominaEnSemana->get($p->id) ?? collect())
                ->map(fn($n) => Carbon::parse($n->fecha)->format('Y-m-d'));
            $diasOcupados = $diasDesdeTiempos->merge($diasDesdeNomina)->unique()->count();

            $diasLibres = max(0, $diasLab - $diasOcupados);

            $asignaciones = $tiempos
                ->groupBy(fn($t) => $t->mueble_id . '|' . $t->proceso)
                ->map(function ($grupo) {
                    $first = $grupo->first();
                    return [
                        'mueble_id' => $first->mueble_id,
                        'proceso' => $first->proceso,
                        'mueble_numero' => $first->mueble?->numero,
                        'proyecto_nombre' => $first->mueble?->proyecto?->nombre,
                        'dias' => $grupo->count(),
                    ];
                })
                ->values();

            $resultado[] = [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'equipo' => $p->equipo,
                'color_hex' => $p->color_hex,
                'dias_libres' => $diasLibres,
                'dias_asignados' => $diasOcupados,
                'asignaciones' => $asignaciones,
            ];
        }

        return response()->json([
            'semana_inicio' => $semanaInicio->format('Y-m-d'),
            'semana_fin' => $semanaFin->format('Y-m-d'),
            'dias_laborables' => $diasLab,
            'personal' => $resultado,
        ]);
    }

    public function asignar(Request $request)
    {
        $data = $request->validate([
            'mueble_id' => 'required|integer|exists:muebles,id',
            'proceso' => 'required|string',
            'personal_id' => 'required|integer|exists:personal,id',
            'semana' => 'required|date',
        ]);

        $semanaInicio = Carbon::parse($data['semana'])->startOfWeek();
        $semanaFin = $semanaInicio->copy()->endOfWeek();

        $diasLab = $this->diasLaborables($semanaInicio, $semanaFin);
        if ($diasLab === 0) {
            return response()->json(['ok' => false, 'error' => 'No hay días laborables en esta semana'], 422);
        }

        $diasDesdeTiempos = Tiempo::where('personal_id', $data['personal_id'])
            ->whereBetween('fecha', [$semanaInicio, $semanaFin])
            ->pluck('fecha')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'));

        $diasDesdeNomina = NominaDiaria::where('personal_id', $data['personal_id'])
            ->whereBetween('fecha', [$semanaInicio, $semanaFin])
            ->pluck('fecha')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'));

        $diasOcupadosPersona = $diasDesdeTiempos->merge($diasDesdeNomina)
            ->unique()
            ->values()
            ->toArray();

        $diasLibres = $diasLab - count($diasOcupadosPersona);
        if ($diasLibres <= 0) {
            return response()->json(['ok' => false, 'error' => 'La persona ya tiene la semana completa'], 422);
        }

        $rango = Tiempo::where('mueble_id', $data['mueble_id'])
            ->where('proceso', $data['proceso'])
            ->where('horas', '>', 0)
            ->selectRaw('MIN(fecha) as fmin, MAX(fecha) as fmax')
            ->first();

        if (!$rango || !$rango->fmin) {
            // Sin barra previa: crear una usando la semana seleccionada como rango inicial
            $procesoInicio = $semanaInicio->copy();
            $procesoFin = $semanaFin->copy();
        } else {
            $procesoInicio = Carbon::parse($rango->fmin);
            $procesoFin = Carbon::parse($rango->fmax);
            // Si la semana del sticky no se solapa con el rango global, usar la semana
            // como rango (permite asignar antes/después del rango actual)
            if ($semanaFin->lessThan($procesoInicio) || $semanaInicio->greaterThan($procesoFin)) {
                $procesoInicio = $semanaInicio->copy();
                $procesoFin = $semanaFin->copy();
            }
        }

        $inicio = $semanaInicio->greaterThan($procesoInicio) ? $semanaInicio : $procesoInicio;
        $fin = $semanaFin->lessThan($procesoFin) ? $semanaFin : $procesoFin;

        $festivos = DiaFestivo::whereBetween('fecha', [$semanaInicio, $semanaFin])
            ->pluck('fecha')
            ->map(fn($f) => Carbon::parse($f)->format('Y-m-d'))
            ->toArray();

        $diasYaTomadosPorOtros = Tiempo::where('mueble_id', $data['mueble_id'])
            ->where('proceso', $data['proceso'])
            ->where('personal_id', '!=', $data['personal_id'])
            ->whereBetween('fecha', [$inicio, $fin])
            ->pluck('fecha')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $candidatos = [];
        $periodo = CarbonPeriod::create($inicio, $fin);
        foreach ($periodo as $dia) {
            if (!$dia->isWeekday()) continue;
            $diaStr = $dia->format('Y-m-d');
            if (in_array($diaStr, $festivos)) continue;
            if (in_array($diaStr, $diasOcupadosPersona)) continue;
            if (in_array($diaStr, $diasYaTomadosPorOtros)) continue;
            $candidatos[] = $diaStr;
        }

        if (empty($candidatos)) {
            return response()->json(['ok' => false, 'error' => 'No quedan días libres en esta barra para esta semana'], 422);
        }

        $aAsignar = array_slice($candidatos, 0, $diasLibres);

        $creados = 0;
        foreach ($aAsignar as $diaStr) {
            $existente = Tiempo::where('mueble_id', $data['mueble_id'])
                ->where('proceso', $data['proceso'])
                ->where('personal_id', $data['personal_id'])
                ->where('fecha', $diaStr)
                ->first();
            if (!$existente) {
                Tiempo::create([
                    'mueble_id' => $data['mueble_id'],
                    'proceso' => $data['proceso'],
                    'personal_id' => $data['personal_id'],
                    'fecha' => $diaStr,
                    'horas' => self::HORAS_DIA,
                ]);
                $creados++;
            }
        }

        return response()->json(['ok' => true, 'creados' => $creados, 'dias' => $aAsignar]);
    }

    public function quitar(Request $request)
    {
        $data = $request->validate([
            'mueble_id' => 'required|integer|exists:muebles,id',
            'proceso' => 'required|string',
            'personal_id' => 'required|integer|exists:personal,id',
            'semana' => 'required|date',
        ]);

        $semanaInicio = Carbon::parse($data['semana'])->startOfWeek();
        $semanaFin = $semanaInicio->copy()->endOfWeek();

        $borrados = Tiempo::where('mueble_id', $data['mueble_id'])
            ->where('proceso', $data['proceso'])
            ->where('personal_id', $data['personal_id'])
            ->whereBetween('fecha', [$semanaInicio, $semanaFin])
            ->delete();

        return response()->json(['ok' => true, 'borrados' => $borrados]);
    }

    private function diasLaborables(Carbon $inicio, Carbon $fin): int
    {
        $festivos = DiaFestivo::whereBetween('fecha', [$inicio, $fin])
            ->pluck('fecha')
            ->map(fn($f) => Carbon::parse($f)->format('Y-m-d'))
            ->toArray();
        $count = 0;
        $periodo = CarbonPeriod::create($inicio, $fin);
        foreach ($periodo as $dia) {
            if (!$dia->isWeekday()) continue;
            if (in_array($dia->format('Y-m-d'), $festivos)) continue;
            $count++;
        }
        return $count;
    }
}
