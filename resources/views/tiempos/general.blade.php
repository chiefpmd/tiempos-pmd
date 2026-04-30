@extends('layouts.app')
@section('title', 'Proyección')

@push('styles')
<style>
    .gen-cell { min-width: 36px; font-size: 11px; padding: 1px; border-right: 1px solid rgba(156,163,175,0.6) !important; }
    .week-start { position: relative; }
    .week-start::before {
        content: '';
        position: absolute;
        left: -1px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: rgba(59,130,246,0.45);
        z-index: 8;
        pointer-events: none;
    }
    .mueble-sep { border-top: 2px solid #d1d5db; }
    .proyecto-sep { border-top: 3px solid #6b7280; }

    /* Sticky headers (vertical scroll) */
    .gantt-sticky-head th { position: sticky; top: 0; z-index: 20; }
    .gantt-sticky-head tr:first-child th { top: 0; }
    .gantt-sticky-head tr:nth-child(2) th { top: 25px; }

    /* Sticky descripcion column */
    .sticky-desc { position: sticky; left: 56px; z-index: 5; background: #fff; }
    .sticky-desc-header { position: sticky; left: 56px; z-index: 21; }
    .add-mueble-form { display: none; }
    .add-mueble-form.active { display: flex; }
    .festivo { background-color: #f3e8ff; }
    .festivo-header { background-color: #e9d5ff; color: #7c3aed; }
    .current-week { background-color: rgba(59,130,246,0.07); }
    .current-week-header { background-color: rgba(59,130,246,0.15); }
    .mat-pedido { box-shadow: inset 2px 0 0 0 #ef4444; }
    .mat-entrega { box-shadow: inset -2px 0 0 0 #dc2626; }
    .mat-ambos { box-shadow: inset 2px 0 0 0 #ef4444, inset -2px 0 0 0 #dc2626; }
    .mat-pedido-header { border-left: 3px solid #ef4444 !important; }
    .mat-entrega-header { border-right: 3px solid #dc2626 !important; }

    /* Gantt row: 3 stacked bars */
    tr.gantt-row { position: relative; height: 48px; }
    .gantt-bar {
        position: absolute;
        border-radius: 3px;
        cursor: grab;
        z-index: 5;
        pointer-events: auto;
        display: flex;
        align-items: center;
        justify-content: center;
        user-select: none;
        box-sizing: border-box;
        border: 1px solid rgba(0,0,0,0.2);
        min-width: 4px;
        font-size: 9px;
        font-weight: bold;
        color: #1f2937;
        text-shadow: 0 0 3px rgba(255,255,255,0.8);
        overflow: hidden;
        white-space: nowrap;
        transition: box-shadow 0.15s;
    }
    .gantt-bar:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.3); z-index: 10; }
    .gantt-bar.dragging { cursor: grabbing; opacity: 0.7; }
    .gantt-bar[data-slot="0"] { top: 1px; height: 14px; }
    .gantt-bar[data-slot="1"] { top: 17px; height: 14px; }
    .gantt-bar[data-slot="2"] { top: 33px; height: 14px; }
    .gantt-handle {
        position: absolute; top: 0; width: 6px; height: 100%;
        cursor: col-resize; z-index: 6;
        display: flex; align-items: center; justify-content: center;
    }
    .gantt-handle::after {
        content: ''; width: 2px; height: 60%;
        background: rgba(255,255,255,0.6); border-radius: 1px;
    }
    .gantt-handle-left { left: 0; border-radius: 3px 0 0 3px; }
    .gantt-handle-right { right: 0; border-radius: 0 3px 3px 0; }

    /* Process color legend dots */
    .proc-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 2px; }
    .proc-dot-carp { background-color: #f59e0b; }
    .proc-dot-barn { background-color: #10b981; }
    .proc-dot-inst { background-color: #3b82f6; }

    /* Fecha entrega mueble */
    .fecha-entrega-cell { background-color: rgba(249,115,22,0.25) !important; }
    .fecha-entrega-marker {
        position: absolute; right: -1px; top: 0; bottom: 0; width: 3px;
        background: #f97316; z-index: 4;
    }

    /* Mueble instalado: atenuado, no editable */
    tr.mueble-instalado { opacity: 0.45; background-color: #f9fafb; }
    tr.mueble-instalado .day-cell { pointer-events: none; }
    tr.mueble-instalado .gantt-bar { pointer-events: none; filter: grayscale(0.7); }

    /* Fecha entrega popup */
    #fecha-entrega-popup {
        display: none; position: fixed; z-index: 100; background: #fff; border: 2px solid #f97316;
        border-radius: 6px; padding: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); font-size: 12px;
    }
    #fecha-entrega-popup.active { display: flex; align-items: center; gap: 6px; }
    #fecha-entrega-popup input[type="date"] {
        border: 1px solid #d1d5db; border-radius: 4px; padding: 2px 6px; font-size: 12px;
    }
    #fecha-entrega-popup button {
        border: none; border-radius: 4px; padding: 3px 10px; cursor: pointer; font-size: 11px; color: #fff;
    }
    #fecha-entrega-popup .save-btn { background: #f97316; }
    #fecha-entrega-popup .save-btn:hover { background: #ea580c; }
    #fecha-entrega-popup .clear-btn { background: #9ca3af; }
    #fecha-entrega-popup .cancel-btn { background: #d1d5db; color: #374151; }
</style>
@endpush

@php
    $weeksOptions = [];
    if (isset($ventanaInicio) && $ventanaInicio) {
        $cursor = $ventanaInicio->copy()->startOfWeek();
        for ($i = 0; $i < 4; $i++) {
            $weeksOptions[] = [
                'start' => $cursor->format('Y-m-d'),
                'label' => $cursor->locale('es')->isoFormat('D MMM'),
            ];
            $cursor->addWeek();
        }
    }
    $semanaActualStart = \Carbon\Carbon::now()->startOfWeek()->format('Y-m-d');
    $defaultWeek = collect($weeksOptions)->firstWhere('start', $semanaActualStart)['start'] ?? ($weeksOptions[0]['start'] ?? $semanaActualStart);
    $isAdminUser = auth()->user()->isAdmin();
@endphp

@section('content')
<div class="max-w-full mx-auto" x-data='proyeccionApp(@json($weeksOptions), "{{ $defaultWeek }}", {{ $isAdminUser ? 'true' : 'false' }})' x-init="init()">
    <div class="flex gap-3">
    <div class="flex-1 min-w-0">
    <div class="flex justify-between items-center mb-3">
        <h1 class="text-xl font-bold">Proyección</h1>
        <div class="flex items-center gap-2">
            @if($ventanaInicio ?? false)
            <div class="flex items-center gap-1 text-sm">
                <a href="{{ route('general', ['desde' => $prevDesde]) }}" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300" title="Semana anterior">&lsaquo;</a>
                <a href="{{ route('general') }}" class="px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-xs font-medium">Hoy</a>
                <a href="{{ route('general', ['desde' => $nextDesde]) }}" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300" title="Semana siguiente">&rsaquo;</a>
                <span class="ml-2 text-xs text-gray-500">{{ $ventanaInicio->format('d M') }} – {{ $ventanaFin->format('d M Y') }}</span>
            </div>
            @endif
            <a href="{{ route('export.general.html', request()->query()) }}" class="bg-gray-600 text-white px-3 py-1.5 rounded text-sm hover:bg-gray-700">Descargar HTML</a>
            <a href="{{ route('export.general') }}" class="bg-green-600 text-white px-3 py-1.5 rounded text-sm hover:bg-green-700">Exportar Excel</a>
        </div>
    </div>

    <div class="flex space-x-4 mb-3 text-xs">
        <span class="flex items-center"><span class="proc-dot proc-dot-carp"></span> Carpinteria</span>
        <span class="flex items-center"><span class="proc-dot proc-dot-barn"></span> Barniz</span>
        <span class="flex items-center"><span class="proc-dot proc-dot-inst"></span> Instalacion</span>
        <span class="flex items-center ml-2"><span style="display:inline-block;width:12px;height:8px;background:rgba(249,115,22,0.35);border-right:3px solid #f97316;margin-right:3px;"></span> Fecha entrega</span>
        <span class="text-gray-400 ml-4">Arrastra para mover | Bordes para redimensionar | Doble click en celda para fecha entrega</span>
    </div>

    @if($proyectos->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">No hay proyectos activos.</div>
    @else
        <div class="mb-4 flex items-center gap-2">
            <label for="proyecto-selector" class="text-sm font-medium text-gray-700">Proyecto:</label>
            <select id="proyecto-selector" x-model="selected" class="border rounded px-3 py-1.5 text-sm bg-white">
                <option value="">— Selecciona un proyecto —</option>
                @foreach($proyectos as $proyecto)
                    <option value="{{ $proyecto->id }}">{{ $proyecto->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div x-show="selected === ''" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            Selecciona un proyecto del menú para verlo.
        </div>

        @php
            $allTiempos = \App\Models\Tiempo::whereIn('mueble_id', $proyectos->flatMap(fn($p) => $p->muebles->pluck('id')))->get();
            $personalByEquipo = [
                'Carpintería' => $personal->where('equipo', 'Carpintería'),
                'Barniz' => $personal->where('equipo', 'Barniz'),
                'Instalación' => $personal->where('equipo', 'Instalación'),
            ];
            $todosLideres = $personal->where('es_lider', true);
            foreach ($personalByEquipo as $equipo => $lista) {
                $personalByEquipo[$equipo] = $lista->merge($todosLideres)->unique('id')->sortBy('nombre');
            }
            $isAdmin = auth()->user()->isAdmin();
            $procesoColors = ['Carpintería' => '#f59e0b', 'Barniz' => '#10b981', 'Instalación' => '#3b82f6'];
            $procesoSlots = ['Carpintería' => 0, 'Barniz' => 1, 'Instalación' => 2];
        @endphp

        <div x-show="selected !== ''" x-cloak>
        @foreach($proyectos as $proyecto)
        <div class="mb-6" x-show="selected === '{{ $proyecto->id }}'" x-cloak>
            <div class="flex items-center space-x-3 mb-1">
                <h2 class="text-sm font-bold">{{ $proyecto->nombre }}</h2>
                <span class="text-xs text-gray-500">({{ $proyecto->muebles->count() }} muebles)</span>
                <a href="{{ route('captura', $proyecto) }}" class="text-xs text-blue-600 hover:underline">Ir a captura</a>
                <a href="{{ route('export.proyecto.html', $proyecto) }}" class="text-xs text-gray-500 hover:text-gray-700 hover:underline no-print" title="Descargar HTML de este proyecto">HTML</a>
                @if($isAdmin)
                    <button class="text-xs text-green-600 hover:underline toggle-add-mueble" data-proyecto="{{ $proyecto->id }}">+ Mueble</button>
                    <button class="text-xs text-purple-600 hover:underline toggle-shift" data-proyecto="{{ $proyecto->id }}">Recorrer fechas</button>
                    <button class="text-xs text-red-600 hover:underline toggle-materiales" data-proyecto="{{ $proyecto->id }}">Materiales</button>
                @endif
            </div>

            @if($isAdmin)
            <form method="POST" action="{{ route('muebles.store', $proyecto) }}" class="add-mueble-form items-center space-x-2 mb-2 bg-white p-2 rounded shadow-sm" id="add-mueble-{{ $proyecto->id }}">
                @csrf
                <input type="text" name="numero" placeholder="Num (ej: CAR-01)" required class="border rounded px-2 py-1 text-sm w-32">
                <input type="text" name="descripcion" placeholder="Descripcion" required class="border rounded px-2 py-1 text-sm flex-1">
                <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Agregar</button>
                <button type="button" class="text-gray-400 hover:text-gray-600 text-sm toggle-add-mueble" data-proyecto="{{ $proyecto->id }}">Cancelar</button>
            </form>
            @endif

            @php
                $matPedido = $proyecto->materiales->where('tipo', 'pedido')->first();
                $matEntrega = $proyecto->materiales->where('tipo', 'entrega')->first();
            @endphp

            @if($isAdmin)
            <div class="shift-form items-center space-x-2 mb-2 bg-purple-50 p-2 rounded shadow-sm" id="shift-form-{{ $proyecto->id }}" style="display:none">
                <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                    <label class="text-xs font-medium text-purple-700">Dias habiles:</label>
                    <input type="number" id="shift-dias-{{ $proyecto->id }}" value="1" min="-60" max="60" class="border rounded px-2 py-1 text-sm w-20 text-center">
                    <span class="text-xs text-gray-500">(+ adelante, - atras)</span>
                    <span class="text-xs text-purple-700 font-medium ml-2">Procesos:</span>
                    <label class="text-xs flex items-center space-x-1"><input type="checkbox" class="shift-proceso" data-proyecto="{{ $proyecto->id }}" value="Carpintería" checked><span>Carpinteria</span></label>
                    <label class="text-xs flex items-center space-x-1"><input type="checkbox" class="shift-proceso" data-proyecto="{{ $proyecto->id }}" value="Barniz" checked><span>Barniz</span></label>
                    <label class="text-xs flex items-center space-x-1"><input type="checkbox" class="shift-proceso" data-proyecto="{{ $proyecto->id }}" value="Instalación" checked><span>Instalacion</span></label>
                    <button type="button" class="bg-purple-600 text-white px-3 py-1 rounded text-sm hover:bg-purple-700 btn-shift" data-proyecto="{{ $proyecto->id }}">Aplicar</button>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-sm toggle-shift" data-proyecto="{{ $proyecto->id }}">Cancelar</button>
                </div>
                <div class="flex items-center flex-wrap gap-1 mt-1">
                    <span class="text-xs text-purple-700 font-medium">Muebles:</span>
                    <label class="text-xs flex items-center space-x-1">
                        <input type="checkbox" class="shift-todos-muebles" data-proyecto="{{ $proyecto->id }}" checked>
                        <span class="font-medium">Todos</span>
                    </label>
                    @foreach($proyecto->muebles as $mueble)
                        <label class="text-xs flex items-center space-x-1 bg-white rounded px-1">
                            <input type="checkbox" class="shift-mueble" data-proyecto="{{ $proyecto->id }}" value="{{ $mueble->id }}" checked>
                            <span>{{ $mueble->numero }}</span>
                        </label>
                    @endforeach
                </div>
                @php $shifts = \App\Models\TiempoShift::where('proyecto_id', $proyecto->id)->where('reverted', false)->latest()->limit(5)->get(); @endphp
                @if($shifts->isNotEmpty())
                <div class="mt-2 text-xs text-gray-500">
                    <span class="font-medium">Historial:</span>
                    @foreach($shifts as $s)
                        <span class="inline-flex items-center space-x-1 bg-white border rounded px-2 py-0.5 mr-1">
                            <span>{{ $s->dias_habiles > 0 ? '+' : '' }}{{ $s->dias_habiles }} dias ({{ $s->created_at->format('d/M H:i') }})</span>
                            <button class="text-red-500 hover:text-red-700 font-bold btn-revert" data-shift="{{ $s->id }}">&circlearrowleft;</button>
                        </span>
                    @endforeach
                </div>
                @endif
            </div>

            <div class="materiales-form mb-2 bg-red-50 p-2 rounded shadow-sm" id="materiales-form-{{ $proyecto->id }}" style="display:none">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <label class="text-xs font-medium text-red-700">Pedido material:</label>
                        <input type="date" class="border rounded px-2 py-1 text-sm mat-date" data-proyecto="{{ $proyecto->id }}" data-tipo="pedido" value="{{ $matPedido?->fecha?->format('Y-m-d') }}">
                    </div>
                    <div class="flex items-center space-x-2">
                        <label class="text-xs font-medium text-red-700">Entrega material:</label>
                        <input type="date" class="border rounded px-2 py-1 text-sm mat-date" data-proyecto="{{ $proyecto->id }}" data-tipo="entrega" value="{{ $matEntrega?->fecha?->format('Y-m-d') }}">
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-sm toggle-materiales" data-proyecto="{{ $proyecto->id }}">Cerrar</button>
                </div>
                <div class="mt-1 text-xs text-gray-400">
                    <span class="inline-block w-3 border-l-2 border-red-500 mr-1">&nbsp;</span> Pedido
                    <span class="inline-block w-3 border-r-2 border-red-700 mr-1 ml-3">&nbsp;</span> Entrega
                </div>
            </div>
            @endif

            <div class="bg-white rounded-lg shadow overflow-auto" style="max-height: calc(100vh - 200px);">
                <table class="min-w-full text-xs">
                    @php
                        $semanas = collect($diasHabiles)->groupBy(fn($d) => $d->weekOfYear);
                        $semanaActual = now()->weekOfYear;
                        $fechaPedido = $matPedido?->fecha?->format('Y-m-d');
                        $fechaEntrega = $matEntrega?->fecha?->format('Y-m-d');
                    @endphp
                    <thead class="bg-gray-50 gantt-sticky-head">
                        <tr>
                            <th class="px-1 py-1 sticky left-0 bg-gray-50 z-20" style="min-width:56px"></th>
                            <th class="px-2 py-1 sticky-desc-header bg-gray-50" style="min-width:120px"></th>
                            @foreach($semanas as $numSemana => $diasSemana)
                                <th class="text-center font-bold text-blue-600 {{ $numSemana == $semanaActual ? 'current-week-header' : 'bg-blue-50' }} week-start text-xs" colspan="{{ count($diasSemana) }}">
                                    Sem {{ $numSemana }}
                                </th>
                            @endforeach
                        </tr>
                        <tr>
                            <th class="px-1 py-1 text-left font-medium text-gray-500 sticky left-0 bg-gray-50 z-20" style="min-width:56px">#</th>
                            <th class="px-2 py-1 text-left font-medium text-gray-500 sticky-desc-header bg-gray-50" style="min-width:120px">Descripción</th>
                            @foreach($diasHabiles as $dia)
                                @php
                                    $esFestivo = isset($festivos[$dia->format('Y-m-d')]);
                                    $diaStr = $dia->format('Y-m-d');
                                    $matHeaderClass = '';
                                    if ($diaStr === $fechaPedido) $matHeaderClass .= ' mat-pedido-header';
                                    if ($diaStr === $fechaEntrega) $matHeaderClass .= ' mat-entrega-header';
                                @endphp
                                <th class="gen-cell text-center font-medium day-header {{ $esFestivo ? 'festivo-header' : 'text-gray-400' }} {{ $dia->isMonday() ? 'week-start' : '' }}{{ $matHeaderClass }} {{ $dia->weekOfYear == $semanaActual ? 'current-week-header' : '' }}"
                                    data-date="{{ $dia->format('Y-m-d') }}"
                                    @if($esFestivo) title="{{ $festivos[$diaStr] }}"
                                    @elseif($diaStr === $fechaPedido && $diaStr === $fechaEntrega) title="Pedido + Entrega material"
                                    @elseif($diaStr === $fechaPedido) title="Pedido material"
                                    @elseif($diaStr === $fechaEntrega) title="Entrega material"
                                    @endif>
                                    {{ $dia->format('d/M') }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($proyecto->muebles as $mi => $mueble)
                            @php
                                // Build bars grouped by (proceso, persona, semana ISO)
                                $muebleProcs = [];
                                $gridDates = collect($diasHabiles)->map(fn($d) => $d->format('Y-m-d'))->values();
                                foreach ($procesos as $proceso) {
                                    $tiemposProceso = $allTiempos
                                        ->where('mueble_id', $mueble->id)
                                        ->where('proceso', $proceso)
                                        ->where('horas', '>', 0);

                                    $bloques = [];
                                    $diasCount = 0;
                                    $byPersonaSemana = $tiemposProceso->groupBy(fn($t) => $t->personal_id . '|' . $t->fecha->copy()->startOfWeek()->format('Y-m-d'));
                                    foreach ($byPersonaSemana as $key => $grupo) {
                                        [$pid, $semanaInicio] = explode('|', $key);
                                        $pid = (int) $pid;
                                        $persona = $personal[$pid] ?? null;
                                        $fechas = $grupo->pluck('fecha')->map(fn($f) => $f->format('Y-m-d'));
                                        $fechasVisibles = $fechas->intersect($gridDates)->sort()->values();
                                        if ($fechasVisibles->isEmpty()) continue;
                                        $bloques[] = [
                                            'personal_id' => $pid,
                                            'nombre' => $persona?->nombre ?? '?',
                                            'color_hex' => $persona?->color_hex ?? '#9ca3af',
                                            'fecha_min' => $fechasVisibles->first(),
                                            'fecha_max' => $fechasVisibles->last(),
                                            'dias' => $fechasVisibles->count(),
                                            'semana_inicio' => $semanaInicio,
                                        ];
                                        $diasCount += $fechasVisibles->count();
                                    }
                                    $muebleProcs[$proceso] = [
                                        'bloques' => $bloques,
                                        'dias' => $diasCount,
                                    ];
                                }
                                $totalDias = collect($muebleProcs)->sum('dias');
                            @endphp
                            <tr class="hover:bg-gray-50 border-b border-gray-100 {{ $mi > 0 ? 'mueble-sep' : '' }} gantt-row {{ $mueble->fecha_instalado ? 'mueble-instalado' : '' }}"
                                data-mueble-id="{{ $mueble->id }}"
                                data-proyecto-id="{{ $proyecto->id }}"
                                data-procs='@json($muebleProcs)'
                                data-fecha-entrega="{{ $mueble->fecha_entrega?->format('Y-m-d') ?? '' }}"
                                data-fecha-instalado="{{ $mueble->fecha_instalado?->format('Y-m-d') ?? '' }}"
                            >
                                <td class="px-1 py-1 font-medium sticky left-0 bg-white z-10 align-top text-xs">
                                    {{ $mueble->numero }}
                                    @if($isAdmin)
                                        @if($mueble->fecha_instalado)
                                            <button type="button" class="btn-instalar text-green-600 hover:text-green-800 ml-1" title="Instalado {{ $mueble->fecha_instalado->format('d/m') }} — clic para revertir">✓</button>
                                        @else
                                            <button type="button" class="btn-instalar text-gray-300 hover:text-green-600 ml-1" title="Marcar como instalado">✓</button>
                                        @endif
                                        <form method="POST" action="{{ route('muebles.destroy', $mueble) }}" class="inline" onsubmit="return confirm('Eliminar mueble {{ $mueble->numero }}?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-400 hover:text-red-600 ml-1">&times;</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-2 py-1 align-top sticky-desc" style="min-width:120px">
                                    <div class="text-xs">{{ $mueble->descripcion }}</div>
                                </td>
                                @foreach($diasHabiles as $dia)
                                    @php
                                        $diaStr = $dia->format('Y-m-d');
                                        $esFestivo = isset($festivos[$diaStr]);
                                        $matClass = '';
                                        $esPedido = $diaStr === $fechaPedido;
                                        $esEntrega = $diaStr === $fechaEntrega;
                                        if ($esPedido && $esEntrega) $matClass = 'mat-ambos';
                                        elseif ($esPedido) $matClass = 'mat-pedido';
                                        elseif ($esEntrega) $matClass = 'mat-entrega';
                                    @endphp
                                    <td class="gen-cell text-center day-cell {{ $esFestivo ? 'festivo' : '' }} {{ $matClass }} {{ $dia->isMonday() ? 'week-start' : '' }} {{ $dia->weekOfYear == $semanaActual ? 'current-week' : '' }}"
                                        data-date="{{ $diaStr }}">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
        </div>{{-- /x-show selected --}}
    @endif
    </div>{{-- /flex-1 --}}

    @if(!$proyectos->isEmpty())
        {{-- Panel lateral inline: personal disponible por semana --}}
        <aside class="w-60 flex-shrink-0" x-show="selected !== ''" x-cloak>
            <div class="sticky top-2 bg-white shadow rounded-lg p-2.5 max-h-[calc(100vh-16px)] overflow-y-auto border border-gray-200">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-gray-700 uppercase">Personal</span>
            <button @click="loadPersonal()" class="text-xs text-blue-600 hover:underline" title="Recargar">↻</button>
        </div>

        <div class="flex gap-1 mb-3">
            <template x-for="w in weeks" :key="w.start">
                <button @click="weekStart = w.start"
                        :class="weekStart === w.start ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        class="text-xs px-2 py-1 rounded flex-1"
                        x-text="w.label"></button>
            </template>
        </div>

        <div class="text-sm font-medium mb-2 flex items-center justify-between">
            <span>Sin asignar</span>
            <span class="text-blue-600 font-mono">
                <span x-text="diasLibresTotales()"></span>d
                <span class="text-gray-400 text-xs">(<span x-text="sinAsignar()"></span>p)</span>
            </span>
        </div>

        <template x-if="picked">
            <div class="mb-2 p-2 bg-blue-50 border border-blue-300 rounded text-xs">
                Modo: asignar <strong x-text="picked.nombre"></strong>
                (<span x-text="picked.dias_libres"></span>d)
                <button @click="picked = null" class="float-right text-gray-500 hover:text-red-600 font-bold">✕</button>
                <div class="text-gray-600 mt-1">Click en una barra del Gantt</div>
            </div>
        </template>

        <template x-if="loading">
            <div class="text-center text-gray-400 text-xs py-4">Cargando…</div>
        </template>

        <template x-if="!loading && diasLibresTotales() === 0">
            <div class="text-center text-green-600 text-sm font-medium py-3">✓ Semana 100% asignada</div>
        </template>

        <template x-for="(lista, equipo) in porEquipo()" :key="equipo">
            <div class="mb-2">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1" x-text="equipo"></div>
                <template x-for="p in lista" :key="p.id">
                    <div @click="pick(p.id)"
                         :class="[
                            picked && picked.id === p.id ? 'ring-2 ring-blue-500 bg-blue-50' : 'bg-gray-50 hover:bg-gray-100',
                            p.dias_libres === 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                         ]"
                         class="flex items-center justify-between px-2 py-1 rounded mb-1 text-sm">
                        <span class="flex items-center gap-1.5 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="'background:' + p.color_hex"></span>
                            <span class="truncate" x-text="p.nombre"></span>
                        </span>
                        <span class="text-xs font-mono bg-white px-1.5 py-0.5 rounded border flex-shrink-0"
                              :class="p.dias_libres === 0 ? 'text-gray-400' : ''"
                              x-text="p.dias_libres + 'd'"></span>
                    </div>
                </template>
            </div>
        </template>
            </div>{{-- /sticky --}}
        </aside>
    @endif
    </div>{{-- /flex outer --}}
</div>

<div id="fecha-entrega-popup">
    <span style="font-weight:600; color:#f97316;">Entrega:</span>
    <input type="date" id="fecha-entrega-input">
    <button class="save-btn" id="fecha-entrega-save">OK</button>
    <button class="clear-btn" id="fecha-entrega-clear">Quitar</button>
    <button class="cancel-btn" id="fecha-entrega-cancel">X</button>
</div>
@endsection

@push('scripts')
<script>
window.proyeccionApp = function(weeks, defaultWeek, isAdmin) {
    return {
        selected: sessionStorage.getItem('tiempos.proyectoSelected') || '',
        weeks: weeks,
        weekStart: defaultWeek,
        picked: null,
        personal: [],
        diasLab: 5,
        loading: false,
        isAdmin: isAdmin,

        init() {
            this.$watch('selected', v => sessionStorage.setItem('tiempos.proyectoSelected', v));
            this.$watch('weekStart', () => this.loadPersonal());
            window.__proyeccionState = this;
            this.loadPersonal();
        },

        loadPersonal() {
            this.loading = true;
            fetch('/asignacion/disponibilidad?semana=' + this.weekStart, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                this.personal = data.personal || [];
                this.diasLab = data.dias_laborables || 5;
                this.loading = false;
            })
            .catch(() => { this.loading = false; });
        },

        pick(id) {
            if (!this.isAdmin) {
                alert('Solo administradores pueden asignar personal');
                return;
            }
            const p = this.personal.find(x => x.id === id);
            if (!p || p.dias_libres === 0) return;
            this.picked = (this.picked && this.picked.id === id) ? null : p;
        },

        assignToBar(muebleId, proceso) {
            if (!this.picked) return false;
            const personalId = this.picked.id;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            fetch('/asignacion/asignar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({
                    mueble_id: parseInt(muebleId),
                    proceso: proceso,
                    personal_id: personalId,
                    semana: this.weekStart,
                })
            })
            .then(r => r.json().then(d => ({ ok: r.ok, data: d })))
            .then(({ ok, data }) => {
                if (ok && data.ok) {
                    this.picked = null;
                    if (window.refreshAfterChange) window.refreshAfterChange(muebleId);
                } else {
                    alert(data.error || 'Error al asignar');
                }
            })
            .catch(() => alert('Error al asignar'));
            return true;
        },

        sinAsignar() {
            return this.personal.filter(p => p.dias_libres > 0).length;
        },

        diasLibresTotales() {
            return this.personal.reduce((acc, p) => acc + (p.dias_libres || 0), 0);
        },

        porEquipo() {
            const groups = {};
            this.personal.forEach(p => {
                // Armado se muestra junto con Carpintería en la Proyección.
                const grupo = p.equipo === 'Armado' ? 'Carpintería' : p.equipo;
                if (!groups[grupo]) groups[grupo] = [];
                groups[grupo].push(p);
            });
            return groups;
        },
    };
};

document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const procesoColors = { 'Carpintería': '#f59e0b', 'Barniz': '#10b981', 'Instalación': '#3b82f6' };
    const procesoSlots = { 'Carpintería': 0, 'Barniz': 1, 'Instalación': 2 };

    // Refresh in-place after assign/move/resize/quitar (no full page reload)
    window.refreshAfterChange = function(muebleId) {
        const app = window.__proyeccionState;
        if (app && app.loadPersonal) app.loadPersonal();

        const row = document.querySelector('tr.gantt-row[data-mueble-id="' + muebleId + '"]');
        if (!row) return;

        const params = new URLSearchParams(window.location.search);
        const desde = params.get('desde');
        const url = '/muebles/' + muebleId + '/procs' + (desde ? '?desde=' + desde : '');

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => {
                if (d.ok && d.procs) {
                    row.dataset.procs = JSON.stringify(d.procs);
                    if (typeof buildGanttBars === 'function') buildGanttBars(row);
                }
            })
            .catch(() => {});
    };

    // Toggle add mueble form
    document.querySelectorAll('.toggle-add-mueble').forEach(btn => {
        btn.addEventListener('click', function() {
            const form = document.getElementById('add-mueble-' + this.dataset.proyecto);
            if (form) form.classList.toggle('active');
        });
    });

    // Toggle shift form
    document.querySelectorAll('.toggle-shift').forEach(btn => {
        btn.addEventListener('click', function() {
            const form = document.getElementById('shift-form-' + this.dataset.proyecto);
            if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });
    });

    // Shift: "Todos" muebles checkbox toggle
    document.querySelectorAll('.shift-todos-muebles').forEach(cb => {
        cb.addEventListener('change', function() {
            const checks = document.querySelectorAll('.shift-mueble[data-proyecto="' + this.dataset.proyecto + '"]');
            checks.forEach(c => c.checked = this.checked);
        });
    });
    document.querySelectorAll('.shift-mueble').forEach(cb => {
        cb.addEventListener('change', function() {
            const pid = this.dataset.proyecto;
            const all = document.querySelectorAll('.shift-mueble[data-proyecto="' + pid + '"]');
            const checked = document.querySelectorAll('.shift-mueble[data-proyecto="' + pid + '"]:checked');
            document.querySelector('.shift-todos-muebles[data-proyecto="' + pid + '"]').checked = all.length === checked.length;
        });
    });

    // Apply shift
    document.querySelectorAll('.btn-shift').forEach(btn => {
        btn.addEventListener('click', function() {
            const proyectoId = this.dataset.proyecto;
            const dias = document.getElementById('shift-dias-' + proyectoId).value;
            if (!dias || dias == 0) return alert('Ingresa dias habiles a recorrer');

            const procesos = [];
            document.querySelectorAll('.shift-proceso[data-proyecto="' + proyectoId + '"]:checked').forEach(cb => {
                procesos.push(cb.value);
            });
            if (procesos.length === 0) return alert('Selecciona al menos un proceso');

            const muebleIds = [];
            const todosMuebles = document.querySelector('.shift-todos-muebles[data-proyecto="' + proyectoId + '"]').checked;
            if (!todosMuebles) {
                document.querySelectorAll('.shift-mueble[data-proyecto="' + proyectoId + '"]:checked').forEach(cb => {
                    muebleIds.push(parseInt(cb.value));
                });
                if (muebleIds.length === 0) return alert('Selecciona al menos un mueble');
            }

            const msg = 'Recorrer ' + procesos.join(', ') + (todosMuebles ? ' (todos los muebles)' : ' (' + muebleIds.length + ' muebles)') + ' ' + dias + ' dias habiles. Continuar?';
            if (!confirm(msg)) return;

            this.disabled = true;
            this.textContent = 'Aplicando...';

            const body = { dias_habiles: parseInt(dias), procesos: procesos };
            if (!todosMuebles) body.mueble_ids = muebleIds;

            fetch('/tiempos/recorrer/' + proyectoId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(body)
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    alert('Fechas recorridas: ' + data.registros + ' registros actualizados');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                    this.disabled = false;
                    this.textContent = 'Aplicar';
                }
            })
            .catch(() => { alert('Error al recorrer fechas'); this.disabled = false; this.textContent = 'Aplicar'; });
        });
    });

    // Revert shift
    document.querySelectorAll('.btn-revert').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Revertir este recorrido de fechas?')) return;
            fetch('/tiempos/revertir/' + this.dataset.shift, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => { if (data.ok) { alert('Recorrido revertido'); location.reload(); } else { alert('Error: ' + data.error); } })
            .catch(() => alert('Error al revertir'));
        });
    });

    // Toggle materiales form
    document.querySelectorAll('.toggle-materiales').forEach(btn => {
        btn.addEventListener('click', function() {
            const form = document.getElementById('materiales-form-' + this.dataset.proyecto);
            if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });
    });

    // Save material dates
    document.querySelectorAll('.mat-date').forEach(input => {
        input.addEventListener('change', function() {
            const el = this;
            fetch('/proyecto/' + el.dataset.proyecto + '/materiales', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ tipo: el.dataset.tipo, fecha: el.value || null })
            })
            .then(r => r.json())
            .then(data => { if (data.ok) location.reload(); })
            .catch(() => alert('Error al guardar fecha de material'));
        });
    });

    // ===== GANTT BAR SYSTEM =====

    const dayHeaders = document.querySelectorAll('.day-header[data-date]');
    const allDates = [];
    dayHeaders.forEach(th => allDates.push(th.dataset.date));

    function getColPositions(table) {
        const headerRow = table.querySelector('thead tr:last-child');
        const ths = headerRow.querySelectorAll('.day-header[data-date]');
        const tableRect = table.getBoundingClientRect();
        const positions = [];
        ths.forEach(th => {
            const r = th.getBoundingClientRect();
            positions.push({ date: th.dataset.date, left: r.left - tableRect.left, width: r.width });
        });
        return positions;
    }

    function buildGanttBars(row) {
        // Remove existing bars
        row.querySelectorAll('.gantt-bar').forEach(b => b.remove());

        const procs = JSON.parse(row.dataset.procs || '{}');
        const table = row.closest('table');
        const colPos = getColPositions(table);

        for (const [proceso, data] of Object.entries(procs)) {
            const bloques = data.bloques || [];
            const porSemana = {};
            for (const b of bloques) {
                (porSemana[b.semana_inicio] = porSemana[b.semana_inicio] || []).push(b);
            }
            for (const bloque of bloques) {
                const firstCol = colPos.find(c => c.date === bloque.fecha_min);
                const lastCol = colPos.find(c => c.date === bloque.fecha_max);
                if (!firstCol || !lastCol) continue;

                const barLeft = firstCol.left;
                const barWidth = (lastCol.left + lastCol.width) - firstCol.left;
                const slot = procesoSlots[proceso];
                const color = bloque.color_hex || procesoColors[proceso];

                const bar = document.createElement('div');
                bar.className = 'gantt-bar';
                bar.setAttribute('data-slot', slot);
                bar.style.left = barLeft + 'px';
                bar.style.width = barWidth + 'px';
                bar.style.backgroundColor = color + 'CC';
                bar.style.borderColor = color;
                bar.dataset.firstDate = bloque.fecha_min;
                bar.dataset.lastDate = bloque.fecha_max;
                bar.dataset.muebleId = row.dataset.muebleId;
                bar.dataset.proceso = proceso;
                bar.dataset.personalId = bloque.personal_id;
                bar.dataset.proyectoId = row.dataset.proyectoId;
                bar.dataset.color = color;
                bar.dataset.semanaInicio = bloque.semana_inicio;
                bar.dataset.nombre = bloque.nombre;

                // Label: name initials + days
                const initials = (bloque.nombre || '?').split(/\s+/).slice(0, 2).map(s => s[0] || '').join('');
                bar.textContent = initials + ' ' + bloque.dias + 'd';
                const compañeros = porSemana[bloque.semana_inicio] || [bloque];
                if (compañeros.length === 1) {
                    bar.title = bloque.nombre + ' — ' + bloque.dias + 'd (' + proceso + ')';
                } else {
                    const lista = compañeros
                        .map(c => (c.personal_id === bloque.personal_id ? '▸ ' : '   ') + c.nombre + ' (' + c.dias + 'd)')
                        .join('\n');
                    bar.title = proceso + ' — semana del ' + bloque.semana_inicio + '\n' + lista;
                }

                const handleLeft = document.createElement('div');
                handleLeft.className = 'gantt-handle gantt-handle-left';
                bar.appendChild(handleLeft);

                const handleRight = document.createElement('div');
                handleRight.className = 'gantt-handle gantt-handle-right';
                bar.appendChild(handleRight);

                row.appendChild(bar);
            }
        }
    }

    function buildAllGanttBars() {
        document.querySelectorAll('.gantt-row').forEach(row => buildGanttBars(row));
    }
    buildAllGanttBars();

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(buildAllGanttBars, 200);
    });

    // ===== DRAG & RESIZE LOGIC =====

    function snapToColumn(x, colPos) {
        let closest = colPos[0];
        let minDist = Infinity;
        for (const col of colPos) {
            const centerX = col.left + col.width / 2;
            const dist = Math.abs(x - centerX);
            if (dist < minDist) { minDist = dist; closest = col; }
        }
        return closest;
    }

    function countBusinessDaysBetween(fromDate, toDate, dates) {
        const fromIdx = dates.indexOf(fromDate);
        const toIdx = dates.indexOf(toDate);
        if (fromIdx === -1 || toIdx === -1) return 0;
        return toIdx - fromIdx;
    }

    document.addEventListener('mousedown', function(e) {
        const handle = e.target.closest('.gantt-handle');
        const bar = e.target.closest('.gantt-bar');
        const app = window.__proyeccionState;

        // Picked + click en celda vacía de una fila Gantt: crea barra usando el slot Y
        if (app && app.picked && !bar) {
            const row = e.target.closest('tr.gantt-row[data-mueble-id]');
            if (row) {
                const rect = row.getBoundingClientRect();
                const y = e.clientY - rect.top;
                let proceso;
                if (y < 17) proceso = 'Carpintería';
                else if (y < 33) proceso = 'Barniz';
                else proceso = 'Instalación';
                e.preventDefault();
                app.assignToBar(row.dataset.muebleId, proceso);
                return;
            }
        }

        if (!bar) return;

        // If a personal card is "picked", clicking a bar assigns the person.
        if (app && app.picked && !handle) {
            e.preventDefault();
            app.assignToBar(bar.dataset.muebleId, bar.dataset.proceso);
            return;
        }

        e.preventDefault();
        const table = bar.closest('table');
        const row = bar.closest('tr');
        const startX = e.clientX;
        const origLeft = parseFloat(bar.style.left);
        const origWidth = parseFloat(bar.style.width);
        const origFirstDate = bar.dataset.firstDate;
        const origLastDate = bar.dataset.lastDate;

        let mode = 'move';
        if (handle) {
            mode = handle.classList.contains('gantt-handle-left') ? 'resize-left' : 'resize-right';
        }

        let dragged = false;
        bar.classList.add('dragging');

        function onMouseMove(ev) {
            const dx = ev.clientX - startX;
            if (Math.abs(dx) > 3) dragged = true;
            if (mode === 'move') {
                bar.style.left = (origLeft + dx) + 'px';
            } else if (mode === 'resize-left') {
                const newLeft = origLeft + dx;
                const newWidth = origWidth - dx;
                if (newWidth > 4) { bar.style.left = newLeft + 'px'; bar.style.width = newWidth + 'px'; }
            } else if (mode === 'resize-right') {
                const newWidth = origWidth + dx;
                if (newWidth > 4) { bar.style.width = newWidth + 'px'; }
            }
        }

        function onMouseUp(ev) {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            bar.classList.remove('dragging');

            // Click without drag: liberate this person from this week
            if (!dragged && !handle) {
                bar.style.left = origLeft + 'px';
                bar.style.width = origWidth + 'px';
                const nombre = bar.dataset.nombre || 'esta persona';
                if (!confirm('¿Liberar a ' + nombre + ' de esta semana?')) return;
                fetch('/asignacion/quitar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        mueble_id: parseInt(bar.dataset.muebleId),
                        proceso: bar.dataset.proceso,
                        personal_id: parseInt(bar.dataset.personalId),
                        semana: bar.dataset.semanaInicio,
                    })
                })
                .then(r => r.json())
                .then(d => {
                    if (d.ok) {
                        if (window.refreshAfterChange) window.refreshAfterChange(parseInt(bar.dataset.muebleId));
                    } else alert(d.error || 'Error al liberar');
                })
                .catch(() => alert('Error al liberar'));
                return;
            }

            const freshColPos = getColPositions(table);

            if (mode === 'move') {
                // Use the original start column center + pixel delta to find the new snapped column
                const origCol = freshColPos.find(c => c.date === origFirstDate);
                const origCenter = origCol ? origCol.left + origCol.width / 2 : origLeft;
                const dx = parseFloat(bar.style.left) - origLeft;
                const snappedFirst = snapToColumn(origCenter + dx, freshColPos);
                const daysMoved = countBusinessDaysBetween(origFirstDate, snappedFirst.date, allDates);

                if (daysMoved === 0) { bar.style.left = origLeft + 'px'; return; }

                // Move only this person's bar: shift their dates by daysMoved
                const newFirstIdx = allDates.indexOf(origFirstDate) + daysMoved;
                const newLastIdx = allDates.indexOf(origLastDate) + daysMoved;
                if (newFirstIdx < 0 || newLastIdx >= allDates.length) {
                    alert('Fuera de la ventana visible');
                    bar.style.left = origLeft + 'px';
                    return;
                }
                const newFirstDate = allDates[newFirstIdx];
                const newLastDate = allDates[newLastIdx];

                bar.style.opacity = '0.5';
                fetch('{{ route("tiempos.guardarRango") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        mueble_id: parseInt(bar.dataset.muebleId),
                        proceso: bar.dataset.proceso,
                        personal_id: parseInt(bar.dataset.personalId),
                        fecha_inicio: newFirstDate,
                        fecha_fin: newLastDate,
                        personas: 1
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok || data.success) {
                        if (window.refreshAfterChange) window.refreshAfterChange(parseInt(bar.dataset.muebleId));
                    } else { alert('Error: ' + (data.error || 'Unknown error')); bar.style.left = origLeft + 'px'; bar.style.opacity = '1'; }
                })
                .catch(() => { alert('Error al mover'); bar.style.left = origLeft + 'px'; bar.style.opacity = '1'; });

            } else {
                // Resize
                let newFirstDate, newLastDate;
                if (mode === 'resize-left') {
                    const snapped = snapToColumn(parseFloat(bar.style.left), freshColPos);
                    newFirstDate = snapped.date;
                    newLastDate = origLastDate;
                } else {
                    const snapped = snapToColumn(parseFloat(bar.style.left) + parseFloat(bar.style.width), freshColPos);
                    newFirstDate = origFirstDate;
                    newLastDate = snapped.date;
                }

                if (newFirstDate > newLastDate) { buildGanttBars(row); return; }
                if (newFirstDate === origFirstDate && newLastDate === origLastDate) { buildGanttBars(row); return; }

                bar.style.opacity = '0.5';
                const personasVal = parseFloat(bar.dataset.personas) || 1;

                fetch('{{ route("tiempos.guardarRango") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        mueble_id: parseInt(bar.dataset.muebleId),
                        proceso: bar.dataset.proceso,
                        personal_id: parseInt(bar.dataset.personalId),
                        fecha_inicio: newFirstDate,
                        fecha_fin: newLastDate,
                        personas: personasVal
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok || data.success) {
                        if (window.refreshAfterChange) window.refreshAfterChange(parseInt(bar.dataset.muebleId));
                    } else { alert('Error: ' + (data.error || 'Unknown error')); buildGanttBars(row); }
                })
                .catch(() => { alert('Error al guardar rango'); buildGanttBars(row); });
            }
        }

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });
    // ========== FECHA ENTREGA POR MUEBLE ==========
    function paintFechaEntrega(row) {
        const fechaEntrega = row.dataset.fechaEntrega;
        // Clear previous
        row.querySelectorAll('.fecha-entrega-cell').forEach(c => c.classList.remove('fecha-entrega-cell'));
        row.querySelectorAll('.fecha-entrega-marker').forEach(m => m.remove());
        if (!fechaEntrega) return;
        const cell = row.querySelector('td.day-cell[data-date="' + fechaEntrega + '"]');
        if (cell) {
            cell.classList.add('fecha-entrega-cell');
            cell.style.position = 'relative';
            const marker = document.createElement('div');
            marker.className = 'fecha-entrega-marker';
            cell.appendChild(marker);
        }
    }

    // Paint all on load
    document.querySelectorAll('tr.gantt-row[data-mueble-id]').forEach(paintFechaEntrega);

    // Double-click on day cell to set fecha entrega
    const fePopup = document.getElementById('fecha-entrega-popup');
    const feInput = document.getElementById('fecha-entrega-input');
    let feCurrentRow = null;

    document.querySelectorAll('tr.gantt-row[data-mueble-id]').forEach(row => {
        row.querySelectorAll('td.day-cell').forEach(cell => {
            cell.addEventListener('dblclick', function(e) {
                e.preventDefault();
                e.stopPropagation();
                feCurrentRow = row;
                feInput.value = row.dataset.fechaEntrega || cell.dataset.date;
                fePopup.classList.add('active');
                fePopup.style.left = Math.min(e.clientX, window.innerWidth - 300) + 'px';
                fePopup.style.top = Math.min(e.clientY - 10, window.innerHeight - 50) + 'px';
            });
        });
    });

    document.getElementById('fecha-entrega-save').addEventListener('click', function() {
        if (!feCurrentRow) return;
        const muebleId = feCurrentRow.dataset.muebleId;
        const fecha = feInput.value;
        fetch('/muebles/' + muebleId + '/fecha-entrega', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ fecha_entrega: fecha || null })
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                feCurrentRow.dataset.fechaEntrega = fecha;
                paintFechaEntrega(feCurrentRow);
            }
        });
        fePopup.classList.remove('active');
    });

    document.getElementById('fecha-entrega-clear').addEventListener('click', function() {
        if (!feCurrentRow) return;
        const muebleId = feCurrentRow.dataset.muebleId;
        fetch('/muebles/' + muebleId + '/fecha-entrega', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ fecha_entrega: null })
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                feCurrentRow.dataset.fechaEntrega = '';
                paintFechaEntrega(feCurrentRow);
            }
        });
        fePopup.classList.remove('active');
    });

    document.getElementById('fecha-entrega-cancel').addEventListener('click', function() {
        fePopup.classList.remove('active');
    });

    document.addEventListener('click', function(e) {
        if (fePopup.classList.contains('active') && !fePopup.contains(e.target)) {
            fePopup.classList.remove('active');
        }
    });

    // ========== MARCAR INSTALADO ==========
    document.querySelectorAll('button.btn-instalar').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const row = btn.closest('tr.gantt-row');
            if (!row) return;
            const muebleId = row.dataset.muebleId;
            const yaInstalado = !!row.dataset.fechaInstalado;
            let fecha = null;
            if (yaInstalado) {
                if (!confirm('Revertir instalación del mueble?')) return;
            } else {
                const hoy = new Date().toISOString().slice(0, 10);
                fecha = prompt('Fecha de instalación (YYYY-MM-DD):', hoy);
                if (!fecha) return;
                if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha)) { alert('Formato inválido'); return; }
            }
            fetch('/muebles/' + muebleId + '/instalar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ fecha_instalado: fecha })
            })
            .then(r => r.json())
            .then(data => { if (data.ok) location.reload(); });
        });
    });

    // Rebuild Gantt bars when project becomes visible via dropdown
    // (bars need real column widths, which are 0 while x-show hides the block).
    const proyectoSelector = document.getElementById('proyecto-selector');
    function rebuildVisibleGantt() {
        requestAnimationFrame(() => requestAnimationFrame(() => {
            buildAllGanttBars();
            document.querySelectorAll('tr.gantt-row[data-mueble-id]').forEach(paintFechaEntrega);
        }));
    }
    if (proyectoSelector) {
        proyectoSelector.addEventListener('change', rebuildVisibleGantt);
        // Initial paint when a project is restored from sessionStorage on page load
        if (proyectoSelector.value) rebuildVisibleGantt();
    }
});
</script>
@endpush
