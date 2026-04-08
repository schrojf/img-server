<?php

use App\Actions\GenerateRandomHashFileNameAction;

test('random file name path', function () {
    $generator = new GenerateRandomHashFileNameAction;

    expect($generator->handle())->toBeString()
        ->and($generator->handle())->toHaveLength(40 + (3 * 3))
        ->and($generator->handle())->toMatch('/^([a-f0-9i]{2}\/){3}[a-f0-9i]{40}$/');
});

test('ad segments are replaced with ia to avoid ad-blocker false positives', function () {
    $generator = new GenerateRandomHashFileNameAction;

    // Generate many hashes and verify none have 'ad' in directory segments
    for ($i = 0; $i < 500; $i++) {
        $path = $generator->handle();
        $segments = explode('/', $path);

        expect($segments[0])->not->toBe('ad')
            ->and($segments[1])->not->toBe('ad')
            ->and($segments[2])->not->toBe('ad');
    }
});

test('file extension', function () {
    expect((new GenerateRandomHashFileNameAction)->handle('.'))
        ->not->toEndWith('.')
        ->and((new GenerateRandomHashFileNameAction)->handle())
        ->not->toEndWith('.')
        ->and((new GenerateRandomHashFileNameAction)->handle('.jpg'))
        ->toEndWith('.jpg')
        ->and((new GenerateRandomHashFileNameAction)->handle('jpeg'))
        ->toEndWith('.jpeg')
        ->and((new GenerateRandomHashFileNameAction)->handle('opt.webp'))
        ->toEndWith('.opt.webp');
});
