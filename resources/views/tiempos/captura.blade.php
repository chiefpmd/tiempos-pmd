@extends('layouts.app')
@section('title', 'Captura - ' . $proyecto->nombre)

@push('styles')
<style>
    .proc-carp { border-left: 3px solid #f59e0b; }
    .proc-barn { border-left: 3px solid #10b981; }
    .proc-inst { border-left: 3px solid #3b82f6; }
    .mueble-header { background-color: #f9fafb; border-top: 2px solid #9ca3af; }
    .rango-input {
        padding: 2px 6px; font-size: 13px; border: 1px solid #e5e7eb; border-radius: 4px;
    }
    .rango-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.2); }
    .btn-guardar {
        padding: 2px 10px; font-size: 12px; border-radius: 4px;
        background: #3b82f6; color: #fff; border: none; cursor: pointer;
    }
    .btn-guardar:hover { background: #2563eb; }
    .btn-guardar.saving { background: #f59e0b; }
    .btn-guardar.saved { background: #10b981; }
    .btn-guardar.error { background: #ef4444; }
    .btn-borrar {
        padding: 2px 8px; font-size: 11px; border-radius: 4px;
        background: #fee2e2; color: #ef4444; border: none; cursor: pointer;
    }
    .btn-borrar:hover { background: #fca5a5; }
    .personas-input { width: 50px; }
    .fecha-input { width: 130px; }
</style>
@endpush

@section('content')
<div class="max-w-full mx-auto">
    <div class="flex justify-between items-center mb-3">
        <div>
            <h1 class="text-xl font-bold">{{ $proyecto->nombre }}</h1>
            <p class="text-sm text-gray-500">{{ $proyecto->cliente }} | Inicio: {{ $proyecto->fecha_inicio->format('d/M/Y') }} | {{ $proyecto->semanas }} semanas</p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="{{ route('export.proyecto', $proyecto) }}" class="bg-green-600 text-white px-3 py-1.5 rounded text-sm hover:bg-green-700">Exportar Excel</a>
            <a href="{{ route('general') }}" class="bg-gray-300 text-gray-700 px-3 py-1.5 rounded text-sm hover:bg-gray-400">Vista General</a>
        </div>
    </div>

    @if(auth()->user()->isAdmin())
    <form method="POST" action="{{ route('muebles.store', $proyecto) }}" class="flex items-center space-x-2 mb-3 bg-white p-2 rounded shadow-sm">
        @csrf
        <input type="text" name="numero" placeholder="Num (ej: CAR-01)" required class="border rounded px-2 py-1 text-sm w-32">
        <input type="text" name="descripcion" placeholder="Descripcion" required class="border rounded px-2 py-1 text-sm flex-1">
        <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded text-sm">+ Mueble</button>
    </form>
    @endif

    @php
        $personalByEquipo = [
            'Carpintería' => $personal->where('equipo', 'Carpintería'),
            'Barniz' => $personal->where('equipo', 'Barniz'),
            'Instalación' => $personal->where('equipo', 'Instalación'),
        ];
        $isAdmin = auth()->user()->isAdmin();
    @endphp

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 sticky top-0 z-10">
                <tr>
                    <th class="px-2 py-2 text-left font-medium text-gray-500 w-24">Mueble</th>
                    <th class="px-2 py-2 text-left font-medium text-gray-500 w-40">Descripcion</th>
                    <th class="px-2 py-2 text-left font-medium text-gray-500 w-24">Proceso</th>
                    <th class="px-2 py-2 text-left font-medium text-gray-500 w-32">Equipo</th>
                    <th class="px-2 py-2 text-center font-medium text-gray-500 w-32">Fecha Inicio</th>
                    <th class="px-2 py-2 text-center font-medium text-gray-500 w-32">Fecha Fin</th>
                    <th class="px-2 py-2 text-center font-medium text-gray-500 w-16">Personas</th>
                    <th class="px-2 py-2 text-center font-medium text-gray-500 w-16">Dias</th>
                    @if($isAdmin)
                        <th class="px-2 py-2 text-center font-medium text-gray-500 w-24">Acciones</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($muebles as $mi => $mueble)
                    @foreach($procesos as $pi => $proceso)
                        @php
                            $procClass = match($proceso) { 'Carpintería' => 'proc-carp', 'Barniz' => 'proc-barn', 'Instalación' => 'proc-inst', default => '' };
                            $key = "{$mueble->id}_{$proceso}";
                            $rango = $rangos[$key] ?? null;
                            $fechaInicio = $rango ? Carbon\Carbon::parse($rango->fecha_inicio)->format('Y-m-d') : '';
                            $fechaFin = $rango ? Carbon\Carbon::parse($rango->fecha_fin)->format('Y-m-d') : '';
                            $personalId = $rango->personal_id ?? '';
                            $personas = $rango ? (float)$rango->personas : '';
                            $persona = $personalId ? $personal->firstWhere('id', $personalId) : null;

                            // Count weekdays in range
                            $diasCount = 0;
                            if ($rango) {
                                $p = Carbon\CarbonPeriod::create($fechaInicio, $fechaFin);
                                foreach ($p as $d) { if ($d->isWeekday()) $diasCount++; }
                            }
                        @endphp
                        <tr class="{{ $procClass }} {{ $pi === 0 ? 'mueble-header' : 'border-b border-gray-100' }} hover:bg-gray-50"
                            data-mueble="{{ $mueble->id }}" data-proceso="{{ $proceso }}">
                            <td class="px-2 py-1 font-medium whitespace-nowrap">
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
                            <td class="px-2 py-1 text-gray-600">@if($pi === 0) {{ $mueble->descripcion }} @endif</td>
                            <td class="px-2 py-1 text-gray-500 font-medium">{{ $proceso }}</td>
                            <td class="px-2 py-1 whitespace-nowrap">
                                @if($isAdmin)
                                    <select class="personal-select text-xs border rounded px-1 py-0.5 w-full"
                                        data-mueble="{{ $mueble->id }}" data-proceso="{{ $proceso }}">
                                        <option value="">-- Asignar --</option>
                                        @foreach($personalByEquipo[$proceso] ?? [] as $p)
                                            <option value="{{ $p->id }}" {{ $personalId == $p->id ? 'selected' : '' }}
                                                style="color: {{ $p->color_hex }}">{{ $p->nombre }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    @if($persona)
                                        <span class="inline-block w-3 h-3 rounded-full mr-1 align-middle" style="background-color: {{ $persona->color_hex }}"></span>
                                        {{ $persona->nombre }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                @endif
                            </td>
                            <td class="px-1 py-1 text-center">
                                @if($isAdmin)
                                    <input type="date" class="rango-input fecha-input fecha-inicio" value="{{ $fechaInicio }}">
                                @else
                                    {{ $fechaInicio ? Carbon\Carbon::parse($fechaInicio)->format('d/M/Y') : '-' }}
                                @endif
                            </td>
                            <td class="px-1 py-1 text-center">
                                @if($isAdmin)
                                    <input type="date" class="rango-input fecha-input fecha-fin" value="{{ $fechaFin }}">
                                @else
                                    {{ $fechaFin ? Carbon\Carbon::parse($fechaFin)->format('d/M/Y') : '-' }}
                                @endif
                            </td>
                            <td class="px-1 py-1 text-center">
                                @if($isAdmin)
                                    <input type="number" step="0.5" min="0.5" max="24" class="rango-input personas-input personas-val" value="{{ $personas }}">
                                @else
                                    {{ $personas ?: '-' }}
                                @endif
                            </td>
                            <td class="px-1 py-1 text-center font-medium dias-count">{{ $diasCount ?: '' }}</td>
                            @if($isAdmin)
                                <td class="px-1 py-1 text-center whitespace-nowrap">
                                    <button class="btn-guardar btn-save-rango">Guardar</button>
                                    @if($rango)
                                        <button class="btn-borrar btn-delete-rango ml-1">Borrar</button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    // Calculate weekdays between two dates
    function countWeekdays(start, end) {
        if (!start || !end) return 0;
        let count = 0;
        let d = new Date(start + 'T00:00:00');
        const e = new Date(end + 'T00:00:00');
        while (d <= e) {
            const day = d.getDay();
            if (day !== 0 && day !== 6) count++;
            d.setDate(d.getDate() + 1);
        }
        return count;
    }

    // Update dias count when dates change
    document.querySelectorAll('.fecha-inicio, .fecha-fin').forEach(input => {
        input.addEventListener('change', function() {
            const row = this.closest('tr');
            const inicio = row.querySelector('.fecha-inicio').value;
            const fin = row.querySelector('.fecha-fin').value;
            const diasCell = row.querySelector('.dias-count');
            const days = countWeekdays(inicio, fin);
            diasCell.textContent = days > 0 ? days : '';
        });
    });

    // Save range
    document.querySelectorAll('.btn-save-rango').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const muebleId = row.dataset.mueble;
            const proceso = row.dataset.proceso;
            const personalSelect = row.querySelector('.personal-select');
            const personalId = personalSelect ? personalSelect.value : '';
            const fechaInicio = row.querySelector('.fecha-inicio').value;
            const fechaFin = row.querySelector('.fecha-fin').value;
            const personas = row.querySelector('.personas-val').value;

            if (!personalId) { alert('Selecciona un equipo'); return; }
            if (!fechaInicio || !fechaFin) { alert('Ingresa fecha inicio y fin'); return; }
            if (!personas) { alert('Ingresa personas por dia'); return; }
            if (fechaFin < fechaInicio) { alert('Fecha fin debe ser igual o posterior a fecha inicio'); return; }

            const el = this;
            el.classList.add('saving');
            el.textContent = '...';

            fetch('{{ route("tiempos.guardarRango") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    mueble_id: muebleId,
                    proceso: proceso,
                    personal_id: personalId,
                    fecha_inicio: fechaInicio,
                    fecha_fin: fechaFin,
                    personas: parseFloat(personas)
                })
            })
            .then(r => {
                if (!r.ok) throw new Error('Error');
                return r.json();
            })
            .then(data => {
                el.classList.remove('saving');
                el.classList.add('saved');
                el.textContent = 'OK ' + data.dias_creados + 'd';
                // Add delete button if not present
                if (!row.querySelector('.btn-delete-rango')) {
                    const delBtn = document.createElement('button');
                    delBtn.className = 'btn-borrar btn-delete-rango ml-1';
                    delBtn.textContent = 'Borrar';
                    el.parentNode.appendChild(delBtn);
                    attachDeleteHandler(delBtn);
                }
                setTimeout(() => { el.textContent = 'Guardar'; el.classList.remove('saved'); }, 2000);
            })
            .catch(err => {
                el.classList.remove('saving');
                el.classList.add('error');
                el.textContent = 'Error';
                setTimeout(() => { el.textContent = 'Guardar'; el.classList.remove('error'); }, 2000);
            });
        });
    });

    // Delete range
    function attachDeleteHandler(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Borrar todos los registros de este proceso?')) return;
            const row = this.closest('tr');
            const el = this;

            fetch('{{ route("tiempos.borrarRango") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    mueble_id: row.dataset.mueble,
                    proceso: row.dataset.proceso
                })
            })
            .then(r => {
                if (!r.ok) throw new Error('Error');
                return r.json();
            })
            .then(() => {
                row.querySelector('.fecha-inicio').value = '';
                row.querySelector('.fecha-fin').value = '';
                row.querySelector('.personas-val').value = '';
                row.querySelector('.dias-count').textContent = '';
                const select = row.querySelector('.personal-select');
                if (select) select.value = '';
                el.remove();
            })
            .catch(() => { alert('Error al borrar'); });
        });
    }

    document.querySelectorAll('.btn-delete-rango').forEach(attachDeleteHandler);
});
</script>
@endpush
