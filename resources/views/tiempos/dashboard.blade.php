@extends('layouts.app')
@section('title', 'Dashboard - Disponibilidad')

@push('styles')
<style>
    .dash-cell { min-width: 36px; height: 28px; font-size: 11px; }
    .libre { background-color: #d1fae5; }
    .un-proyecto { background-color: #bfdbfe; }
    .alerta { background-color: #fecaca; font-weight: bold; }
    .festivo { background-color: #f3e8ff; }
    .festivo-header { background-color: #e9d5ff; color: #7c3aed; }
</style>
@endpush

@section('content')
<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-3">
        <div>
            <h1 class="text-xl font-bold">Dashboard - Disponibilidad por Equipo</h1>
            <p class="text-sm text-gray-500">Detección de sobreposición entre proyectos</p>
        </div>
        <a href="{{ route('export.general') }}" class="bg-green-600 text-white px-3 py-1.5 rounded text-sm hover:bg-green-700">Exportar General</a>
    </div>

    <div class="flex space-x-4 mb-3 text-xs">
        <span class="flex items-center"><span class="inline-block w-4 h-4 rounded libre mr-1"></span> Libre</span>
        <span class="flex items-center"><span class="inline-block w-4 h-4 rounded un-proyecto mr-1"></span> 1 Proyecto</span>
        <span class="flex items-center"><span class="inline-block w-4 h-4 rounded alerta mr-1"></span> ALERTA: 2+ Proyectos</span>
        <span class="flex items-center"><span class="inline-block w-4 h-4 rounded festivo mr-1"></span> Festivo</span>
    </div>

    @if($personal->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">No hay proyectos activos.</div>
    @else
    @php
        $equipos = $personal->groupBy('equipo');
    @endphp

    @php
        $semanas = collect($diasHabiles)->groupBy(fn($d) => $d->weekOfYear);
    @endphp

    @foreach($equipos as $equipo => $miembros)
    <div class="mb-4">
        <h2 class="text-sm font-bold mb-1 text-gray-700">{{ $equipo }}</h2>
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-2 py-1 sticky left-0 bg-gray-50 w-28"></th>
                        @foreach($semanas as $numSemana => $diasSemana)
                            <th class="text-center font-bold text-blue-600 bg-blue-50 border-l-2 border-blue-200 text-xs" colspan="{{ count($diasSemana) }}">
                                Sem {{ $numSemana }}
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="px-2 py-1 text-left font-medium text-gray-500 sticky left-0 bg-gray-50 w-28">Personal</th>
                        @foreach($diasHabiles as $dia)
                            @php $esFestivo = isset($festivos[$dia->format('Y-m-d')]); @endphp
                            <th class="dash-cell text-center font-medium {{ $esFestivo ? 'festivo-header' : 'text-gray-400' }} {{ $dia->isMonday() ? 'border-l-2 border-blue-200' : '' }}"
                                @if($esFestivo) title="{{ $festivos[$dia->format('Y-m-d')] }}" @endif>
                                {{ $dia->locale('es')->isoFormat('dd') }}<br>{{ $dia->format('d/M') }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($miembros as $persona)
                    <tr>
                        <td class="px-2 py-1 font-medium sticky left-0 bg-white whitespace-nowrap">
                            <span class="inline-block w-3 h-3 rounded-full mr-1" style="background-color: {{ $persona->color_hex }}"></span>
                            {{ $persona->nombre }}
                        </td>
                        @foreach($diasHabiles as $dia)
                            @php
                                $fechaStr = $dia->format('Y-m-d');
                                $esFestivo = isset($festivos[$fechaStr]);
                                $info = $disponibilidad[$persona->id][$fechaStr] ?? ['proyectos' => 0, 'nombres' => [], 'horas' => 0];
                                $class = $esFestivo ? 'festivo' : ($info['proyectos'] === 0 ? 'libre' : ($info['proyectos'] === 1 ? 'un-proyecto' : 'alerta'));
                            @endphp
                            <td class="dash-cell text-center {{ $class }} {{ $dia->isMonday() ? 'border-l-2 border-blue-200' : '' }}"
                                title="{{ $esFestivo ? $festivos[$fechaStr] : implode(', ', $info['nombres']) . ($info['horas'] > 0 ? ' (' . $info['horas'] . 'h)' : '') }}">
                                {{ $esFestivo ? '' : ($info['horas'] > 0 ? number_format($info['horas'], 0) : '') }}
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
