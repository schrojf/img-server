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
     */
    protected $signature = 'images:cleanup-orphaned {--d|dry-run : List orphaned files but do not delete them}';

    /**
     * The console command description.
     */
    protected $description = 'Remove orphaned image and variant files from storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->line($isDryRun ? '🔍 Running in dry-run mode. No files will be deleted.' : '🧹 Running cleanup of orphaned files...');

        $images = $this->checkForOrphanedImageFiles($isDryRun);
        $variants = $this->checkForOrphanedVariants($isDryRun);

        $this->info("✅ Cleanup completed. Orphaned files handled: {$images} originals, {$variants} variants.");

        return self::SUCCESS;
    }

    protected function checkForOrphanedImageFiles(bool $dryRun): int
    {
        $disk = ImageStorage::originalDisk();
        $orphanedCount = 0;

        $this->line('🔎 Scanning original image files...');

        foreach ($disk->files(recursive: true) as $fileName) {
            if (in_array($fileName, self::IGNORED_FILES)) {
                continue;
            }

            if (Image::where('image_file->file_name', $fileName)->exists()) {
                continue;
            }

            $orphanedCount++;
            if ($dryRun) {
                $this->warn("Would delete orphaned file: {$fileName}");
            } else {
                $disk->delete($fileName);
                $this->info("Deleted orphaned file: {$fileName}");
            }
        }

        return $orphanedCount;
    }

    protected function checkForOrphanedVariants(bool $dryRun): int
    {
        $disk = ImageStorage::variantDisk();
        $orphanedCount = 0;

        $this->line('🔎 Scanning variant files...');

        foreach ($disk->files(recursive: true) as $fileName) {
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

            $orphanedCount++;
            if ($dryRun) {
                $this->warn("Would delete orphaned variant: {$fileName}");
            } else {
                $disk->delete($fileName);
                $this->info("Deleted orphaned variant: {$fileName}");
            }
        }

        return $orphanedCount;
    }
}
