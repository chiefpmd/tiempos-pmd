<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1f2937">
    <title>Nómina {{ $fecha->format('d/m/Y') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/icon-512.png">
    <link rel="apple-touch-icon" href="/icon-192.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Nómina">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { -webkit-tap-highlight-color: transparent; }
        select { font-size: 16px; }
        .safe-bottom { padding-bottom: env(safe-area-inset-bottom, 0); }
    </style>
</head>
<body class="bg-gray-100 min-h-screen pb-20">

@php
    $hoy = \Carbon\Carbon::today();
    $ayer = $fecha->copy()->subDay();
    $maniana = $fecha->copy()->addDay();
    $esHoy = $fecha->isSameDay($hoy);
    $diasNombre = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
    $capturados = $empleados->filter(fn($e) => isset($registros[$e->id]) && ($registros[$e->id]->proyecto_id || $registros[$e->id]->categoria_id))->count();
    $empleadosPorEquipo = $empleados->groupBy(fn($e) => $e->equipo ?: 'Sin equipo');
@endphp

<header class="bg-gray-800 text-white shadow-md sticky top-0 z-20">
    <div class="px-4 py-3">
        <div class="flex items-center justify-between gap-2">
            <a href="{{ route('nomina.movil', ['fecha' => $ayer->format('Y-m-d')]) }}"
               class="bg-gray-700 hover:bg-gray-600 rounded-full w-10 h-10 flex items-center justify-center text-xl">‹</a>
            <div class="flex-1 text-center">
                <div class="text-[11px] uppercase tracking-wider text-gray-400 leading-none">
                    {{ $diasNombre[$fecha->dayOfWeek] }}
                </div>
                <div class="text-lg font-bold leading-tight">
                    {{ $fecha->format('d/m/Y') }}
                    @if($esHoy)<span class="text-[10px] bg-green-600 text-white px-1.5 py-0.5 rounded ml-1 align-middle">HOY</span>@endif
                </div>
            </div>
            <a href="{{ route('nomina.movil', ['fecha' => $maniana->format('Y-m-d')]) }}"
               class="bg-gray-700 hover:bg-gray-600 rounded-full w-10 h-10 flex items-center justify-center text-xl">›</a>
        </div>
        <div class="flex items-center justify-between mt-2 text-xs">
            <span class="text-gray-300">{{ $capturados }}/{{ $empleados->count() }} capturados</span>
            @if(!$esHoy)
                <a href="{{ route('nomina.movil') }}" class="text-blue-300 hover:text-blue-200">Ir a hoy</a>
            @else
                <span class="text-gray-400">·</span>
            @endif
        </div>
    </div>
</header>

<main class="px-3 py-3 space-y-4">
    @foreach($empleadosPorEquipo as $equipo => $emps)
        <section>
            <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider px-1 mb-2">{{ $equipo }}</h2>
            <div class="space-y-2">
                @foreach($emps as $emp)
                    @php
                        $reg = $registros[$emp->id] ?? null;
                        $asignado = $reg && ($reg->proyecto_id || $reg->categoria_id);
                        $currentAsigVal = '';
                        if ($reg && $reg->proyecto_id) $currentAsigVal = 'proyecto_' . $reg->proyecto_id;
                        elseif ($reg && $reg->categoria_id) $currentAsigVal = 'categoria_' . $reg->categoria_id;
                    @endphp
                    <div class="bg-white rounded-lg shadow-sm border-l-4 {{ $asignado ? 'border-green-500' : 'border-gray-200' }} p-3"
                         data-personal-id="{{ $emp->id }}">
                        <div class="flex items-center justify-between mb-2">
                            <div class="font-semibold text-gray-800">
                                {{ $emp->nombre }}
                            </div>
                            <span class="text-xs status-badge {{ $asignado ? 'text-green-600' : 'text-gray-400' }}">
                                {{ $asignado ? '✓' : '○' }}
                            </span>
                        </div>
                        <div class="space-y-2">
                            <select class="asignacion-select w-full border border-gray-300 rounded-md px-3 py-2 bg-white"
                                    onchange="guardar(this)">
                                <option value="">— Sin asignar —</option>
                                <optgroup label="Proyectos">
                                    @foreach($proyectos as $p)
                                        <option value="proyecto_{{ $p->id }}" {{ $currentAsigVal === 'proyecto_' . $p->id ? 'selected' : '' }}>
                                            {{ $p->nombre }}
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="No productivo">
                                    @foreach($categorias as $cat)
                                        <option value="categoria_{{ $cat->id }}" {{ $currentAsigVal === 'categoria_' . $cat->id ? 'selected' : '' }}>
                                            {{ $cat->nombre }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            </select>

                            <select class="mueble-select w-full border border-gray-300 rounded-md px-3 py-2 bg-white {{ $reg && $reg->proyecto_id ? '' : 'hidden' }}"
                                    onchange="guardar(this)">
                                <option value="">— Sin mueble —</option>
                                @if($reg && $reg->proyecto_id && isset($mueblesPorProyecto[$reg->proyecto_id]))
                                    @foreach($mueblesPorProyecto[$reg->proyecto_id] as $m)
                                        <option value="{{ $m->id }}" {{ $reg->mueble_id == $m->id ? 'selected' : '' }}>
                                            {{ $m->numero }} — {{ $m->descripcion }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    @if($empleados->isEmpty())
        <p class="text-center text-gray-400 text-sm mt-10">No hay empleados activos.</p>
    @endif
</main>

<div id="toast" class="fixed bottom-4 left-1/2 -translate-x-1/2 bg-gray-900 text-white px-4 py-2 rounded-full text-sm shadow-lg opacity-0 transition-opacity pointer-events-none safe-bottom z-30">
    Guardado
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const fecha = @json($fecha->format('Y-m-d'));
const mueblesPorProyecto = @json($mueblesPorProyecto->map(fn($ms) => $ms->map(fn($m) => ['id' => $m->id, 'numero' => $m->numero, 'descripcion' => $m->descripcion])));

let toastTimer = null;
function toast(msg, error = false) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.classList.toggle('bg-red-600', error);
    el.classList.toggle('bg-gray-900', !error);
    el.style.opacity = '1';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.style.opacity = '0'; }, 1800);
}

function actualizarMuebles(card, proyectoId, keepVal = null) {
    const sel = card.querySelector('.mueble-select');
    sel.innerHTML = '<option value="">— Sin mueble —</option>';
    if (proyectoId && mueblesPorProyecto[proyectoId]) {
        mueblesPorProyecto[proyectoId].forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.numero + ' — ' + m.descripcion;
            if (keepVal && String(m.id) === String(keepVal)) opt.selected = true;
            sel.appendChild(opt);
        });
        sel.classList.remove('hidden');
    } else {
        sel.classList.add('hidden');
    }
}

async function guardar(el) {
    const card = el.closest('[data-personal-id]');
    const personalId = card.dataset.personalId;
    const asigSel = card.querySelector('.asignacion-select');
    const muebleSel = card.querySelector('.mueble-select');
    const statusBadge = card.querySelector('.status-badge');

    let asignacionTipo = null, asignacionId = null;
    if (asigSel.value) {
        const parts = asigSel.value.split('_');
        asignacionTipo = parts[0];
        asignacionId = parts.slice(1).join('_');
    }

    // Si cambió la asignación, refrescar muebles
    if (el === asigSel) {
        const proyectoId = (asignacionTipo === 'proyecto') ? asignacionId : null;
        actualizarMuebles(card, proyectoId);
    }

    const muebleId = muebleSel.value || null;

    try {
        const res = await fetch(@json(route('nomina.guardar')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                personal_id: personalId,
                fecha: fecha,
                asignacion_tipo: asignacionTipo,
                asignacion_id: asignacionId,
                mueble_id: muebleId,
                horas_extra: 0,
                proyecto_he_id: null,
            }),
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (!data.ok) throw new Error('Respuesta inválida');

        // Actualizar estado visual
        const asignado = !!(data.registro.proyecto_id || data.registro.categoria_id);
        card.classList.toggle('border-green-500', asignado);
        card.classList.toggle('border-gray-200', !asignado);
        statusBadge.textContent = asignado ? '✓' : '○';
        statusBadge.classList.toggle('text-green-600', asignado);
        statusBadge.classList.toggle('text-gray-400', !asignado);

        toast('Guardado');
        actualizarContador();
    } catch (e) {
        toast('Error al guardar', true);
        console.error(e);
    }
}

function actualizarContador() {
    const total = document.querySelectorAll('[data-personal-id]').length;
    const asig = document.querySelectorAll('[data-personal-id].border-green-500').length;
    const span = document.querySelector('header span.text-gray-300');
    if (span) span.textContent = asig + '/' + total + ' capturados';
}
</script>

</body>
</html>
