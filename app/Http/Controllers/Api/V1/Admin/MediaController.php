<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Car;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Media;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class MediaController extends Controller
{
    public function store(Request $request, string $type, int $id, AuditLogger $audit): MediaResource
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'collection' => ['required', Rule::in(['cover', 'gallery', 'profile'])],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
        $subject = $this->subject($type, $id);
        $replacedMedia = in_array($validated['collection'], ['cover', 'profile'], true)
            ? $subject->media()->where('collection', $validated['collection'])->get()
            : collect();
        $file = $request->file('file');
        $disk = (string) config('filesystems.default', 'public');
        abort_if($disk === 'local', 500, 'FILESYSTEM_DISK must be a publicly addressable disk for website media.');
        $path = $file->storeAs("media/{$type}", Str::uuid().'.'.$file->extension(), $disk);
        abort_if($path === false, 500, 'Media storage failed.');

        try {
            $media = $subject->media()->create([
                'collection' => $validated['collection'],
                'disk' => $disk,
                'path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'alt_text' => $validated['alt_text'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
        foreach ($replacedMedia as $replaced) {
            Storage::disk($replaced->disk)->delete($replaced->path);
            $replaced->delete();
        }
        $audit->record($request->user(), 'media.uploaded', $media, [], $media->toArray(), $request->ip());

        return new MediaResource($media);
    }

    public function destroy(Request $request, Media $media, AuditLogger $audit): Response
    {
        $old = $media->toArray();
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
        $audit->record($request->user(), 'media.deleted', $media, $old, [], $request->ip());

        return response()->noContent();
    }

    private function subject(string $type, int $id): Model
    {
        $class = match ($type) {
            'tours' => Tour::class,
            'destinations' => Destination::class,
            'cars' => Car::class,
            'drivers' => Driver::class,
            'tour-categories' => TourCategory::class,
            default => abort(404),
        };

        return $class::query()->findOrFail($id);
    }
}
