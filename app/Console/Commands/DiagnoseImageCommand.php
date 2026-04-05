<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Support\ImageFile;
use App\Variants\ImageVariantRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DiagnoseImageCommand extends Command
{
    protected $signature = 'images:doctor {id : Image ID to diagnose}';

    protected $description = 'Run a complete diagnostic on an image without taking any action';

    public function handle(): int
    {
        $image = Image::find($this->argument('id'));

        if ($image === null) {
            $this->error("Image #{$this->argument('id')} not found.");

            return self::FAILURE;
        }

        $this->printGeneralInfo($image);
        $this->printImageFileInfo($image);
        $this->printVariantFilesInfo($image);
        $this->printErrorInfo($image);

        return self::SUCCESS;
    }

    protected function printGeneralInfo(Image $image): void
    {
        $this->components->twoColumnDetail('<fg=cyan>ID</>', (string) $image->id);
        $this->components->twoColumnDetail('<fg=cyan>UID</>', $image->uid);
        $this->components->twoColumnDetail('<fg=cyan>Status</>', $this->formatStatus($image));
        $this->components->twoColumnDetail('<fg=cyan>Original URL</>', $image->original_url);
        $this->components->twoColumnDetail('<fg=cyan>Created at</>', $image->created_at?->toDateTimeString() ?? '-');
        $this->components->twoColumnDetail('<fg=cyan>Updated at</>', $image->updated_at?->toDateTimeString() ?? '-');
        $this->components->twoColumnDetail('<fg=cyan>Downloaded at</>', $image->downloaded_at?->toDateTimeString() ?? '-');
        $this->components->twoColumnDetail('<fg=cyan>Processed at</>', $image->processed_at?->toDateTimeString() ?? '-');
    }

    protected function printImageFileInfo(Image $image): void
    {
        $this->newLine();
        $this->components->info('Original Image File');

        if (empty($image->image_file)) {
            $this->components->warn('image_file is null — image was never downloaded.');

            return;
        }

        $imageFile = ImageFile::fromArray($image->image_file);

        $this->components->twoColumnDetail('Disk', $imageFile->disk);
        $this->components->twoColumnDetail('File name', $imageFile->fileName);
        $this->components->twoColumnDetail('MIME type', $imageFile->mimeType);
        $this->components->twoColumnDetail('Dimensions (DB)', "{$imageFile->width} x {$imageFile->height}");
        $this->components->twoColumnDetail('Size (DB)', $this->formatBytes($imageFile->size));

        if (! $imageFile->stillExists()) {
            $this->components->error('File does NOT exist on disk.');

            return;
        }

        $diskSize = Storage::disk($imageFile->disk)->size($imageFile->fileName);
        $sizeMatch = $diskSize === $imageFile->size;

        $this->components->twoColumnDetail('Exists on disk', '<fg=green>Yes</>');
        $this->components->twoColumnDetail('Size (disk)', $this->formatBytes($diskSize).($sizeMatch ? '' : ' <fg=red>MISMATCH</>'));
        $this->components->twoColumnDetail('Full path', $imageFile->fullPath());
    }

    protected function printVariantFilesInfo(Image $image): void
    {
        $this->newLine();
        $this->components->info('Variant Files');

        if (empty($image->variant_files)) {
            $this->components->warn('variant_files is null — variants were never generated.');

            return;
        }

        if (isset($image->variant_files['_pending'])) {
            $count = count($image->variant_files['_pending']);
            $this->components->warn("Generation was interrupted — {$count} pending file(s) found.");

            $this->table(
                ['Disk', 'File name', 'On disk'],
                collect($image->variant_files['_pending'])->map(fn (array $file) => [
                    $file['disk'],
                    $file['file_name'],
                    Storage::disk($file['disk'])->exists($file['file_name']) ? '<fg=green>Yes</>' : '<fg=red>No</>',
                ])->all(),
            );

            return;
        }

        $registeredVariants = ImageVariantRegistry::names();
        $rows = [];

        foreach ($image->variant_files as $variant => $formats) {
            foreach ($formats as $format => $file) {
                $exists = Storage::disk($file['disk'])->exists($file['file_name']);
                $diskSize = $exists ? Storage::disk($file['disk'])->size($file['file_name']) : null;
                $dbSize = $file['size'] ?? null;
                $sizeMatch = $exists && $dbSize !== null && $diskSize === $dbSize;

                $rows[] = [
                    $variant,
                    $format,
                    $file['file_name'],
                    $dbSize !== null ? $this->formatBytes($dbSize) : '-',
                    $exists ? '<fg=green>Yes</>' : '<fg=red>No</>',
                    $exists ? $this->formatBytes($diskSize).($sizeMatch ? '' : ' <fg=red>MISMATCH</>') : '-',
                ];
            }
        }

        $this->table(['Variant', 'Format', 'File name', 'Size (DB)', 'On disk', 'Size (disk)'], $rows);

        $presentVariants = array_keys($image->variant_files);
        $missingVariants = array_diff($registeredVariants, $presentVariants);

        if (! empty($missingVariants)) {
            $this->components->warn('Missing registered variants: '.implode(', ', $missingVariants));
        }

        $missingFiles = collect($rows)->filter(fn (array $row) => str_contains($row[4], 'No'))->count();
        $sizeMismatches = collect($rows)->filter(fn (array $row) => str_contains($row[5], 'MISMATCH'))->count();

        if ($missingFiles > 0) {
            $this->components->error("{$missingFiles} variant file(s) missing on disk.");
        }

        if ($sizeMismatches > 0) {
            $this->components->error("{$sizeMismatches} variant file(s) have size mismatches.");
        }

        if ($missingFiles === 0 && $sizeMismatches === 0 && empty($missingVariants)) {
            $this->components->info('All variant files present and sizes match.');
        }
    }

    protected function printErrorInfo(Image $image): void
    {
        if (empty($image->last_error)) {
            return;
        }

        $this->newLine();
        $this->components->info('Last Error');
        $this->line($image->last_error);
    }

    protected function formatStatus(Image $image): string
    {
        return match ($image->status->value) {
            'done' => '<fg=green>done</>',
            'failed' => '<fg=red>failed</>',
            'expired' => '<fg=yellow>expired</>',
            'processing', 'queued' => '<fg=blue>'.$image->status->value.'</>',
            'deleting' => '<fg=gray>deleting</>',
            default => $image->status->value,
        };
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));

        return round($bytes / pow(1024, $i), 2).' '.$units[$i];
    }
}
