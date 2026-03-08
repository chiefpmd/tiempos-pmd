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
    .today-col { box-shadow: inset 0 0 0 2px #ef4444; }
    .today-header { background-color: #fef2f2 !important; color: #dc2626 !important; font-weight: bold; }
    .proj-tag {
        display: inline-block; padding: 1px 4px; border-radius: 3px;
        font-size: 10px; line-height: 16px; font-weight: 600; color: #fff;
        letter-spacing: 0.3px;
    }
    .cap-cell { font-size: 12px; font-weight: 600; text-align: center; padding: 4px 8px; }
    .cap-high { background-color: #dbeafe; color: #1e40af; }
    .cap-med { background-color: #fef3c7; color: #92400e; }
    .cap-zero { background-color: #f9fafb; color: #d1d5db; }
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

    {{-- ===== PEOPLE PER PROJECT PER WEEK ===== --}}
    <div class="mb-5">
        <h2 class="text-sm font-bold mb-2 text-gray-700">Personas por Proyecto por Semana</h2>
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 sticky left-0 bg-gray-50 z-10 w-36">Proyecto</th>
                        @foreach($semanasNums as $sem)
                            <th class="cap-cell text-gray-500 {{ $sem == now()->weekOfYear ? 'today-header' : '' }}">S{{ $sem }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($proyectos as $proy)
                    <tr>
                        <td class="px-3 py-2 font-medium sticky left-0 bg-white z-10 whitespace-nowrap">
                            <span class="proj-tag" style="background-color: {{ $proyectoColores[$proy->nombre] ?? '#6b7280' }}">{{ $proyectoAbrev[$proy->nombre] ?? $proy->nombre }}</span>
                            <span class="text-gray-500 ml-1">{{ $proy->nombre }}</span>
                        </td>
                        @foreach($semanasNums as $sem)
                            @php
                                $count = $proyectoCapacidad[$proy->nombre][$sem] ?? 0;
                                $cellClass = $count >= 5 ? 'cap-high' : ($count > 0 ? 'cap-med' : 'cap-zero');
                            @endphp
                            <td class="cap-cell {{ $cellClass }} {{ $sem == now()->weekOfYear ? 'today-col' : '' }}">
                                {{ $count ?: '-' }}
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ===== DEPARTMENT CAPACITY PER WEEK ===== --}}
    <div class="mb-5">
        <h2 class="text-sm font-bold mb-2 text-gray-700">Capacidad por Departamento por Semana <span class="font-normal text-gray-400">(asignados / total — libres)</span></h2>
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 sticky left-0 bg-gray-50 z-10 w-36">Departamento</th>
                        @foreach($semanasNums as $sem)
                            <th class="cap-cell text-gray-500 {{ $sem == now()->weekOfYear ? 'today-header' : '' }}">S{{ $sem }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($deptCapacidad as $equipo => $semanaData)
                    <tr>
                        <td class="px-3 py-2 font-medium sticky left-0 bg-white z-10 whitespace-nowrap">
                            {{ $equipo }}
                            <span class="text-[10px] text-gray-400">({{ $deptTotals[$equipo] ?? 0 }})</span>
                        </td>
                        @foreach($semanasNums as $sem)
                            @php
                                $data = $semanaData[$sem] ?? ['asignados' => 0, 'total' => $deptTotals[$equipo] ?? 0, 'libres' => $deptTotals[$equipo] ?? 0];
                                $libres = $data['libres'];
                            @endphp
                            <td class="cap-cell {{ $sem == now()->weekOfYear ? 'today-col' : '' }}" title="{{ $data['asignados'] }} asignados de {{ $data['total'] }} — {{ $libres }} libres">
                                <span class="text-gray-600">{{ $data['asignados'] }}</span><span class="text-gray-300">/{{ $data['total'] }}</span>
                                @if($libres > 0)
                                    <div class="text-[9px] cap-free">{{ $libres }} libre{{ $libres > 1 ? 's' : '' }}</div>
                                @else
                                    <div class="text-[9px] cap-none">lleno</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ===== LEADER AVAILABILITY GRID ===== --}}
    <div class="mb-3">
        <h2 class="text-sm font-bold mb-2 text-gray-700">Disponibilidad por Lider</h2>
        <div class="flex space-x-4 mb-2 text-xs">
            <span class="flex items-center"><span class="inline-block w-4 h-4 rounded libre border mr-1"></span> Libre</span>
            <span class="flex items-center"><span class="inline-block w-4 h-4 rounded un-proyecto mr-1"></span> 1 Proyecto</span>
            <span class="flex items-center"><span class="inline-block w-4 h-4 rounded alerta mr-1"></span> 2+ Proyectos</span>
            <span class="flex items-center"><span class="inline-block w-4 h-4 rounded festivo mr-1"></span> Festivo</span>
            <span class="flex items-center"><span class="inline-block w-4 h-4 rounded nomina-proyecto mr-1"></span> Nomina</span>
        </div>
    </div>

    @foreach($equipos as $equipo => $miembros)
    <div class="mb-4">
        <h2 class="text-sm font-bold mb-1 text-gray-700">{{ $equipo }} <span class="font-normal text-gray-400">({{ $deptTotals[$equipo] ?? $miembros->count() }} personas)</span></h2>
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-2 py-1 sticky left-0 bg-gray-50 z-10 w-36"></th>
                        @foreach($semanas as $numSemana => $diasSemana)
                            <th class="text-center font-bold text-blue-600 bg-blue-50 border-l-2 border-blue-200 text-xs" colspan="{{ count($diasSemana) }}">
                                S{{ $numSemana }}
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="px-2 py-1 text-left font-medium text-gray-500 sticky left-0 bg-gray-50 z-10 w-36">Lider</th>
                        @foreach($diasHabiles as $dia)
                            @php
                                $diaStr = $dia->format('Y-m-d');
                                $esFestivo = isset($festivos[$diaStr]);
                                $esHoy = $diaStr === $hoy;
                            @endphp
                            <th class="dash-cell text-center font-medium {{ $esHoy ? 'today-header' : ($esFestivo ? 'festivo-header' : 'text-gray-400') }} {{ $dia->isMonday() ? 'border-l-2 border-blue-200' : '' }}"
                                @if($esFestivo) title="{{ $festivos[$diaStr] }}" @endif>
                                {{ $dia->locale('es')->isoFormat('dd') }}<br>{{ $dia->format('d/M') }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($miembros as $persona)
                    <tr>
                        <td class="px-2 py-1 font-medium sticky left-0 bg-white z-10 whitespace-nowrap">
                            <span class="inline-block w-3 h-3 rounded-full mr-1 align-middle" style="background-color: {{ $persona->color_hex }}"></span>
                            <span class="align-middle">{{ Str::limit($persona->nombre, 20) }}</span>
                            @if(isset($teamCounts[$persona->id]))
                                <span class="text-[9px] text-gray-400 ml-0.5">({{ $teamCounts[$persona->id] }})</span>
                            @endif
                        </td>
                        @foreach($diasHabiles as $dia)
                            @php
                                $fechaStr = $dia->format('Y-m-d');
                                $esFestivo = isset($festivos[$fechaStr]);
                                $esHoy = $fechaStr === $hoy;
                                $info = $disponibilidad[$persona->id][$fechaStr] ?? ['proyectos' => 0, 'nombres' => [], 'horas' => 0, 'source' => 'tiempos'];
                                $isNomina = ($info['source'] ?? 'tiempos') === 'nomina';
                                if ($esFestivo) {
                                    $class = 'festivo';
                                } elseif ($isNomina && $info['proyectos'] > 0) {
                                    $class = 'nomina-proyecto';
                                } elseif ($info['proyectos'] === 0) {
                                    $class = 'libre';
                                } elseif ($info['proyectos'] === 1) {
                                    $class = 'un-proyecto';
                                } else {
                                    $class = 'alerta';
                                }
                            @endphp
                            <td class="dash-cell text-center {{ $class }} {{ $esHoy ? 'today-col' : '' }} {{ $dia->isMonday() ? 'border-l-2 border-blue-200' : '' }}"
                                title="{{ $esFestivo ? $festivos[$fechaStr] : implode(', ', $info['nombres']) . ($info['horas'] > 0 ? ' (' . $info['horas'] . 'p)' : ($isNomina ? ' (nomina)' : '')) }}">
                                @if(!$esFestivo && $info['proyectos'] > 0)
                                    @foreach($info['nombres'] as $nombre)
                                        <span class="proj-tag" style="background-color: {{ $proyectoColores[$nombre] ?? '#6b7280' }}" title="{{ $nombre }}">{{ $proyectoAbrev[$nombre] ?? Str::limit($nombre, 5, '') }}</span>
                                    @endforeach
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
    @endif
</div>
@endsection
