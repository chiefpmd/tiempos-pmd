@extends('layouts.app')
@section('title', 'Gantt Anual ' . $anio)

@push('styles')
<style>
    .gantt-table { border-collapse: collapse; width: 100%; table-layout: fixed; }
    .gantt-table th, .gantt-table td { border: 1px solid #e5e7eb; }
    .gantt-table thead th { position: sticky; top: 0; z-index: 10; background: #f9fafb; }
    .month-header { text-align: center; font-weight: 700; font-size: 13px; padding: 6px 0; background: #f3f4f6; }
    .week-header { text-align: center; font-size: 9px; color: #6b7280; padding: 2px 0; background: #f9fafb; min-width: 32px; }
    .proj-name { position: sticky; left: 0; z-index: 5; background: #fff; white-space: nowrap; padding: 4px 10px; font-size: 12px; font-weight: 600; min-width: 180px; max-width: 180px; }
    .proj-dates { position: sticky; left: 180px; z-index: 5; background: #fff; white-space: nowrap; padding: 4px 6px; font-size: 10px; color: #6b7280; min-width: 200px; max-width: 200px; }
    .week-cell { padding: 0; height: 32px; position: relative; }
    .gantt-bar-fill { position: absolute; top: 4px; bottom: 4px; border-radius: 3px; opacity: 0.85; }
    .gantt-bar-fill:hover { opacity: 1; }
    .status-badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 9px; font-weight: 600; margin-left: 6px; }
    .date-input { width: 90px; font-size: 10px; padding: 1px 3px; border: 1px solid #d1d5db; border-radius: 3px; }
    .date-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
    .save-indicator { display: none; font-size: 9px; color: #16a34a; font-weight: 600; }
    .save-indicator.show { display: inline; }
    .no-dates { color: #9ca3af; font-style: italic; font-size: 9px; }
</style>
@endpush

@section('content')
<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-3">
        <div>
            <h1 class="text-xl font-bold">Gantt Anual {{ $anio }}</h1>
            <p class="text-sm text-gray-500">Fechas independientes por proyecto - editable</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('gantt.anual', ['anio' => $anio - 1]) }}" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 text-sm">&lsaquo; {{ $anio - 1 }}</a>
            <a href="{{ route('gantt.anual') }}" class="px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-xs font-medium">Hoy</a>
            <a href="{{ route('gantt.anual', ['anio' => $anio + 1]) }}" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 text-sm">{{ $anio + 1 }} &rsaquo;</a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-auto" style="max-height: calc(100vh - 140px);">
        <table class="gantt-table">
            <thead>
                <tr>
                    <th class="proj-name" rowspan="2" style="border-bottom: 2px solid #d1d5db;">Proyecto</th>
                    <th class="proj-dates" rowspan="2" style="border-bottom: 2px solid #d1d5db;">Fechas Gantt</th>
                    @foreach($meses as $mes)
                        <th colspan="{{ count($mes['semanas']) }}" class="month-header">{{ $mes['nombre'] }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach($meses as $mes)
                        @foreach($mes['semanas'] as $sem)
                            <th class="week-header">{{ $sem['inicio']->weekOfYear }}</th>
                        @endforeach
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($proyectos as $i => $proyecto)
                    @php
                        $color = $colores[$i % count($colores)];
                        $gd = $ganttData[$proyecto->id];
                        $hasDates = $gd['fecha_inicio'] && $gd['fecha_fin'];
                    @endphp
                    <tr data-proyecto-id="{{ $proyecto->id }}">
                        <td class="proj-name">
                            <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:{{ $color }};margin-right:6px;vertical-align:middle;"></span>
                            {{ $proyecto->nombre }}
                            @if($proyecto->status === 'completado')
                                <span class="status-badge" style="background:#dcfce7;color:#166534;">Completado</span>
                            @elseif($proyecto->status === 'pausado')
                                <span class="status-badge" style="background:#fef3c7;color:#92400e;">Pausado</span>
                            @endif
                        </td>
                        <td class="proj-dates">
                            @if($isAdmin)
                                <input type="date" class="date-input gantt-inicio" value="{{ $hasDates ? $gd['fecha_inicio']->format('Y-m-d') : '' }}" data-proyecto="{{ $proyecto->id }}">
                                <input type="date" class="date-input gantt-fin" value="{{ $hasDates ? $gd['fecha_fin']->format('Y-m-d') : '' }}" data-proyecto="{{ $proyecto->id }}">
                                <span class="save-indicator" id="saved-{{ $proyecto->id }}">OK</span>
                            @else
                                @if($hasDates)
                                    {{ $gd['fecha_inicio']->format('d/m/Y') }} - {{ $gd['fecha_fin']->format('d/m/Y') }}
                                @else
                                    <span class="no-dates">Sin fechas</span>
                                @endif
                            @endif
                        </td>
                        @foreach($meses as $mes)
                            @foreach($mes['semanas'] as $sem)
                                @php
                                    $semInicio = $sem['inicio'];
                                    $semFin = $sem['fin'];
                                    $overlaps = $hasDates && $gd['fecha_inicio']->lte($semFin) && $gd['fecha_fin']->gte($semInicio);
                                @endphp
                                <td class="week-cell" data-sem-inicio="{{ $semInicio->format('Y-m-d') }}" data-sem-fin="{{ $semFin->format('Y-m-d') }}">
                                    @if($overlaps)
                                        @php
                                            $cellDays = max($semInicio->diffInDays($semFin) + 1, 1);
                                            $barStartDay = $gd['fecha_inicio']->gt($semInicio) ? $semInicio->diffInDays($gd['fecha_inicio']) : 0;
                                            $barEndDay = $gd['fecha_fin']->lt($semFin) ? $semInicio->diffInDays($gd['fecha_fin']) + 1 : $cellDays;
                                            $leftPct = ($barStartDay / $cellDays) * 100;
                                            $widthPct = (($barEndDay - $barStartDay) / $cellDays) * 100;
                                        @endphp
                                        <div class="gantt-bar-fill" style="left:{{ $leftPct }}%;width:{{ $widthPct }}%;background:{{ $color }};"></div>
                                    @endif
                                </td>
                            @endforeach
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $totalSemanas + 2 }}" class="text-center py-8 text-gray-400">No hay proyectos registrados</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Legend --}}
    <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-600">
        @foreach($proyectos as $i => $proyecto)
            @php $gd = $ganttData[$proyecto->id]; @endphp
            <div class="flex items-center gap-1">
                <span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:{{ $colores[$i % count($colores)] }};"></span>
                {{ $proyecto->abreviacion ?? $proyecto->nombre }}
                @if($gd['fecha_inicio'] && $gd['fecha_fin'])
                    <span class="text-gray-400">({{ $gd['fecha_inicio']->format('d/m') }} - {{ $gd['fecha_fin']->format('d/m') }})</span>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const saveUrl = @json(route('gantt.anual.guardar'));
    const colores = @json($colores);
    const proyectoCount = @json(count($proyectos));

    // Debounce saves per project
    const timers = {};

    document.querySelectorAll('.gantt-inicio, .gantt-fin').forEach(input => {
        input.addEventListener('change', function() {
            const proyectoId = this.dataset.proyecto;
            const row = document.querySelector(`tr[data-proyecto-id="${proyectoId}"]`);
            const inicioInput = row.querySelector('.gantt-inicio');
            const finInput = row.querySelector('.gantt-fin');

            const inicio = inicioInput.value;
            const fin = finInput.value;

            if (!inicio || !fin) return;
            if (fin < inicio) {
                finInput.style.borderColor = '#ef4444';
                return;
            }
            finInput.style.borderColor = '#d1d5db';

            clearTimeout(timers[proyectoId]);
            timers[proyectoId] = setTimeout(() => {
                guardarFechas(proyectoId, inicio, fin, row);
            }, 300);
        });
    });

    function guardarFechas(proyectoId, inicio, fin, row) {
        const indicator = document.getElementById('saved-' + proyectoId);

        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                proyecto_id: proyectoId,
                fecha_inicio: inicio,
                fecha_fin: fin,
            }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                // Show saved indicator
                indicator.classList.add('show');
                setTimeout(() => indicator.classList.remove('show'), 1500);
                // Update bars in the row
                actualizarBarras(row, inicio, fin, proyectoId);
            }
        })
        .catch(err => {
            indicator.textContent = 'Error';
            indicator.style.color = '#dc2626';
            indicator.classList.add('show');
            setTimeout(() => {
                indicator.classList.remove('show');
                indicator.textContent = 'OK';
                indicator.style.color = '#16a34a';
            }, 2000);
        });
    }

    function actualizarBarras(row, inicioStr, finStr, proyectoId) {
        const inicio = new Date(inicioStr + 'T00:00:00');
        const fin = new Date(finStr + 'T00:00:00');

        // Determine color index
        const rows = document.querySelectorAll('tr[data-proyecto-id]');
        let colorIdx = 0;
        rows.forEach((r, i) => {
            if (r.dataset.proyectoId === String(proyectoId)) colorIdx = i;
        });
        const color = colores[colorIdx % colores.length];

        const cells = row.querySelectorAll('.week-cell');
        cells.forEach(cell => {
            // Remove existing bars
            cell.querySelectorAll('.gantt-bar-fill').forEach(b => b.remove());

            const semInicio = new Date(cell.dataset.semInicio + 'T00:00:00');
            const semFin = new Date(cell.dataset.semFin + 'T00:00:00');

            if (inicio <= semFin && fin >= semInicio) {
                const cellDays = Math.max(Math.round((semFin - semInicio) / 86400000) + 1, 1);
                const barStartDay = inicio > semInicio ? Math.round((inicio - semInicio) / 86400000) : 0;
                const barEndDay = fin < semFin ? Math.round((fin - semInicio) / 86400000) + 1 : cellDays;
                const leftPct = (barStartDay / cellDays) * 100;
                const widthPct = ((barEndDay - barStartDay) / cellDays) * 100;

                const bar = document.createElement('div');
                bar.className = 'gantt-bar-fill';
                bar.style.left = leftPct + '%';
                bar.style.width = widthPct + '%';
                bar.style.background = color;
                cell.appendChild(bar);
            }
        });
    }
});
</script>
@endpush
