<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Control de Tiempos PMD')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .grid-cell { min-width: 50px; }
        .grid-cell input { width: 50px; text-align: center; }
        .nav-link { @apply px-3 py-2 rounded-md text-sm font-medium; }
        .nav-active { @apply bg-gray-900 text-white; }
        .nav-inactive { @apply text-gray-300 hover:bg-gray-700 hover:text-white; }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-gray-800 shadow">
        <div class="max-w-full mx-auto px-4">
            <div class="flex items-center justify-between h-14">
                <div class="flex items-center space-x-4">
                    <span class="text-white font-bold text-lg">PMD Tiempos</span>
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-active' : 'nav-inactive' }}">Dashboard</a>
                    <a href="{{ route('general') }}" class="nav-link {{ request()->routeIs('general') ? 'nav-active' : 'nav-inactive' }}">Vista General</a>
                    <a href="{{ route('proyectos.index') }}" class="nav-link {{ request()->routeIs('proyectos.*') ? 'nav-active' : 'nav-inactive' }}">Proyectos</a>
                    <a href="{{ route('personal.index') }}" class="nav-link {{ request()->routeIs('personal.*') ? 'nav-active' : 'nav-inactive' }}">Personal</a>
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
