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
                        <div class="text-xs text-gray-500">
                            {{ $proy['jornales'] }} jornales &middot;
                            ${{ number_format($proy['costo'], 2) }} &middot;
                            {{ count($proy['personal']) }} personas
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
                                    <th class="px-3 py-1.5 text-right font-medium text-gray-500 w-20">% Avance</th>
                                    <th class="px-3 py-1.5 text-right font-medium text-gray-500 w-28">Prod. Avanzada</th>
                                    <th class="px-3 py-1.5 text-left font-medium text-gray-500">Personal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($proy['muebles'] as $mueble)
                                    @php
                                        $avance = $mueble['avance_porcentaje'];
                                        $costoMueble = $mueble['costo_mueble'];
                                        $prodAvanzada = ($avance !== null && $costoMueble > 0) ? $costoMueble * $avance / 100 : null;
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
                                                       placeholder="%"
                                                       data-mueble-id="{{ $mueble['mueble_id'] }}"
                                                       data-costo-mueble="{{ $costoMueble }}"
                                                       onchange="guardarAvance(this)">
                                            @else
                                                <span class="font-mono {{ $avance !== null ? ($avance >= 100 ? 'text-green-600 font-semibold' : ($avance >= 80 ? 'text-amber-600' : 'text-gray-600')) : 'text-gray-300' }}">
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
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function guardarAvance(input) {
    const muebleId = input.dataset.muebleId;
    const costoMueble = parseFloat(input.dataset.costoMueble) || 0;
    const valor = parseFloat(input.value) || 0;
    const row = input.closest('tr');
    const prodCell = row.querySelector('.prod-avanzada');

    fetch(`/nomina/costo-mueble/${muebleId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ avance_porcentaje: valor || null }),
    })
    .then(r => r.json())
    .then(data => {
        // Update produccion avanzada
        if (valor > 0 && costoMueble > 0) {
            const prod = costoMueble * valor / 100;
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
