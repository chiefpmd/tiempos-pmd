@extends('layouts.app')
@section('title', 'Categorías de Nómina')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-xl font-bold mb-4">Categorías de Nómina</h1>

    <form method="POST" action="{{ route('nomina.categorias.store') }}" class="bg-white rounded-lg shadow p-4 mb-4">
        @csrf
        <div class="flex items-end space-x-3">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nueva categoría</label>
                <input type="text" name="nombre" required placeholder="Ej: Vacaciones"
                       class="border rounded px-3 py-2 text-sm w-full">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">Agregar</button>
        </div>
        @if($errors->any())
            <p class="text-red-500 text-xs mt-2">{{ $errors->first() }}</p>
        @endif
    </form>

    <div class="bg-white rounded-lg shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Nombre</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Estado</th>
                    <th class="px-4 py-2 w-32"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($categorias as $cat)
                <tr>
                    <td class="px-4 py-2">
                        <form method="POST" action="{{ route('nomina.categorias.update', $cat) }}" class="flex items-center gap-2" id="form-{{ $cat->id }}">
                            @csrf @method('PUT')
                            <input type="text" name="nombre" value="{{ $cat->nombre }}"
                                   class="border rounded px-2 py-1 text-sm w-48">
                            <input type="hidden" name="activa" value="0">
                            <label class="flex items-center gap-1 text-xs text-gray-500">
                                <input type="checkbox" name="activa" value="1" {{ $cat->activa ? 'checked' : '' }}>
                                Activa
                            </label>
                            <button type="submit" class="text-blue-500 hover:text-blue-700 text-xs">Guardar</button>
                        </form>
                    </td>
                    <td class="px-4 py-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $cat->activa ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $cat->activa ? 'Activa' : 'Inactiva' }}
                        </span>
                    </td>
                    <td class="px-4 py-2">
                        <form method="POST" action="{{ route('nomina.categorias.destroy', $cat) }}" onsubmit="return confirm('Eliminar?')">
                            @csrf @method('DELETE')
                            <button class="text-red-500 hover:text-red-700">&times;</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-4 text-center text-gray-400">No hay categorías registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
