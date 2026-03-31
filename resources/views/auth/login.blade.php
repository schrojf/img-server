@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="w-full max-w-sm">
    <h1 class="text-xl font-semibold text-center mb-6">Login</h1>

    @if($errors->any())
        <div class="mb-4 text-sm text-red-600" role="alert">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" id="password" name="password" required
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" id="remember" name="remember"
                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <label for="remember" class="text-sm text-gray-600">Remember me</label>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white text-sm font-medium rounded px-4 py-2 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 cursor-pointer">
            Login
        </button>
    </form>
</div>
@endsection
