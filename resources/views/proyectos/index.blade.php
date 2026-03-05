@extends('layouts.app')
@section('title', 'Proyectos')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-xl font-bold">Proyectos</h1>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('proyectos.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">+ Nuevo Proyecto</a>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Inicio</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Semanas</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Muebles</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($proyectos as $p)
                <tr>
                    <td class="px-4 py-3 text-sm font-medium">{{ $p->nombre }}</td>
                    <td class="px-4 py-3 text-sm">{{ $p->cliente }}</td>
                    <td class="px-4 py-3 text-sm">{{ $p->fecha_inicio->format('d/M/Y') }}</td>
                    <td class="px-4 py-3 text-sm">{{ $p->semanas }}</td>
                    <td class="px-4 py-3 text-sm">{{ $p->muebles_count }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="px-2 py-1 rounded-full text-xs font-medium
                            {{ $p->status === 'activo' ? 'bg-green-100 text-green-800' : ($p->status === 'pausado' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                            {{ ucfirst($p->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm space-x-2">
                        <a href="{{ route('captura', $p) }}" class="text-blue-600 hover:underline">Captura</a>
                        <a href="{{ route('export.proyecto', $p) }}" class="text-green-600 hover:underline">Excel</a>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('proyectos.edit', $p) }}" class="text-yellow-600 hover:underline">Editar</a>
                            <form method="POST" action="{{ route('proyectos.destroy', $p) }}" class="inline" onsubmit="return confirm('¿Eliminar proyecto?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Eliminar</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
