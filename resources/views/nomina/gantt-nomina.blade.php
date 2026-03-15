@extends('layouts.app')
@section('title', 'Gantt Nómina')

@push('styles')
<style>
    .gn-cell { min-width: 36px; font-size: 10px; padding: 1px; border-right: 1px solid rgba(209,213,219,0.3); }
    .gn-cell-filled { cursor: default; }
    .mueble-sep { border-top: 2px solid #d1d5db; }
    .proyecto-sep { border-top: 3px solid #6b7280; }
    .festivo { background-color: #f3e8ff; }
    .festivo-header { background-color: #e9d5ff; color: #7c3aed; }
    .today-col { background-color: #eff6ff; }
    .today-header { background-color: #dbeafe !important; color: #1e40af !important; font-weight: bold; }
    .equipo-dot {
        display: inline-block; width: 8px; height: 8px; border-radius: 50%;
        margin: 0 1px;
    }
    .sticky-col { position: sticky; left: 0; z-index: 5; background: #fff; }
    .sticky-head { position: sticky; left: 0; z-index: 21; }
    .gn-sticky-head th { position: sticky; top: 0; z-index: 20; }
    .gn-sticky-head tr:first-child th { top: 0; }
    .gn-sticky-head tr:nth-child(2) th { top: 25px; }
    .jornal-badge {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 16px; height: 16px; border-radius: 3px;
        font-size: 9px; font-weight: 700; color: #fff;
        padding: 0 3px; line-height: 1;
    }
</style>
@endpush

@section('content')
<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-3">
        <div>
            <h1 class="text-xl font-bold">Gantt Nomina</h1>
            <p class="text-sm text-gray-500">Uso real del personal por mueble (de nomina semanal)</p>
        </div>
        <div class="flex items-center gap-2">
            @if(isset($canGoBack))
            <div class="flex items-center gap-1 text-sm">
                @if($canGoBack)
                    <a href="{{ route('gantt.nomina', ['desde' => $allDesde]) }}" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300" title="Ver todo">&laquo;</a>
                    <a href="{{ route('gantt.nomina', ['desde' => $prevDesde]) }}" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300" title="2 semanas atras">&lsaquo;</a>
                @endif
                <a href="{{ route('gantt.nomina') }}" class="px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-xs font-medium">Hoy</a>
                <a href="{{ route('gantt.nomina', ['desde' => $nextDesde]) }}" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300" title="2 semanas adelante">&rsaquo;</a>
            </div>
            @endif
        </div>
    </div>

    @if($proyectos->isEmpty() || empty($diasHabiles))
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">No hay proyectos activos.</div>
    @else

    {{-- Legend --}}
    <div class="flex flex-wrap gap-3 mb-3 text-xs">
        @foreach($equipoColores as $equipo => $color)
            <span class="flex items-center">
                <span class="equipo-dot" style="background-color: {{ $color }};"></span>
                <span class="ml-1">{{ $equipo }}</span>
            </span>
        @endforeach
    </div>

    @php
        $semanas = collect($diasHabiles)->groupBy(fn($d) => $d->weekOfYear);
        $hoy = now()->format('Y-m-d');
        $semanaActual = now()->weekOfYear;
        $semanaTotalCol = $semanaActual + 1; // columna total va después de esta semana
        // Índice del último día de semanaTotalCol (o la última semana disponible si no existe)
        $totalColIndex = null;
        $diasArray = array_values($diasHabiles);
        foreach ($diasArray as $idx => $d) {
            if ($d->weekOfYear <= $semanaTotalCol) {
                $totalColIndex = $idx;
            }
        }
        if ($totalColIndex === null) $totalColIndex = count($diasArray) - 1;
    @endphp

    <div class="bg-white rounded-lg shadow overflow-auto" style="max-height: 85vh;">
        <table class="min-w-full text-xs border-collapse">
            <thead class="bg-gray-50 gn-sticky-head">
                <tr>
                    <th class="px-2 py-1 text-left font-medium text-gray-500 sticky-head bg-gray-50 w-48" rowspan="2">Mueble</th>
                    @foreach($semanas as $numSemana => $diasSemana)
                        <th class="text-center font-bold text-blue-600 bg-blue-50 border-l-2 border-blue-200 text-xs" colspan="{{ count($diasSemana) }}">
                            S{{ $numSemana }}
                        </th>
                        @if($numSemana == $semanaTotalCol)
                            <th class="px-2 py-1 text-center font-medium text-gray-600 bg-gray-100 border-l-2 border-gray-400" rowspan="2" style="min-width: 50px;">Total<br>Jornales</th>
                        @endif
                    @endforeach
                    @if(!$semanas->keys()->contains($semanaTotalCol))
                        <th class="px-2 py-1 text-center font-medium text-gray-600 bg-gray-100 border-l-2 border-gray-400" rowspan="2" style="min-width: 50px;">Total<br>Jornales</th>
                    @endif
                </tr>
                <tr>
                    @foreach($diasHabiles as $idx => $dia)
                        @php
                            $diaStr = $dia->format('Y-m-d');
                            $esFestivo = isset($festivos[$diaStr]);
                            $esHoy = $diaStr === $hoy;
                        @endphp
                        <th class="gn-cell text-center font-medium {{ $esHoy ? 'today-header' : ($esFestivo ? 'festivo-header' : 'text-gray-400') }} {{ $dia->isMonday() ? 'border-l-2 border-blue-200' : '' }}"
                            @if($esFestivo) title="{{ $festivos[$diaStr] }}" @endif>
                            {{ $dia->locale('es')->isoFormat('dd') }}<br>{{ $dia->format('d/M') }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($proyectos as $pi => $proyecto)
                    @php $muebles = $proyecto->muebles; @endphp
                    @if($muebles->isEmpty()) @continue @endif

                    {{-- Project header row --}}
                    <tr class="{{ $pi > 0 ? 'proyecto-sep' : '' }}" style="background-color: {{ $proyectoColores[$proyecto->id] ?? '#6b7280' }}15;">
                        <td class="px-2 py-1.5 font-bold sticky-col" style="background-color: {{ $proyectoColores[$proyecto->id] ?? '#6b7280' }}15;" colspan="{{ count($diasHabiles) + 2 }}">
                            <span class="inline-block px-2 py-0.5 rounded text-white text-xs font-bold" style="background-color: {{ $proyectoColores[$proyecto->id] ?? '#6b7280' }};">
                                {{ $proyecto->nombre }}
                            </span>
                        </td>
                    </tr>

                    @foreach($muebles as $mi => $mueble)
                    <tr class="{{ $mi > 0 ? 'mueble-sep' : 'border-t-2 border-gray-300' }}">
                        <td class="px-2 py-1 sticky-col bg-white whitespace-nowrap">
                            <span class="font-semibold text-gray-700">M{{ $mueble->numero }}</span>
                            <span class="text-gray-400 ml-1">{{ Str::limit($mueble->descripcion, 25) }}</span>
                        </td>
                        @php
                            $totalMuebleJornales = 0;
                            // Pre-calculate total
                            foreach ($diasHabiles as $d) {
                                $eqs = $nominaMap[$mueble->id][$d->format('Y-m-d')] ?? [];
                                foreach ($eqs as $eq) { $totalMuebleJornales += $eq['count']; }
                            }
                            $totalInserted = false;
                        @endphp
                        @foreach($diasHabiles as $idx => $dia)
                            @php
                                $fechaStr = $dia->format('Y-m-d');
                                $esFestivo = isset($festivos[$fechaStr]);
                                $esHoy = $fechaStr === $hoy;
                                $equipos = $nominaMap[$mueble->id][$fechaStr] ?? [];
                            @endphp
                            <td class="gn-cell text-center {{ $esHoy ? 'today-col' : '' }} {{ $esFestivo ? 'festivo' : '' }} {{ $dia->isMonday() ? 'border-l-2 border-blue-200' : '' }}"
                                @if(!$esFestivo && !empty($equipos))
                                    title="@foreach($equipos as $eq => $data){{ $eq }}: {{ implode(', ', $data['personas']) }}&#10;@endforeach"
                                @endif>
                                @if(!$esFestivo && !empty($equipos))
                                    @foreach($equipos as $equipo => $data)
                                        <span class="jornal-badge" style="background-color: {{ $equipoColores[$equipo] ?? '#6b7280' }};">{{ $data['count'] }}</span>
                                    @endforeach
                                @endif
                            </td>
                            @if($idx == $totalColIndex && !$totalInserted)
                                @php $totalInserted = true; @endphp
                                <td class="px-2 py-1 text-center font-bold text-gray-700 border-l-2 border-gray-400 bg-gray-100" style="min-width: 50px;">
                                    {{ $totalMuebleJornales ?: '-' }}
                                </td>
                            @endif
                        @endforeach
                        @if(!$totalInserted)
                            <td class="px-2 py-1 text-center font-bold text-gray-700 border-l-2 border-gray-400 bg-gray-100" style="min-width: 50px;">
                                {{ $totalMuebleJornales ?: '-' }}
                            </td>
                        @endif
                    </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
