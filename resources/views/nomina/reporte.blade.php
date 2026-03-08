@extends('layouts.app')
@section('title', 'Reporte Costo por Proyecto')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-4">
    <h1 class="text-xl font-bold">Costo por Proyecto</h1>

    <div class="flex items-center gap-3">
        <form method="GET" action="{{ route('nomina.reporte') }}" class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Sem inicio:</label>
            <input type="number" name="semana_inicio" value="{{ $semanaInicio }}" min="1" max="53"
                   class="border rounded px-2 py-1 text-sm w-16">
            <label class="text-sm text-gray-600">Sem fin:</label>
            <input type="number" name="semana_fin" value="{{ $semanaFin }}" min="1" max="53"
                   class="border rounded px-2 py-1 text-sm w-16">
            <label class="text-sm text-gray-600">Año:</label>
            <input type="number" name="anio" value="{{ $anio }}" min="2020" max="2030"
                   class="border rounded px-2 py-1 text-sm w-20">
            <button type="submit" class="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700">Filtrar</button>
        </form>

        <a href="{{ route('nomina.exportar', ['semana_inicio' => $semanaInicio, 'semana_fin' => $semanaFin, 'anio' => $anio]) }}"
           class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
            Exportar Excel
        </a>
    </div>
</div>

@if($semanasConDatos->isEmpty())
    <p class="text-gray-400 text-sm">No hay datos de nómina para el rango seleccionado.</p>
@else

@php
function renderCostoSection($titulo, $datos, $semanasConDatos, $totalGeneral) {
    if (empty($datos)) return;
    echo '<h2 class="text-base font-semibold text-gray-700 mt-6 mb-2">' . e($titulo) . '</h2>';
    echo '<div class="bg-white rounded-lg shadow overflow-x-auto mb-4"><table class="min-w-full text-xs">';
    echo '<thead class="bg-gray-50"><tr>';
    echo '<th class="px-3 py-2 text-left font-medium text-gray-500">Concepto</th>';
    foreach ($semanasConDatos as $sem) {
        echo '<th class="px-3 py-2 text-right font-medium text-gray-500 min-w-[80px]">Sem ' . $sem . '</th>';
    }
    echo '<th class="px-3 py-2 text-right font-medium text-gray-700 min-w-[90px]">Total</th>';
    echo '<th class="px-3 py-2 text-right font-medium text-gray-400 w-14">%</th>';
    echo '</tr></thead><tbody class="divide-y divide-gray-100">';

    $subtotal = 0;
    foreach ($datos as $nombre => $semanas) {
        $totalFila = array_sum($semanas);
        $subtotal += $totalFila;
        echo '<tr class="hover:bg-gray-50">';
        echo '<td class="px-3 py-1.5 text-gray-800">' . e($nombre) . '</td>';
        foreach ($semanasConDatos as $sem) {
            $val = $semanas[$sem] ?? 0;
            echo '<td class="px-3 py-1.5 text-right font-mono">' . ($val > 0 ? '$' . number_format($val, 2) : '-') . '</td>';
        }
        echo '<td class="px-3 py-1.5 text-right font-mono font-semibold">$' . number_format($totalFila, 2) . '</td>';
        $pct = $totalGeneral > 0 ? ($totalFila / $totalGeneral * 100) : 0;
        echo '<td class="px-3 py-1.5 text-right text-gray-400">' . number_format($pct, 1) . '%</td>';
        echo '</tr>';
    }

    // Subtotal
    echo '<tr class="bg-gray-50 font-semibold"><td class="px-3 py-1.5 text-gray-700">Subtotal</td>';
    foreach ($semanasConDatos as $sem) {
        $colTotal = 0;
        foreach ($datos as $semanas) { $colTotal += $semanas[$sem] ?? 0; }
        echo '<td class="px-3 py-1.5 text-right font-mono">' . ($colTotal > 0 ? '$' . number_format($colTotal, 2) : '-') . '</td>';
    }
    echo '<td class="px-3 py-1.5 text-right font-mono">$' . number_format($subtotal, 2) . '</td>';
    $pctSub = $totalGeneral > 0 ? ($subtotal / $totalGeneral * 100) : 0;
    echo '<td class="px-3 py-1.5 text-right text-gray-400">' . number_format($pctSub, 1) . '%</td>';
    echo '</tr></tbody></table></div>';
}
@endphp

@php renderCostoSection('Proyectos (Productivo)', $costoProyectos, $semanasConDatos, $totalGeneral) @endphp
@php renderCostoSection('No Productivo', $costoNoProd, $semanasConDatos, $totalGeneral) @endphp
@php renderCostoSection('Horas Extra', $costoHe, $semanasConDatos, $totalGeneral) @endphp

<div class="mt-6 bg-white rounded-lg shadow p-4">
    <p class="text-lg font-bold text-gray-800">
        Total General: <span class="text-blue-600">${{ number_format($totalGeneral, 2) }}</span>
    </p>
</div>

@endif
@endsection
