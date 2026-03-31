# Image Variants Specification

Standard image variant definitions for this project and future e-commerce integrations. All variants are square (box) format. Non-square source images are padded with a solid background color to fit the target dimensions.

## Formats

| Format | Extension | Use Case                             | Quality |
| ------ | --------- | ------------------------------------ |---------|
| WebP   | `.webp`   | Primary format, 97%+ browser support | default |
| AVIF   | `.avif`   | Best compression, growing support    | default |
| JPEG   | `.jpg`    | Universal fallback                   | default |

For smaller variants a i do not need AVIF,

## Variants

| Name          | Alias\*  | Dimensions  | Use Case                                                                      |
| ------------- | -------- | ----------- | ----------------------------------------------------------------------------- |
| `80x80wh`     | `micro`  | 80 x 80     | Micro thumbnail: cart widget, mini-cart, autocomplete                         |
| `155x155wh`   | `thumb`  | 155 x 155   | Thumbnail: product cards, cart/mini-cart, gallery nav, search results, drawer |
| `300x300wh`   | `small`  | 300 x 300   | Product card: grid listings, category pages, embed ads                        |
| `600x600wh`   | `medium` | 600 x 600   | Full image: product detail main image, embed script large card                |
| `1200x1200wh` | `large`  | 1200 x 1200 | High-res detail: product page zoom, retina displays                           |
| `2000x2000wh` | `xlarge` | 2000 x 2000 | Maximum: full-zoom overlay, print-quality preview                             |

The `wh` suffix denotes box/square crop with padding. Aliases are human-friendly names that can be used interchangeably with the dimension-based names in configuration and API references.

\* Alias will not be used.

## Processing Rules

1. **Box fit with padding** -- Resize the source image to fit within the target dimensions (maintain aspect ratio), then pad the remaining space with a solid background color. The image is always centered.

2. **Background color** -- Default: `#FFFFFF` (white). Configurable per variant or globally. Transparent PNG sources should be composited onto the background color before encoding.

3. **Upscaling** -- Do NOT upscale. If the source image is smaller than the target dimensions, pad to the target size without stretching. For example, a 400x400 source processed for `600x600wh` is centered at its original size with padding around it.

4. **Minimum source size** -- No minimum enforced. Very small sources (below 80x80) will still generate all variants but with padding. The `80x80wh` variant will contain the original at its native size.

5. **Strip metadata** -- Remove EXIF, IPTC, and XMP data from all outputs. Reduces file size and avoids leaking location or camera data.

## Variant Selection by Context

| Context                           | Primary Variant | Retina Variant |
| --------------------------------- | --------------- | -------------- |
| Embed script: grid card           | `300x300wh`     | `600x600wh`    |
| Embed script: list layout         | `155x155wh`     | `300x300wh`    |
| Dashboard: product list thumbnail | `80x80wh`       | `155x155wh`    |
| Dashboard: product detail         | `600x600wh`     | `1200x1200wh`  |
| E-shop: product card              | `300x300wh`     | `600x600wh`    |
| E-shop: product detail            | `600x600wh`     | `1200x1200wh`  |
| E-shop: zoom overlay              | `1200x1200wh`   | `2000x2000wh`  |
| E-shop: cart/mini-cart            | `80x80wh`       | `155x155wh`    |
| Open Graph / social sharing       | `600x600wh`     | --             |

Retina variant is served to displays with `devicePixelRatio >= 2` (or via `srcset`).

## Image Server Registration

Register variants in the image server's `AppServiceProvider`:

```php
public function boot(): void
{
    ImageVariantRegistry::register(fn () => ImageVariant::make('80x80wh')
        ->addModifier(new ImageBoxFitModifier(80, 80, '#FFFFFF'))
        ->withDefaultEncoders()
    );

    ImageVariantRegistry::register(fn () => ImageVariant::make('155x155wh')
        ->addModifier(new ImageBoxFitModifier(155, 155, '#FFFFFF'))
        ->withDefaultEncoders()
    );

    ImageVariantRegistry::register(fn () => ImageVariant::make('300x300wh')
        ->addModifier(new ImageBoxFitModifier(300, 300, '#FFFFFF'))
        ->withDefaultEncoders()
    );

    ImageVariantRegistry::register(fn () => ImageVariant::make('600x600wh')
        ->addModifier(new ImageBoxFitModifier(600, 600, '#FFFFFF'))
        ->withDefaultEncoders()
        ->withAvifEncoder()
    );

    ImageVariantRegistry::register(fn () => ImageVariant::make('1200x1200wh')
        ->addModifier(new ImageBoxFitModifier(1200, 1200, '#FFFFFF'))
        ->withDefaultEncoders()
        ->withAvifEncoder()
    );

    ImageVariantRegistry::register(fn () => ImageVariant::make('2000x2000wh')
        ->addModifier(new ImageBoxFitModifier(2000, 2000, '#FFFFFF'))
        ->withDefaultEncoders()
        ->withAvifEncoder()
    );
}
```

AVIF is enabled only for 600x600 and above where the compression advantage justifies the slower encoding time. Smaller variants are fast enough with WebP + JPEG.

## Estimated File Sizes

Approximate sizes for a typical e-commerce product photo (solid background, single product):

| Variant       | JPEG   | WebP   | AVIF   |
| ------------- | ------ | ------ | ------ |
| `80x80wh`     | 3 KB   | 2 KB   | --     |
| `155x155wh`   | 8 KB   | 5 KB   | --     |
| `300x300wh`   | 25 KB  | 15 KB  | --     |
| `600x600wh`   | 70 KB  | 40 KB  | 25 KB  |
| `1200x1200wh` | 200 KB | 110 KB | 70 KB  |
| `2000x2000wh` | 450 KB | 250 KB | 150 KB |

Total storage per image (all variants, all formats): ~1.5 MB typical.
