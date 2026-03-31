<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Status</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex items-center justify-center">
    <div class="text-center space-y-4">
        <div class="text-green-600 text-5xl font-bold">OK</div>
        <p class="text-gray-400 text-sm">Service is running</p>

        @auth
            <div class="mt-6 space-y-2 text-sm text-gray-600">
                <p>Logged in as <span class="font-medium text-gray-800">{{ auth()->user()->name }}</span></p>
                <div class="flex gap-3 justify-center">
                    @if(Route::has('dashboard'))
                        <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline">Dashboard</a>
                    @endif
                    @if(Route::has('logout'))
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-red-600 hover:underline">Logout</button>
                        </form>
                    @endif
                </div>
            </div>
        @else
            @if(Route::has('login'))
                <div class="mt-6">
                    <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">Login</a>
                </div>
            @endif
        @endauth
    </div>
</body>
</html>
