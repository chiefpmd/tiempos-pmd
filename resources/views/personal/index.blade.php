@extends('layouts.app')
@section('title', 'Personal')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-xl font-bold">Personal</h1>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('personal.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">+ Nuevo</a>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Color</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Equipo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    @if(auth()->user()->isAdmin())
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($personal as $p)
                <tr>
                    <td class="px-4 py-3"><span class="inline-block w-6 h-6 rounded" style="background-color: {{ $p->color_hex }}"></span></td>
                    <td class="px-4 py-3 text-sm font-medium">{{ $p->nombre }}</td>
                    <td class="px-4 py-3 text-sm">{{ $p->equipo }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="px-2 py-1 rounded-full text-xs {{ $p->activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $p->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    @if(auth()->user()->isAdmin())
                    <td class="px-4 py-3 text-sm space-x-2">
                        <a href="{{ route('personal.edit', $p) }}" class="text-yellow-600 hover:underline">Editar</a>
                        <form method="POST" action="{{ route('personal.destroy', $p) }}" class="inline" onsubmit="return confirm('¿Eliminar?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
