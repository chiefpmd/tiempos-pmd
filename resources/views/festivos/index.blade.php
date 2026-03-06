@extends('layouts.app')
@section('title', 'Días Festivos')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-xl font-bold mb-4">Días Festivos</h1>

    <form method="POST" action="{{ route('festivos.store') }}" class="bg-white rounded-lg shadow p-4 mb-4">
        @csrf
        <div class="flex items-end space-x-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                <input type="date" name="fecha" required class="border rounded px-3 py-2 text-sm">
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                <input type="text" name="nombre" placeholder="Ej: Día de la Independencia" required class="border rounded px-3 py-2 text-sm w-full">
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
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Fecha</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Nombre</th>
                    <th class="px-4 py-2 w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($festivos as $f)
                <tr>
                    <td class="px-4 py-2">{{ $f->fecha->format('d/m/Y') }} ({{ $f->fecha->locale('es')->isoFormat('dddd') }})</td>
                    <td class="px-4 py-2">{{ $f->nombre }}</td>
                    <td class="px-4 py-2">
                        <form method="POST" action="{{ route('festivos.destroy', $f) }}" onsubmit="return confirm('Eliminar?')">
                            @csrf @method('DELETE')
                            <button class="text-red-500 hover:text-red-700">&times;</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-4 text-center text-gray-400">No hay días festivos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
