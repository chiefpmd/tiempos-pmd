@extends('layouts.app')
@section('title', 'Vista General')

@push('styles')
<style>
    .gen-cell { min-width: 36px; font-size: 11px; padding: 1px; }
    .mueble-sep { border-top: 2px solid #d1d5db; }
    .proyecto-sep { border-top: 3px solid #6b7280; }
    .time-input {
        width: 36px; padding: 1px 2px; text-align: center; font-size: 11px;
        border: 1px solid #e5e7eb; border-radius: 3px; background: transparent;
    }
    .time-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.2); }
    .time-input.saving { background-color: #fef3c7; }
    .time-input.saved { background-color: #d1fae5; }
    .time-input.error { background-color: #fee2e2; }
    .time-input:disabled { background-color: #f3f4f6; cursor: not-allowed; }
    .proc-carp { border-left: 3px solid #f59e0b; }
    .proc-barn { border-left: 3px solid #10b981; }
    .proc-inst { border-left: 3px solid #3b82f6; }
    .add-mueble-form { display: none; }
    .add-mueble-form.active { display: flex; }
    .festivo { background-color: #f3e8ff; }
    .festivo-header { background-color: #e9d5ff; color: #7c3aed; }
</style>
@endpush

@section('content')
<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-3">
        <h1 class="text-xl font-bold">Vista General - Todos los Proyectos</h1>
        <a href="{{ route('export.general') }}" class="bg-green-600 text-white px-3 py-1.5 rounded text-sm hover:bg-green-700">Exportar Excel</a>
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
            $isAdmin = auth()->user()->isAdmin();
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
                <div class="flex items-center space-x-2">
                    <label class="text-xs font-medium text-purple-700">Dias habiles a recorrer:</label>
                    <input type="number" id="shift-dias-{{ $proyecto->id }}" value="1" min="-60" max="60" class="border rounded px-2 py-1 text-sm w-20 text-center">
                    <span class="text-xs text-gray-500">(positivo = adelante, negativo = atras)</span>
                    <button type="button" class="bg-purple-600 text-white px-3 py-1 rounded text-sm hover:bg-purple-700 btn-shift" data-proyecto="{{ $proyecto->id }}">Aplicar</button>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-sm toggle-shift" data-proyecto="{{ $proyecto->id }}">Cancelar</button>
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
            @endif

            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-full text-xs">
                    @php
                        $semanas = collect($diasHabiles)->groupBy(fn($d) => $d->weekOfYear);
                    @endphp
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-2 py-1 sticky left-0 bg-gray-50 z-10" colspan="4"></th>
                            @foreach($semanas as $numSemana => $diasSemana)
                                <th class="text-center font-bold text-blue-600 bg-blue-50 border-l-2 border-blue-200 text-xs" colspan="{{ count($diasSemana) }}">
                                    Sem {{ $numSemana }}
                                </th>
                            @endforeach
                            <th></th>
                        </tr>
                        <tr>
                            <th class="px-2 py-1 text-left font-medium text-gray-500 sticky left-0 bg-gray-50 z-10" style="min-width:100px">Mueble</th>
                            <th class="px-2 py-1 text-left font-medium text-gray-500" style="min-width:180px">Descripcion</th>
                            <th class="px-2 py-1 text-left font-medium text-gray-500" style="min-width:100px">Proceso</th>
                            <th class="px-2 py-1 text-left font-medium text-gray-500" style="min-width:140px">Equipo</th>
                            @foreach($diasHabiles as $dia)
                                @php $esFestivo = isset($festivos[$dia->format('Y-m-d')]); @endphp
                                <th class="gen-cell text-center font-medium {{ $esFestivo ? 'festivo-header' : 'text-gray-400' }} {{ $dia->isMonday() ? 'border-l-2 border-blue-200' : '' }}"
                                    @if($esFestivo) title="{{ $festivos[$dia->format('Y-m-d')] }}" @endif>
                                    {{ $dia->format('d/M') }}
                                </th>
                            @endforeach
                            <th class="px-1 py-1 text-center font-medium text-gray-500 w-10">T</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($proyecto->muebles as $mi => $mueble)
                            @foreach($procesos as $pi => $proceso)
                                @php
                                    $procClass = match($proceso) { 'Carpintería' => 'proc-carp', 'Barniz' => 'proc-barn', 'Instalación' => 'proc-inst', default => '' };
                                    $asignado = $allTiempos->where('mueble_id', $mueble->id)->where('proceso', $proceso)->pluck('personal_id')->unique()->first();
                                    $persona = $asignado ? ($personal[$asignado] ?? null) : null;
                                    $rowTotal = 0;
                                @endphp
                                <tr class="{{ $procClass }} hover:bg-gray-50 border-b border-gray-100 {{ $pi === 0 && $mi > 0 ? 'mueble-sep' : '' }}">
                                    <td class="px-2 py-1 font-medium sticky left-0 bg-white z-10">
                                        @if($pi === 0)
                                            {{ $mueble->numero }}
                                            @if($isAdmin)
                                                <form method="POST" action="{{ route('muebles.destroy', $mueble) }}" class="inline" onsubmit="return confirm('Eliminar mueble {{ $mueble->numero }}?')">
                                                    @csrf @method('DELETE')
                                                    <button class="text-red-400 hover:text-red-600 ml-1">&times;</button>
                                                </form>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-2 py-1">@if($pi === 0) {{ $mueble->descripcion }} @endif</td>
                                    <td class="px-2 py-1 text-gray-500">{{ $proceso }}</td>
                                    <td class="px-2 py-1 whitespace-nowrap">
                                        @if($isAdmin)
                                            <select class="personal-select text-xs border rounded px-1 py-0.5 w-full"
                                                data-mueble="{{ $mueble->id }}" data-proceso="{{ $proceso }}">
                                                <option value="">--</option>
                                                @foreach($personalByEquipo[$proceso] ?? [] as $p)
                                                    <option value="{{ $p->id }}" {{ $asignado == $p->id ? 'selected' : '' }}
                                                        style="color: {{ $p->color_hex }}">{{ $p->nombre }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            @if($persona)
                                                <span class="inline-block w-2 h-2 rounded-full mr-0.5" style="background-color: {{ $persona->color_hex }}"></span>
                                                {{ $persona->nombre }}
                                            @else
                                                <span class="text-gray-300">-</span>
                                            @endif
                                        @endif
                                    </td>
                                    @foreach($diasHabiles as $dia)
                                        @php
                                            $esFestivo = isset($festivos[$dia->format('Y-m-d')]);
                                            $key = $asignado ? "{$mueble->id}_{$proceso}_{$asignado}_{$dia->format('Y-m-d')}" : null;
                                            $val = ($key && isset($tiemposMap[$key])) ? (float)$tiemposMap[$key] : null;
                                            if ($val) $rowTotal += $val;
                                        @endphp
                                        <td class="gen-cell text-center {{ $esFestivo ? 'festivo' : '' }} {{ $dia->isMonday() ? 'border-l-2 border-blue-200' : '' }}"
                                            @if($val && $persona && !$esFestivo) style="background-color: {{ $persona->color_hex }}" @endif>
                                            @if($isAdmin)
                                                <input type="number" step="0.5" min="0" max="24"
                                                    class="time-input {{ $val ? 'text-white font-bold' : '' }}"
                                                    value="{{ $val ? $val : '' }}"
                                                    data-mueble="{{ $mueble->id }}"
                                                    data-proceso="{{ $proceso }}"
                                                    data-personal="{{ $asignado ?? '' }}"
                                                    data-fecha="{{ $dia->format('Y-m-d') }}"
                                                    data-color="{{ $persona->color_hex ?? '' }}"
                                                    {{ !$asignado ? 'disabled' : '' }}
                                                    @if($val && $persona) style="background-color: {{ $persona->color_hex }}; color: #fff; border-color: {{ $persona->color_hex }};" @endif>
                                            @else
                                                <span class="{{ $val ? 'font-medium text-white' : '' }}">{{ $val ?: '' }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-1 py-1 text-center font-bold row-total">{{ $rowTotal > 0 ? $rowTotal : '' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let debounceTimers = {};

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

    // Apply shift
    document.querySelectorAll('.btn-shift').forEach(btn => {
        btn.addEventListener('click', function() {
            const proyectoId = this.dataset.proyecto;
            const dias = document.getElementById('shift-dias-' + proyectoId).value;
            if (!dias || dias == 0) return alert('Ingresa dias habiles a recorrer');
            if (!confirm('Recorrer todas las fechas del proyecto ' + dias + ' dias habiles. Continuar?')) return;

            this.disabled = true;
            this.textContent = 'Aplicando...';

            fetch('/tiempos/recorrer/' + proyectoId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ dias_habiles: parseInt(dias) })
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
            .catch(() => {
                alert('Error al recorrer fechas');
                this.disabled = false;
                this.textContent = 'Aplicar';
            });
        });
    });

    // Revert shift
    document.querySelectorAll('.btn-revert').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Revertir este recorrido de fechas?')) return;

            fetch('/tiempos/revertir/' + this.dataset.shift, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    alert('Recorrido revertido');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(() => alert('Error al revertir'));
        });
    });

    // Personal select change
    document.querySelectorAll('.personal-select').forEach(select => {
        select.addEventListener('change', function() {
            const row = this.closest('tr');
            const personalId = this.value;
            const selectedOption = this.options[this.selectedIndex];
            const color = selectedOption && personalId ? selectedOption.style.color : '';

            row.querySelectorAll('.time-input').forEach(input => {
                input.dataset.personal = personalId;
                input.dataset.color = color;
                if (personalId) {
                    input.disabled = false;
                } else {
                    input.disabled = true;
                    input.value = '';
                    input.style.backgroundColor = '';
                    input.style.color = '';
                    input.style.borderColor = '';
                    input.closest('td').style.backgroundColor = '';
                }
            });
        });
    });

    // Time inputs
    document.querySelectorAll('.time-input').forEach(input => {
        input.addEventListener('change', function() {
            const el = this;
            if (!el.dataset.personal) return;
            const key = el.dataset.mueble + '_' + el.dataset.proceso + '_' + el.dataset.personal + '_' + el.dataset.fecha;
            clearTimeout(debounceTimers[key]);
            debounceTimers[key] = setTimeout(() => saveCell(el), 300);
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.dispatchEvent(new Event('change'));
                const allInputs = Array.from(document.querySelectorAll('.time-input:not([disabled])'));
                const idx = allInputs.indexOf(this);
                if (idx < allInputs.length - 1) allInputs[idx + 1].focus();
            }
            // Arrow key navigation
            if (e.key === 'Tab' && !e.shiftKey) {
                const allInputs = Array.from(document.querySelectorAll('.time-input:not([disabled])'));
                const idx = allInputs.indexOf(this);
                if (idx < allInputs.length - 1) {
                    e.preventDefault();
                    allInputs[idx + 1].focus();
                }
            }
        });
    });

    function saveCell(el) {
        el.classList.add('saving');
        el.classList.remove('saved', 'error');

        fetch('{{ route("tiempos.guardar") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                mueble_id: el.dataset.mueble,
                proceso: el.dataset.proceso,
                personal_id: el.dataset.personal,
                fecha: el.dataset.fecha,
                horas: parseFloat(el.value) || 0
            })
        })
        .then(r => {
            if (!r.ok) throw new Error('Error');
            return r.json();
        })
        .then(data => {
            el.classList.remove('saving');
            el.classList.add('saved');
            updateCellColor(el);
            updateRowTotal(el);
            setTimeout(() => el.classList.remove('saved'), 1500);
        })
        .catch(err => {
            el.classList.remove('saving');
            el.classList.add('error');
        });
    }

    function updateCellColor(el) {
        const val = parseFloat(el.value) || 0;
        const color = el.dataset.color;
        const td = el.closest('td');

        if (val > 0 && color) {
            el.style.backgroundColor = color;
            el.style.color = '#fff';
            el.style.borderColor = color;
            el.classList.add('font-bold');
            td.style.backgroundColor = color;
        } else {
            el.style.backgroundColor = '';
            el.style.color = '';
            el.style.borderColor = '';
            el.classList.remove('font-bold');
            td.style.backgroundColor = '';
        }
    }

    function updateRowTotal(el) {
        const row = el.closest('tr');
        const inputs = row.querySelectorAll('.time-input');
        let total = 0;
        inputs.forEach(i => { total += parseFloat(i.value) || 0; });
        const totalCell = row.querySelector('.row-total');
        if (totalCell) totalCell.textContent = total > 0 ? total : '';
    }
});
</script>
@endpush
