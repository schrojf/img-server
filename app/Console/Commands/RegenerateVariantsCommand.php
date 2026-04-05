<?php

namespace App\Console\Commands;

use App\Jobs\RegenerateImageVariantsJob;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use App\Variants\ImageVariantRegistry;
use Illuminate\Console\Command;

class RegenerateVariantsCommand extends Command
{
    protected $signature = 'images:regenerate-variants {--dry-run : Show what would be regenerated without dispatching jobs}';

    protected $description = 'Detect variant config changes and dispatch regeneration jobs for affected DONE images';

    public function handle(): int
    {
        $expectedFormats = $this->getExpectedVariantFormats();

        $this->components->info('Registered variants: '.implode(', ', array_keys($expectedFormats)));

        $images = Image::where('status', ImageStatus::DONE)->get();

        if ($images->isEmpty()) {
            $this->info('No images with DONE status found.');

            return self::SUCCESS;
        }

        $isDryRun = $this->option('dry-run');
        $dispatched = 0;
        $skipped = 0;

        foreach ($images as $image) {
            $diff = $this->detectDrift($image, $expectedFormats);

            if ($diff === null) {
                $skipped++;

                continue;
            }

            $dispatched++;
            $this->line("[Image #{$image->id}] {$diff}");

            if (! $isDryRun) {
                RegenerateImageVariantsJob::dispatch($image->id);
            }
        }

        $this->newLine();

        if ($isDryRun) {
            $this->components->info("Dry run: {$dispatched} image(s) would be regenerated, {$skipped} up to date.");
        } else {
            $this->components->info("Dispatched: {$dispatched} job(s), Skipped: {$skipped} (up to date).");
        }

        return self::SUCCESS;
    }

    /**
     * Build a map of expected variant names to sorted format keys from the registry.
     *
     * @return array<string, list<string>>
     */
    protected function getExpectedVariantFormats(): array
    {
        $expected = [];

        foreach (ImageVariantRegistry::all() as $name => $variant) {
            $formats = array_keys($variant->getEncoders());
            sort($formats);
            $expected[$name] = $formats;
        }

        ksort($expected);

        return $expected;
    }

    /**
     * Compare stored variant_files against expected config. Returns a description of the drift, or null if up to date.
     */
    protected function detectDrift(Image $image, array $expectedFormats): ?string
    {
        if (empty($image->variant_files)) {
            return 'variant_files is empty, needs full generation';
        }

        $storedFormats = [];
        foreach ($image->variant_files as $name => $formats) {
            if ($name === '_pending') {
                return 'has interrupted _pending variants';
            }

            $keys = array_keys($formats);
            sort($keys);
            $storedFormats[$name] = $keys;
        }

        ksort($storedFormats);

        if ($storedFormats === $expectedFormats) {
            return null;
        }

        $reasons = [];

        $addedVariants = array_diff(array_keys($expectedFormats), array_keys($storedFormats));
        if (! empty($addedVariants)) {
            $reasons[] = 'new variants: '.implode(', ', $addedVariants);
        }

        $removedVariants = array_diff(array_keys($storedFormats), array_keys($expectedFormats));
        if (! empty($removedVariants)) {
            $reasons[] = 'removed variants: '.implode(', ', $removedVariants);
        }

        $commonVariants = array_intersect(array_keys($expectedFormats), array_keys($storedFormats));
        foreach ($commonVariants as $name) {
            if ($expectedFormats[$name] !== $storedFormats[$name]) {
                $added = array_diff($expectedFormats[$name], $storedFormats[$name]);
                $removed = array_diff($storedFormats[$name], $expectedFormats[$name]);
                $changes = [];
                if (! empty($added)) {
                    $changes[] = '+'.implode(', +', $added);
                }
                if (! empty($removed)) {
                    $changes[] = '-'.implode(', -', $removed);
                }
                $reasons[] = "'{$name}' encoders changed (".implode(', ', $changes).')';
            }
        }

        return implode('; ', $reasons);
    }
}
