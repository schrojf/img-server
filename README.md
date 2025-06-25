<p align="center"><img src="/.github/art/logo.svg" alt="img-server"></p>

<h1 align="center">Laravel Image Server</h1>
<p align="center">
  A lightweight, robust image server with queued downloads, variant processing, and a REST API.
</p>

## 🚀 Introduction

Laravel Image Server is a lightweight image processing API built for robustness and configurability. It uses Laravel queues to download and process images asynchronously, generate custom variants, and expose a complete RESTful interface for external integration.

## ✨ Key Features

- **Queued Downloads**: Uses Laravel Jobs to handle downloads asynchronously
- **Image Variants**: Configurable sizes, compression, encoders, and modifiers
- **Complete REST API**: Endpoints to upload, view, and manage images
- **Error Resilience**: Detailed error logging and retry logic
- **Organized File Storage**: Auto cleanup and variant generation
- **Customizable Pipelines**: Define how variants are built via simple config

## 📦 API Endpoints

| Method | Endpoint               | Description                     |
|--------|------------------------|---------------------------------|
| POST   | `/api/images`          | Queue a new image for download  |
| GET    | `/api/images`          | List images and job stats       |
| GET    | `/api/images/{id}`     | Get image details + variants    |
| DELETE | `/api/images/{id}`     | Delete image and all its files  |

## 🛠️ Installation

Use Laravel Sail for development and testing:

### 1. Clone the Repository

```bash
git clone https://github.com/schrojf/img-server.git
cd img-server
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Start the Application

Copy `.env.example` to `.env`, configure your DB, then:

```bash
./vendor/bin/sail up -d
```

### 4. Set Up the Database

```bash
./vendor/bin/sail artisan migrate
```

### 5. Set Up Storage Symlink

```bash
./vendor/bin/sail artisan storage:link
```

## 🔧 Configuration

### Image Variants

Customize image processing variants inside `AppServiceProvider.php`:

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

### Image Server Options

Edit `config/images.php`:

```php
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

    'driver' => env('INTERVENTION_IMAGE_DRIVER', 'gd'), // gd, imagick, vips
    'avif' => env('AVIF_ENABLED', false),

];
```

## 🔐 Authentication

This API uses [Laravel Sanctum](https://laravel.com/docs/sanctum) for token-based authentication. You must include a `Bearer` token in your requests:

```
Authorization: Bearer your-api-token
```

Tokens can be created via:

```bash
./vendor/bin/sail artisan manage:tokens create --user=user@example.com
```

## 📚 API Documentation

### 1. Image Statistics

Get statistics about image processing.

```http
GET /api/images
```

**Returns:**

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

### 2. Queue Image from URL

Queue an image for download and processing.

```http
POST /api/images
Content-Type: application/json

{
  "url": "https://picsum.photos/5000/4000.jpg"
}
```

**Response:**

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

### 3. Get Image Details

Returns full details including URLs of generated variants.

```http
GET /api/images/1
```

**Response:**

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

### 4. Delete an Image

Deletes original and all variant files.

```http
DELETE /api/images/{id}
```

### 🗂 Status Values

* `queued`: Waiting for download
* `processing`: Downloading or transforming
* `done`: Image is ready
* `failed`: An error occurred
* `expired`: Marked as stale
* `deleting`: Scheduled for deletion

## 🧹 Artisan Maintenance Commands

### 🔐 Manage Users

```bash
php artisan manage:users
```

Actions: `create`, `list`, `show`, `update`, `delete`, `reset-password`

```Terminal
./vendor/bin/sail artisan manage:users --help

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
```

### 🔑 Manage API Tokens

```bash
php artisan manage:tokens
```

Actions: `create`, `list`, `show`, `revoke`, `revoke-all`, `prune`

```Terminal
./vendor/bin/sail artisan manage:tokens --help

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
```

### ⏳ Mark Expired Images

Mark stale processing images as expired.

```bash
php artisan image:expire
```

### 🧪 Check Supported Formats

```bash
php artisan images:supported-check
```

**Example output:**

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

### 🧼 Cleanup Orphaned Files

Remove orphaned image and variant files from storage:

```bash
php artisan image:images:cleanup-orphaned
```

List orphaned files but do not delete them:

```bash
php artisan image:images:cleanup-orphaned --dry-run
```

## ✅ Testing

**Run all tests:**

```bash
./vendor/bin/sail artisan test
```

**Manual test helpers:**

```bash
./vendor/bin/sail artisan app:download-test-image # Download test image and generate all registered variants.
./vendor/bin/sail artisan app:generate-test-image # Generate a fake test image and image record for local testing.
```

## 🛡 Security

Please report security issues to [viliam@schrojf.sk](mailto:viliam@schrojf.sk). Do not open public issues.

## 🙌 Credits

* [Viliam Schrojf](https://github.com/schrojf)
* [All Contributors](../../contributors)

## 📄 License

The MIT License (MIT). Please see [MIT license](https://opensource.org/licenses/MIT) for more information.
