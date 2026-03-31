<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Image Server')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">
    <header class="border-b border-gray-200 bg-white">
        <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ url('/') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Image Server</a>
            <nav class="flex items-center gap-4 text-sm">
                @auth
                    @if(Route::has('dashboard'))
                        <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-900">Dashboard</a>
                    @endif
                    @if(Route::has('logout'))
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-red-600 hover:underline">Logout</button>
                        </form>
                    @endif
                @else
                    @if(Route::has('login'))
                        <a href="{{ route('login') }}" class="text-blue-600 hover:underline">Login</a>
                    @endif
                @endauth
            </nav>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center p-4">
        @yield('content')
    </main>
</body>
</html>
