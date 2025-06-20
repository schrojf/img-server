<?php

namespace App\Actions;

use App\Models\Enums\ImageStatus;
use App\Models\Image;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MarkExpiredImagesAction
{
    /**
     * Images stuck in 'processing' longer than this (in hours) will be marked expired.
     */
    protected int $afterHours;

    /**
     * Maximum number of records to process per batch.
     */
    protected int $batchCount;

    public function __construct(int $afterHours = 24, int $batchCount = 50)
    {
        $this->afterHours = $afterHours;
        $this->batchCount = $batchCount;
    }

    /**
     * Mark expired images and return the number of images processed.
     */
    public function handle(): int
    {
        $processed = 0;

        do {
            $count = DB::transaction(function () {
                $threshold = Carbon::now()->subHours($this->afterHours);

                $images = Image::where('status', ImageStatus::PROCESSING)
                    ->where('updated_at', '<', $threshold)
                    ->lockForUpdate()
                    ->limit($this->batchCount)
                    ->get();

                foreach ($images as $image) {
                    $image->update(['status' => ImageStatus::EXPIRED]);
                }

                return $images->count();
            });

            $processed += $count;
        } while ($count === $this->batchCount);

        return $processed;
    }
}
