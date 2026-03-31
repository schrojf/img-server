@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="w-full max-w-5xl">

    {{-- User info --}}
    <div class="mb-8 text-sm text-gray-500">
        Signed in as <span class="font-medium text-gray-800">{{ $user->name }}</span> &middot; {{ $user->email }}
    </div>

    {{-- Image Statuses --}}
    <div class="dashboard-section">
        <h2 data-toggle-section role="button" tabindex="0" aria-expanded="true" aria-controls="section-statuses" class="flex items-center justify-between cursor-pointer select-none bg-white border border-gray-200 rounded-lg px-5 py-3.5 text-base font-semibold text-gray-800 hover:bg-gray-50 transition-colors">
            Image Statuses
            <x-toggle-chevron />
        </h2>
        <div id="section-statuses" class="section-body mt-3">
            @php
                $statusConfig = [
                    'queued'     => ['bg' => 'bg-amber-50',  'text' => 'text-amber-800',  'border' => 'border-amber-200', 'label' => 'text-amber-600'],
                    'processing' => ['bg' => 'bg-sky-50',    'text' => 'text-sky-800',    'border' => 'border-sky-200',   'label' => 'text-sky-600'],
                    'done'       => ['bg' => 'bg-emerald-50','text' => 'text-emerald-800','border' => 'border-emerald-200','label' => 'text-emerald-600'],
                    'failed'     => ['bg' => 'bg-red-50',    'text' => 'text-red-800',    'border' => 'border-red-200',   'label' => 'text-red-600'],
                    'expired'    => ['bg' => 'bg-gray-50',   'text' => 'text-gray-600',   'border' => 'border-gray-200',  'label' => 'text-gray-500'],
                    'deleting'   => ['bg' => 'bg-orange-50', 'text' => 'text-orange-800', 'border' => 'border-orange-200','label' => 'text-orange-600'],
                ];
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                @foreach($statusConfig as $status => $colors)
                    @php $count = $statuses[$status] ?? 0; @endphp
                    <div class="border rounded-lg px-4 py-3 {{ $colors['border'] }} {{ $count > 0 ? $colors['bg'] : 'bg-white' }}">
                        <div class="text-2xl font-bold tabular-nums {{ $count > 0 ? $colors['text'] : 'text-gray-300' }}">{{ number_format($count) }}</div>
                        <div class="text-[11px] font-medium uppercase tracking-wider mt-0.5 {{ $count > 0 ? $colors['label'] : 'text-gray-400' }}">{{ $status }}</div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 border border-indigo-200 bg-indigo-50 rounded-lg px-4 py-3 flex items-baseline justify-between">
                <span class="text-[11px] font-medium uppercase tracking-wider text-indigo-600">Total images</span>
                <span class="text-2xl font-bold tabular-nums text-indigo-800">{{ number_format($statuses['total'] ?? 0) }}</span>
            </div>
        </div>
    </div>

    {{-- Supported Image Formats --}}
    <div class="dashboard-section mt-10">
        <h2 data-toggle-section role="button" tabindex="0" aria-expanded="true" aria-controls="section-formats" class="flex items-center justify-between cursor-pointer select-none bg-white border border-gray-200 rounded-lg px-5 py-3.5 text-base font-semibold text-gray-800 hover:bg-gray-50 transition-colors">
            Supported Image Formats
            <x-toggle-chevron />
        </h2>
        <div id="section-formats" class="section-body mt-3">
            <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/80">
                            <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">MIME Type</th>
                            <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Extension</th>
                            <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Message</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($formats as $format)
                            <tr class="hover:bg-gray-50/50 transition-colors {{ $format['supported'] ? '' : 'bg-red-50/30' }}">
                                <td class="px-5 py-2.5 font-mono text-sm text-gray-700">{{ $format['mime'] }}</td>
                                <td class="px-5 py-2.5 font-mono text-sm text-gray-500">.{{ $format['extension'] }}</td>
                                <td class="px-5 py-2.5">
                                    @if($format['supported'])
                                        <span class="inline-flex items-center gap-1 text-emerald-700 text-xs font-medium">
                                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            Supported
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-red-600 text-xs font-medium">
                                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                            Unsupported
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-xs text-gray-500">{{ $format['message'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Registered Image Variants --}}
    <div class="dashboard-section mt-10">
        <h2 data-toggle-section role="button" tabindex="0" aria-expanded="true" aria-controls="section-variants" class="flex items-center justify-between cursor-pointer select-none bg-white border border-gray-200 rounded-lg px-5 py-3.5 text-base font-semibold text-gray-800 hover:bg-gray-50 transition-colors">
            Registered Image Variants
            <x-toggle-chevron />
        </h2>
        <div id="section-variants" class="section-body mt-3">
            <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/80">
                            <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Dimensions</th>
                            <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Background</th>
                            <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Encoders</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($variants as $variant)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-5 py-2.5 font-mono text-sm font-medium text-gray-800">{{ $variant['name'] }}</td>
                                <td class="px-5 py-2.5 text-sm tabular-nums text-gray-600">
                                    @foreach($variant['modifiers'] as $mod)
                                        {{ $mod['width'] }} &times; {{ $mod['height'] }}
                                    @endforeach
                                </td>
                                <td class="px-5 py-2.5 text-xs">
                                    @foreach($variant['modifiers'] as $mod)
                                        <span class="inline-flex items-center gap-1.5 text-gray-600">
                                            <span class="inline-block w-4 h-4 rounded border border-gray-300 shadow-sm" style="background-color: #{{ $mod['backgroundColor'] }}" aria-hidden="true"></span>
                                            <span class="font-mono text-xs">#{{ $mod['backgroundColor'] }}</span>
                                        </span>
                                    @endforeach
                                </td>
                                <td class="px-5 py-2.5">
                                    <div class="flex flex-wrap gap-1">
                                        @php
                                            $encoderColors = [
                                                'jpg'  => 'bg-amber-100 text-amber-700',
                                                'webp' => 'bg-emerald-100 text-emerald-700',
                                                'avif' => 'bg-violet-100 text-violet-700',
                                            ];
                                        @endphp
                                        @foreach($variant['encoders'] as $enc)
                                            <span class="inline-block rounded px-2 py-0.5 text-[11px] font-medium {{ $encoderColors[$enc['extension']] ?? 'bg-gray-100 text-gray-600' }}">
                                                .{{ $enc['extension'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Configuration --}}
    <div class="dashboard-section mt-10">
        <h2 data-toggle-section role="button" tabindex="0" aria-expanded="true" aria-controls="section-config" class="flex items-center justify-between cursor-pointer select-none bg-white border border-gray-200 rounded-lg px-5 py-3.5 text-base font-semibold text-gray-800 hover:bg-gray-50 transition-colors">
            Configuration
            <x-toggle-chevron />
        </h2>
        <div id="section-config" class="section-body mt-3">
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
                    <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-0">
                        <div class="sm:w-72 shrink-0 whitespace-nowrap">
                            <span class="font-mono text-xs text-gray-600">{{ $key }}</span>
                        </div>
                        <div class="sm:w-36 shrink-0">
                            @if($value === 'Enabled')
                                <span class="text-xs font-semibold text-emerald-700">{{ $value }}</span>
                            @elseif($value === 'Disabled')
                                <span class="text-xs font-semibold text-gray-400">{{ $value }}</span>
                            @else
                                <span class="text-sm font-medium text-gray-800">{{ $value }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 sm:pl-4">{{ $description }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- API Tokens --}}
    <div class="dashboard-section mt-10">
        <h2 data-toggle-section role="button" tabindex="0" aria-expanded="true" aria-controls="section-tokens" class="flex items-center justify-between cursor-pointer select-none bg-white border border-gray-200 rounded-lg px-5 py-3.5 text-base font-semibold text-gray-800 hover:bg-gray-50 transition-colors">
            <span>API Tokens <span class="ml-1.5 text-sm font-normal text-gray-500">({{ $tokens->count() }})</span></span>
            <x-toggle-chevron />
        </h2>
        <div id="section-tokens" class="section-body mt-3">
            @if($tokens->isEmpty())
                <div class="bg-white border border-gray-200 rounded-lg px-5 py-8 text-center text-sm text-gray-500">No API tokens found.</div>
            @else
                <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50/80">
                                <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">User</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Abilities</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Last Used</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Expires</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($tokens as $token)
                                @php
                                    $isExpired = $token->expires_at && $token->expires_at->isPast();
                                @endphp
                                <tr class="hover:bg-gray-50/50 transition-colors {{ $isExpired ? 'bg-red-50/30' : '' }}">
                                    <td class="px-5 py-2.5 text-xs tabular-nums text-gray-500">{{ $token->id }}</td>
                                    <td class="px-5 py-2.5 text-sm font-medium text-gray-800">{{ $token->name }}</td>
                                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $token->tokenable?->name ?? '-' }}</td>
                                    <td class="px-5 py-2.5">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach(array_slice($token->abilities, 0, 3) as $ability)
                                                <span class="inline-block bg-gray-100 text-gray-600 rounded px-2 py-0.5 text-[11px] font-medium">{{ $ability }}</span>
                                            @endforeach
                                            @if(count($token->abilities) > 3)
                                                <span class="text-xs text-gray-500">+{{ count($token->abilities) - 3 }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-2.5 text-xs text-gray-500">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td class="px-5 py-2.5 text-xs {{ $isExpired ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                        {{ $token->expires_at?->format('Y-m-d') ?? 'Never' }}
                                        @if($isExpired) <span class="text-red-500">(expired)</span> @endif
                                    </td>
                                    <td class="px-5 py-2.5 text-xs tabular-nums text-gray-500">{{ $token->created_at->format('Y-m-d') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Users --}}
    <div class="dashboard-section mt-10">
        <h2 data-toggle-section role="button" tabindex="0" aria-expanded="true" aria-controls="section-users" class="flex items-center justify-between cursor-pointer select-none bg-white border border-gray-200 rounded-lg px-5 py-3.5 text-base font-semibold text-gray-800 hover:bg-gray-50 transition-colors">
            <span>Users <span class="ml-1.5 text-sm font-normal text-gray-500">({{ $users->count() }})</span></span>
            <x-toggle-chevron />
        </h2>
        <div id="section-users" class="section-body mt-3">
            @if($users->isEmpty())
                <div class="bg-white border border-gray-200 rounded-lg px-5 py-8 text-center text-sm text-gray-500">No users found.</div>
            @else
                <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50/80">
                                <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Verified</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Tokens</th>
                                <th scope="col" class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($users as $u)
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-5 py-2.5 text-xs tabular-nums text-gray-500">{{ $u->id }}</td>
                                    <td class="px-5 py-2.5 text-sm font-medium text-gray-800">{{ $u->name }}</td>
                                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $u->email }}</td>
                                    <td class="px-5 py-2.5 text-xs">
                                        @if($u->email_verified_at)
                                            <span class="text-emerald-600 font-medium">{{ $u->email_verified_at->format('Y-m-d') }}</span>
                                        @else
                                            <span class="text-gray-400">No</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5 text-sm tabular-nums text-gray-600">{{ $u->tokens_count }}</td>
                                    <td class="px-5 py-2.5 text-xs tabular-nums text-gray-500">{{ $u->created_at->format('Y-m-d') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="h-8"></div>
</div>
@endsection
