<?php

namespace App\Http\Controllers;

use App\Actions\CheckSupportedImageFormatsAction;
use App\Models\Enums\ImageStatus;
use App\Models\Image;
use App\Models\User;
use App\Variants\ImageVariant;
use App\Variants\ImageVariantRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CheckSupportedImageFormatsAction $formatCheck,
    ): View {
        return view('dashboard', [
            'user' => $request->user(),

            'formats' => $formatCheck->handle(),

            'variants' => collect(ImageVariantRegistry::all())->map(fn (ImageVariant $v) => [
                'name' => $v->variantName,
                'modifiers' => collect($v->getModifiers())->map(fn ($m) => $m->toArray())->all(),
                'encoders' => collect($v->getEncoders())->map(fn ($e, $ext) => [
                    'extension' => $ext,
                    'encoder' => class_basename($e),
                ])->values()->all(),
            ])->values()->all(),

            'statuses' => [
                'queued' => Image::where('status', ImageStatus::QUEUED)->count(),
                'processing' => Image::where('status', ImageStatus::PROCESSING)->count(),
                'done' => Image::where('status', ImageStatus::DONE)->count(),
                'failed' => Image::where('status', ImageStatus::FAILED)->count(),
                'expired' => Image::where('status', ImageStatus::EXPIRED)->count(),
                'deleting' => Image::where('status', ImageStatus::DELETING)->count(),
                'total' => Image::count(),
            ],

            'config' => config('images'),

            'tokens' => PersonalAccessToken::with('tokenable')->latest()->get(),

            'users' => User::withCount('tokens')->orderBy('created_at', 'desc')->get(),
        ]);
    }
}
