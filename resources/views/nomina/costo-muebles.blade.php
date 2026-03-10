@extends('layouts.app')
@section('title', 'Costo por Mueble - ' . $proyecto->nombre)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-4">
    <div>
        <h1 class="text-xl font-bold">Costo por Mueble</h1>
        <p class="text-sm text-gray-500">{{ $proyecto->nombre }} &mdash; {{ $proyecto->cliente }}</p>
    </div>

    <div class="flex items-center gap-3">
        <form method="GET" action="{{ route('nomina.costoMuebles', $proyecto) }}" class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Sem inicio:</label>
            <input type="number" name="semana_inicio" value="{{ $semanaInicio }}" min="1" max="53"
                   class="border rounded px-2 py-1 text-sm w-16">
            <label class="text-sm text-gray-600">Sem fin:</label>
            <input type="number" name="semana_fin" value="{{ $semanaFin }}" min="1" max="53"
                   class="border rounded px-2 py-1 text-sm w-16">
            <label class="text-sm text-gray-600">Anio:</label>
            <input type="number" name="anio" value="{{ $anio }}" min="2020" max="2030"
                   class="border rounded px-2 py-1 text-sm w-20">
            <button type="submit" class="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700">Filtrar</button>
        </form>
        <a href="{{ route('nomina.reporte', ['semana_inicio' => $semanaInicio, 'semana_fin' => $semanaFin, 'anio' => $anio]) }}"
           class="text-sm text-blue-600 hover:underline">&larr; Reporte General</a>
    </div>
</div>

@if($muebles->isEmpty())
    <p class="text-gray-400 text-sm mt-4">Este proyecto no tiene muebles.</p>
@else
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="min-w-full text-xs">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-500 min-w-[200px]">Mueble</th>
                @foreach($semanasConDatos as $sem)
                    <th class="px-3 py-2 text-right font-medium text-gray-500 min-w-[80px]">Sem {{ $sem }}</th>
                @endforeach
                <th class="px-3 py-2 text-right font-medium text-gray-700 min-w-[90px]">Total Nomina</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500 min-w-[120px]">Valor Mueble</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500 w-20">% MO</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @php $granTotal = 0; $granValor = 0; @endphp
            @foreach($muebles as $mueble)
                @php
                    $costosSem = $costosPorMueble[$mueble->id] ?? [];
                    $totalNomina = array_sum($costosSem);
                    $granTotal += $totalNomina;
                    $granValor += floatval($mueble->costo_mueble);
                    $pct = $mueble->costo_mueble > 0 ? ($totalNomina / $mueble->costo_mueble * 100) : 0;
                @endphp
                <tr class="hover:bg-gray-50" data-mueble-id="{{ $mueble->id }}">
                    <td class="px-3 py-1.5 text-gray-800">
                        <span class="font-semibold">{{ $mueble->numero }}</span>
                        <span class="text-gray-500">- {{ $mueble->descripcion }}</span>
                    </td>
                    @foreach($semanasConDatos as $sem)
                        @php $val = $costosSem[$sem] ?? 0; @endphp
                        <td class="px-3 py-1.5 text-right font-mono">{{ $val > 0 ? '$' . number_format($val, 2) : '-' }}</td>
                    @endforeach
                    <td class="px-3 py-1.5 text-right font-mono font-semibold total-nomina">
                        ${{ number_format($totalNomina, 2) }}
                    </td>
                    <td class="px-3 py-1.5 text-right">
                        @if(auth()->user()->isAdmin())
                        <input type="number" step="0.01" min="0"
                               class="costo-input border rounded px-1 py-0.5 text-xs w-24 text-right font-mono"
                               value="{{ $mueble->costo_mueble ? floatval($mueble->costo_mueble) : '' }}"
                               placeholder="0.00"
                               onchange="guardarCostoMueble(this, {{ $mueble->id }})">
                        @else
                            <span class="font-mono">{{ $mueble->costo_mueble ? '$' . number_format($mueble->costo_mueble, 2) : '-' }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono pct-cell {{ $pct > 100 ? 'text-red-600 font-bold' : ($pct > 80 ? 'text-amber-600' : 'text-gray-600') }}">
                        {{ $mueble->costo_mueble > 0 ? number_format($pct, 1) . '%' : '-' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="bg-gray-50">
            <tr class="font-semibold">
                <td class="px-3 py-2 text-gray-700">Total Proyecto</td>
                @foreach($semanasConDatos as $sem)
                    @php
                        $colTotal = 0;
                        foreach ($costosPorMueble as $costosSem) { $colTotal += $costosSem[$sem] ?? 0; }
                    @endphp
                    <td class="px-3 py-2 text-right font-mono">{{ $colTotal > 0 ? '$' . number_format($colTotal, 2) : '-' }}</td>
                @endforeach
                <td class="px-3 py-2 text-right font-mono text-blue-600">${{ number_format($granTotal, 2) }}</td>
                <td class="px-3 py-2 text-right font-mono">${{ number_format($granValor, 2) }}</td>
                <td class="px-3 py-2 text-right font-mono {{ $granValor > 0 && ($granTotal/$granValor*100) > 100 ? 'text-red-600' : 'text-gray-700' }}">
                    {{ $granValor > 0 ? number_format($granTotal / $granValor * 100, 1) . '%' : '-' }}
                </td>
            </tr>
        </tfoot>
    </table>
</div>

@if($semanasConDatos->isEmpty())
    <p class="text-gray-400 text-sm mt-4">No hay datos de nomina con mueble asignado en este rango.</p>
@endif

@endif

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function guardarCostoMueble(input, muebleId) {
    const row = input.closest('tr');
    const valor = parseFloat(input.value) || 0;

    fetch(`/nomina/costo-mueble/${muebleId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ costo_mueble: valor || null }),
    })
    .then(r => r.json())
    .then(data => {
        // Update percentage
        const pctCell = row.querySelector('.pct-cell');
        const totalText = row.querySelector('.total-nomina').textContent;
        const totalNomina = parseFloat(totalText.replace(/[$,]/g, '')) || 0;

        if (valor > 0) {
            const pct = (totalNomina / valor * 100);
            pctCell.textContent = pct.toFixed(1) + '%';
            pctCell.className = 'px-3 py-1.5 text-right font-mono pct-cell ' +
                (pct > 100 ? 'text-red-600 font-bold' : (pct > 80 ? 'text-amber-600' : 'text-gray-600'));
        } else {
            pctCell.textContent = '-';
            pctCell.className = 'px-3 py-1.5 text-right font-mono pct-cell text-gray-600';
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
