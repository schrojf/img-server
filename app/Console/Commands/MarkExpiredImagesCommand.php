<?php

namespace App\Console\Commands;

use App\Actions\MarkExpiredImagesAction;
use Illuminate\Console\Command;

class MarkExpiredImagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark stale processing images as expired';

    /**
     * Execute the console command.
     */
    public function handle(MarkExpiredImagesAction $action): int
    {
        $count = $action->handle();
        $this->info("Marked {$count} images as expired.");

        return self::SUCCESS;
    }
}
