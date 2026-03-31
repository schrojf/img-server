@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="w-full max-w-3xl">
    <h1 class="text-xl font-semibold mb-4">Dashboard</h1>

    <div class="bg-white border border-gray-200 rounded p-4 text-sm text-gray-600">
        <p>Logged in as <span class="font-medium text-gray-800">{{ $user->name }}</span> ({{ $user->email }})</p>
    </div>
</div>
@endsection
