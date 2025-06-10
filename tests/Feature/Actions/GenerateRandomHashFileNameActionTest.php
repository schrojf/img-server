<?php

use App\Actions\GenerateRandomHashFileNameAction;

test('random file name path', function () {
    $generator = new GenerateRandomHashFileNameAction;

    expect($generator->handle())->toBeString()
        ->and($generator->handle())->toHaveLength(40 + (3 * 3))
        ->and($generator->handle())->toMatch('/^([a-f0-9][a-f0-9]\/){3}[a-f0-9]{40}$/');
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
