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
                @foreach(['Carpintería', 'Barniz', 'Instalación'] as $eq)
                    <option value="{{ $eq }}" {{ old('equipo', $persona->equipo) === $eq ? 'selected' : '' }}>{{ $eq }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
            <input type="color" name="color_hex" value="{{ old('color_hex', $persona->color_hex ?? '#95A5A6') }}"
                class="h-10 w-20 border border-gray-300 rounded cursor-pointer">
        </div>

        <div class="flex items-center">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" value="1" id="activo" {{ old('activo', $persona->activo ?? true) ? 'checked' : '' }}
                class="mr-2">
            <label for="activo" class="text-sm text-gray-700">Activo</label>
        </div>

        <div class="flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">Guardar</button>
            <a href="{{ route('personal.index') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded text-sm hover:bg-gray-400">Cancelar</a>
        </div>
    </form>
</div>
@endsection
