@extends('layouts.app')
@section('title', $persona->exists ? 'Editar Personal' : 'Nuevo Personal')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-xl font-bold mb-4">{{ $persona->exists ? 'Editar Personal' : 'Nuevo Personal' }}</h1>

    <form method="POST" action="{{ $persona->exists ? route('personal.update', $persona) : route('personal.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4">
        @csrf
        @if($persona->exists) @method('PUT') @endif

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre', $persona->nombre) }}" required
                class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Equipo</label>
            <select name="equipo" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                @foreach(['Carpintería', 'Barniz', 'Instalación', 'Vidrio', 'Eléctrico', 'Mantenimiento', 'Herrero', 'Armado', 'Tapicero'] as $eq)
                    <option value="{{ $eq }}" {{ old('equipo', $persona->equipo) === $eq ? 'selected' : '' }}>{{ $eq }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
            <input type="color" name="color_hex" value="{{ old('color_hex', $persona->color_hex ?? '#95A5A6') }}"
                class="h-10 w-20 border border-gray-300 rounded cursor-pointer">
        </div>

        <div class="flex items-center gap-6">
            <div class="flex items-center">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" value="1" id="activo" {{ old('activo', $persona->activo ?? true) ? 'checked' : '' }}
                    class="mr-2">
                <label for="activo" class="text-sm text-gray-700">Activo</label>
            </div>
            <div class="flex items-center">
                <input type="hidden" name="es_lider" value="0">
                <input type="checkbox" name="es_lider" value="1" id="es_lider" {{ old('es_lider', $persona->es_lider ?? false) ? 'checked' : '' }}
                    class="mr-2" onchange="document.getElementById('lider-select').style.display = this.checked ? 'none' : 'block'">
                <label for="es_lider" class="text-sm text-gray-700">Es líder de equipo</label>
            </div>
        </div>

        <div id="lider-select" style="{{ old('es_lider', $persona->es_lider ?? false) ? 'display:none' : '' }}">
            <label class="block text-sm font-medium text-gray-700 mb-1">Líder / Equipo por defecto</label>
            <select name="lider_id" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                <option value="">-- Sin líder --</option>
                @foreach(\App\Models\Personal::where('es_lider', true)->where('activo', true)->orderBy('nombre')->get() as $lider)
                    <option value="{{ $lider->id }}" {{ old('lider_id', $persona->lider_id) == $lider->id ? 'selected' : '' }}>
                        {{ $lider->nombre }} ({{ $lider->equipo }})
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">El líder por defecto se usa como sugerencia en Equipos del Día.</p>
        </div>

        {{-- Datos de Nómina --}}
        <div class="border-t pt-4 mt-2">
            <h3 class="font-semibold text-gray-700 mb-3">Datos de Nómina</h3>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Clave Empleado</label>
                    <input type="text" name="clave_empleado" value="{{ old('clave_empleado', $persona->clave_empleado) }}"
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="Opcional">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nómina Bruta Semanal ($)</label>
                    <input type="number" step="0.01" name="nomina_bruta_semanal" value="{{ old('nomina_bruta_semanal', $persona->nomina_bruta_semanal) }}"
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Días por Semana</label>
                    <input type="number" name="dias_semana" value="{{ old('dias_semana', $persona->dias_semana ?? 5) }}" min="1" max="7"
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Factor Hora Extra</label>
                    <input type="number" step="0.01" name="factor_he" value="{{ old('factor_he', $persona->factor_he ?? 0.20) }}"
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                </div>
            </div>

            @if($persona->exists && $persona->salario_diario > 0)
            <div class="mt-3 text-sm text-gray-500">
                Salario Diario: <strong>${{ number_format($persona->salario_diario, 2) }}</strong> &middot;
                Costo HE/hora: <strong>${{ number_format($persona->salario_he, 2) }}</strong>
            </div>
            @endif
        </div>

        <div class="flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">Guardar</button>
            <a href="{{ route('personal.index') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded text-sm hover:bg-gray-400">Cancelar</a>
        </div>
    </form>
</div>
@endsection
