<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Status</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen relative">
    <nav class="absolute top-0 right-0 p-4 text-sm">
        @auth
            <div class="flex items-center gap-3">
                <span class="text-gray-500">{{ auth()->user()->name }}</span>
                @if(Route::has('dashboard'))
                    <a href="{{ route('dashboard') }}" class="text-blue-600 hover:text-blue-800 font-medium">Dashboard</a>
                @endif
                @if(Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-red-500 hover:text-red-700">Logout</button>
                    </form>
                @endif
            </div>
        @else
            @if(Route::has('login'))
                <a href="{{ route('login') }}" class="text-gray-500 hover:text-gray-600 transition-colors">Login</a>
            @endif
        @endauth
    </nav>

    <div class="min-h-screen flex items-center justify-center">
        <div class="text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 mb-4">
                <svg class="w-10 h-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div class="text-green-600 text-3xl font-bold tracking-tight">OK</div>
            <p class="text-gray-500 text-sm mt-1">Service is running</p>
        </div>
    </div>
</body>
</html>
