<?php

namespace App\Http\Controllers;

use App\Actions\DeleteImageAction;
use App\Jobs\DownloadImageAndGenerateImageVariantsJob;
use App\Jobs\DownloadImageJob;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'queued' => Image::where('status', ImageStatus::QUEUED)->count(),
            'processing' => Image::where('status', ImageStatus::PROCESSING)->count(),
            'done' => Image::where('status', ImageStatus::DONE)->count(),
            'failed' => Image::where('status', ImageStatus::FAILED)->count(),
            'expired' => Image::where('status', ImageStatus::EXPIRED)->count(),
            'deleting' => Image::where('status', ImageStatus::DELETING)->count(),
            'total' => Image::count(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => 'required|url',
        ]);

        $uid = hash('xxh128', $data['url']);

        $image = Image::firstOrCreate([
            'uid' => $uid,
        ], [
            'status' => ImageStatus::QUEUED,
            'original_url' => $data['url'],
        ]);

        abort_if($image->status === ImageStatus::DELETING, 404);

        if ($image->wasRecentlyCreated) {
            $dispatchMethod = config('images.jobs.dispatch');

            if ($dispatchMethod === 'sync') {
                DownloadImageJob::dispatchSync($image->id);
                $image->refresh();
            } elseif ($dispatchMethod === 'batch') {
                DownloadImageAndGenerateImageVariantsJob::dispatch($image->id);
            } elseif ($dispatchMethod === 'chain') {
                DownloadImageJob::dispatch($image->id);
            }
        }

        return response()->json([
            'id' => $image->id,
            'status' => $image->status,
            'uid' => $image->uid,
            'original_url' => $image->original_url,
            'last_error' => $image->last_error,
            'variants' => $this->toVariantsArray($image),
            'downloaded_at' => $image->downloaded_at?->toISOString(),
            'processed_at' => $image->processed_at?->toISOString(),
            'is_new' => $image->wasRecentlyCreated,
        ], $image->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $image = Image::findOrFail($id);

        abort_if($image->status === ImageStatus::DELETING, 404);

        return response()->json([
            'id' => $image->id,
            'status' => $image->status,
            'uid' => $image->uid,
            'original_url' => $image->original_url,
            'last_error' => $image->last_error,
            'variants' => $this->toVariantsArray($image),
            'downloaded_at' => $image->downloaded_at?->toISOString(),
            'processed_at' => $image->processed_at?->toISOString(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id, DeleteImageAction $deleteImageAction)
    {
        $image = Image::findOrFail($id);

        abort_if($image->status === ImageStatus::DELETING, 404);

        // if ($image->status !== ImageStatus::PROCESSING) {
        //     response()->json([
        //         'error' => true,
        //         'message' => 'Resource is locked.',
        //     ], 503);
        // }

        $deleteImageAction->handle($image->id);

        return response()->noContent();
    }

    /**
     * Transform generated image variants into the API response array.
     */
    protected function toVariantsArray(Image $image): array
    {
        if ($image->status !== ImageStatus::DONE) {
            return [];
        }

        $variants = [];
        foreach ($image->variant_files as $variant => $formats) {
            foreach ($formats as $format => $file) {
                $variants[$variant][$format] = Storage::disk($file['disk'])->url($file['file_name']);
            }
        }

        return $variants;
    }
}
