<?php

namespace App\Providers;

use App\Variants\ImageVariant;
use App\Variants\ImageVariantRegistry;
use App\Variants\Modifiers\ImageCropModifier;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Drivers\Vips\Driver as VipsDriver;
use Intervention\Image\ImageManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImageManager::class, function ($app) {
            $driver = strtolower(config('images.driver', 'gd'));

            $driverInstance = match ($driver) {
                'gd' => new GdDriver,
                'imagick', 'imagemagic' => new ImagickDriver,
                'vips', 'libvips' => new VipsDriver,
                default => $app->make($driver),
            };

            return new ImageManager($driverInstance);
        });
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
