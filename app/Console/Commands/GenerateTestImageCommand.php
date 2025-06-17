<?php

namespace App\Console\Commands;

use App\Actions\GenerateRandomHashFileNameAction;
use App\Actions\GenerateVariantsAction;
use App\Jobs\GenerateImageVariantsJob;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use App\Support\ImageFile;
use App\Support\ImageStorage;
use Illuminate\Console\Command;
use Illuminate\Http\Testing\FileFactory;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class GenerateTestImageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-test-image';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a fake test image and image record for local testing.';

    /**
     * Execute the console command.
     */
    public function handle(GenerateVariantsAction $generateVariantsAction): int
    {
        if (App::environment('production')) {
            $this->warn('❌ This command is not allowed in the production environment.');

            return self::FAILURE;
        }

        $disk = ImageStorage::originalDisk();
        $imageFile = (new FileFactory)->image('image.jpg', 10, 10); // Small dummy image
        $filePath = (new GenerateRandomHashFileNameAction)->handle('jpg');

        $disk->put($filePath, $imageFile->getContent());

        $imageFile = new ImageFile(
            ImageStorage::original(),
            $filePath,
            'image/jpg',
            $imageFile->getSize(),
            10,
            10,
        );

        $url = 'https://example.org/image_'.Str::random(6).'.jpg';

        $image = Image::create([
            'status' => ImageStatus::IMAGE_DOWNLOADED,
            'original_url' => $url,
            'uid' => hash('xxh128', $url),
            'image_file' => $imageFile->toArray(),
        ]);

        if (false) {
            $result = $generateVariantsAction->handle($image);
        } else {
            $variantGeneratorJob = new GenerateImageVariantsJob($image->id);
            $variantGeneratorJob->handle($generateVariantsAction);
        }

        $this->info('✅ Test image and variants generated successfully.');
        dump($image->fresh()->toArray());

        return self::SUCCESS;
    }
}
