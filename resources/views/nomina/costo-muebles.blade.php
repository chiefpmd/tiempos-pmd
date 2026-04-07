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
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-500 min-w-[200px]">Mueble</th>
                @foreach($semanasConDatos as $sem)
                    <th class="px-3 py-2 text-right font-medium text-gray-500 min-w-[80px]">Sem {{ $sem }}</th>
                @endforeach
                <th class="px-3 py-2 text-right font-medium text-gray-700 min-w-[90px]">Total Nomina</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500 min-w-[120px]">Valor Mueble</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500 min-w-[120px]">Presup. Nómina</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500 min-w-[110px]">Jornales Presup.</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500 w-20">% MO</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @php $granTotal = 0; $granValor = 0; $granPresupNomina = 0; @endphp
            @foreach($muebles as $mueble)
                @php
                    $costosSem = $costosPorMueble[$mueble->id] ?? [];
                    $totalNomina = array_sum($costosSem);
                    $granTotal += $totalNomina;
                    $granValor += floatval($mueble->costo_mueble);
                    $granPresupNomina += floatval($mueble->presupuesto_nomina);
                    $pct = $mueble->presupuesto_nomina > 0 ? ($totalNomina / $mueble->presupuesto_nomina * 100) : 0;
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
                               class="costo-input border rounded px-1 py-0.5 text-sm w-28 text-right font-mono"
                               value="{{ $mueble->costo_mueble ? floatval($mueble->costo_mueble) : '' }}"
                               placeholder="0.00"
                               onchange="guardarCostoMueble(this, {{ $mueble->id }})">
                        @else
                            <span class="font-mono">{{ $mueble->costo_mueble ? '$' . number_format($mueble->costo_mueble, 2) : '-' }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right">
                        @if(auth()->user()->isAdmin())
                        <input type="number" step="0.01" min="0"
                               class="presup-nomina-input border rounded px-1 py-0.5 text-sm w-28 text-right font-mono"
                               value="{{ $mueble->presupuesto_nomina ? floatval($mueble->presupuesto_nomina) : '' }}"
                               placeholder="0.00"
                               onchange="guardarPresupuestoNomina(this, {{ $mueble->id }})">
                        @else
                            <span class="font-mono">{{ $mueble->presupuesto_nomina ? '$' . number_format($mueble->presupuesto_nomina, 2) : '-' }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right">
                        @if(auth()->user()->isAdmin())
                        <input type="number" step="0.5" min="0"
                               class="jornales-input border rounded px-1 py-0.5 text-sm w-20 text-right font-mono"
                               value="{{ $mueble->jornales_presupuesto ? floatval($mueble->jornales_presupuesto) : '' }}"
                               placeholder="0"
                               onchange="guardarJornalesPresupuesto(this, {{ $mueble->id }})">
                        @else
                            <span class="font-mono">{{ $mueble->jornales_presupuesto ? number_format($mueble->jornales_presupuesto, 1) : '-' }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono pct-cell {{ $pct > 100 ? 'text-red-600 font-bold' : ($pct > 80 ? 'text-amber-600' : 'text-gray-600') }}">
                        {{ $mueble->presupuesto_nomina > 0 ? number_format($pct, 1) . '%' : '-' }}
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
                <td class="px-3 py-2 text-right font-mono">${{ number_format($granPresupNomina, 2) }}</td>
                <td class="px-3 py-2 text-right font-mono">{{ $muebles->sum('jornales_presupuesto') > 0 ? number_format($muebles->sum('jornales_presupuesto'), 1) : '-' }}</td>
                <td class="px-3 py-2 text-right font-mono {{ $granPresupNomina > 0 && ($granTotal/$granPresupNomina*100) > 100 ? 'text-red-600' : 'text-gray-700' }}">
                    {{ $granPresupNomina > 0 ? number_format($granTotal / $granPresupNomina * 100, 1) . '%' : '-' }}
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
        row.style.backgroundColor = '#f0fdf4';
        setTimeout(() => row.style.backgroundColor = '', 500);
        recalcularTotales();
    })
    .catch(err => {
        console.error(err);
        alert('Error al guardar');
    });
}

function guardarPresupuestoNomina(input, muebleId) {
    const row = input.closest('tr');
    const valor = parseFloat(input.value) || 0;

    fetch(`/nomina/costo-mueble/${muebleId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ presupuesto_nomina: valor || null }),
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
        recalcularTotales();
    })
    .catch(err => {
        console.error(err);
        alert('Error al guardar');
    });
}

function guardarJornalesPresupuesto(input, muebleId) {
    const row = input.closest('tr');
    const valor = parseFloat(input.value) || 0;

    fetch(`/nomina/costo-mueble/${muebleId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ jornales_presupuesto: valor || null }),
    })
    .then(r => r.json())
    .then(data => {
        row.style.backgroundColor = '#f0fdf4';
        setTimeout(() => row.style.backgroundColor = '', 500);
    })
    .catch(err => {
        console.error(err);
        alert('Error al guardar');
    });
}

function recalcularTotales() {
    let granValor = 0;
    let granPresupNomina = 0;
    let granTotal = 0;

    document.querySelectorAll('tbody tr[data-mueble-id]').forEach(row => {
        const costoInput = row.querySelector('.costo-input');
        const presupInput = row.querySelector('.presup-nomina-input');
        const valorMueble = costoInput ? (parseFloat(costoInput.value) || 0) : 0;
        const presupNomina = presupInput ? (parseFloat(presupInput.value) || 0) : 0;
        const totalText = row.querySelector('.total-nomina').textContent;
        const totalNomina = parseFloat(totalText.replace(/[$,]/g, '')) || 0;

        granValor += valorMueble;
        granPresupNomina += presupNomina;
        granTotal += totalNomina;
    });

    const footerCells = document.querySelectorAll('tfoot td');
    // Valor Mueble total
    const valorCell = footerCells[footerCells.length - 4];
    valorCell.textContent = '$' + granValor.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    // Presup Nómina total
    const presupCell = footerCells[footerCells.length - 3];
    presupCell.textContent = '$' + granPresupNomina.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    // % MO total (última columna)
    const pctCell = footerCells[footerCells.length - 1];
    if (granPresupNomina > 0) {
        const pct = (granTotal / granPresupNomina * 100);
        pctCell.textContent = pct.toFixed(1) + '%';
        pctCell.className = 'px-3 py-2 text-right font-mono ' + (pct > 100 ? 'text-red-600' : 'text-gray-700');
    } else {
        pctCell.textContent = '-';
    }
}
</script>
@endpush
@endsection
