@extends('layouts.app')
@section('title', 'Equipos del Día')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-4">
    <h1 class="text-xl font-bold">Equipos del Día</h1>

    <div class="flex items-center gap-3">
        <form method="GET" action="{{ route('nomina.equipos') }}" class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Semana:</label>
            <input type="number" name="semana" value="{{ $semana }}" min="1" max="53"
                   class="border rounded px-2 py-1 text-sm w-16">
            <label class="text-sm text-gray-600">Año:</label>
            <input type="number" name="anio" value="{{ $anio }}" min="2020" max="2030"
                   class="border rounded px-2 py-1 text-sm w-20">
            <button type="submit" class="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700">Ir</button>
        </form>
    </div>
</div>

<p class="text-sm text-gray-500 mb-4">
    Semana {{ $semana }} &mdash; {{ $inicioSemana->format('d/m/Y') }} al {{ $finSemana->format('d/m/Y') }}
    &middot; Marca qué trabajadores están con cada líder cada día.
</p>

@if($trabajadores->isEmpty())
    <div class="bg-amber-50 border border-amber-300 text-amber-800 px-4 py-3 rounded mb-4 text-sm">
        No hay trabajadores individuales registrados. Ve a <a href="{{ route('personal.index') }}" class="underline font-semibold">Personal</a> y agrega trabajadores con un líder asignado.
    </div>
@endif

@php $diasNombre = ['Lun','Mar','Mié','Jue','Vie']; @endphp

@foreach($lideres as $lider)
<div class="bg-white rounded-lg shadow mb-4">
    <div class="px-4 py-3 border-b bg-gray-50 rounded-t-lg flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="inline-block w-3 h-3 rounded-full" style="background: {{ $lider->color_hex }}"></span>
            <span class="font-semibold text-gray-800">{{ $lider->nombre }}</span>
            <span class="text-xs text-gray-400">{{ $lider->equipo }}</span>
        </div>
        <div class="flex gap-1">
            @foreach($dias as $idx => $dia)
                <button onclick="copiarDia('{{ $dias[0]->format('Y-m-d') }}', '{{ $dia->format('Y-m-d') }}')"
                        class="text-xs text-blue-500 hover:text-blue-700 px-1 {{ $idx === 0 ? 'hidden' : '' }}"
                        title="Copiar Lunes a {{ $diasNombre[$idx] }}">
                    Lun&rarr;{{ $diasNombre[$idx] }}
                </button>
            @endforeach
        </div>
    </div>

    <table class="min-w-full text-sm">
        <thead>
            <tr class="border-b">
                <th class="px-4 py-2 text-left font-medium text-gray-500 w-48">Trabajador</th>
                @foreach($dias as $idx => $dia)
                    <th class="px-4 py-2 text-center font-medium text-gray-500 w-24">
                        {{ $diasNombre[$idx] }} {{ $dia->format('d/m') }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($trabajadores as $trab)
                @php
                    $defaultLider = $trab->lider_id;
                    $showRow = !$defaultLider || $defaultLider == $lider->id;
                @endphp
                <tr class="{{ !$showRow ? 'opacity-40' : '' }}" data-default-lider="{{ $defaultLider }}">
                    <td class="px-4 py-1.5 text-gray-700">{{ $trab->nombre }}</td>
                    @foreach($dias as $dia)
                        @php
                            $key = $lider->id . '_' . $dia->format('Y-m-d');
                            $checked = isset($asignaciones[$key]) && in_array($trab->id, $asignaciones[$key]);
                        @endphp
                        <td class="px-4 py-1.5 text-center">
                            <input type="checkbox"
                                   class="equipo-check rounded"
                                   data-lider-id="{{ $lider->id }}"
                                   data-personal-id="{{ $trab->id }}"
                                   data-fecha="{{ $dia->format('Y-m-d') }}"
                                   {{ $checked ? 'checked' : '' }}
                                   onchange="guardarEquipo(this)">
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endforeach

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function guardarEquipo(checkbox) {
    const liderId = checkbox.dataset.liderId;
    const fecha = checkbox.dataset.fecha;

    // Collect all checked workers for this leader + date
    const checks = document.querySelectorAll(`.equipo-check[data-lider-id="${liderId}"][data-fecha="${fecha}"]`);
    const personalIds = [];
    checks.forEach(c => {
        if (c.checked) personalIds.push(c.dataset.personalId);
    });

    // If this worker was checked here, uncheck from other leaders on same date
    if (checkbox.checked) {
        const personalId = checkbox.dataset.personalId;
        document.querySelectorAll(`.equipo-check[data-personal-id="${personalId}"][data-fecha="${fecha}"]`).forEach(c => {
            if (c !== checkbox && c.checked) {
                c.checked = false;
                // Save the other leader's updated list too
                guardarEquipo(c);
            }
        });
    }

    fetch('{{ route("nomina.equipos.guardar") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            lider_id: liderId,
            fecha: fecha,
            personal_ids: personalIds,
        }),
    })
    .then(r => {
        if (!r.ok) {
            return r.text().then(t => { throw new Error('HTTP ' + r.status + ': ' + t); });
        }
        return r.json();
    })
    .then(data => {
        checkbox.closest('td').style.backgroundColor = '#f0fdf4';
        setTimeout(() => checkbox.closest('td').style.backgroundColor = '', 400);
    })
    .catch(err => {
        console.error(err);
        checkbox.closest('td').style.backgroundColor = '#fef2f2';
        alert('Error al guardar equipo: ' + err.message);
    });
}

function copiarDia(fechaOrigen, fechaDestino) {
    if (!confirm('¿Copiar asignaciones de ' + fechaOrigen + ' a ' + fechaDestino + '?')) return;

    fetch('{{ route("nomina.equipos.copiar") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            fecha_origen: fechaOrigen,
            fecha_destino: fechaDestino,
        }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            window.location.reload();
        } else {
            alert(data.message || 'Error');
        }
    })
    .catch(err => alert('Error al copiar'));
}
</script>
@endpush
@endsection
