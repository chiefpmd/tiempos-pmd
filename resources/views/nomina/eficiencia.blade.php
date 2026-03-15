@extends('layouts.app')
@section('title', 'Eficiencia Nómina vs Producción')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-4">
    <h1 class="text-xl font-bold">Eficiencia: Nómina vs Producción</h1>

    <div class="flex items-center gap-3">
        <form method="GET" action="{{ route('nomina.eficiencia') }}" class="flex items-center gap-2">
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
    </div>
</div>

@if($semanasConDatos->isEmpty())
    <p class="text-gray-400 text-sm">No hay datos de nómina para el rango seleccionado.</p>
@else

@php
    // Máximo 4 semanas visibles (las más recientes)
    $semanasVista = $semanasConDatos->slice(-4)->values();
@endphp

{{-- Summary Cards --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
        <p class="text-sm text-gray-500">Nómina (muebles con valor)</p>
        <p class="text-2xl font-bold text-red-600">{{ $totalNominaEficiencia > 0 ? '$' . number_format($totalNominaEficiencia, 0) : 'Sin dato' }}</p>
        <p class="text-xs text-gray-400 mt-1">Total nómina: ${{ number_format($totalNomina, 0) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <p class="text-sm text-gray-500">Total Valor Producido</p>
        <p class="text-2xl font-bold text-green-600">
            @if($totalValor > 0)
                ${{ number_format($totalValor, 0) }}
            @else
                Sin dato
            @endif
        </p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 {{ $totalMargen >= 0 ? 'border-green-500' : 'border-red-500' }}">
        <p class="text-sm text-gray-500">Margen</p>
        <p class="text-2xl font-bold {{ $totalMargen >= 0 ? 'text-green-600' : 'text-red-600' }}">
            @if($totalValor > 0)
                ${{ number_format($totalMargen, 0) }}
            @else
                Sin dato
            @endif
        </p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 {{ $totalEficiencia >= 100 ? 'border-green-500' : 'border-yellow-500' }}">
        <p class="text-sm text-gray-500">Eficiencia %</p>
        <p class="text-2xl font-bold {{ $totalEficiencia >= 100 ? 'text-green-600' : 'text-yellow-600' }}">
            @if($totalValor > 0)
                {{ number_format($totalEficiencia, 1) }}%
            @else
                Sin dato
            @endif
        </p>
    </div>
</div>

{{-- Weekly Table --}}
<h2 class="text-base font-semibold text-gray-700 mt-6 mb-2">Eficiencia Semanal <span class="text-xs font-normal text-gray-400">— Solo muebles con valor | 25% del valor = nómina estimada, repartido por jornales</span></h2>
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="min-w-full text-xs">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-500">Semana</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Nómina</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Valor Producido</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Margen</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Eficiencia %</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($semanasVista as $sem)
                @php
                    $nomSem = $costoNominaEficiencia[$sem] ?? 0;
                    $valSem = $valorProducidoPorSemana[$sem] ?? 0;
                    $marSem = $valSem - $nomSem;
                    $efSem = $nomSem > 0 ? ($valSem / $nomSem) * 100 : 0;
                @endphp
                <tr class="hover:bg-gray-50 {{ $valSem > 0 ? ($marSem >= 0 ? 'bg-green-50' : 'bg-red-50') : '' }}">
                    <td class="px-3 py-1.5 text-gray-800 font-medium">Sem {{ $sem }}</td>
                    <td class="px-3 py-1.5 text-right font-mono">{{ $nomSem > 0 ? '$' . number_format($nomSem, 0) : '-' }}</td>
                    <td class="px-3 py-1.5 text-right font-mono">
                        @if($valSem > 0) ${{ number_format($valSem, 0) }} @else <span class="text-gray-400">Sin dato</span> @endif
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono {{ $valSem > 0 ? ($marSem >= 0 ? 'text-green-600' : 'text-red-600') : 'text-gray-400' }}">
                        @if($valSem > 0) ${{ number_format($marSem, 0) }} @else - @endif
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono {{ $valSem > 0 ? ($efSem >= 100 ? 'text-green-600' : 'text-yellow-600') : 'text-gray-400' }}">
                        @if($valSem > 0) {{ number_format($efSem, 1) }}% @else - @endif
                    </td>
                </tr>
            @endforeach
            {{-- Totals row --}}
            <tr class="bg-gray-50 font-semibold">
                <td class="px-3 py-1.5 text-gray-700">Total</td>
                <td class="px-3 py-1.5 text-right font-mono">{{ $totalNominaEficiencia > 0 ? '$' . number_format($totalNominaEficiencia, 0) : '-' }}</td>
                <td class="px-3 py-1.5 text-right font-mono">
                    @if($totalValor > 0) ${{ number_format($totalValor, 0) }} @else <span class="text-gray-400">Sin dato</span> @endif
                </td>
                <td class="px-3 py-1.5 text-right font-mono {{ $totalValor > 0 ? ($totalMargen >= 0 ? 'text-green-600' : 'text-red-600') : 'text-gray-400' }}">
                    @if($totalValor > 0) ${{ number_format($totalMargen, 0) }} @else - @endif
                </td>
                <td class="px-3 py-1.5 text-right font-mono {{ $totalValor > 0 ? ($totalEficiencia >= 100 ? 'text-green-600' : 'text-yellow-600') : 'text-gray-400' }}">
                    @if($totalValor > 0) {{ number_format($totalEficiencia, 1) }}% @else - @endif
                </td>
            </tr>
        </tbody>
    </table>
</div>

{{-- Por Proceso Table (jornales por semana) --}}
@if(!empty($costoPorProceso))
<h2 class="text-base font-semibold text-gray-700 mt-6 mb-2">Jornales por Proceso</h2>
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="min-w-full text-xs">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-500">Proceso</th>
                @foreach($semanasVista as $sem)
                    <th class="px-3 py-2 text-right font-medium text-gray-500 min-w-[70px]">Sem {{ $sem }}</th>
                @endforeach
                <th class="px-3 py-2 text-right font-medium text-gray-700 min-w-[70px]">Total</th>
                <th class="px-3 py-2 text-right font-medium text-gray-700 min-w-[90px]">Costo</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500 min-w-[80px]">$/Jornal</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500 w-14">%</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($costoPorProceso as $proceso => $semanas)
                @php $totP = $totalPorProceso[$proceso]; @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5 text-gray-800 font-medium">{{ $proceso }}</td>
                    @foreach($semanasVista as $sem)
                        <td class="px-3 py-1.5 text-right font-mono">{{ $semanas[$sem]['jornales'] ?? '' }}</td>
                    @endforeach
                    <td class="px-3 py-1.5 text-right font-mono font-semibold">{{ $totP['jornales'] }}</td>
                    <td class="px-3 py-1.5 text-right font-mono font-semibold">${{ number_format($totP['costo'], 0) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono">${{ $totP['jornales'] > 0 ? number_format($totP['costo'] / $totP['jornales'], 0) : 0 }}</td>
                    <td class="px-3 py-1.5 text-right text-gray-400">{{ $totalCostoProceso > 0 ? number_format(($totP['costo'] / $totalCostoProceso) * 100, 1) : '0.0' }}%</td>
                </tr>
            @endforeach
            <tr class="bg-gray-50 font-semibold">
                <td class="px-3 py-1.5 text-gray-700">Total</td>
                @foreach($semanasVista as $sem)
                    @php $semTotal = 0; foreach($costoPorProceso as $eq => $ss) { $semTotal += $ss[$sem]['jornales'] ?? 0; } @endphp
                    <td class="px-3 py-1.5 text-right font-mono">{{ $semTotal }}</td>
                @endforeach
                <td class="px-3 py-1.5 text-right font-mono">{{ $totalJornales }}</td>
                <td class="px-3 py-1.5 text-right font-mono">${{ number_format($totalCostoProceso, 0) }}</td>
                <td class="px-3 py-1.5 text-right font-mono">${{ $totalJornales > 0 ? number_format($totalCostoProceso / $totalJornales, 0) : 0 }}</td>
                <td class="px-3 py-1.5 text-right text-gray-400">100%</td>
            </tr>
        </tbody>
    </table>
</div>

@endif

{{-- Por Proyecto Table --}}
@if(!empty($costoPorProyecto))
<h2 class="text-base font-semibold text-gray-700 mt-6 mb-2">Costo por Proyecto</h2>
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="min-w-full text-xs">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-500">Proyecto</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Nómina</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Valor Muebles</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Margen</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">%</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @php $totalProjNomina = 0; $totalProjValor = 0; @endphp
            @foreach($costoPorProyecto as $nombre => $data)
                @php
                    $margenProy = $data['valor_muebles'] - $data['nomina'];
                    $totalProjNomina += $data['nomina'];
                    $totalProjValor += $data['valor_muebles'];
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5 text-gray-800">{{ $nombre }}</td>
                    <td class="px-3 py-1.5 text-right font-mono">${{ number_format($data['nomina'], 0) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono">
                        @if($data['valor_muebles'] > 0) ${{ number_format($data['valor_muebles'], 0) }} @else <span class="text-gray-400">Sin dato</span> @endif
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono {{ $data['valor_muebles'] > 0 ? ($margenProy >= 0 ? 'text-green-600' : 'text-red-600') : 'text-gray-400' }}">
                        @if($data['valor_muebles'] > 0) ${{ number_format($margenProy, 0) }} @else - @endif
                    </td>
                    <td class="px-3 py-1.5 text-right text-gray-400">
                        {{ $totalNomina > 0 ? number_format(($data['nomina'] / $totalNomina) * 100, 1) : '0.0' }}%
                    </td>
                </tr>
            @endforeach
            <tr class="bg-gray-50 font-semibold">
                <td class="px-3 py-1.5 text-gray-700">Total</td>
                <td class="px-3 py-1.5 text-right font-mono">${{ number_format($totalProjNomina, 0) }}</td>
                <td class="px-3 py-1.5 text-right font-mono">
                    @if($totalProjValor > 0) ${{ number_format($totalProjValor, 0) }} @else <span class="text-gray-400">Sin dato</span> @endif
                </td>
                <td class="px-3 py-1.5 text-right font-mono {{ $totalProjValor > 0 ? (($totalProjValor - $totalProjNomina) >= 0 ? 'text-green-600' : 'text-red-600') : 'text-gray-400' }}">
                    @if($totalProjValor > 0) ${{ number_format($totalProjValor - $totalProjNomina, 0) }} @else - @endif
                </td>
                <td class="px-3 py-1.5 text-right text-gray-400">{{ $totalNomina > 0 ? number_format(($totalProjNomina / $totalNomina) * 100, 1) : '0.0' }}%</td>
            </tr>
        </tbody>
    </table>
</div>
@endif

{{-- Muebles en Producción --}}
@if(!empty($mueblesEnProduccion))
<h2 class="text-base font-semibold text-gray-700 mt-6 mb-2">Muebles en Producción ({{ count($mueblesEnProduccion) }}) <span class="text-xs font-normal text-gray-400">— Carpintería + Barniz (proyección) + Otros (nómina) | Sin Instalación</span></h2>
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="min-w-full text-xs">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-500">Mueble</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500">Proyecto</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500">Proyección</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Jornales Reales</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Costo Nómina</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Valor Mueble</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @php $totalJornalesProd = 0; $totalCostoProd = 0; $totalValorProd = 0; @endphp
            @foreach($mueblesEnProduccion as $data)
                @php
                    $totalJornalesProd += $data['jornales'];
                    $totalCostoProd += $data['costo_nomina'];
                    $totalValorProd += $data['valor_mueble'];
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5 text-gray-800 font-medium">{{ $data['mueble'] }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $data['proyecto'] }}</td>
                    <td class="px-3 py-1.5 text-gray-500">{{ $data['procesos'] }}</td>
                    <td class="px-3 py-1.5 text-right font-mono">{{ $data['jornales'] }}</td>
                    <td class="px-3 py-1.5 text-right font-mono">${{ number_format($data['costo_nomina'], 0) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono">
                        @if($data['valor_mueble'] > 0) ${{ number_format($data['valor_mueble'], 0) }} @else <span class="text-gray-400">-</span> @endif
                    </td>
                </tr>
            @endforeach
            <tr class="bg-gray-50 font-semibold">
                <td class="px-3 py-2 text-gray-700" colspan="3">Total en producción</td>
                <td class="px-3 py-2 text-right font-mono">{{ $totalJornalesProd }}</td>
                <td class="px-3 py-2 text-right font-mono">${{ number_format($totalCostoProd, 0) }}</td>
                <td class="px-3 py-2 text-right font-mono">
                    @if($totalValorProd > 0) ${{ number_format($totalValorProd, 0) }} @else <span class="text-gray-400">-</span> @endif
                </td>
            </tr>
        </tbody>
    </table>
</div>
@else
<p class="text-gray-400 text-sm mt-6">No hay muebles en producción actualmente.</p>
@endif

@endif
@endsection
