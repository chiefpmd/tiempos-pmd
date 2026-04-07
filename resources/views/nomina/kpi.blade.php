@extends('layouts.app')
@section('title', 'KPI Producción')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-4">
    <h1 class="text-xl font-bold">KPI Producción</h1>

    <div class="flex items-center gap-3">
        <form method="GET" action="{{ route('nomina.kpi') }}" class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Año:</label>
            <input type="number" name="anio" value="{{ $anio }}" min="2020" max="2030"
                   class="border rounded px-2 py-1 text-sm w-20">
            <button type="submit" class="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700">Filtrar</button>
        </form>
    </div>
</div>

<p class="text-xs text-gray-400 mb-4">Solo muebles con asignación en nómina. Semana actual: {{ $semanaActual }}</p>

{{-- Cards resumen --}}
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 mb-6">
    <div class="bg-white rounded-lg shadow p-3">
        <p class="text-xs text-gray-500">WIP (en proceso)</p>
        <p class="text-2xl font-bold text-gray-800">{{ $totales['wip'] ?? 0 }}</p>
        <p class="text-xs text-gray-400">muebles abiertos</p>
    </div>
    <div class="bg-white rounded-lg shadow p-3">
        <p class="text-xs text-gray-500">Terminados</p>
        <p class="text-2xl font-bold text-green-600">{{ $totales['terminados'] ?? 0 }}</p>
        <p class="text-xs text-gray-400">total {{ $anio }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-3">
        <p class="text-xs text-gray-500">Terminados recientes</p>
        <p class="text-2xl font-bold text-blue-600">{{ $totales['terminados_semana'] ?? 0 }}</p>
        <p class="text-xs text-gray-400">sem {{ $semanaActual - 1 }} + {{ $semanaActual }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-3">
        <p class="text-xs text-gray-500">Ratio WIP / Terminados</p>
        <p class="text-2xl font-bold {{ ($totales['ratio_wip'] ?? 0) > 3 ? 'text-red-600' : (($totales['ratio_wip'] ?? 0) > 2 ? 'text-amber-600' : 'text-green-600') }}">{{ $totales['ratio_wip'] ?? 0 }}</p>
        <p class="text-xs text-gray-400">{{ ($totales['ratio_wip'] ?? 0) > 3 ? 'muy disperso' : (($totales['ratio_wip'] ?? 0) > 2 ? 'algo disperso' : 'buen flujo') }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-3">
        <p class="text-xs text-gray-500">Accuracy Presupuesto</p>
        <p class="text-2xl font-bold {{ ($totales['accuracy'] ?? 0) > 120 ? 'text-red-600' : (($totales['accuracy'] ?? 0) > 100 ? 'text-amber-600' : 'text-green-600') }}">{{ $totales['accuracy'] ?? 0 }}%</p>
        <p class="text-xs text-gray-400">real vs presupuesto</p>
    </div>
</div>

{{-- Lead times cards --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-lg shadow p-3">
        <p class="text-xs text-gray-500">Lead Time Total</p>
        <p class="text-2xl font-bold text-gray-800">{{ $totales['avg_lead_total'] ?? 0 }}</p>
        <p class="text-xs text-gray-400">días hábiles promedio</p>
    </div>
    <div class="bg-white rounded-lg shadow p-3">
        <p class="text-xs text-gray-500">Lead Time Carpintería</p>
        <p class="text-2xl font-bold text-amber-600">{{ $totales['avg_lead_carp'] ?? 0 }}</p>
        <p class="text-xs text-gray-400">días hábiles promedio</p>
    </div>
    <div class="bg-white rounded-lg shadow p-3">
        <p class="text-xs text-gray-500">Lead Time Barniz</p>
        <p class="text-2xl font-bold text-green-600">{{ $totales['avg_lead_barniz'] ?? 0 }}</p>
        <p class="text-xs text-gray-400">días hábiles promedio</p>
    </div>
    <div class="bg-white rounded-lg shadow p-3">
        <p class="text-xs text-gray-500">Espera Carp. -> Barniz</p>
        <p class="text-2xl font-bold {{ ($totales['avg_espera'] ?? 0) > 5 ? 'text-red-600' : (($totales['avg_espera'] ?? 0) > 3 ? 'text-amber-600' : 'text-green-600') }}">{{ $totales['avg_espera'] ?? 0 }}</p>
        <p class="text-xs text-gray-400">días hábiles promedio</p>
    </div>
</div>

{{-- Terminados por semana --}}
@if(!empty($terminadosPorSemana))
<h2 class="text-base font-semibold text-gray-700 mt-6 mb-2">Producción Terminada por Semana</h2>
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="min-w-full text-xs">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-500"></th>
                @foreach($terminadosPorSemana as $sem => $data)
                    <th class="px-3 py-2 text-center font-medium text-gray-500 min-w-[80px]">Sem {{ $sem }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <tr>
                <td class="px-3 py-1.5 text-gray-600 font-medium">Muebles</td>
                @foreach($terminadosPorSemana as $sem => $data)
                    <td class="px-3 py-1.5 text-center font-mono font-semibold">{{ $data['count'] }}</td>
                @endforeach
            </tr>
            <tr>
                <td class="px-3 py-1.5 text-gray-600 font-medium">Valor</td>
                @foreach($terminadosPorSemana as $sem => $data)
                    <td class="px-3 py-1.5 text-center font-mono {{ $data['valor'] > 0 ? 'text-green-600' : 'text-gray-400' }}">${{ number_format($data['valor'], 0) }}</td>
                @endforeach
            </tr>
            <tr>
                <td class="px-3 py-1.5 text-gray-600 font-medium">Jornales</td>
                @foreach($terminadosPorSemana as $sem => $data)
                    <td class="px-3 py-1.5 text-center font-mono">{{ $data['jornales'] }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
</div>
@endif

{{-- WIP Table --}}
@if($wip->count() > 0)
<h2 class="text-base font-semibold text-gray-700 mt-6 mb-2">En Proceso ({{ $wip->count() }})</h2>
<div class="bg-white rounded-lg shadow overflow-auto max-h-[50vh] mb-6">
    <table class="min-w-full text-xs">
        <thead class="bg-gray-50 sticky top-0 z-10">
            <tr>
                <th class="px-2 py-2 text-left font-medium text-gray-500 bg-gray-50">Mueble</th>
                <th class="px-2 py-2 text-left font-medium text-gray-500 bg-gray-50">Proyecto</th>
                <th class="px-2 py-2 text-right font-medium text-gray-500 bg-gray-50">Jor. Presup.</th>
                <th class="px-2 py-2 text-right font-medium text-gray-500 bg-gray-50">Jor. Reales</th>
                <th class="px-2 py-2 text-center font-medium text-gray-500 bg-gray-50">Inicio Carp.</th>
                <th class="px-2 py-2 text-center font-medium text-gray-500 bg-gray-50">Fin Carp.</th>
                <th class="px-2 py-2 text-center font-medium text-gray-500 bg-gray-50">Inicio Barniz</th>
                <th class="px-2 py-2 text-right font-medium text-gray-500 bg-gray-50">Días</th>
                <th class="px-2 py-2 text-right font-medium text-gray-500 bg-gray-50">%</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @php
                $coloresProyecto = ['bg-blue-50', 'bg-amber-50', 'bg-green-50', 'bg-purple-50', 'bg-rose-50', 'bg-cyan-50', 'bg-orange-50', 'bg-indigo-50'];
                $proyectoColorMap = []; $colorIdx = 0;
            @endphp
            @foreach($wip as $m)
                @php
                    if (!isset($proyectoColorMap[$m['proyecto']])) {
                        $proyectoColorMap[$m['proyecto']] = $coloresProyecto[$colorIdx % count($coloresProyecto)];
                        $colorIdx++;
                    }
                    $bgColor = $proyectoColorMap[$m['proyecto']];
                    $pctJornales = $m['jornales_presupuesto'] > 0 ? ($m['jornales'] / $m['jornales_presupuesto']) * 100 : 0;
                @endphp
                <tr class="{{ $bgColor }}">
                    <td class="px-2 py-1 text-gray-800 font-medium">{{ $m['mueble'] }}</td>
                    <td class="px-2 py-1 text-gray-600">{{ $m['proyecto'] }}</td>
                    <td class="px-2 py-1 text-right font-mono">
                        @if($m['jornales_presupuesto'] > 0) {{ number_format($m['jornales_presupuesto'], 1) }} @else <span class="text-gray-400">-</span> @endif
                    </td>
                    <td class="px-2 py-1 text-right font-mono">{{ $m['jornales'] }}</td>
                    <td class="px-2 py-1 text-center font-mono text-gray-500">{{ $m['carp_inicio'] ? \Carbon\Carbon::parse($m['carp_inicio'])->format('d/m') : '-' }}</td>
                    <td class="px-2 py-1 text-center font-mono text-gray-500">{{ $m['carp_fin'] ? \Carbon\Carbon::parse($m['carp_fin'])->format('d/m') : '-' }}</td>
                    <td class="px-2 py-1 text-center font-mono text-gray-500">{{ $m['barniz_inicio'] ? \Carbon\Carbon::parse($m['barniz_inicio'])->format('d/m') : '-' }}</td>
                    <td class="px-2 py-1 text-right font-mono">{{ $m['lead_total'] ?? '-' }}</td>
                    <td class="px-2 py-1 text-right font-mono">
                        @if($m['jornales_presupuesto'] > 0)
                            <span class="{{ $pctJornales > 100 ? 'text-red-600 font-semibold' : ($pctJornales > 75 ? 'text-amber-600' : 'text-green-600') }}">{{ number_format($pctJornales, 0) }}%</span>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Terminados Table --}}
@if($terminados->count() > 0)
<h2 class="text-base font-semibold text-gray-700 mt-6 mb-2">Terminados ({{ $terminados->count() }})</h2>
<div class="bg-white rounded-lg shadow overflow-auto max-h-[50vh] mb-6">
    <table class="min-w-full text-xs">
        <thead class="bg-gray-50 sticky top-0 z-10">
            <tr>
                <th class="px-2 py-2 text-left font-medium text-gray-500 bg-gray-50">Mueble</th>
                <th class="px-2 py-2 text-left font-medium text-gray-500 bg-gray-50">Proyecto</th>
                <th class="px-2 py-2 text-left font-medium text-gray-500 bg-gray-50">Equipos</th>
                <th class="px-2 py-2 text-right font-medium text-gray-500 bg-gray-50">Jor. Presup.</th>
                <th class="px-2 py-2 text-right font-medium text-gray-500 bg-gray-50">Jor. Reales</th>
                <th class="px-2 py-2 text-right font-medium text-gray-500 bg-gray-50">Lead Carp.</th>
                <th class="px-2 py-2 text-right font-medium text-gray-500 bg-gray-50">Espera</th>
                <th class="px-2 py-2 text-right font-medium text-gray-500 bg-gray-50">Lead Barniz</th>
                <th class="px-2 py-2 text-right font-medium text-gray-500 bg-gray-50">Lead Total</th>
                <th class="px-2 py-2 text-right font-medium text-gray-500 bg-gray-50">%</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($terminados as $m)
                @php $pctJornales = $m['jornales_presupuesto'] > 0 ? ($m['jornales'] / $m['jornales_presupuesto']) * 100 : 0; @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-2 py-1 text-gray-800 font-medium">{{ $m['mueble'] }}</td>
                    <td class="px-2 py-1 text-gray-600">{{ $m['proyecto'] }}</td>
                    <td class="px-2 py-1 text-gray-500">{{ $m['equipos'] ?? '' }}</td>
                    <td class="px-2 py-1 text-right font-mono">
                        @if($m['jornales_presupuesto'] > 0) {{ number_format($m['jornales_presupuesto'], 1) }} @else <span class="text-gray-400">-</span> @endif
                    </td>
                    <td class="px-2 py-1 text-right font-mono">{{ $m['jornales'] }}</td>
                    <td class="px-2 py-1 text-right font-mono text-amber-600">{{ $m['lead_carp'] ? $m['lead_carp'].'d' : '-' }}</td>
                    <td class="px-2 py-1 text-right font-mono {{ ($m['dias_espera'] ?? 0) > 5 ? 'text-red-600' : 'text-gray-500' }}">{{ $m['dias_espera'] !== null ? $m['dias_espera'].'d' : '-' }}</td>
                    <td class="px-2 py-1 text-right font-mono text-green-600">{{ $m['lead_barniz'] ? $m['lead_barniz'].'d' : '-' }}</td>
                    <td class="px-2 py-1 text-right font-mono font-semibold">{{ $m['lead_total'] ? $m['lead_total'].'d' : '-' }}</td>
                    <td class="px-2 py-1 text-right font-mono">
                        @if($m['jornales_presupuesto'] > 0)
                            <span class="{{ $pctJornales > 100 ? 'text-red-600 font-semibold' : ($pctJornales > 75 ? 'text-amber-600' : 'text-green-600') }}">{{ number_format($pctJornales, 0) }}%</span>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@endsection
