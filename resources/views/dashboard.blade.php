@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="w-full max-w-5xl space-y-6">

    {{-- User info --}}
    <div class="bg-white border border-gray-200 rounded-lg p-4 text-sm text-gray-600">
        Logged in as <span class="font-medium text-gray-800">{{ $user->name }}</span> ({{ $user->email }})
    </div>

    {{-- Image Statuses --}}
    <div class="dashboard-section">
        <h2 data-toggle-section role="button" tabindex="0" aria-expanded="true" aria-controls="section-statuses" class="flex items-center justify-between cursor-pointer select-none bg-white border border-gray-200 rounded-lg px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            Image Statuses
            <x-toggle-chevron />
        </h2>
        <div id="section-statuses" class="section-body mt-2">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @php
                    $statusColors = [
                        'queued' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                        'processing' => 'bg-blue-50 text-blue-700 border-blue-200',
                        'done' => 'bg-green-50 text-green-700 border-green-200',
                        'failed' => 'bg-red-50 text-red-700 border-red-200',
                        'expired' => 'bg-gray-100 text-gray-600 border-gray-200',
                        'deleting' => 'bg-orange-50 text-orange-700 border-orange-200',
                        'total' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                    ];
                @endphp
                @foreach($statuses as $status => $count)
                    <div class="border rounded-lg p-3 text-center {{ $statusColors[$status] ?? 'bg-white border-gray-200' }}">
                        <div class="text-2xl font-bold">{{ number_format($count) }}</div>
                        <div class="text-xs font-medium uppercase tracking-wide mt-1">{{ $status }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Supported Image Formats --}}
    <div class="dashboard-section">
        <h2 data-toggle-section role="button" tabindex="0" aria-expanded="true" aria-controls="section-formats" class="flex items-center justify-between cursor-pointer select-none bg-white border border-gray-200 rounded-lg px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            Supported Image Formats
            <x-toggle-chevron />
        </h2>
        <div id="section-formats" class="section-body mt-2">
            <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <tr>
                            <th scope="col" class="px-4 py-2">MIME Type</th>
                            <th scope="col" class="px-4 py-2">Extension</th>
                            <th scope="col" class="px-4 py-2">Supported</th>
                            <th scope="col" class="px-4 py-2">Message</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($formats as $format)
                            <tr class="{{ $format['supported'] ? '' : 'bg-red-50/50' }}">
                                <td class="px-4 py-2 font-mono text-xs">{{ $format['mime'] }}</td>
                                <td class="px-4 py-2 font-mono text-xs">.{{ $format['extension'] }}</td>
                                <td class="px-4 py-2">
                                    @if($format['supported'])
                                        <span class="text-green-600">Supported</span>
                                    @else
                                        <span class="text-red-500">Not supported</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $format['message'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Registered Image Variants --}}
    <div class="dashboard-section">
        <h2 data-toggle-section role="button" tabindex="0" aria-expanded="true" aria-controls="section-variants" class="flex items-center justify-between cursor-pointer select-none bg-white border border-gray-200 rounded-lg px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            Registered Image Variants
            <x-toggle-chevron />
        </h2>
        <div id="section-variants" class="section-body mt-2">
            <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <tr>
                            <th scope="col" class="px-4 py-2">Name</th>
                            <th scope="col" class="px-4 py-2">Dimensions</th>
                            <th scope="col" class="px-4 py-2">Background</th>
                            <th scope="col" class="px-4 py-2">Encoders</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($variants as $variant)
                            <tr>
                                <td class="px-4 py-2 font-mono text-xs font-medium">{{ $variant['name'] }}</td>
                                <td class="px-4 py-2 text-xs">
                                    @foreach($variant['modifiers'] as $mod)
                                        {{ $mod['width'] }}x{{ $mod['height'] }}
                                    @endforeach
                                </td>
                                <td class="px-4 py-2 text-xs">
                                    @foreach($variant['modifiers'] as $mod)
                                        <span class="inline-flex items-center gap-1">
                                            <span class="inline-block w-3 h-3 rounded border border-gray-300" style="background-color: #{{ $mod['backgroundColor'] }}" aria-hidden="true"></span>
                                            #{{ $mod['backgroundColor'] }}
                                        </span>
                                    @endforeach
                                </td>
                                <td class="px-4 py-2 text-xs">
                                    @foreach($variant['encoders'] as $enc)
                                        <span class="inline-block bg-gray-100 text-gray-600 rounded px-1.5 py-0.5 mr-1">
                                            .{{ $enc['extension'] }}
                                        </span>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Configuration --}}
    <div class="dashboard-section">
        <h2 data-toggle-section role="button" tabindex="0" aria-expanded="true" aria-controls="section-config" class="flex items-center justify-between cursor-pointer select-none bg-white border border-gray-200 rounded-lg px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            Configuration
            <x-toggle-chevron />
        </h2>
        <div id="section-config" class="section-body mt-2">
            <div class="bg-white border border-gray-200 rounded-lg divide-y divide-gray-100">
                @php
                    $configItems = [
                        ['images.driver', $config['driver'], 'Image processing driver (GD, Imagick, or VIPS)'],
                        ['images.avif', $config['avif'] ? 'Enabled' : 'Disabled', 'AVIF encoding support. Requires driver support and adds encoding time.'],
                        ['images.disk.original', $config['disk']['original'], 'Storage disk for original downloaded images.'],
                        ['images.disk.variant', $config['disk']['variant'], 'Storage disk for generated image variants.'],
                        ['images.downloads.allowedExtensions', implode(', ', $config['downloads']['allowedExtensions']), 'File extensions allowed for download.'],
                        ['images.downloads.maxFileSize', number_format($config['downloads']['maxFileSize'] / 1024 / 1024) . ' MB', 'Maximum allowed file size for image downloads.'],
                        ['images.downloads.timeout', $config['downloads']['timeout'] . 's', 'HTTP timeout for downloading source images.'],
                        ['images.downloads.retries', $config['downloads']['retries'], 'Number of retry attempts for failed downloads.'],
                        ['images.downloads.baseBackoffMs', $config['downloads']['baseBackoffMs'] . ' ms', 'Base delay for exponential backoff between retries.'],
                        ['images.downloads.userAgent', $config['downloads']['userAgent'], 'User-Agent header sent when downloading images.'],
                        ['images.downloads.tmpPrefix', $config['downloads']['tmpPrefix'], 'Prefix for temporary files during download.'],
                        ['images.jobs.dispatch', $config['jobs']['dispatch'], 'Job dispatch strategy: sync (immediate), batch (parallel), chain (sequential), or null (disabled).'],
                        ['images.jobs.autoExpire', $config['jobs']['autoExpire'] ? 'Enabled' : 'Disabled', 'Automatically expire images after processing.'],
                    ];
                @endphp
                @foreach($configItems as [$key, $value, $description])
                    <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4 text-sm">
                        <div class="sm:w-64 shrink-0">
                            <span class="font-mono text-xs font-medium text-gray-700">{{ $key }}</span>
                        </div>
                        <div class="sm:w-32 shrink-0 font-medium text-gray-800 text-xs">{{ $value }}</div>
                        <div class="text-xs text-gray-500">{{ $description }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- API Tokens --}}
    <div class="dashboard-section">
        <h2 data-toggle-section role="button" tabindex="0" aria-expanded="true" aria-controls="section-tokens" class="flex items-center justify-between cursor-pointer select-none bg-white border border-gray-200 rounded-lg px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            API Tokens <span class="ml-2 text-xs font-normal text-gray-500">({{ $tokens->count() }})</span>
            <x-toggle-chevron />
        </h2>
        <div id="section-tokens" class="section-body mt-2">
            @if($tokens->isEmpty())
                <div class="bg-white border border-gray-200 rounded-lg px-4 py-6 text-center text-sm text-gray-500">No API tokens found.</div>
            @else
                <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="px-4 py-2">ID</th>
                                <th scope="col" class="px-4 py-2">Name</th>
                                <th scope="col" class="px-4 py-2">User</th>
                                <th scope="col" class="px-4 py-2">Abilities</th>
                                <th scope="col" class="px-4 py-2">Last Used</th>
                                <th scope="col" class="px-4 py-2">Expires</th>
                                <th scope="col" class="px-4 py-2">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($tokens as $token)
                                @php
                                    $isExpired = $token->expires_at && $token->expires_at->isPast();
                                @endphp
                                <tr class="{{ $isExpired ? 'bg-red-50/50' : '' }}">
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $token->id }}</td>
                                    <td class="px-4 py-2 text-xs font-medium">{{ $token->name }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $token->tokenable?->name ?? '-' }}</td>
                                    <td class="px-4 py-2 text-xs">
                                        @foreach(array_slice($token->abilities, 0, 3) as $ability)
                                            <span class="inline-block bg-gray-100 text-gray-600 rounded px-1.5 py-0.5 mr-1">{{ $ability }}</span>
                                        @endforeach
                                        @if(count($token->abilities) > 3)
                                            <span class="text-gray-500">+{{ count($token->abilities) - 3 }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td class="px-4 py-2 text-xs {{ $isExpired ? 'text-red-500 font-medium' : 'text-gray-500' }}">
                                        {{ $token->expires_at?->format('Y-m-d') ?? 'Never' }}
                                        @if($isExpired) (expired) @endif
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $token->created_at->format('Y-m-d') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Users --}}
    <div class="dashboard-section">
        <h2 data-toggle-section role="button" tabindex="0" aria-expanded="true" aria-controls="section-users" class="flex items-center justify-between cursor-pointer select-none bg-white border border-gray-200 rounded-lg px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            Users <span class="ml-2 text-xs font-normal text-gray-500">({{ $users->count() }})</span>
            <x-toggle-chevron />
        </h2>
        <div id="section-users" class="section-body mt-2">
            @if($users->isEmpty())
                <div class="bg-white border border-gray-200 rounded-lg px-4 py-6 text-center text-sm text-gray-500">No users found.</div>
            @else
                <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th scope="col" class="px-4 py-2">ID</th>
                                <th scope="col" class="px-4 py-2">Name</th>
                                <th scope="col" class="px-4 py-2">Email</th>
                                <th scope="col" class="px-4 py-2">Verified</th>
                                <th scope="col" class="px-4 py-2">Tokens</th>
                                <th scope="col" class="px-4 py-2">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($users as $u)
                                <tr>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $u->id }}</td>
                                    <td class="px-4 py-2 text-xs font-medium">{{ $u->name }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $u->email }}</td>
                                    <td class="px-4 py-2 text-xs">
                                        @if($u->email_verified_at)
                                            <span class="text-green-600">{{ $u->email_verified_at->format('Y-m-d') }}</span>
                                        @else
                                            <span class="text-gray-500">No</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $u->tokens_count }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $u->created_at->format('Y-m-d') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
