<?php

namespace App\Console\Commands;

use App\Models\Enums\ImageStatus;
use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RenameAdSegmentsCommand extends Command
{
    protected $signature = 'images:rename-ad-segments
        {--dry-run : Show affected images without renaming}';

    protected $description = 'Rename image files with "ad" directory segments to "ia" to avoid ad-blocker false positives';

    protected int $renamed = 0;

    protected int $resumed = 0;

    protected int $errors = 0;

    protected const SUPPORTED_DRIVERS = ['mysql', 'mariadb'];

    public function handle(): int
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, self::SUPPORTED_DRIVERS)) {
            $this->components->error(
                "Unsupported database driver \"{$driver}\". This command requires MySQL or MariaDB for JSON path queries."
            );

            return self::FAILURE;
        }

        $isDryRun = $this->option('dry-run');

        $this->resumeInterruptedRenames($isDryRun);
        $this->renameAffectedImages($isDryRun);

        $this->newLine();

        if ($isDryRun) {
            $this->components->info('Dry run complete. No files were modified.');
        } else {
            $this->components->info(
                "Done. Renamed: {$this->renamed}, Resumed: {$this->resumed}, Errors: {$this->errors}"
            );
        }

        return $this->errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function resumeInterruptedRenames(bool $isDryRun): void
    {
        $pending = Image::whereNotNull('image_file')
            ->whereRaw("JSON_CONTAINS_PATH(image_file, 'one', '\$._rename_pending')")
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        $this->components->warn("Found {$pending->count()} interrupted rename(s) from a previous run.");

        foreach ($pending as $image) {
            $renameMap = $image->image_file['_rename_pending'] ?? [];

            if (empty($renameMap)) {
                continue;
            }

            if ($isDryRun) {
                $this->line("  [Image #{$image->id}] Would resume interrupted rename (".count($renameMap).' file(s))');

                continue;
            }

            if ($this->executeRenames($image->id, $renameMap)) {
                $this->finalizeRename($image, $renameMap);
                $this->resumed++;
                $this->line("  [Image #{$image->id}] Resumed and completed.");
            }
        }
    }

    protected function renameAffectedImages(bool $isDryRun): void
    {
        $affected = Image::where('status', ImageStatus::DONE)
            ->whereNotNull('image_file')
            ->whereRaw("NOT JSON_CONTAINS_PATH(image_file, 'one', '\$._rename_pending')")
            ->whereRaw("(
                JSON_UNQUOTE(JSON_EXTRACT(image_file, '$.file_name')) LIKE 'ad/%'
                OR JSON_UNQUOTE(JSON_EXTRACT(image_file, '$.file_name')) LIKE '__/ad/%'
                OR JSON_UNQUOTE(JSON_EXTRACT(image_file, '$.file_name')) LIKE '__/__/ad/%'
            )")
            ->get();

        if ($affected->isEmpty()) {
            $this->components->info('No images with "ad" directory segments found.');

            return;
        }

        $this->components->info("Found {$affected->count()} image(s) with \"ad\" directory segments.");

        foreach ($affected as $image) {
            $renameMap = $this->buildRenameMap($image);

            if (empty($renameMap)) {
                continue;
            }

            if ($isDryRun) {
                $oldPath = $image->image_file['file_name'];
                $newPath = $this->transformPath($oldPath);
                $this->line("  [Image #{$image->id}] {$oldPath} → {$newPath} (".count($renameMap).' file(s))');

                continue;
            }

            // Step 1: Save pending rename map to DB before touching filesystem
            $imageFile = $image->image_file;
            $imageFile['_rename_pending'] = $renameMap;
            $image->update(['image_file' => $imageFile]);

            // Step 2: Rename files on filesystem
            if ($this->executeRenames($image->id, $renameMap)) {
                // Step 3: Update filenames in DB and remove pending flag
                $this->finalizeRename($image, $renameMap);
                $this->renamed++;
                $this->line("  [Image #{$image->id}] Renamed.");
            }
        }
    }

    protected function executeRenames(int $imageId, array $renameMap): bool
    {
        foreach ($renameMap as $entry) {
            $disk = Storage::disk($entry['disk']);
            $oldExists = $disk->exists($entry['old']);
            $newExists = $disk->exists($entry['new']);

            if ($newExists) {
                // Already renamed from a partial previous run
                continue;
            }

            if (! $oldExists) {
                $this->components->error(
                    "[Image #{$imageId}] Missing file: {$entry['old']} (disk: {$entry['disk']})"
                );
                $this->errors++;

                return false;
            }

            $disk->move($entry['old'], $entry['new']);
        }

        return true;
    }

    protected function finalizeRename(Image $image, array $renameMap): void
    {
        $pathMap = [];
        foreach ($renameMap as $entry) {
            $pathMap[$entry['old']] = $entry['new'];
        }

        $imageFile = $image->image_file;
        if (isset($pathMap[$imageFile['file_name']])) {
            $imageFile['file_name'] = $pathMap[$imageFile['file_name']];
        }
        unset($imageFile['_rename_pending']);

        $variantFiles = $image->variant_files;
        if (is_array($variantFiles)) {
            foreach ($variantFiles as $variant => $formats) {
                foreach ($formats as $format => $file) {
                    if (isset($file['file_name'], $pathMap[$file['file_name']])) {
                        $variantFiles[$variant][$format]['file_name'] = $pathMap[$file['file_name']];
                    }
                }
            }
        }

        $image->update([
            'image_file' => $imageFile,
            'variant_files' => $variantFiles,
        ]);
    }

    protected function buildRenameMap(Image $image): array
    {
        $map = [];

        if (! empty($image->image_file['file_name'])) {
            $newPath = $this->transformPath($image->image_file['file_name']);
            if ($newPath !== null) {
                $map[] = [
                    'disk' => $image->image_file['disk'],
                    'old' => $image->image_file['file_name'],
                    'new' => $newPath,
                ];
            }
        }

        if (is_array($image->variant_files)) {
            foreach ($image->variant_files as $formats) {
                foreach ($formats as $file) {
                    if (! isset($file['file_name'])) {
                        continue;
                    }

                    $newPath = $this->transformPath($file['file_name']);
                    if ($newPath !== null) {
                        $map[] = [
                            'disk' => $file['disk'],
                            'old' => $file['file_name'],
                            'new' => $newPath,
                        ];
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Replace 'ad' directory segments with 'ia' in a file path.
     * Returns null if no change is needed.
     */
    protected function transformPath(string $path): ?string
    {
        $parts = explode('/', $path);

        if (count($parts) < 4) {
            return null;
        }

        $changed = false;

        for ($i = 0; $i < 3; $i++) {
            if ($parts[$i] === 'ad') {
                $parts[$i] = 'ia';
                $changed = true;
            }
        }

        if (! $changed) {
            return null;
        }

        // Fix corresponding positions in the filename hash
        $filename = $parts[3];
        for ($i = 0; $i < 3; $i++) {
            $offset = $i * 2;
            if (substr($filename, $offset, 2) === 'ad') {
                $filename = substr($filename, 0, $offset).'ia'.substr($filename, $offset + 2);
            }
        }
        $parts[3] = $filename;

        return implode('/', $parts);
    }
}
