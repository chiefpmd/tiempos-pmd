<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Control de Tiempos PMD')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .grid-cell { min-width: 50px; }
        .grid-cell input { width: 50px; text-align: center; }
        .nav-link { @apply px-3 py-2 rounded-md text-sm font-medium; }
        .nav-active { @apply bg-gray-900 text-white; }
        .nav-inactive { @apply text-gray-100 hover:bg-gray-700 hover:text-white; }
        [x-cloak] { display: none !important; }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-gray-800 shadow">
        <div class="max-w-full mx-auto px-4">
            <div class="flex items-center justify-between h-14">
                <div class="flex items-center space-x-4">
                    <span class="text-white font-bold text-lg">PMD Tiempos</span>
                    <a href="{{ route('general') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('general') ? 'bg-gray-900 text-white' : 'text-gray-100 hover:bg-gray-700 hover:text-white' }}">Proyección</a>
                    <a href="{{ route('gantt.nomina') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('gantt.nomina') ? 'bg-gray-900 text-white' : 'text-gray-100 hover:bg-gray-700 hover:text-white' }}">Gantt Nómina</a>
                    <a href="{{ route('gantt.anual') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('gantt.anual') ? 'bg-gray-900 text-white' : 'text-gray-100 hover:bg-gray-700 hover:text-white' }}">Gantt Anual</a>
                    <a href="{{ route('nomina.semanal') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('nomina.semanal') ? 'bg-gray-900 text-white' : 'text-gray-100 hover:bg-gray-700 hover:text-white' }}">Nómina</a>
                    <a href="{{ route('nomina.eficiencia') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('nomina.eficiencia') ? 'bg-gray-900 text-white' : 'text-gray-100 hover:bg-gray-700 hover:text-white' }}">Eficiencia</a>
                    <a href="{{ route('nomina.reporteMensual') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('nomina.reporteMensual') ? 'bg-gray-900 text-white' : 'text-gray-100 hover:bg-gray-700 hover:text-white' }}">Reporte Mensual</a>
                    <!-- Dropdown Base de Datos -->
                    <div class="relative" x-data="{ open: false }" @click.away="open = false">
                        <button @click="open = !open" class="px-3 py-2 rounded-md text-sm font-medium flex items-center {{ request()->routeIs('proyectos.*') || request()->routeIs('personal.*') || request()->routeIs('nomina.reporte') || request()->routeIs('festivos.*') || request()->routeIs('nomina.categorias') ? 'bg-gray-900 text-white' : 'text-gray-100 hover:bg-gray-700 hover:text-white' }}">
                            Base de Datos
                            <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="open" x-transition class="absolute left-0 mt-1 w-48 bg-gray-700 rounded-md shadow-lg z-50">
                            <a href="{{ route('proyectos.index') }}" class="block px-4 py-2 text-sm {{ request()->routeIs('proyectos.*') ? 'bg-gray-900 text-white' : 'text-gray-100 hover:bg-gray-600' }}">Proyectos</a>
                            <a href="{{ route('personal.index') }}" class="block px-4 py-2 text-sm {{ request()->routeIs('personal.*') ? 'bg-gray-900 text-white' : 'text-gray-100 hover:bg-gray-600' }}">Personal</a>
                            <a href="{{ route('nomina.reporte') }}" class="block px-4 py-2 text-sm {{ request()->routeIs('nomina.reporte') ? 'bg-gray-900 text-white' : 'text-gray-100 hover:bg-gray-600' }}">Costo x Proyecto</a>
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('festivos.index') }}" class="block px-4 py-2 text-sm {{ request()->routeIs('festivos.*') ? 'bg-gray-900 text-white' : 'text-gray-100 hover:bg-gray-600' }}">Festivos</a>
                                <a href="{{ route('nomina.categorias') }}" class="block px-4 py-2 text-sm {{ request()->routeIs('nomina.categorias') ? 'bg-gray-900 text-white' : 'text-gray-100 hover:bg-gray-600' }}">Cat. Nómina</a>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="text-gray-300 text-sm">{{ auth()->user()->name }} ({{ auth()->user()->role }})</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-gray-300 hover:text-white text-sm">Salir</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    @if(session('success'))
        <div class="max-w-7xl mx-auto mt-3 px-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded text-sm">
                {{ session('success') }}
            </div>
        </div>
    @endif

    <main class="py-4 px-4">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
