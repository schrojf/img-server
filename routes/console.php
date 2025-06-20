<?php

use App\Actions\MarkExpiredImagesAction;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

when(config('images.jobs.autoExpire', false), function () {
    Schedule::use(function () {
        Schedule::call(fn () => app(MarkExpiredImagesAction::class)->handle())
            ->dailyAt('3:00')
            ->name('expire-processing-images')
            ->withoutOverlapping();
    });
});
