<?php

namespace App\Console\Commands;

use App\Actions\CheckSupportedImageFormatsAction;
use Illuminate\Console\Command;

class CheckForSupportedImageFormatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:supported-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for supported image formats';

    /**
     * Execute the console command.
     */
    public function handle(CheckSupportedImageFormatsAction $action): int
    {
        $this->warn('Checking for supported image formats...');

        $results = $action->handle();

        $rows = array_map(fn (array $result) => [
            $result['mime'],
            $result['extension'],
            $result['supported'] ? '✅️' : '❌',
            $result['message'],
        ], $results);

        $this->table(['MIME Type', 'Extension', 'Supported', 'Message'], $rows);

        return 0;
    }
}
