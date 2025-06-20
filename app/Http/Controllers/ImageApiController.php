<?php

namespace App\Http\Controllers;

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

        if ($image->wasRecentlyCreated) {
            dispatch(new DownloadImageJob($image->id));
        }

        return response()->json([
            'id' => $image->id,
            'status' => $image->status,
            'uid' => $image->uid,
            'original_url' => $image->original_url,
            'last_error' => $image->last_error,
            'variants' => $this->toVariantsArray($image),
            'is_new' => $image->wasRecentlyCreated,
        ], $image->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $image = Image::findOrFail($id);

        return response()->json([
            'id' => $image->id,
            'status' => $image->status,
            'uid' => $image->uid,
            'original_url' => $image->original_url,
            'last_error' => $image->last_error,
            'variants' => $this->toVariantsArray($image),
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
    public function destroy(int $id)
    {
        //
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
                $variants[$variant][$format] = Storage::disk($file['disk'])->url($file['fileName']);
            }
        }

        return $variants;
    }
}
