<?php

namespace App\Http\Controllers;

use App\Jobs\DownloadImageJob;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'total' =>  Image::count(),
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
            'original_url' => $data['url'],
        ]);

        if ($image->wasRecentlyCreated) {
            dispatch(new DownloadImageJob($image->id));
        }

        return response()->json([
            'image' => $image,
            'is_new' => $image->wasRecentlyCreated,
        ], $image->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        //
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
}
