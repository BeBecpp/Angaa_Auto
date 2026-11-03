<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function show(MediaFile $media, string $variant): StreamedResponse
    {
        Gate::authorize('view', $media);

        $path = $variant === 'thumb' && $media->preview_path ? $media->preview_path : $media->private_path;
        $disk = Storage::disk($media->disk);

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $media->public_id.($variant === 'thumb' ? '_t' : '').'.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
