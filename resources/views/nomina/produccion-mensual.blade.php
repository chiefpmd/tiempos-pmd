@extends('layouts.app')
@section('title', 'Produccion por Trabajador - ' . $nombreMes)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-4">
    <h1 class="text-xl font-bold">Produccion por Trabajador - {{ $nombreMes }}</h1>

    <div class="flex items-center gap-3">
        <form method="GET" action="{{ route('nomina.produccionMensual') }}" class="flex items-center gap-2">
            <select name="mes" class="border rounded px-2 py-1 text-sm">
                @foreach([1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'] as $m => $nombre)
                    <option value="{{ $m }}" {{ $mes == $m ? 'selected' : '' }}>{{ $nombre }}</option>
                @endforeach
            </select>
            <input type="number" name="anio" value="{{ $anio }}" min="2020" max="2030"
                   class="border rounded px-2 py-1 text-sm w-20">
            <select name="personal_id" class="border rounded px-2 py-1 text-sm">
                <option value="0">Todos</option>
                @foreach($todosEmpleados as $emp)
                    <option value="{{ $emp->id }}" {{ $personalFiltro == $emp->id ? 'selected' : '' }}>{{ $emp->nombre }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700">Filtrar</button>
        </form>
        <button onclick="window.print()" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">Imprimir</button>
    </div>
</div>

@if(empty($porTrabajador))
    <p class="text-gray-400 text-sm">No hay registros de produccion para {{ $nombreMes }}.</p>
@else

{{-- Resumen general --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <p class="text-2xl font-bold text-blue-600">{{ count($porTrabajador) }}</p>
        <p class="text-xs text-gray-500">Trabajadores</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <p class="text-2xl font-bold text-green-600">{{ number_format($totalesGlobal['jornadas']) }}</p>
        <p class="text-xs text-gray-500">Total Jornadas</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
        <p class="text-2xl font-bold text-orange-600">${{ number_format($totalesGlobal['costo'], 0) }}</p>
        <p class="text-xs text-gray-500">Costo Total</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
        <p class="text-2xl font-bold text-purple-600">{{ count($porProyecto) }}</p>
        <p class="text-xs text-gray-500">Proyectos</p>
    </div>
</div>

{{-- Resumen por proyecto --}}
<div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
    <div class="px-4 py-3 border-b bg-gray-50">
        <h2 class="font-semibold text-gray-700">Resumen por Proyecto</h2>
    </div>
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left font-medium text-gray-500">Proyecto</th>
                <th class="px-4 py-2 text-right font-medium text-gray-500">Trabajadores</th>
                <th class="px-4 py-2 text-right font-medium text-gray-500">Muebles</th>
                <th class="px-4 py-2 text-right font-medium text-gray-500">Jornadas</th>
                <th class="px-4 py-2 text-right font-medium text-gray-500">Costo</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($porProyecto as $pNombre => $pData)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-semibold text-gray-800">{{ $pNombre }}</td>
                <td class="px-4 py-2 text-right">{{ count($pData['trabajadores']) }}</td>
                <td class="px-4 py-2 text-right">{{ count($pData['muebles']) }}</td>
                <td class="px-4 py-2 text-right font-mono">{{ $pData['jornadas'] }}</td>
                <td class="px-4 py-2 text-right font-mono">${{ number_format($pData['costo'], 2) }}</td>
            </tr>
            @endforeach
            <tr class="bg-gray-50 font-semibold">
                <td class="px-4 py-2">Total</td>
                <td class="px-4 py-2 text-right">{{ count($porTrabajador) }}</td>
                <td class="px-4 py-2 text-right"></td>
                <td class="px-4 py-2 text-right font-mono">{{ $totalesGlobal['jornadas'] }}</td>
                <td class="px-4 py-2 text-right font-mono">${{ number_format($totalesGlobal['costo'], 2) }}</td>
            </tr>
        </tbody>
    </table>
</div>

{{-- Detalle por trabajador --}}
@php $equipoActual = ''; @endphp
@foreach($porTrabajador as $pid => $data)
    @if($data['equipo'] !== $equipoActual)
        @php $equipoActual = $data['equipo']; @endphp
        <h2 class="text-base font-bold text-gray-600 mt-6 mb-2">{{ $equipoActual }}</h2>
    @endif

    <div class="bg-white rounded-lg shadow mb-4 overflow-hidden">
        <div class="px-4 py-3 bg-gray-800 text-white flex justify-between items-center">
            <span class="font-semibold">{{ $data['nombre'] }}</span>
            <div class="flex gap-4 text-sm">
                <span>{{ $data['total_jornadas'] }} jornadas</span>
                <span>${{ number_format($data['total_costo'], 2) }}</span>
            </div>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Proyecto</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Mueble</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Descripcion</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-500">Jornadas</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-500">Costo</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Dias</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($data['muebles'] as $mueble)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-1.5 text-gray-800">{{ $mueble['proyecto'] }}</td>
                    <td class="px-4 py-1.5 font-semibold">{{ $mueble['numero'] }}</td>
                    <td class="px-4 py-1.5 text-gray-600">{{ $mueble['descripcion'] }}</td>
                    <td class="px-4 py-1.5 text-right font-mono">{{ $mueble['jornadas'] }}</td>
                    <td class="px-4 py-1.5 text-right font-mono">${{ number_format($mueble['costo'], 2) }}</td>
                    <td class="px-4 py-1.5 text-xs text-gray-400">{{ implode(', ', array_unique($mueble['fechas'])) }}</td>
                </tr>
                @endforeach
                <tr class="bg-gray-50 font-semibold">
                    <td colspan="3" class="px-4 py-1.5 text-gray-700">Subtotal {{ $data['nombre'] }}</td>
                    <td class="px-4 py-1.5 text-right font-mono">{{ $data['total_jornadas'] }}</td>
                    <td class="px-4 py-1.5 text-right font-mono">${{ number_format($data['total_costo'], 2) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
@endforeach

@endif

@push('styles')
<style>
@media print {
    nav, form, button { display: none !important; }
    .shadow { box-shadow: none !important; }
    body { background: white !important; }
    .bg-gray-100 { background: white !important; }
}
</style>
@endpush
@endsection
