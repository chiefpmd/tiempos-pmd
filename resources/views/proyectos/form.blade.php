@extends('layouts.app')
@section('title', $proyecto->exists ? 'Editar Proyecto' : 'Nuevo Proyecto')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-xl font-bold mb-4">{{ $proyecto->exists ? 'Editar Proyecto' : 'Nuevo Proyecto' }}</h1>

    <form method="POST" action="{{ $proyecto->exists ? route('proyectos.update', $proyecto) : route('proyectos.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4">
        @csrf
        @if($proyecto->exists) @method('PUT') @endif

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre', $proyecto->nombre) }}" required
                class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            @error('nombre') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
            <input type="text" name="cliente" value="{{ old('cliente', $proyecto->cliente) }}" required
                class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', $proyecto->fecha_inicio?->format('Y-m-d')) }}" required
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Semanas</label>
                <input type="number" name="semanas" value="{{ old('semanas', $proyecto->semanas ?? 12) }}" min="1" max="52" required
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                @foreach(['activo', 'completado', 'pausado'] as $s)
                    <option value="{{ $s }}" {{ old('status', $proyecto->status ?? 'activo') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">Guardar</button>
            <a href="{{ route('proyectos.index') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded text-sm hover:bg-gray-400">Cancelar</a>
        </div>
    </form>
</div>
@endsection
