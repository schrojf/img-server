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

        // Micro thumbnail: cart widget, mini-cart, autocomplete (WebP + JPEG)
        ImageVariantRegistry::register(fn () => ImageVariant::make('80x80wh')
            ->addModifier(new ImageCropModifier(80, 80))
            ->withDefaultEncoders()
        );

        // Thumbnail: product cards, cart/mini-cart, gallery nav, search results, drawer (WebP + JPEG)
        ImageVariantRegistry::register(fn () => ImageVariant::make('155x155wh')
            ->addModifier(new ImageCropModifier(155, 155))
            ->withDefaultEncoders()
        );

        // Product card: grid listings, category pages, embed ads (WebP + JPEG)
        ImageVariantRegistry::register(fn () => ImageVariant::make('300x300wh')
            ->addModifier(new ImageCropModifier(300, 300))
            ->withDefaultEncoders()
        );

        // Full image: product detail main image, embed script large card (WebP + JPEG + AVIF)
        ImageVariantRegistry::register(fn () => ImageVariant::make('600x600wh')
            ->addModifier(new ImageCropModifier(600, 600))
            ->withDefaultEncoders()
            ->withAvifEncoder()
        );

        // High-res detail: product page zoom, retina displays (WebP + JPEG + AVIF)
        ImageVariantRegistry::register(fn () => ImageVariant::make('1200x1200wh')
            ->addModifier(new ImageCropModifier(1200, 1200))
            ->withDefaultEncoders()
            ->withAvifEncoder()
        );

        // Maximum: full-zoom overlay, print-quality preview (WebP + JPEG + AVIF)
        ImageVariantRegistry::register(fn () => ImageVariant::make('2000x2000wh')
            ->addModifier(new ImageCropModifier(2000, 2000))
            ->withDefaultEncoders()
            ->withAvifEncoder()
        );
    }
}
