@extends('layouts.app')
@section('title', 'Dashboard - Disponibilidad')

@push('styles')
<style>
    .dash-cell { min-width: 48px; height: 32px; font-size: 10px; padding: 1px; }
    .libre { background-color: #f0fdf4; }
    .un-proyecto { background-color: #eff6ff; }
    .alerta { background-color: #fef2f2; font-weight: bold; }
    .festivo { background-color: #f3e8ff; }
    .festivo-header { background-color: #e9d5ff; color: #7c3aed; }
    .nomina-proyecto { background-color: #fffbeb; }
    .today-col { background-color: #eff6ff; }
    .today-header { background-color: #dbeafe !important; color: #1e40af !important; font-weight: bold; }
    .proj-tag {
        display: inline-block; padding: 1px 4px; border-radius: 3px;
        font-size: 10px; line-height: 16px; font-weight: 600; color: #fff;
        letter-spacing: 0.3px;
    }
    .cap-cell { font-size: 12px; font-weight: 600; text-align: center; padding: 4px 8px; }
    .cap-high { color: #374151; }
    .cap-med { color: #374151; }
    .cap-zero { color: #d1d5db; }
    .cap-free { color: #16a34a; font-weight: 700; }
    .cap-none { color: #dc2626; font-weight: 700; }
</style>
@endpush

@section('content')
<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-3">
        <div>
            <h1 class="text-xl font-bold">Dashboard</h1>
            <p class="text-sm text-gray-500">Disponibilidad y capacidad por proyecto</p>
        </div>
        <div class="flex items-center gap-2">
            @if(isset($canGoBack))
            <div class="flex items-center gap-1 text-sm">
                @if($canGoBack)
                    <a href="{{ route('dashboard', ['desde' => $allDesde]) }}" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300" title="Ver todo">&laquo;</a>
                    <a href="{{ route('dashboard', ['desde' => $prevDesde]) }}" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300" title="2 semanas atras">&lsaquo;</a>
                @endif
                <a href="{{ route('dashboard') }}" class="px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-xs font-medium">Hoy</a>
                <a href="{{ route('dashboard', ['desde' => $nextDesde]) }}" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300" title="2 semanas adelante">&rsaquo;</a>
            </div>
            @endif
            <a href="{{ route('export.dashboard.html', request()->query()) }}" class="bg-gray-600 text-white px-3 py-1.5 rounded text-sm hover:bg-gray-700">Descargar HTML</a>
        </div>
    </div>

    @if($personal->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">No hay proyectos activos.</div>
    @else
    @php
        $equipos = $personal->groupBy('equipo');
        $semanas = collect($diasHabiles)->groupBy(fn($d) => $d->weekOfYear);
        $hoy = now()->format('Y-m-d');
        $proyectoColores = [];
        $proyectoAbrev = [];
        $colores = ['#3b82f6','#f59e0b','#10b981','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16'];
        foreach ($proyectos as $i => $proy) {
            $proyectoColores[$proy->nombre] = $colores[$i % count($colores)];
            $proyectoAbrev[$proy->nombre] = $proy->abreviacion ?: Str::limit($proy->nombre, 5, '');
        }
    @endphp

    {{-- ===== SUMMARY CARDS ===== --}}
    @php
        $totalPersonal = collect($deptTotals)->sum();
        $hoyKey = now()->format('Y-m-d');
        $libresHoy = 0;
        $alertasHoy = 0;
        foreach ($disponibilidad as $pId => $dias) {
            $info = $dias[$hoyKey] ?? null;
            if ($info) {
                if ($info['proyectos'] === 0) $libresHoy++;
                if ($info['proyectos'] > 1) $alertasHoy++;
            }
        }
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <div class="bg-white rounded-lg shadow p-3">
            <div class="text-xs text-gray-500">Proyectos activos</div>
            <div class="text-2xl font-bold text-blue-600">{{ $proyectos->count() }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3">
            <div class="text-xs text-gray-500">Personal total</div>
            <div class="text-2xl font-bold text-gray-800">{{ $totalPersonal }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3">
            <div class="text-xs text-gray-500">Lideres libres hoy</div>
            <div class="text-2xl font-bold {{ $libresHoy > 0 ? 'text-green-600' : 'text-gray-400' }}">{{ $libresHoy }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3">
            <div class="text-xs text-gray-500">Alertas hoy (2+ proy)</div>
            <div class="text-2xl font-bold {{ $alertasHoy > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $alertasHoy }}</div>
        </div>
    </div>

    {{-- ===== PEOPLE PER PROJECT PER WEEK (by process) ===== --}}
    @php
        $procesoLabels = ['Carpintería' => 'Carp.', 'Barniz' => 'Barniz', 'Instalación' => 'Inst.'];
        $procesoColoresTab = ['Carpintería' => '#f59e0b', 'Barniz' => '#10b981', 'Instalación' => '#3b82f6'];
        $totalesPorProceso = []; // proceso => [semana => total]
        foreach (['Carpintería', 'Barniz', 'Instalación'] as $proc) {
            foreach ($semanasNums as $sem) {
                $sum = 0;
                foreach ($proyectos as $proy) {
                    $sum += $proyectoCapacidadProceso[$proy->nombre][$proc][$sem] ?? 0;
                }
                $totalesPorProceso[$proc][$sem] = $sum;
            }
        }
    @endphp
    <div class="mb-5">
        <h2 class="text-sm font-bold mb-2 text-gray-700">Personas por Proyecto por Semana</h2>
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 sticky left-0 bg-gray-50 z-10">Proyecto</th>
                        <th class="px-1 py-2 text-right font-medium text-gray-500 sticky bg-gray-50 z-10" style="left: auto;">Proc.</th>
                        @foreach($semanasNums as $sem)
                            <th class="cap-cell text-gray-500 {{ $sem == now()->weekOfYear ? 'today-header' : '' }}">S{{ $sem }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($proyectos as $proy)
                        @foreach(['Carpintería', 'Barniz', 'Instalación'] as $pi => $proc)
                        <tr class="{{ $pi === 0 ? 'border-t-2 border-gray-300' : '' }}">
                            <td class="px-3 py-1 sticky left-0 bg-white z-10 whitespace-nowrap">
                                @if($pi === 0)
                                    <span class="proj-tag" style="background-color: {{ $proyectoColores[$proy->nombre] ?? '#6b7280' }}">{{ $proyectoAbrev[$proy->nombre] ?? $proy->nombre }}</span>
                                    <span class="text-gray-500 ml-1">{{ $proy->nombre }}</span>
                                @endif
                            </td>
                            <td class="px-1 py-1 text-right bg-white z-10 whitespace-nowrap">
                                <span class="text-[10px]" style="color: {{ $procesoColoresTab[$proc] }}; font-weight: 600;">{{ $procesoLabels[$proc] }}</span>
                            </td>
                            @foreach($semanasNums as $sem)
                                @php
                                    $count = $proyectoCapacidadProceso[$proy->nombre][$proc][$sem] ?? 0;
                                    $cellClass = $count >= 5 ? 'cap-high' : ($count > 0 ? 'cap-med' : 'cap-zero');
                                @endphp
                                <td class="cap-cell {{ $cellClass }} {{ $sem == now()->weekOfYear ? 'today-col' : '' }}">
                                    {{ $count ?: '-' }}
                                </td>
                            @endforeach
                        </tr>
                        @endforeach
                    @endforeach
                    {{-- Totals per process --}}
                    @foreach(['Carpintería', 'Barniz', 'Instalación'] as $proc)
                    <tr class="{{ $proc === 'Carpintería' ? 'border-t-4 border-gray-400' : 'border-t border-gray-200' }}" style="background-color: {{ $procesoColoresTab[$proc] }}15;">
                        <td class="px-3 py-1 font-bold sticky left-0 z-10 whitespace-nowrap" style="background-color: {{ $procesoColoresTab[$proc] }}15;">
                            <span style="color: {{ $procesoColoresTab[$proc] }};">Total</span>
                            <span class="text-[10px] text-gray-400 ml-1">({{ $deptTotals[$proc] ?? '?' }})</span>
                        </td>
                        <td class="px-1 py-1 text-right z-10 whitespace-nowrap" style="background-color: {{ $procesoColoresTab[$proc] }}15;">
                            <span class="text-[10px] font-bold" style="color: {{ $procesoColoresTab[$proc] }};">{{ $procesoLabels[$proc] }}</span>
                        </td>
                        @foreach($semanasNums as $sem)
                            @php
                                $total = $totalesPorProceso[$proc][$sem];
                                $disponibles = ($deptTotals[$proc] ?? 0) - $total;
                            @endphp
                            <td class="cap-cell {{ $sem == now()->weekOfYear ? 'today-col' : '' }}" style="background-color: {{ $disponibles < 0 ? '#fef2f2' : $procesoColoresTab[$proc] . '15' }};">
                                <span class="font-bold" style="color: {{ $disponibles < 0 ? '#dc2626' : $procesoColoresTab[$proc] }};">{{ $total }}</span>
                                @if($disponibles > 0)
                                    <div class="text-[9px] cap-free">{{ $disponibles }} disp.</div>
                                @elseif($disponibles == 0 && ($deptTotals[$proc] ?? 0) > 0)
                                    <div class="text-[9px] cap-none">lleno</div>
                                @else
                                    <div class="text-[9px] cap-none">{{ $disponibles }} faltan</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @endif
</div>
@endsection
