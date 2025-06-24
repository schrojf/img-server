<p align="center"><img src="/.github/art/logo.svg" alt="img-server"></p>

## Introduction

Lightweight image server designed for robustness, configurable via REST API.

## Key Features

-  **Queued Image Downloads:** Uses Laravel jobs to handle image downloads asynchronously 
-  **Image Variants:** Configurable variants (thumbnail, medium, large) with resizing, compression, and watermarking 
-  **Robust API:** Complete REST API for managing images 
-  **Error Handling:** Comprehensive error handling and logging 
-  **File Management:** Organized storage structure with cleanup commands

## API Endpoints

- `POST /api/images` - Queue image download
- `GET /api/images` - List images and processing jobs stats
- `GET /api/images/{id}` - Get specific image details with variants
- `DELETE /api/images/{id}` - Delete image and its files

## 🛠️ Installation

Use laravel Sail during development and testing ...

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/laravel-image-server.git
cd laravel-image-server
```

### 2. Install Dependencies

```bash
composer install
```

### 3.Start the Application

Configure your `.env` file.

```bash
./vendor/bin/sail up -d
```

### 4. Database Setup

```bash
./vendor/bin/sail artisan migrate
```

### 5. Storage Setup

```bash
./vendor/bin/sail artisan storage:link
```

## 🔧 Configuration

### Image Variants Configuration

Edit `AppServiceProvider.php` to customize image variants generation:

```php
    public function boot(): void
    {
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
```

### Image server configuration

Edit `config/images.php` file to customize app behavior:

```php
<?php

return [

    'disk' => [
        'original' => 'downloaded',
        'variant' => 'converted',
    ],

    'downloads' => [
        'allowedExtensions' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
        'maxFileSize' => 30 * 1024 * 1024, // 30 MB
        'timeout' => 120, // seconds
        'retries' => 3,
        'baseBackoffMs' => 200,
        'userAgent' => 'ImageServer Downloader',
        'tmpPrefix' => 'image-server-',
    ],

    'jobs' => [
        'dispatch' => 'chain', // 'sync', 'batch', 'chain' or 'null'
        'autoExpire' => false,
    ],

    'driver' => env('INTERVENTION_IMAGE_DRIVE', 'Gd'), // gd, imagick, vips

    'avif' => env('AVIF_ENABLED', false),

];
```

## 📚 API Documentation

### Base URL

```php
https://your-domain.com/api
```

### Authentication
Authentication is using Laravel Sanctum....

### Endpoints

#### 1. Image statistics

Get statistics about image processing.

```http
GET /api/images
```

##### Response:

```json
{
    "queued": 1,
    "processing": 1,
    "done": 7,
    "failed": 0,
    "expired": 0,
    "deleting": 0,
    "total": 9
}
```

#### 2. Create image from url

Queue an image for download and processing.

```http
POST /api/images
Content-Type: application/json

{
    "url": "https://picsum.photos/5000/4000.jpg"
}
```

##### Response:

```json
{
    "id": 1,
    "status": "queued",
    "uid": "6ed6c09eb43326f246c71ce8796d1b32",
    "original_url": "https://picsum.photos/5000/4000.jpg",
    "last_error": null,
    "variants": [],
    "downloaded_at": null,
    "processed_at": null,
    "is_new": true
}
```

#### 3. Get Image Details

Retrieve details of a specific image.

```http
GET `/api/images/1`
```

##### Response:

```json
{
    "id": 1,
    "status": "done",
    "uid": "6ed6c09eb43326f246c71ce8796d1b32",
    "original_url": "https://picsum.photos/5000/4000.jpg",
    "last_error": null,
    "variants": {
        "150x150wh": {
            "jpg": "https://your-domain.com/img/24/e3/69/24e3698cffe9236afda8486e017c25310ff366e0_1_150x150wh.jpg",
            "webp": "https://your-domain.com/img/24/e3/69/24e3698cffe9236afda8486e017c25310ff366e0_1_150x150wh.webp"
        },
        "600x600wh": {
            "jpg": "https://your-domain.com/img/24/e3/69/24e3698cffe9236afda8486e017c25310ff366e0_1_600x600wh.jpg",
            "avif": "https://your-domain.com/img/24/e3/69/24e3698cffe9236afda8486e017c25310ff366e0_1_600x600wh.avif",
            "webp": "https://your-domain.com/img/24/e3/69/24e3698cffe9236afda8486e017c25310ff366e0_1_600x600wh.webp"
        }
    },
    "downloaded_at": "2025-06-22T13:16:07.000000Z",
    "processed_at": "2025-06-22T13:16:08.000000Z"
}
```

#### 4. Delete Image

Delete an image and all its associated files.

```http
DELETE /api/images/{id}
```

### Status Values

* `queued`: Image is queued for download
* `processing`: Image is currently being downloaded or being processed (creating variants)
* `done`: Image and all variants are ready
* `failed`: Processing failed (check error_message)
* `expired`: Process hang during processing phase
* `deleting`: Image is marked as deleted and being removed with all its files

### 🧹 Maintenance Commands

#### Manage users

./vendor/bin/sail artisan manage:users

Available actions:
create - Create a new user
list - List all users
show - Show user details
update - Update user information
delete - Delete a user
reset-password - Reset user password

Usage examples:
php artisan manage:users create
php artisan manage:users update --email=user@example.com
php artisan manage:users show --id=1

#### Manage api tokens

./vendor/bin/sail artisan manage:tokens
Missing or invalid action.

Available actions:
create — Create a new API token for a user
list — List API tokens (all or by user)
show — Show token details
revoke — Revoke a specific token
revoke-all — Revoke all tokens for a user
prune — Remove expired tokens from database

Usage examples:
php artisan token:manage create --user=user@example.com
php artisan token:manage list
php artisan token:manage list --user=user@example.com
php artisan token:manage show --token-id=1
php artisan token:manage revoke --token-id=1
php artisan token:manage revoke-all --user=user@example.com
php artisan token:manage prune


#### Mark stale processing images as expired

/vendor/bin/sail artisan image:expire

#### Check for supported image formats

```bash
./vendor/bin/sail artisan images:supported-check --help
```

```Terminal
+------------+-----------+-----------+-----------------------------------------------------------------+
| MIME Type  | Extension | Supported | Message                                                         |
+------------+-----------+-----------+-----------------------------------------------------------------+
| image/jpeg | jpg       | ✅️       |                                                                 |
| image/jp2  | jp2       | ❌        | Class 'Encoders\Jpeg2000Encoder' is not supported by GD driver. |
| image/png  | png       | ✅️       |                                                                 |
| image/gif  | gif       | ✅️       |                                                                 |
| image/webp | webp      | ✅️       |                                                                 |
| image/avif | avif      | 💣        | imageavif(): AVIF image support has been disabled               |
| image/tiff | tiff      | ❌        | Class 'Encoders\TiffEncoder' is not supported by GD driver.     |
| image/bmp  | bmp       | ✅️       |                                                                 |
| image/heic | heic      | ❌        | Class 'Encoders\HeicEncoder' is not supported by GD driver.     |
+------------+-----------+-----------+-----------------------------------------------------------------+
```

./vendor/bin/sail artisan image:

#### Remove orphaned image and variant files from storage

./vendor/bin/sail artisan image:images:cleanup-orphaned

List orphaned files but do not delete them:
./vendor/bin/sail artisan image:images:cleanup-orphaned -d
./vendor/bin/sail artisan image:images:cleanup-orphaned --dry-run

## Testing

```bash
./vendor/bin/sail artisan test
```

### Manual testing

./vendor/bin/sail artisan app:download-test-image - Download test image and generate all registered variants.
./vendor/bin/sail artisan app:generate-test-image - Generate a fake test image and image record for local testing.

## Security Vulnerabilities

If you discover a security vulnerability within this project, please send an e-mail to Viliam Schrojf via [viliam@schrojf.sk](mailto:viliam@schrojf.sk).

## Credits

- [Viliam Schrojf](https://github.com/schrojf)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [MIT license](https://opensource.org/licenses/MIT) for more information.
