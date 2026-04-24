<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Mensual - {{ $nombreMes }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none; }
        }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    </style>
</head>
<body class="bg-gray-50 p-6 text-gray-800">

<div class="max-w-6xl mx-auto">

    <div class="flex items-center justify-between mb-4 no-print">
        <h1 class="text-2xl font-bold">Reporte Mensual por Departamento</h1>
        <button onclick="window.print()" class="bg-gray-700 text-white px-3 py-1.5 rounded text-sm hover:bg-gray-800">
            Imprimir / Guardar PDF
        </button>
    </div>

    <div class="hidden print:block mb-2">
        <h1 class="text-2xl font-bold">Reporte Mensual por Departamento</h1>
    </div>

    <p class="text-sm text-gray-500 mb-4">{{ $nombreMes }}</p>

    {{-- Tabla resumen --}}
    @php
        $proyectosResumen = [];
        $sumCT = 0;
        foreach($departamentos as $depto) {
            $sumCT += $data[$depto]['totalCosto'];
            foreach($data[$depto]['proyectos'] as $key => $proy) {
                if (!isset($proyectosResumen[$key])) {
                    $proyectosResumen[$key] = [
                        'nombre' => $proy['nombre'],
                        'abreviacion' => $proy['abreviacion'],
                        'jornales' => 0,
                        'costo' => 0,
                        'personal' => [],
                        'prod_avanzada' => 0,
                    ];
                }
                $proyectosResumen[$key]['jornales'] += $proy['jornales'];
                $proyectosResumen[$key]['costo'] += $proy['costo'];
                $proyectosResumen[$key]['personal'] += $proy['personal'];
                $proyectosResumen[$key]['prod_avanzada'] += ($proy['prod_avanzada'] ?? 0);
            }
        }
        $tJor = 0; $tCosto = 0; $tProd = 0;
    @endphp

    <div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Proyecto</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-500">Jornales</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-500">Costo Nómina</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-500">Personas</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-500">Prod. Avanzada</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-500">Factor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($proyectosResumen as $proy)
                    @php
                        $tJor += $proy['jornales'];
                        $tCosto += $proy['costo'];
                        $tProd += $proy['prod_avanzada'];
                        $factor = ($proy['prod_avanzada'] > 0 && $proy['costo'] > 0) ? $proy['costo'] / $proy['prod_avanzada'] : null;
                    @endphp
                    <tr>
                        <td class="px-4 py-2 font-semibold">
                            {{ $proy['nombre'] }}
                            @if($proy['abreviacion'])
                                <span class="text-xs text-gray-400 ml-1">({{ $proy['abreviacion'] }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right font-mono">{{ $proy['jornales'] }}</td>
                        <td class="px-4 py-2 text-right font-mono">${{ number_format($proy['costo'], 2) }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ count($proy['personal']) }}</td>
                        <td class="px-4 py-2 text-right font-mono {{ $proy['prod_avanzada'] > 0 ? 'text-blue-600 font-semibold' : 'text-gray-300' }}">
                            {{ $proy['prod_avanzada'] > 0 ? '$' . number_format($proy['prod_avanzada'], 2) : '-' }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            @if($factor !== null)
                                <span class="text-xs font-semibold px-1.5 py-0.5 rounded {{ $factor <= 0.25 ? 'bg-green-100 text-green-700' : ($factor <= 0.40 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                    ×{{ number_format($factor, 2) }}
                                </span>
                            @else
                                <span class="text-gray-300">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-3 text-center text-gray-400 text-sm">Sin proyectos este mes.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50">
                @php $factorTotal = ($tProd > 0 && $tCosto > 0) ? $tCosto / $tProd : null; @endphp
                <tr class="font-semibold">
                    <td class="px-4 py-2 text-gray-700">Total</td>
                    <td class="px-4 py-2 text-right font-mono">{{ $tJor }}</td>
                    <td class="px-4 py-2 text-right font-mono">${{ number_format($tCosto, 2) }}</td>
                    <td class="px-4 py-2 text-right font-mono"></td>
                    <td class="px-4 py-2 text-right font-mono text-blue-600">${{ number_format($tProd, 2) }}</td>
                    <td class="px-4 py-2 text-right">
                        @if($factorTotal !== null)
                            <span class="text-xs font-semibold px-1.5 py-0.5 rounded {{ $factorTotal <= 0.25 ? 'bg-green-100 text-green-700' : ($factorTotal <= 0.40 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                ×{{ number_format($factorTotal, 2) }}
                            </span>
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Totales --}}
    <div class="bg-white rounded-lg shadow px-4 py-3 mb-6 flex flex-wrap items-center gap-6">
        <div class="flex items-center gap-2">
            <span class="text-sm font-semibold text-blue-600">Mueble Producido:</span>
            <span class="text-lg font-mono font-bold text-blue-600">${{ number_format($totalProdAvanzada, 2) }}</span>
            <span class="text-xs text-gray-400">({{ $mueblesConAvance }} muebles con avance)</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-sm font-semibold text-gray-700">Costo Nómina Total:</span>
            <span class="text-lg font-mono font-bold text-gray-700">${{ number_format($sumCT, 2) }}</span>
        </div>
    </div>

    {{-- Desglose por departamento --}}
    @foreach($departamentos as $depto)
        @php $info = $data[$depto]; @endphp
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-3">
                <h2 class="text-lg font-bold {{ $depto === 'Carpintería' ? 'text-amber-600' : 'text-emerald-600' }}">{{ $depto }}</h2>
                <span class="text-xs text-gray-400">
                    {{ $info['totalJornales'] }} jornales &middot; ${{ number_format($info['totalCosto'], 2) }}
                </span>
            </div>

            @if(empty($info['proyectos']) && empty($info['categorias']))
                <p class="text-gray-400 text-sm">No hay datos para este departamento.</p>
            @else
                @foreach($info['proyectos'] as $proy)
                    <div class="bg-white rounded-lg shadow mb-3 overflow-hidden">
                        <div class="px-4 py-2 bg-gray-50 border-b flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-sm text-blue-600">{{ $proy['nombre'] }}</span>
                                @if($proy['abreviacion'])
                                    <span class="text-xs text-gray-400">({{ $proy['abreviacion'] }})</span>
                                @endif
                                @if(($proy['prod_avanzada'] ?? 0) > 0 && $proy['costo'] > 0)
                                    @php $factor = $proy['costo'] / $proy['prod_avanzada']; @endphp
                                    <span class="text-xs font-semibold px-1.5 py-0.5 rounded {{ $factor <= 0.25 ? 'bg-green-100 text-green-700' : ($factor <= 0.40 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                        ×{{ number_format($factor, 2) }}
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 flex items-center gap-3">
                                <span>{{ $proy['jornales'] }} jornales &middot;
                                ${{ number_format($proy['costo'], 2) }} &middot;
                                {{ count($proy['personal']) }} personas</span>
                                @if(($proy['prod_avanzada'] ?? 0) > 0)
                                    <span class="font-semibold text-blue-700">Prod: ${{ number_format($proy['prod_avanzada'], 2) }}</span>
                                @endif
                            </div>
                        </div>

                        @if(!empty($proy['muebles']))
                            <table class="min-w-full text-xs">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-1.5 text-left font-medium text-gray-500 w-28">Mueble</th>
                                        <th class="px-3 py-1.5 text-left font-medium text-gray-500">Descripción</th>
                                        <th class="px-3 py-1.5 text-right font-medium text-gray-500 w-20">Jornales</th>
                                        <th class="px-3 py-1.5 text-right font-medium text-gray-500 w-24">Costo Nómina</th>
                                        <th class="px-3 py-1.5 text-right font-medium text-gray-500 w-24">Valor Mueble</th>
                                        <th class="px-3 py-1.5 text-right font-medium {{ $depto === 'Carpintería' ? 'text-amber-500' : 'text-emerald-500' }} w-20">% Avance</th>
                                        <th class="px-3 py-1.5 text-right font-medium text-gray-500 w-28">Prod. Avanzada</th>
                                        <th class="px-3 py-1.5 text-left font-medium text-gray-500">Personal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($proy['muebles'] as $mueble)
                                        @php
                                            $campoAvance = $depto === 'Carpintería' ? 'avance_carpinteria' : 'avance_barniz';
                                            $campoPrev = $depto === 'Carpintería' ? 'prev_carpinteria' : 'prev_barniz';
                                            $avance = $mueble[$campoAvance];
                                            $prev = $mueble[$campoPrev] ?? 0;
                                            $costoMueble = $mueble['costo_mueble'];
                                            $delta = (float)($avance ?? 0) - (float)$prev;
                                            $prodAvanzada = ($delta > 0 && $costoMueble > 0) ? $costoMueble * $delta / 100 : null;
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-1.5 font-mono text-gray-800">{{ $mueble['numero'] }}</td>
                                            <td class="px-3 py-1.5 text-gray-600">{{ $mueble['descripcion'] }}</td>
                                            <td class="px-3 py-1.5 text-right font-mono">{{ $mueble['jornales'] }}</td>
                                            <td class="px-3 py-1.5 text-right font-mono">${{ number_format($mueble['costo'], 2) }}</td>
                                            <td class="px-3 py-1.5 text-right font-mono">{{ $costoMueble > 0 ? '$' . number_format($costoMueble, 2) : '-' }}</td>
                                            <td class="px-3 py-1.5 text-right">
                                                <span class="font-mono {{ $avance !== null ? ($depto === 'Carpintería' ? 'text-amber-600' : 'text-emerald-600') : 'text-gray-300' }}">
                                                    {{ $avance !== null ? number_format($avance, 1) . '%' : '-' }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-1.5 text-right font-mono {{ $prodAvanzada !== null ? 'text-blue-600' : 'text-gray-300' }}">
                                                {{ $prodAvanzada !== null ? '$' . number_format($prodAvanzada, 2) : '-' }}
                                            </td>
                                            <td class="px-3 py-1.5 text-gray-500">
                                                {{ implode(', ', array_map(fn($n) => explode(' ', $n)[0], $mueble['personal'])) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        @php
                            $sinMueble = $proy['jornales'] - collect($proy['muebles'])->sum('jornales');
                        @endphp
                        @if($sinMueble > 0)
                            <div class="px-3 py-1.5 text-xs text-gray-400 border-t">
                                {{ $sinMueble }} jornales sin mueble asignado
                            </div>
                        @endif
                    </div>
                @endforeach

                @if(!empty($info['categorias']))
                    <div class="bg-white rounded-lg shadow mb-3 overflow-hidden">
                        <div class="px-4 py-2 bg-gray-50 border-b">
                            <span class="font-semibold text-sm text-gray-600">Ausencias / Otros</span>
                        </div>
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-1.5 text-left font-medium text-gray-500">Categoría</th>
                                    <th class="px-3 py-1.5 text-right font-medium text-gray-500 w-20">Jornales</th>
                                    <th class="px-3 py-1.5 text-left font-medium text-gray-500">Personal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($info['categorias'] as $catNombre => $cat)
                                    <tr>
                                        <td class="px-3 py-1.5 text-gray-800">{{ $catNombre }}</td>
                                        <td class="px-3 py-1.5 text-right font-mono">{{ $cat['jornales'] }}</td>
                                        <td class="px-3 py-1.5 text-gray-500">
                                            {{ implode(', ', array_map(fn($n) => explode(' ', $n)[0], $cat['personal'])) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    @endforeach

</div>

</body>
</html>
