<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\MediaAsset;
use App\Services\AdminMfaService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate(['page' => 'nullable|integer|min:1|max:10000']);

        return response()->json(MediaAsset::when(! $request->user()->can('articles.review'), fn ($q) => $q->where('owner_id', $request->user()->id))->latest('id')->paginate(24)->through(fn ($media) => $media->only(['id', 'alt_text', 'mime_type', 'width', 'height', 'bytes']) + ['url' => url('/api/v1/media/'.$media->id)]));
    }

    public function upload(Request $request): Response
    {
        $request->validate(['file' => 'required|file|max:5120|mimes:jpg,jpeg,png,webp', 'alt_text' => 'required|string|max:200']);
        $bytes = file_get_contents($request->file('file')->getRealPath());
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) || $info[0] * $info[1] > 12000000 || max($info[0], $info[1]) > 6000) {
            throw ValidationException::withMessages(['file' => 'Gambar tidak valid atau melebihi 12 megapiksel/6.000px.']);
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            throw ValidationException::withMessages(['file' => 'Gambar gagal didekode.']);
        }
        $path = 'media/'.Str::uuid().'.webp';
        ob_start();
        imagewebp($image, null, 85);
        $normalized = ob_get_clean();
        Storage::disk('local')->put($path, $normalized);
        $width = min(400, $info[0]);
        $height = max(1, (int) round($width * $info[1] / $info[0]));
        $thumb = imagecreatetruecolor($width, $height);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        ob_start();
        imagewebp($thumb, null, 80);
        Storage::disk('local')->put(str_replace('.webp', '-thumb.webp', $path), ob_get_clean());
        imagedestroy($image);
        imagedestroy($thumb);
        $media = MediaAsset::create(['owner_id' => $request->user()->id, 'path' => $path, 'mime_type' => 'image/webp', 'bytes' => strlen($normalized), 'width' => $info[0], 'height' => $info[1], 'alt_text' => $request->string('alt_text')->toString()]);
        AuditService::record('media.uploaded', $request->user(), ['media_id' => $media->id]);

        return response()->json(['data' => $media->only(['id', 'alt_text', 'width', 'height']) + ['url' => url('/api/v1/media/'.$media->id)]], 201);
    }

    public function show(Request $request, MediaAsset $media): Response
    {
        $published = Article::published()->where(fn ($q) => $q->where('cover_media_id', $media->id)->orWhere('body_html', 'like', '%/api/v1/media/'.$media->id.'"%')->orWhere('body_html', 'like', '%/api/v1/media/'.$media->id.'?size=thumb"%'))->exists();
        $user = $request->user();
        $authorized = $user && $user->status === 'active' && $request->session()->get('auth_version') === $user->auth_version && AdminMfaService::verified($request) && ($user->can('articles.review') || ($user->can('articles.create') && $media->owner_id === $user->id));
        abort_unless($published || $authorized, 404);
        $path = $request->query('size') === 'thumb' ? str_replace('.webp', '-thumb.webp', $media->path) : $media->path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => $published ? 'public, max-age=86400' : 'private, no-store']);
    }

    private function referencesMedia(array $snapshot, int $mediaId): bool
    {
        if ((int) ($snapshot['cover_media_id'] ?? 0) === $mediaId) {
            return true;
        }
        $pattern = '~/api/v1/media/'.$mediaId.'(?=[?"\'\s<>]|$)~';
        if (preg_match($pattern, (string) ($snapshot['body_html'] ?? ''))) {
            return true;
        }
        $document = $snapshot['body_doc'] ?? null;
        if (is_string($document)) {
            $document = json_decode($document, true);
        }

        return is_array($document) && preg_match($pattern, json_encode($document, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) === 1;
    }

    public function destroy(Request $request, MediaAsset $media): Response
    {
        $path = DB::transaction(function () use ($request, $media): string {
            $media = MediaAsset::whereKey($media->id)->lockForUpdate()->firstOrFail();
            abort_unless($media->owner_id === $request->user()->id || $request->user()->can('articles.review'), 403);
            $referenced = Article::withTrashed()->select('cover_media_id', 'body_html', 'body_doc')->cursor()->contains(fn (Article $article): bool => $this->referencesMedia($article->toArray(), $media->id));
            $retained = ArticleRevision::select('snapshot')->cursor()->contains(fn (ArticleRevision $revision): bool => $this->referencesMedia($revision->snapshot, $media->id));
            if ($referenced || $retained) {
                throw ValidationException::withMessages(['media' => 'Gambar masih digunakan oleh artikel atau riwayat revisi. Gambar dipertahankan agar publikasi dan pemulihan revisi tetap utuh.']);
            }
            $path = $media->path;
            $media->delete();
            AuditService::record('media.deleted', $request->user(), ['media_id' => $media->id]);

            return $path;
        });
        Storage::disk('local')->delete([$path, str_replace('.webp', '-thumb.webp', $path)]);

        return response()->noContent();
    }
}
