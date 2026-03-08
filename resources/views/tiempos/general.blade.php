@extends('layouts.app')
@section('title', 'Vista General')

@push('styles')
<style>
    .gen-cell { min-width: 36px; font-size: 11px; padding: 1px; }
    .mueble-sep { border-top: 2px solid #d1d5db; }
    .proyecto-sep { border-top: 3px solid #6b7280; }
    .add-mueble-form { display: none; }
    .add-mueble-form.active { display: flex; }
    .festivo { background-color: #f3e8ff; }
    .festivo-header { background-color: #e9d5ff; color: #7c3aed; }
    .mat-pedido { box-shadow: inset 2px 0 0 0 #ef4444; }
    .mat-entrega { box-shadow: inset -2px 0 0 0 #dc2626; }
    .mat-ambos { box-shadow: inset 2px 0 0 0 #ef4444, inset -2px 0 0 0 #dc2626; }
    .mat-pedido-header { border-left: 3px solid #ef4444 !important; }
    .mat-entrega-header { border-right: 3px solid #dc2626 !important; }

    /* Gantt row: 3 stacked bars */
    tr.gantt-row { position: relative; height: 42px; }
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
        color: #fff;
        text-shadow: 0 0 2px rgba(0,0,0,0.4);
        overflow: hidden;
        white-space: nowrap;
        transition: box-shadow 0.15s;
    }
    .gantt-bar:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.3); z-index: 10; }
    .gantt-bar.dragging { cursor: grabbing; opacity: 0.7; }
    .gantt-bar[data-slot="0"] { top: 1px; height: 12px; }
    .gantt-bar[data-slot="1"] { top: 15px; height: 12px; }
    .gantt-bar[data-slot="2"] { top: 29px; height: 12px; }
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

    /* Assignment popover */
    .assign-popover {
        display: none; position: absolute; z-index: 50;
        background: white; border: 1px solid #d1d5db; border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 8px; min-width: 220px;
    }
    .assign-popover.active { display: block; }
    .assign-row { display: flex; align-items: center; gap: 4px; margin-bottom: 4px; }
    .assign-row label { font-size: 10px; font-weight: 600; min-width: 70px; }
    .assign-row select { font-size: 11px; flex: 1; }
</style>
@endpush

@section('content')
<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-3">
        <h1 class="text-xl font-bold">Vista General - Todos los Proyectos</h1>
        <div class="flex items-center gap-2">
            @if($ventanaInicio ?? false)
            <div class="flex items-center gap-1 text-sm">
                @if($canGoBack)
                    <a href="{{ route('general', ['desde' => $allDesde]) }}" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300" title="Ver todo">&laquo;</a>
                    <a href="{{ route('general', ['desde' => $prevDesde]) }}" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300" title="2 semanas atras">&lsaquo;</a>
                @endif
                <a href="{{ route('general') }}" class="px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-xs font-medium">Hoy</a>
                <a href="{{ route('general', ['desde' => $nextDesde]) }}" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300" title="2 semanas adelante">&rsaquo;</a>
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
        <span class="text-gray-400 ml-4">Arrastra para mover | Bordes para redimensionar | Click derecho para asignar equipo</span>
    </div>

    @if($proyectos->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">No hay proyectos activos.</div>
    @else
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

        @foreach($proyectos as $proyecto)
        <div class="mb-6">
            <div class="flex items-center space-x-3 mb-1">
                <h2 class="text-sm font-bold">{{ $proyecto->nombre }}</h2>
                <span class="text-xs text-gray-500">({{ $proyecto->muebles->count() }} muebles)</span>
                <a href="{{ route('captura', $proyecto) }}" class="text-xs text-blue-600 hover:underline">Ir a captura</a>
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

            @php
                $matPedido = $proyecto->materiales->where('tipo', 'pedido')->first();
                $matEntrega = $proyecto->materiales->where('tipo', 'entrega')->first();
            @endphp
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

            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-full text-xs">
                    @php
                        $semanas = collect($diasHabiles)->groupBy(fn($d) => $d->weekOfYear);
                        $fechaPedido = $matPedido?->fecha?->format('Y-m-d');
                        $fechaEntrega = $matEntrega?->fecha?->format('Y-m-d');
                    @endphp
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-2 py-1 sticky left-0 bg-gray-50 z-10" colspan="2"></th>
                            @foreach($semanas as $numSemana => $diasSemana)
                                <th class="text-center font-bold text-blue-600 bg-blue-50 border-l-2 border-blue-200 text-xs" colspan="{{ count($diasSemana) }}">
                                    Sem {{ $numSemana }}
                                </th>
                            @endforeach
                            <th></th>
                        </tr>
                        <tr>
                            <th class="px-2 py-1 text-left font-medium text-gray-500 sticky left-0 bg-gray-50 z-10" style="min-width:80px">Mueble</th>
                            <th class="px-2 py-1 text-left font-medium text-gray-500" style="min-width:160px">Descripcion</th>
                            @foreach($diasHabiles as $dia)
                                @php
                                    $esFestivo = isset($festivos[$dia->format('Y-m-d')]);
                                    $diaStr = $dia->format('Y-m-d');
                                    $matHeaderClass = '';
                                    if ($diaStr === $fechaPedido) $matHeaderClass .= ' mat-pedido-header';
                                    if ($diaStr === $fechaEntrega) $matHeaderClass .= ' mat-entrega-header';
                                @endphp
                                <th class="gen-cell text-center font-medium day-header {{ $esFestivo ? 'festivo-header' : 'text-gray-400' }} {{ $dia->isMonday() ? 'border-l-2 border-blue-200' : '' }}{{ $matHeaderClass }}"
                                    data-date="{{ $dia->format('Y-m-d') }}"
                                    @if($esFestivo) title="{{ $festivos[$diaStr] }}"
                                    @elseif($diaStr === $fechaPedido && $diaStr === $fechaEntrega) title="Pedido + Entrega material"
                                    @elseif($diaStr === $fechaPedido) title="Pedido material"
                                    @elseif($diaStr === $fechaEntrega) title="Entrega material"
                                    @endif>
                                    {{ $dia->format('d/M') }}
                                </th>
                            @endforeach
                            <th class="px-1 py-1 text-center font-medium text-gray-500 w-10">Dias</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($proyecto->muebles as $mi => $mueble)
                            @php
                                // Gather data for all 3 processes in this mueble
                                $muebleProcs = [];
                                foreach ($procesos as $proceso) {
                                    $tiemposProceso = $allTiempos->where('mueble_id', $mueble->id)->where('proceso', $proceso);
                                    $asignado = $tiemposProceso->pluck('personal_id')->unique()->first();
                                    $persona = $asignado ? ($personal[$asignado] ?? null) : null;
                                    $personas = $tiemposProceso->where('horas', '>', 0)->first()?->horas ?? 0;
                                    $fechas = $tiemposProceso->where('horas', '>', 0)->pluck('fecha')->map(fn($f) => $f->format('Y-m-d'));
                                    $gridDates = collect($diasHabiles)->map(fn($d) => $d->format('Y-m-d'));
                                    $fechasVisibles = $fechas->intersect($gridDates);
                                    $fechaMin = $fechasVisibles->min();
                                    $fechaMax = $fechasVisibles->max();
                                    $diasCount = $fechasVisibles->count();
                                    $muebleProcs[$proceso] = [
                                        'asignado' => $asignado,
                                        'persona' => $persona ? ['nombre' => $persona->nombre, 'color_hex' => $persona->color_hex] : null,
                                        'personas' => $personas,
                                        'fecha_min' => $fechaMin,
                                        'fecha_max' => $fechaMax,
                                        'dias' => $diasCount,
                                    ];
                                }
                                $totalDias = collect($muebleProcs)->sum('dias');
                            @endphp
                            <tr class="hover:bg-gray-50 border-b border-gray-100 {{ $mi > 0 ? 'mueble-sep' : '' }} gantt-row"
                                data-mueble-id="{{ $mueble->id }}"
                                data-proyecto-id="{{ $proyecto->id }}"
                                data-procs='@json($muebleProcs)'
                            >
                                <td class="px-2 py-1 font-medium sticky left-0 bg-white z-10 align-top">
                                    {{ $mueble->numero }}
                                    @if($isAdmin)
                                        <form method="POST" action="{{ route('muebles.destroy', $mueble) }}" class="inline" onsubmit="return confirm('Eliminar mueble {{ $mueble->numero }}?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-400 hover:text-red-600 ml-1">&times;</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-2 py-1 align-top">
                                    <div class="text-xs">{{ $mueble->descripcion }}</div>
                                    <div class="flex gap-1 mt-0.5">
                                        @foreach($procesos as $proceso)
                                            @php $mp = $muebleProcs[$proceso]; @endphp
                                            <span class="inline-flex items-center text-[9px] text-gray-500" title="{{ $proceso }}: {{ $mp['persona']['nombre'] ?? 'Sin asignar' }}">
                                                <span class="proc-dot proc-dot-{{ match($proceso) { 'Carpintería' => 'carp', 'Barniz' => 'barn', 'Instalación' => 'inst' } }}"></span>
                                                {{ $mp['persona'] ? Str::limit($mp['persona']['nombre'], 8) : '-' }}
                                            </span>
                                        @endforeach
                                    </div>
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
                                    <td class="gen-cell text-center day-cell {{ $esFestivo ? 'festivo' : '' }} {{ $matClass }} {{ $dia->isMonday() ? 'border-l-2 border-blue-200' : '' }}"
                                        data-date="{{ $diaStr }}">
                                    </td>
                                @endforeach
                                <td class="px-1 py-1 text-center text-[9px] text-gray-400 align-top">
                                    @foreach($procesos as $proceso)
                                        @php $mp = $muebleProcs[$proceso]; @endphp
                                        @if($mp['dias'] > 0)
                                            <div title="{{ $proceso }}">{{ $mp['dias'] }}</div>
                                        @endif
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    @endif
</div>

{{-- Assignment popover (shared, moved by JS) --}}
<div class="assign-popover" id="assign-popover">
    <div class="text-xs font-bold mb-2 text-gray-700" id="assign-title">Asignar equipo</div>
    @foreach(['Carpintería', 'Barniz', 'Instalación'] as $proc)
    <div class="assign-row" data-proceso="{{ $proc }}">
        <label><span class="proc-dot proc-dot-{{ match($proc) { 'Carpintería' => 'carp', 'Barniz' => 'barn', 'Instalación' => 'inst' } }}"></span>{{ $proc }}</label>
        <select class="assign-select border rounded px-1 py-0.5" data-proceso="{{ $proc }}">
            <option value="">--</option>
            @foreach($personalByEquipo[$proc] ?? [] as $p)
                <option value="{{ $p->id }}" style="color: {{ $p->color_hex }}">{{ $p->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="assign-dates hidden ml-2 mb-1" data-proceso="{{ $proc }}">
        <div class="flex items-center gap-1 text-[10px] text-gray-500">
            <input type="date" class="assign-fecha-inicio border rounded px-1 py-0.5 text-[10px]" data-proceso="{{ $proc }}">
            <span>a</span>
            <input type="date" class="assign-fecha-fin border rounded px-1 py-0.5 text-[10px]" data-proceso="{{ $proc }}">
            <input type="number" class="assign-personas border rounded px-1 py-0.5 text-[10px] w-10 text-center" data-proceso="{{ $proc }}" value="1" min="0.5" max="24" step="0.5" title="Personas">
            <button type="button" class="bg-blue-600 text-white px-2 py-0.5 rounded text-[10px] assign-save-btn" data-proceso="{{ $proc }}">Crear</button>
        </div>
    </div>
    @endforeach
    <div class="flex justify-end gap-2 mt-2">
        <button type="button" class="text-xs text-gray-400 hover:text-gray-600" id="assign-cancel">Cerrar</button>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const procesoColors = { 'Carpintería': '#f59e0b', 'Barniz': '#10b981', 'Instalación': '#3b82f6' };
    const procesoSlots = { 'Carpintería': 0, 'Barniz': 1, 'Instalación': 2 };

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
            if (!data.fecha_min || !data.fecha_max) continue;

            const firstCol = colPos.find(c => c.date === data.fecha_min);
            const lastCol = colPos.find(c => c.date === data.fecha_max);
            if (!firstCol || !lastCol) continue;

            const barLeft = firstCol.left;
            const barWidth = (lastCol.left + lastCol.width) - firstCol.left;
            const slot = procesoSlots[proceso];
            const color = data.persona?.color_hex || procesoColors[proceso];

            const bar = document.createElement('div');
            bar.className = 'gantt-bar';
            bar.setAttribute('data-slot', slot);
            bar.style.left = barLeft + 'px';
            bar.style.width = barWidth + 'px';
            bar.style.backgroundColor = color + 'AA';
            bar.style.borderColor = color;
            bar.dataset.firstDate = data.fecha_min;
            bar.dataset.lastDate = data.fecha_max;
            bar.dataset.muebleId = row.dataset.muebleId;
            bar.dataset.proceso = proceso;
            bar.dataset.personalId = data.asignado || '';
            bar.dataset.proyectoId = row.dataset.proyectoId;
            bar.dataset.color = color;
            bar.dataset.personas = data.personas || 1;

            // Label: process initial + personas count
            const initial = proceso.charAt(0);
            bar.textContent = initial + (data.personas > 0 ? ' ' + data.personas : '');

            const handleLeft = document.createElement('div');
            handleLeft.className = 'gantt-handle gantt-handle-left';
            bar.appendChild(handleLeft);

            const handleRight = document.createElement('div');
            handleRight.className = 'gantt-handle gantt-handle-right';
            bar.appendChild(handleRight);

            row.appendChild(bar);
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
        if (!bar) return;

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

        bar.classList.add('dragging');

        function onMouseMove(ev) {
            const dx = ev.clientX - startX;
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

            const freshColPos = getColPositions(table);

            if (mode === 'move') {
                const newLeft = parseFloat(bar.style.left);
                const snappedFirst = snapToColumn(newLeft, freshColPos);
                const daysMoved = countBusinessDaysBetween(origFirstDate, snappedFirst.date, allDates);

                if (daysMoved === 0) { bar.style.left = origLeft + 'px'; return; }

                const proyectoId = bar.dataset.proyectoId;
                bar.style.opacity = '0.5';

                fetch('/tiempos/recorrer/' + proyectoId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        dias_habiles: daysMoved,
                        procesos: [bar.dataset.proceso],
                        mueble_ids: [parseInt(bar.dataset.muebleId)]
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) { location.reload(); }
                    else { alert('Error: ' + (data.error || 'Unknown error')); bar.style.left = origLeft + 'px'; bar.style.opacity = '1'; }
                })
                .catch(() => { alert('Error al recorrer fechas'); bar.style.left = origLeft + 'px'; bar.style.opacity = '1'; });

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
                    if (data.ok || data.success) { location.reload(); }
                    else { alert('Error: ' + (data.error || 'Unknown error')); buildGanttBars(row); }
                })
                .catch(() => { alert('Error al guardar rango'); buildGanttBars(row); });
            }
        }

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });

    // ===== ASSIGNMENT POPOVER (right-click on row) =====
    const popover = document.getElementById('assign-popover');
    let activeRow = null;

    document.querySelectorAll('.gantt-row').forEach(row => {
        row.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            activeRow = this;
            const procs = JSON.parse(this.dataset.procs || '{}');

            // Position popover
            popover.style.left = e.pageX + 'px';
            popover.style.top = e.pageY + 'px';
            popover.classList.add('active');

            // Set current values
            popover.querySelectorAll('.assign-select').forEach(sel => {
                const proc = sel.dataset.proceso;
                const data = procs[proc];
                sel.value = data?.asignado || '';
                sel.dataset.muebleId = this.dataset.muebleId;
            });

            document.getElementById('assign-title').textContent = 'Asignar equipo - Mueble ' + this.querySelector('td').textContent.trim();
        });
    });

    document.getElementById('assign-cancel').addEventListener('click', () => {
        popover.classList.remove('active');
        activeRow = null;
    });

    document.addEventListener('click', function(e) {
        if (!popover.contains(e.target) && !e.target.closest('.gantt-row')) {
            popover.classList.remove('active');
            activeRow = null;
        }
    });

    // Handle assignment change
    popover.querySelectorAll('.assign-select').forEach(sel => {
        sel.addEventListener('change', function() {
            if (!activeRow) return;
            const muebleId = this.dataset.muebleId;
            const proceso = this.dataset.proceso;
            const personalId = this.value;
            const procs = JSON.parse(activeRow.dataset.procs || '{}');
            const prevPersonalId = procs[proceso]?.asignado || '';

            // Hide all date rows first
            const dateRow = popover.querySelector('.assign-dates[data-proceso="' + proceso + '"]');

            if (personalId && prevPersonalId && personalId !== prevPersonalId) {
                // Reassign existing times
                if (dateRow) dateRow.classList.add('hidden');
                fetch('{{ route("tiempos.reasignarEquipo") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        mueble_id: muebleId,
                        proceso: proceso,
                        personal_id: personalId,
                        personal_anterior_id: prevPersonalId
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        this.style.backgroundColor = '#d1fae5';
                        setTimeout(() => location.reload(), 500);
                    }
                })
                .catch(() => alert('Error al reasignar equipo'));
            } else if (personalId && !prevPersonalId) {
                // New assignment — show date fields to create initial range
                if (dateRow) {
                    dateRow.classList.remove('hidden');
                    // Set default dates: project start + 5 days
                    const proyectoId = activeRow.dataset.proyectoId;
                    const today = new Date().toISOString().split('T')[0];
                    const inicio = dateRow.querySelector('.assign-fecha-inicio');
                    const fin = dateRow.querySelector('.assign-fecha-fin');
                    if (inicio && !inicio.value) inicio.value = today;
                    if (fin && !fin.value) {
                        // Default: 1 week from start
                        const d = new Date();
                        d.setDate(d.getDate() + 7);
                        fin.value = d.toISOString().split('T')[0];
                    }
                }
            } else if (!personalId) {
                if (dateRow) dateRow.classList.add('hidden');
            }
        });
    });

    // Handle "Crear" button — save new range
    popover.querySelectorAll('.assign-save-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!activeRow) return;
            const proceso = this.dataset.proceso;
            const muebleId = activeRow.dataset.muebleId;
            const select = popover.querySelector('.assign-select[data-proceso="' + proceso + '"]');
            const personalId = select?.value;
            const fechaInicio = popover.querySelector('.assign-fecha-inicio[data-proceso="' + proceso + '"]')?.value;
            const fechaFin = popover.querySelector('.assign-fecha-fin[data-proceso="' + proceso + '"]')?.value;
            const personas = parseFloat(popover.querySelector('.assign-personas[data-proceso="' + proceso + '"]')?.value) || 1;

            if (!personalId) return alert('Selecciona una persona');
            if (!fechaInicio || !fechaFin) return alert('Selecciona fechas de inicio y fin');
            if (fechaInicio > fechaFin) return alert('La fecha inicio debe ser antes de la fecha fin');

            this.disabled = true;
            this.textContent = '...';

            fetch('{{ route("tiempos.guardarRango") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({
                    mueble_id: parseInt(muebleId),
                    proceso: proceso,
                    personal_id: parseInt(personalId),
                    fecha_inicio: fechaInicio,
                    fecha_fin: fechaFin,
                    personas: personas
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || JSON.stringify(data)));
                    this.disabled = false;
                    this.textContent = 'Crear';
                }
            })
            .catch(() => {
                alert('Error al crear rango');
                this.disabled = false;
                this.textContent = 'Crear';
            });
        });
    });
});
</script>
@endpush
