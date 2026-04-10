@extends('layouts.app')
@section('title', 'Reporte Mensual por Departamento')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-4">
    <h1 class="text-xl font-bold">Reporte Mensual por Departamento</h1>

    <form method="GET" action="{{ route('nomina.reporteMensual') }}" class="flex items-center gap-2">
        <label class="text-sm text-gray-600">Mes:</label>
        <select name="mes" class="border rounded px-2 py-1 text-sm">
            @foreach(range(1,12) as $m)
                <option value="{{ $m }}" {{ $m == $mes ? 'selected' : '' }}>
                    {{ \Carbon\Carbon::create(null, $m)->translatedFormat('F') }}
                </option>
            @endforeach
        </select>
        <label class="text-sm text-gray-600">Ano:</label>
        <input type="number" name="anio" value="{{ $anio }}" min="2020" max="2030"
               class="border rounded px-2 py-1 text-sm w-20">
        <button type="submit" class="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700">Filtrar</button>
    </form>
</div>

<p class="text-sm text-gray-500 mb-2">{{ $nombreMes }}</p>

{{-- Tabla resumen --}}
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left font-medium text-gray-500">Departamento</th>
                <th class="px-4 py-2 text-right font-medium text-gray-500">Jornales Proyecto</th>
                <th class="px-4 py-2 text-right font-medium text-gray-500">Costo Proyecto</th>
                <th class="px-4 py-2 text-right font-medium text-gray-500">Jornales Ausencias</th>
                <th class="px-4 py-2 text-right font-medium text-gray-500">Costo Ausencias</th>
                <th class="px-4 py-2 text-right font-medium text-gray-700">Total Jornales</th>
                <th class="px-4 py-2 text-right font-medium text-gray-700">Total Costo</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @php $sumJP = 0; $sumCP = 0; $sumJC = 0; $sumCC = 0; $sumJT = 0; $sumCT = 0; @endphp
            @foreach($departamentos as $depto)
                @php
                    $info = $data[$depto];
                    $sumJP += $info['jornalesProyecto'];
                    $sumCP += $info['costoProyecto'];
                    $sumJC += $info['jornalesCategoria'];
                    $sumCC += $info['costoCategoria'];
                    $sumJT += $info['totalJornales'];
                    $sumCT += $info['totalCosto'];
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 font-semibold {{ $depto === 'Carpintería' ? 'text-amber-600' : 'text-emerald-600' }}">{{ $depto }}</td>
                    <td class="px-4 py-2 text-right font-mono">{{ $info['jornalesProyecto'] }}</td>
                    <td class="px-4 py-2 text-right font-mono">${{ number_format($info['costoProyecto'], 2) }}</td>
                    <td class="px-4 py-2 text-right font-mono">{{ $info['jornalesCategoria'] }}</td>
                    <td class="px-4 py-2 text-right font-mono">${{ number_format($info['costoCategoria'], 2) }}</td>
                    <td class="px-4 py-2 text-right font-mono font-semibold">{{ $info['totalJornales'] }}</td>
                    <td class="px-4 py-2 text-right font-mono font-semibold">${{ number_format($info['totalCosto'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="bg-gray-50">
            <tr class="font-semibold">
                <td class="px-4 py-2 text-gray-700">Total</td>
                <td class="px-4 py-2 text-right font-mono">{{ $sumJP }}</td>
                <td class="px-4 py-2 text-right font-mono">${{ number_format($sumCP, 2) }}</td>
                <td class="px-4 py-2 text-right font-mono">{{ $sumJC }}</td>
                <td class="px-4 py-2 text-right font-mono">${{ number_format($sumCC, 2) }}</td>
                <td class="px-4 py-2 text-right font-mono">{{ $sumJT }}</td>
                <td class="px-4 py-2 text-right font-mono">${{ number_format($sumCT, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

{{-- Mueble producido: total único --}}
<div class="bg-white rounded-lg shadow px-4 py-3 mb-6 flex items-center gap-6">
    <div class="flex items-center gap-2">
        <span class="text-sm font-semibold text-blue-600">Mueble Producido:</span>
        <span class="text-lg font-mono font-bold text-blue-600">${{ number_format($totalProdAvanzada, 2) }}</span>
        <span class="text-xs text-gray-400">({{ $mueblesConAvance }} muebles con avance)</span>
    </div>
    <div class="flex items-center gap-2">
        <span class="text-sm font-semibold text-gray-700">Costo Nomina Total:</span>
        <span class="text-lg font-mono font-bold text-gray-700">${{ number_format($sumCT, 2) }}</span>
    </div>
</div>

{{-- Buscador de mueble en nómina --}}
<div class="bg-white rounded-lg shadow mb-6" x-data="buscadorMueble()">
    <div class="px-4 py-3 border-b bg-gray-50 flex items-center gap-2 cursor-pointer" @click="abierto = !abierto">
        <svg class="w-4 h-4 text-gray-500 transition-transform" :class="abierto && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-sm font-semibold text-gray-700">Buscar Mueble en Nomina</span>
    </div>
    <div x-show="abierto" x-cloak class="px-4 py-3">
        <div class="flex flex-wrap items-end gap-3 mb-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Mueble (numero o descripcion)</label>
                <input type="text" x-model="q" placeholder="Ej: M-01, mesa..."
                       class="border rounded px-2 py-1 text-sm w-48"
                       @keydown.enter="buscar()">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Desde</label>
                <input type="date" x-model="fechaDesde" class="border rounded px-2 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                <input type="date" x-model="fechaHasta" class="border rounded px-2 py-1 text-sm">
            </div>
            <button @click="buscar()" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">Buscar</button>
            <button x-show="resultados.length > 0" @click="resultados = []; q = ''" class="text-gray-400 text-sm hover:text-gray-600">Limpiar</button>
        </div>

        <div x-show="cargando" class="text-sm text-gray-400 py-2">Buscando...</div>

        <template x-if="!cargando && buscado && resultados.length === 0">
            <p class="text-sm text-gray-400 py-2">No se encontraron resultados.</p>
        </template>

        <template x-for="mueble in resultados" :key="mueble.mueble_id">
            <div class="border rounded mb-3 overflow-hidden">
                <div class="px-3 py-2 bg-gray-50 border-b flex items-center justify-between">
                    <div>
                        <span class="font-mono font-semibold text-sm" x-text="mueble.numero"></span>
                        <span class="text-gray-500 text-sm ml-2" x-text="mueble.descripcion"></span>
                        <span class="text-xs text-blue-600 ml-2" x-text="mueble.proyecto"></span>
                        <template x-if="mueble.abreviacion">
                            <span class="text-xs text-gray-400 ml-1" x-text="'(' + mueble.abreviacion + ')'"></span>
                        </template>
                    </div>
                    <div class="text-xs text-gray-500">
                        <span x-text="mueble.jornales"></span> jornales &middot;
                        $<span x-text="Number(mueble.costo).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                    </div>
                </div>
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-1 text-left font-medium text-gray-500">Fecha</th>
                            <th class="px-3 py-1 text-left font-medium text-gray-500">Personal</th>
                            <th class="px-3 py-1 text-left font-medium text-gray-500">Equipo</th>
                            <th class="px-3 py-1 text-right font-medium text-gray-500">Costo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(personas, fecha) in mueble.dias" :key="fecha">
                            <template x-for="(p, idx) in personas" :key="fecha + '-' + idx">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-1 font-mono" x-text="idx === 0 ? fecha : ''"></td>
                                    <td class="px-3 py-1" x-text="p.personal"></td>
                                    <td class="px-3 py-1">
                                        <span class="px-1.5 py-0.5 rounded text-xs"
                                              :class="p.equipo === 'Carpintería' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'"
                                              x-text="p.equipo"></span>
                                    </td>
                                    <td class="px-3 py-1 text-right font-mono" x-text="'$' + p.costo.toLocaleString('en-US', {minimumFractionDigits: 2})"></td>
                                </tr>
                            </template>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</div>

@foreach($departamentos as $depto)
    @php $info = $data[$depto]; @endphp

    <div class="mb-8">
        <div class="flex items-center gap-3 mb-3">
            <h2 class="text-lg font-bold {{ $depto === 'Carpintería' ? 'text-amber-600' : 'text-emerald-600' }}">{{ $depto }}</h2>
            <span class="text-xs text-gray-400">
                {{ $info['totalJornales'] }} jornales &middot; ${{ number_format($info['totalCosto'], 2) }}
            </span>
        </div>

        @if(empty($info['proyectos']) && empty($info['categorias']))
            <p class="text-gray-400 text-sm">No hay datos para este departamento.</p>
        @else
            {{-- Proyectos y muebles --}}
            @foreach($info['proyectos'] as $proy)
                <div class="bg-white rounded-lg shadow mb-3 overflow-hidden">
                    <div class="px-4 py-2 bg-gray-50 border-b flex items-center justify-between">
                        <div>
                            <a href="{{ route('nomina.costoMuebles', $proy['proyecto_id']) }}"
                               class="font-semibold text-sm text-blue-600 hover:underline">
                                {{ $proy['nombre'] }}
                            </a>
                            @if($proy['abreviacion'])
                                <span class="text-xs text-gray-400 ml-1">({{ $proy['abreviacion'] }})</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 flex items-center gap-3">
                            <span>{{ $proy['jornales'] }} jornales &middot;
                            ${{ number_format($proy['costo'], 2) }} &middot;
                            {{ count($proy['personal']) }} personas</span>
                            @if(($proy['prod_avanzada'] ?? 0) > 0)
                                <span class="font-semibold text-blue-700">Prod: ${{ number_format($proy['prod_avanzada'], 2) }}</span>
                            @endif
                        </div>
                    </div>

                    @if(!empty($proy['muebles']))
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-1.5 text-left font-medium text-gray-500 w-28">Mueble</th>
                                    <th class="px-3 py-1.5 text-left font-medium text-gray-500">Descripcion</th>
                                    <th class="px-3 py-1.5 text-right font-medium text-gray-500 w-20">Jornales</th>
                                    <th class="px-3 py-1.5 text-right font-medium text-gray-500 w-24">Costo Nomina</th>
                                    <th class="px-3 py-1.5 text-right font-medium text-gray-500 w-24">Valor Mueble</th>
                                    <th class="px-3 py-1.5 text-right font-medium {{ $depto === 'Carpintería' ? 'text-amber-500' : 'text-emerald-500' }} w-20">% Avance</th>
                                    <th class="px-3 py-1.5 text-right font-medium text-gray-500 w-28">Prod. Avanzada</th>
                                    <th class="px-3 py-1.5 text-left font-medium text-gray-500">Personal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($proy['muebles'] as $mueble)
                                    @php
                                        $campoAvance = $depto === 'Carpintería' ? 'avance_carpinteria' : 'avance_barniz';
                                        $campoPrev = $depto === 'Carpintería' ? 'prev_carpinteria' : 'prev_barniz';
                                        $avance = $mueble[$campoAvance];
                                        $prev = $mueble[$campoPrev] ?? 0;
                                        $costoMueble = $mueble['costo_mueble'];
                                        $delta = (float)($avance ?? 0) - (float)$prev;
                                        $prodAvanzada = ($delta > 0 && $costoMueble > 0) ? $costoMueble * $delta / 100 : null;
                                    @endphp
                                    <tr class="hover:bg-gray-50" data-mueble-id="{{ $mueble['mueble_id'] }}">
                                        <td class="px-3 py-1.5 font-mono text-gray-800">{{ $mueble['numero'] }}</td>
                                        <td class="px-3 py-1.5 text-gray-600">{{ $mueble['descripcion'] }}</td>
                                        <td class="px-3 py-1.5 text-right font-mono">{{ $mueble['jornales'] }}</td>
                                        <td class="px-3 py-1.5 text-right font-mono">${{ number_format($mueble['costo'], 2) }}</td>
                                        <td class="px-3 py-1.5 text-right font-mono">{{ $costoMueble > 0 ? '$' . number_format($costoMueble, 2) : '-' }}</td>
                                        <td class="px-3 py-1.5 text-right">
                                            @if(auth()->user()->isAdmin())
                                                <input type="number" step="0.1" min="0" max="100"
                                                       class="avance-input border rounded px-1 py-0.5 text-xs w-16 text-right font-mono"
                                                       value="{{ $avance !== null ? floatval($avance) : '' }}"
                                                       placeholder="{{ $prev > 0 ? number_format($prev, 0) . '%' : '%' }}"
                                                       title="{{ $prev > 0 ? 'Mes anterior: ' . number_format($prev, 1) . '%' : '' }}"
                                                       data-mueble-id="{{ $mueble['mueble_id'] }}"
                                                       data-campo="{{ $campoAvance }}"
                                                       data-prev="{{ $prev }}"
                                                       data-costo-mueble="{{ $costoMueble }}"
                                                       data-mes="{{ $mes }}"
                                                       data-anio="{{ $anio }}"
                                                       onchange="guardarAvance(this)">
                                            @else
                                                <span class="font-mono {{ $avance !== null ? ($depto === 'Carpintería' ? 'text-amber-600' : 'text-emerald-600') : 'text-gray-300' }}">
                                                    {{ $avance !== null ? number_format($avance, 1) . '%' : '-' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-1.5 text-right font-mono prod-avanzada {{ $prodAvanzada !== null ? 'text-blue-600' : 'text-gray-300' }}">
                                            {{ $prodAvanzada !== null ? '$' . number_format($prodAvanzada, 2) : '-' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-gray-500">
                                            {{ implode(', ', array_map(fn($n) => explode(' ', $n)[0], $mueble['personal'])) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    @php
                        $sinMueble = $proy['jornales'] - collect($proy['muebles'])->sum('jornales');
                    @endphp
                    @if($sinMueble > 0)
                        <div class="px-3 py-1.5 text-xs text-gray-400 border-t">
                            {{ $sinMueble }} jornales sin mueble asignado
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Categorias (faltas, vacaciones, etc.) --}}
            @if(!empty($info['categorias']))
                <div class="bg-white rounded-lg shadow mb-3 overflow-hidden">
                    <div class="px-4 py-2 bg-gray-50 border-b">
                        <span class="font-semibold text-sm text-gray-600">Ausencias / Otros</span>
                    </div>
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-1.5 text-left font-medium text-gray-500">Categoria</th>
                                <th class="px-3 py-1.5 text-right font-medium text-gray-500 w-20">Jornales</th>
                                <th class="px-3 py-1.5 text-left font-medium text-gray-500">Personal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($info['categorias'] as $catNombre => $cat)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-1.5 text-gray-800">{{ $catNombre }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ $cat['jornales'] }}</td>
                                    <td class="px-3 py-1.5 text-gray-500">
                                        {{ implode(', ', array_map(fn($n) => explode(' ', $n)[0], $cat['personal'])) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
@endforeach

@push('scripts')
<script>
function buscadorMueble() {
    return {
        abierto: false,
        q: '',
        fechaDesde: '',
        fechaHasta: '',
        resultados: [],
        cargando: false,
        buscado: false,
        buscar() {
            if (!this.q && !this.fechaDesde) return;
            this.cargando = true;
            this.buscado = false;
            const params = new URLSearchParams();
            if (this.q) params.set('q', this.q);
            if (this.fechaDesde) params.set('fecha_desde', this.fechaDesde);
            if (this.fechaHasta) params.set('fecha_hasta', this.fechaHasta);

            fetch(`/nomina/buscar-mueble?${params}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    this.resultados = data;
                    this.cargando = false;
                    this.buscado = true;
                })
                .catch(err => {
                    console.error(err);
                    this.cargando = false;
                });
        }
    };
}

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function guardarAvance(input) {
    const muebleId = input.dataset.muebleId;
    const campo = input.dataset.campo;
    const costoMueble = parseFloat(input.dataset.costoMueble) || 0;
    const prev = parseFloat(input.dataset.prev) || 0;
    const valor = parseFloat(input.value) || 0;
    const mesVal = input.dataset.mes;
    const anioVal = input.dataset.anio;
    const row = input.closest('tr');
    const prodCell = row.querySelector('.prod-avanzada');

    const body = {};
    body[campo] = valor || null;
    body.mes = mesVal;
    body.anio = anioVal;

    fetch(`/nomina/costo-mueble/${muebleId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify(body),
    })
    .then(r => r.json())
    .then(data => {
        // Prod avanzada = delta de este depto vs mes anterior
        const delta = valor - prev;
        if (delta > 0 && costoMueble > 0) {
            const prod = costoMueble * delta / 100;
            prodCell.textContent = '$' + prod.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            prodCell.className = 'px-3 py-1.5 text-right font-mono prod-avanzada text-blue-600';
        } else {
            prodCell.textContent = '-';
            prodCell.className = 'px-3 py-1.5 text-right font-mono prod-avanzada text-gray-300';
        }

        row.style.backgroundColor = '#f0fdf4';
        setTimeout(() => row.style.backgroundColor = '', 500);
    })
    .catch(err => {
        console.error(err);
        alert('Error al guardar');
    });
}
</script>
@endpush

@endsection
