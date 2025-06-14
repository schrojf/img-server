<?php

namespace App\Providers;

use App\Variants\ImageVariant;
use App\Variants\ImageVariantRegistry;
use App\Variants\Modifiers\ImageCropModifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('testing')) {
            // Temporarily fix when boot method is called before each test method
            // and causing exception: "Duplicate variant name" being resolved.
            ImageVariantRegistry::clear();
        }

        ImageVariantRegistry::register(fn () => ImageVariant::make('600x600wh')
            ->addModifier(new ImageCropModifier(600, 600))
            ->withDefaultEncoders()
            ->withAvifEncoder()
        );

        ImageVariantRegistry::register(fn () => ImageVariant::make('150x150wh')
            ->addModifier(new ImageCropModifier(150, 150))
            ->withDefaultEncoders()
        );
    }
}
