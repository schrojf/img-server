<?php

namespace App\Console\Commands;

use App\Actions\DownloadImageAction;
use App\Actions\GenerateRandomHashFileNameAction;
use App\Actions\TempFileDownloadAction;
use App\Jobs\DownloadImageJob;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class DownloadTestImageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:download-test-image';

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
    protected $description = 'Download test image and generate all registered variants.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (App::environment('production')) {
            $this->warn('❌ This command is not allowed in the production environment.');

            return self::FAILURE;
        }

        $url = $this->components->choice('Url to download', [
            'https://picsum.photos/5000/4000.jpg',
            'https://sampletestfile.com/wp-content/uploads/2023/05/7.2-MB.jpg',
            'https://sampletestfile.com/wp-content/uploads/2023/08/11.5-MB.png',
            'https://sampletestfile.com/wp-content/uploads/2023/05/15.8-MB.bmp',
            'https://sample-videos.com/img/Sample-jpg-image-20mb.jpg',
            'https://graydart.com/app-media/img/jpg/dev_sample_jpg_30mbmb.jpg',
            'https://sample-videos.com/img/Sample-jpg-image-30mb.jpg',
        ], 0);

        $image = Image::create([
            'status' => ImageStatus::QUEUED,
            'original_url' => $url,
            'uid' => hash('xxh128', $url.Str::random()),
        ]);

        $downloadImageAction = new DownloadImageAction(
            new TempFileDownloadAction,
            new GenerateRandomHashFileNameAction,
        );

        if (false) {
            $result = $downloadImageAction->handle($image);
        } else {
            Config::set('queue.default', 'sync');
            $downloadImageJob = new DownloadImageJob($image->id);
            $downloadImageJob->handle($downloadImageAction);
        }

        $this->info('✅ Test image downloaded and variants generated successfully.');
        dump($image->fresh()->toArray(), Number::fileSize(memory_get_peak_usage()));

        return self::SUCCESS;
    }
}
