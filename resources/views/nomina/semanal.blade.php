@extends('layouts.app')
@section('title', 'Nómina Semanal')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-4">
    <h1 class="text-xl font-bold">Nómina Semanal</h1>

    <div class="flex items-center gap-3">
        <form method="GET" action="{{ route('nomina.semanal') }}" class="flex items-center gap-2 flex-wrap">
            <label class="text-sm text-gray-600">Semana:</label>
            <input type="number" name="semana" value="{{ $semana }}" min="1" max="53"
                   class="border rounded px-2 py-1 text-sm w-16">
            <label class="text-sm text-gray-600">a:</label>
            <input type="number" name="semana_fin" value="{{ $semanaFin }}" min="1" max="53"
                   class="border rounded px-2 py-1 text-sm w-16">
            <label class="text-sm text-gray-600">Año:</label>
            <input type="number" name="anio" value="{{ $anio }}" min="2020" max="2030"
                   class="border rounded px-2 py-1 text-sm w-20">
            <label class="text-sm text-gray-600">Personal:</label>
            <select name="personal_id" class="border rounded px-2 py-1 text-sm w-48">
                <option value="">-- Todos --</option>
                @foreach($todosEmpleados as $emp)
                    <option value="{{ $emp->id }}" {{ $personalFiltro == $emp->id ? 'selected' : '' }}>{{ $emp->nombre }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700">Ir</button>
        </form>

        <a href="{{ route('export.nomina.excel', ['semana' => $semana, 'semana_fin' => $semanaFin, 'anio' => $anio, 'personal_id' => $personalFiltro]) }}"
           class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
            Exportar Excel
        </a>

        @if(auth()->user()->isAdmin())
        @if($festivosEnRango->isNotEmpty())
        <button onclick="aplicarFestivos()" id="btn-festivos"
                class="bg-amber-500 text-white px-3 py-1 rounded text-sm hover:bg-amber-600">
            Aplicar Festivos ({{ $festivosEnRango->count() }})
        </button>
        @endif
        @endif
    </div>
</div>

<p class="text-sm text-gray-500 mb-4">
    @if($semana === $semanaFin)
        Semana {{ $semana }} &mdash; {{ $inicioSemana->format('d/m/Y') }} al {{ $finSemana->format('d/m/Y') }}
    @else
        Semanas {{ $semana }} a {{ $semanaFin }} &mdash; {{ $inicioSemana->format('d/m/Y') }} al {{ $finSemana->format('d/m/Y') }}
    @endif
    @if($personalFiltro)
        &mdash; <span class="font-semibold text-blue-600">{{ $todosEmpleados->find($personalFiltro)?->nombre }}</span>
    @endif
</p>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="min-w-full text-xs">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-500 min-w-[180px]">Empleado</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500 w-24">Día</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500 min-w-[200px]">Proyecto / Categoría</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500 min-w-[160px]">Mueble</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500 w-16">HE</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500 min-w-[160px]">Proyecto HE</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500 w-24">Costo</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($empleados as $emp)
                @php
                    $salarioDiario = $emp->salario_diario;
                    $salarioHe = $emp->salario_he;
                    $diasNombre = ['Lun','Mar','Mié','Jue','Vie'];
                    $diasPorSemana = collect($dias)->groupBy(fn($d) => $d->weekOfYear);
                    $totalSemanas = $diasPorSemana->count();
                    $totalDias = count($dias);
                @endphp
                @foreach($dias as $idx => $dia)
                    @php
                        $key = $emp->id . '_' . $dia->format('Y-m-d');
                        $reg = $registros[$key] ?? null;
                        $dayOfWeek = $dia->dayOfWeekIso - 1; // 0=Mon, 4=Fri
                        $isFirstDayOfWeek = $dayOfWeek === 0;
                        $currentWeek = $dia->weekOfYear;
                    @endphp
                    <tr class="{{ $idx === 0 ? 'border-t-2 border-gray-300' : '' }} {{ $isFirstDayOfWeek && $idx > 0 ? 'border-t border-blue-200' : '' }} hover:bg-gray-50"
                        data-personal-id="{{ $emp->id }}"
                        data-fecha="{{ $dia->format('Y-m-d') }}"
                        data-salario-diario="{{ $salarioDiario }}"
                        data-salario-he="{{ $salarioHe }}">

                        @if($idx === 0)
                        <td class="px-3 py-1 align-top" rowspan="{{ $totalDias }}">
                            <div class="font-semibold text-gray-800">{{ $emp->nombre }}</div>
                            @if($emp->clave_empleado)
                                <div class="text-gray-400 text-[10px]">{{ $emp->clave_empleado }}</div>
                            @endif
                            @if($salarioDiario > 0)
                                <div class="text-gray-400 text-[10px]">${{ number_format($salarioDiario, 2) }}/día</div>
                            @else
                                <div class="text-amber-500 text-[10px]">Sin sueldo</div>
                            @endif
                            @if(auth()->user()->isAdmin())
                            <div class="flex flex-wrap gap-1 mt-1">
                                <button onclick="aplicarProyectoSemana({{ $emp->id }})"
                                        class="text-[10px] bg-green-50 text-green-600 border border-green-200 rounded px-1.5 py-0.5 hover:bg-green-100"
                                        title="Copia el proyecto del primer día a todos los días sin asignar">
                                    Proy &rarr; sem
                                </button>
                                <button onclick="aplicarMuebleSemana({{ $emp->id }})"
                                        class="text-[10px] bg-blue-50 text-blue-600 border border-blue-200 rounded px-1.5 py-0.5 hover:bg-blue-100"
                                        title="Copia el mueble del primer día a todos los días de esta persona">
                                    Mueble &rarr; sem
                                </button>
                            </div>
                            @endif
                        </td>
                        @endif

                        <td class="px-3 py-1 {{ isset($festivosEnRango[$dia->format('Y-m-d')]) ? 'bg-amber-50 text-amber-700 font-semibold' : 'text-gray-500' }}">
                            @if($isFirstDayOfWeek && $totalSemanas > 1)
                                <span class="text-blue-600 font-semibold text-[9px]">S{{ $currentWeek }}</span>
                            @endif
                            {{ $diasNombre[$dayOfWeek] }} {{ $dia->format('d/m') }}
                            @if(isset($festivosEnRango[$dia->format('Y-m-d')]))
                                <div class="text-[9px] text-amber-500">{{ $festivosEnRango[$dia->format('Y-m-d')] }}</div>
                            @endif
                        </td>

                        <td class="px-3 py-1">
                            <select class="asignacion-select border rounded px-1 py-0.5 text-xs w-full"
                                    onchange="guardarCelda(this)" {{ auth()->user()->isAdmin() ? '' : 'disabled' }}>
                                <option value="">-- Sin asignar --</option>
                                <optgroup label="Proyectos">
                                    @foreach($proyectos as $p)
                                        <option value="proyecto_{{ $p->id }}"
                                            {{ $reg && $reg->proyecto_id == $p->id ? 'selected' : '' }}>
                                            {{ $p->nombre }}
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="No Productivo">
                                    @foreach($categorias as $cat)
                                        <option value="categoria_{{ $cat->id }}"
                                            {{ $reg && $reg->categoria_id == $cat->id ? 'selected' : '' }}>
                                            {{ $cat->nombre }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            </select>
                        </td>

                        <td class="px-3 py-1">
                            <select class="mueble-select border rounded px-1 py-0.5 text-xs w-full"
                                    onchange="guardarCelda(this)" {{ auth()->user()->isAdmin() ? '' : 'disabled' }}>
                                <option value="">-- Sin mueble --</option>
                                @if($reg && $reg->proyecto_id && isset($mueblesPorProyecto[$reg->proyecto_id]))
                                    @foreach($mueblesPorProyecto[$reg->proyecto_id] as $m)
                                        <option value="{{ $m->id }}" {{ $reg->mueble_id == $m->id ? 'selected' : '' }}>
                                            {{ $m->numero }} - {{ $m->descripcion }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </td>

                        <td class="px-3 py-1">
                            <input type="number" step="0.5" min="0" max="24"
                                   class="he-input border rounded px-1 py-0.5 text-xs w-14"
                                   value="{{ $reg ? floatval($reg->horas_extra) : 0 }}"
                                   onchange="guardarCelda(this)" {{ auth()->user()->isAdmin() ? '' : 'disabled' }}>
                        </td>

                        <td class="px-3 py-1">
                            <select class="he-proyecto-select border rounded px-1 py-0.5 text-xs w-full"
                                    onchange="guardarCelda(this)" {{ auth()->user()->isAdmin() ? '' : 'disabled' }}>
                                <option value="">-- Mismo --</option>
                                @foreach($proyectos as $p)
                                    <option value="{{ $p->id }}"
                                        {{ $reg && $reg->proyecto_he_id == $p->id ? 'selected' : '' }}>
                                        {{ $p->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </td>

                        <td class="px-3 py-1 text-right costo-cell font-mono text-gray-700">
                            @if($reg && $reg->costo_total > 0)
                                ${{ number_format($reg->costo_total, 2) }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</div>

@if($empleados->isEmpty())
    <p class="text-gray-400 text-sm mt-4">No hay empleados activos.</p>
@endif

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

const mueblesPorProyecto = @json($mueblesPorProyecto->map(fn($muebles) => $muebles->map(fn($m) => ['id' => $m->id, 'numero' => $m->numero, 'descripcion' => $m->descripcion])));

function actualizarMuebles(row, proyectoId) {
    const muebleSelect = row.querySelector('.mueble-select');
    if (!muebleSelect) return;
    const currentVal = muebleSelect.value;
    muebleSelect.innerHTML = '<option value="">-- Sin mueble --</option>';
    if (proyectoId && mueblesPorProyecto[proyectoId]) {
        mueblesPorProyecto[proyectoId].forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.numero + ' - ' + m.descripcion;
            if (String(m.id) === String(currentVal)) opt.selected = true;
            muebleSelect.appendChild(opt);
        });
    }
}

function guardarCelda(el) {
    const row = el.closest('tr');
    const personalId = row.dataset.personalId;
    const fecha = row.dataset.fecha;
    const salarioDiario = parseFloat(row.dataset.salarioDiario) || 0;
    const salarioHe = parseFloat(row.dataset.salarioHe) || 0;

    const asignacionSelect = row.querySelector('.asignacion-select');
    const muebleSelect = row.querySelector('.mueble-select');
    const heInput = row.querySelector('.he-input');
    const heProyectoSelect = row.querySelector('.he-proyecto-select');
    const costoCell = row.querySelector('.costo-cell');

    const asignacionVal = asignacionSelect.value;
    let asignacionTipo = null;
    let asignacionId = null;

    if (asignacionVal) {
        const parts = asignacionVal.split('_');
        asignacionTipo = parts[0];
        asignacionId = parts.slice(1).join('_');
    }

    // When project changes, update mueble dropdown
    if (el === asignacionSelect) {
        const proyectoId = (asignacionTipo === 'proyecto') ? asignacionId : null;
        actualizarMuebles(row, proyectoId);
    }

    const muebleId = muebleSelect.value || null;
    const horasExtra = parseFloat(heInput.value) || 0;
    const projectHeId = heProyectoSelect.value || null;

    // Optimistic cost update
    let costo = asignacionVal ? salarioDiario : 0;
    costo += horasExtra * salarioHe;
    costoCell.textContent = costo > 0 ? '$' + costo.toFixed(2) : '-';

    fetch('{{ route("nomina.guardar") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            personal_id: personalId,
            fecha: fecha,
            asignacion_tipo: asignacionTipo,
            asignacion_id: asignacionId,
            mueble_id: muebleId,
            horas_extra: horasExtra,
            proyecto_he_id: projectHeId,
        }),
    })
    .then(r => r.json())
    .then(data => {
        const ct = parseFloat(data.costo_total);
        costoCell.textContent = ct > 0 ? '$' + ct.toFixed(2) : '-';
        row.style.backgroundColor = '#f0fdf4';
        setTimeout(() => row.style.backgroundColor = '', 500);
    })
    .catch(err => {
        costoCell.textContent = 'ERROR';
        console.error(err);
    });
}

async function aplicarMuebleSemana(personalId) {
    const rows = document.querySelectorAll(`tr[data-personal-id="${personalId}"]`);
    if (!rows.length) return;

    // Find the first row that has a mueble selected
    let muebleId = null;
    let proyectoVal = null;
    for (const row of rows) {
        const ms = row.querySelector('.mueble-select');
        const as = row.querySelector('.asignacion-select');
        if (ms && ms.value) {
            muebleId = ms.value;
            proyectoVal = as.value;
            break;
        }
    }

    if (!muebleId) {
        alert('Primero selecciona un mueble en algún día.');
        return;
    }

    let count = 0;
    for (const row of rows) {
        const muebleSelect = row.querySelector('.mueble-select');
        const asignacionSelect = row.querySelector('.asignacion-select');

        // Only apply to rows that have the same project assigned
        if (asignacionSelect.value !== proyectoVal) continue;
        // Skip if already has the same mueble
        if (muebleSelect.value === muebleId) continue;

        // Update dropdown options if needed, then set value
        const parts = proyectoVal.split('_');
        if (parts[0] === 'proyecto') {
            actualizarMuebles(row, parts.slice(1).join('_'));
        }
        muebleSelect.value = muebleId;
        guardarCelda(muebleSelect);
        count++;
        // Small delay to not overwhelm the server
        await new Promise(r => setTimeout(r, 100));
    }

    if (count === 0) {
        alert('Todos los días ya tienen ese mueble asignado.');
    }
}

async function aplicarProyectoSemana(personalId) {
    const rows = document.querySelectorAll(`tr[data-personal-id="${personalId}"]`);
    if (!rows.length) return;

    // Find first row with a project assigned
    let proyectoVal = null;
    for (const row of rows) {
        const as = row.querySelector('.asignacion-select');
        if (as && as.value && as.value.startsWith('proyecto_')) {
            proyectoVal = as.value;
            break;
        }
    }

    if (!proyectoVal) {
        alert('Primero selecciona un proyecto en algún día.');
        return;
    }

    let count = 0;
    for (const row of rows) {
        const asignacionSelect = row.querySelector('.asignacion-select');

        // Only apply to rows without assignment
        if (asignacionSelect.value) continue;

        asignacionSelect.value = proyectoVal;

        // Update mueble dropdown for the new project
        const parts = proyectoVal.split('_');
        actualizarMuebles(row, parts.slice(1).join('_'));

        guardarCelda(asignacionSelect);
        count++;
        await new Promise(r => setTimeout(r, 100));
    }

    if (count === 0) {
        alert('Todos los días ya tienen asignación.');
    }
}

const festivosEnRango = @json($festivosEnRango ?? []);

function aplicarFestivos() {
    const fechas = Object.keys(festivosEnRango);
    if (!fechas.length) {
        alert('No hay festivos en este rango.');
        return;
    }

    const nombres = Object.values(festivosEnRango).join(', ');
    if (!confirm(`¿Aplicar "${nombres}" como día festivo a todos los empleados sin asignación?`)) return;

    const btn = document.getElementById('btn-festivos');
    btn.disabled = true;
    btn.textContent = 'Aplicando...';

    fetch('{{ route("nomina.aplicarFestivos") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ fechas: fechas }),
    })
    .then(r => r.json())
    .then(data => {
        alert(data.message);
        window.location.reload();
    })
    .catch(err => {
        alert('Error al aplicar festivos');
        btn.disabled = false;
        btn.textContent = 'Aplicar Festivos';
    });
}

</script>
@endpush
@endsection
