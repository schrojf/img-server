<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Support\ImageStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedImagesCommand extends Command
{
    const IGNORED_FILES = ['.gitignore'];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:cleanup-orphaned';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove orphaned image files from storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->checkForOrphanedImageFiles();
        $this->checkForOrphanedVariants();

        $this->info("Cleanup completed.");

        return self::SUCCESS;
    }

    protected function checkForOrphanedImageFiles(): void
    {
        $disk = ImageStorage::originalDisk();

        // Check original images
        $originalFiles = $disk->files(recursive: true);
        foreach ($originalFiles as $fileName) {
            if (in_array($fileName, self::IGNORED_FILES)) {
                continue;
            }

            if (Image::where('image_file->file_name', $fileName)->exists()) {
                continue;
            }

            if ($disk->exists($fileName)) {
                $disk->delete($fileName);
            }

            $this->info("Deleted orphaned file: {$fileName}");
        }
    }

    protected function checkForOrphanedVariants(): void
    {
        $disk = ImageStorage::variantDisk();

        // Check variant files
        $variantFiles = $disk->files(recursive: true);
        foreach ($variantFiles as $fileName) {
            if (in_array($fileName, self::IGNORED_FILES)) {
                continue;
            }

            if (DB::getDriverName() === 'mysql') {
                if (Image::whereRaw("JSON_SEARCH(variant_files, 'one', ?) IS NOT NULL", [$fileName])->exists()) {
                    continue;
                }
            } else {
                if (Image::where('variant_files', 'like', '%'.$fileName.'%')->exists()) {
                    continue;
                }
            }

            if ($disk->exists($fileName)) {
                $disk->delete($fileName);
            }

            $this->info("Deleted orphaned variant: {$fileName}");
        }
    }
}
