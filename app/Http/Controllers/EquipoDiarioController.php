<?php

namespace App\Http\Controllers;

use App\Models\EquipoDiario;
use App\Models\Personal;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EquipoDiarioController extends Controller
{
    public function index(Request $request)
    {
        $anio = $request->integer('anio', now()->year);
        $semana = $request->integer('semana', now()->weekOfYear);

        $inicioSemana = Carbon::now()->setISODate($anio, $semana, 1);
        $finSemana = $inicioSemana->copy()->addDays(4);

        $dias = [];
        for ($d = $inicioSemana->copy(); $d->lte($finSemana); $d->addDay()) {
            $dias[] = $d->copy();
        }

        $lideres = Personal::where('es_lider', true)->where('activo', true)->orderBy('nombre')->get();
        $trabajadores = Personal::where('es_lider', false)->where('activo', true)->orderBy('nombre')->get();

        // Load existing assignments for this week
        $asignaciones = EquipoDiario::whereBetween('fecha', [$inicioSemana->format('Y-m-d'), $finSemana->format('Y-m-d')])
            ->get()
            ->groupBy(fn($a) => $a->lider_id . '_' . $a->fecha->format('Y-m-d'))
            ->map(fn($group) => $group->pluck('personal_id')->toArray());

        return view('nomina.equipos', compact(
            'anio', 'semana', 'dias', 'lideres', 'trabajadores',
            'asignaciones', 'inicioSemana', 'finSemana'
        ));
    }

    public function guardar(Request $request)
    {
        $request->validate([
            'lider_id' => 'required|exists:personal,id',
            'fecha' => 'required|date',
            'personal_ids' => 'nullable|array',
            'personal_ids.*' => 'exists:personal,id',
        ]);

        $fecha = $request->fecha;
        $liderId = $request->lider_id;
        $personalIds = $request->personal_ids ?? [];

        // Remove existing assignments for this leader on this date
        EquipoDiario::where('lider_id', $liderId)->where('fecha', $fecha)->delete();

        // Also remove these workers from any other leader on this date
        if (!empty($personalIds)) {
            EquipoDiario::whereIn('personal_id', $personalIds)->where('fecha', $fecha)->delete();
        }

        // Create new assignments
        foreach ($personalIds as $personalId) {
            EquipoDiario::create([
                'personal_id' => $personalId,
                'lider_id' => $liderId,
                'fecha' => $fecha,
            ]);
        }

        return response()->json([
            'ok' => true,
            'count' => count($personalIds),
        ]);
    }

    public function copiarDia(Request $request)
    {
        $request->validate([
            'fecha_origen' => 'required|date',
            'fecha_destino' => 'required|date',
        ]);

        $origen = EquipoDiario::where('fecha', $request->fecha_origen)->get();

        if ($origen->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'No hay asignaciones en el día origen.']);
        }

        // Remove existing on destination
        EquipoDiario::where('fecha', $request->fecha_destino)->delete();

        $count = 0;
        foreach ($origen as $a) {
            EquipoDiario::create([
                'personal_id' => $a->personal_id,
                'lider_id' => $a->lider_id,
                'fecha' => $request->fecha_destino,
            ]);
            $count++;
        }

        return response()->json([
            'ok' => true,
            'count' => $count,
            'message' => "Se copiaron {$count} asignaciones.",
        ]);
    }
}
