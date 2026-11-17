<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\AuthorProfile;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\ArticleContent;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ArticleController extends Controller
{
    public function index(Request $request, ArticleContent $content): Response
    {
        $input = $request->validate(['query' => 'nullable|string|max:100', 'category' => 'nullable|string|max:200', 'author' => 'nullable|string|max:200', 'tag' => 'nullable|string|max:64', 'sort' => 'nullable|in:newest,oldest', 'type' => 'nullable|in:education,research', 'status' => 'nullable|in:draft,in_review,scheduled,published,archived', 'page' => 'nullable|integer|min:1|max:10000']);
        $query = Article::with(['author', 'category', 'cover']);
        if ($request->is('api/v1/admin/*')) {
            if (! $request->user()->can('articles.review') && ! $request->user()->can('articles.publish')) {
                $query->whereHas('author', fn ($author) => $author->where('user_id', $request->user()->id));
            }
            if (! empty($input['status'])) {
                $query->where('status', $input['status']);
            }
        } else {
            $query->published();
        }
        if (! empty($input['query'])) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$input['query'].'%')->orWhere('excerpt', 'like', '%'.$input['query'].'%'));
        }
        if (! empty($input['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $input['category']));
        }
        if (! empty($input['author'])) {
            $query->whereHas('author', fn ($q) => $q->where('slug', $input['author']));
        }
        if (! empty($input['type'])) {
            $query->where('content_type', $input['type']);
        }
        if (! empty($input['tag'])) {
            $query->whereJsonContains('tags', $input['tag']);
        }

        return response()->json($query->orderBy('published_at', ($input['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc')->orderByDesc('id')->paginate(12)->through(fn (Article $article) => $content->present($article)));
    }

    public function show(string $slug, ArticleContent $content): Response
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $content->present($article, true), 'related' => Article::published()->where('primary_category_id', $article->primary_category_id)->where('id', '!=', $article->id)->with(['author', 'category', 'cover'])->latest('published_at')->limit(3)->get()->map(fn ($item) => $content->present($item))]);
    }

    public function edit(Article $article, ArticleContent $content): Response
    {
        Gate::authorize('view', $article);

        return response()->json(['data' => $content->present($article, true, true), 'can_delete' => Gate::allows('delete', $article), 'preview_url' => URL::temporarySignedRoute('article.preview', now()->addMinutes(15), ['article' => $article->id])]);
    }

    private function validated(Request $request, ?Article $article): array
    {
        if (! $request->filled('slug')) {
            $request->merge(['slug' => $article?->slug ?? $this->availableSlug((string) $request->input('title', ''))]);
        }
        $input = $request->validate(['title' => 'required|string|min:5|max:200', 'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:180', Rule::unique('articles')->ignore($article?->id)],
            'author_id' => 'required|integer|exists:author_profiles,id', 'primary_category_id' => 'required|integer|exists:categories,id', 'cover_media_id' => 'nullable|integer|exists:media_assets,id',
            'excerpt' => 'required|string|max:1000', 'body_html' => 'required|string|max:200000', 'body_doc' => 'nullable|array', 'sources' => 'required|array|min:1|max:30', 'sources.*.title' => 'required|string|max:200', 'sources.*.url' => 'required|url:https|max:2000',
            'tags' => 'present|array|max:20', 'tags.*' => 'string|max:64', 'content_type' => 'required|in:education,research', 'seo_title' => 'nullable|string|max:200', 'seo_description' => 'nullable|string|max:320', 'allow_comments' => 'required|boolean', 'is_featured' => 'required|boolean', 'revision' => 'nullable|integer|min:1', 'change_note' => 'nullable|string|max:200']);
        $profile = AuthorProfile::with('user')->findOrFail($input['author_id']);
        if (! $profile->user || $profile->user->status !== 'active' || ! $profile->user->hasPermission('articles.create')) {
            throw ValidationException::withMessages(['author_id' => 'Penulis harus memiliki akun aktif dengan izin menulis artikel.']);
        }
        if (! $request->user()->can('articles.review') && $profile->user_id !== $request->user()->id) {
            abort(403);
        }
        if ($input['cover_media_id'] ?? null) {
            $cover = MediaAsset::findOrFail($input['cover_media_id']);
            abort_unless($cover->owner_id === $request->user()->id || $request->user()->can('articles.review'), 403);
        }
        if (DB::table('article_slug_redirects')->where('old_slug', $input['slug'])->when($article, fn ($q) => $q->where('article_id', '!=', $article->id))->exists()) {
            throw ValidationException::withMessages(['slug' => 'Slug sudah digunakan dalam riwayat permalink.']);
        }
        $input['body_html'] = app(ArticleContent::class)->sanitize($input['body_html']);
        preg_match_all('~/api/v1/media/([0-9]+)~', $input['body_html'], $embedded);
        foreach (array_unique($embedded[1]) as $mediaId) {
            $image = MediaAsset::find($mediaId);
            abort_unless($image && ($image->owner_id === $request->user()->id || $request->user()->can('articles.review')), 403, 'Gambar bukan milik penulis atau tidak tersedia.');
        }
        if (mb_strlen(trim(strip_tags($input['body_html']))) < 20) {
            throw ValidationException::withMessages(['body_html' => 'Isi artikel minimal 20 karakter teks.']);
        }

        return $input;
    }

    private function availableSlug(string $title): string
    {
        $base = rtrim(Str::limit(Str::slug($title), 170, ''), '-') ?: 'artikel';
        $candidate = $base;
        $number = 2;
        while (Article::withTrashed()->where('slug', $candidate)->exists() || DB::table('article_slug_redirects')->where('old_slug', $candidate)->exists()) {
            $candidate = $base.'-'.$number++;
        }

        return $candidate;
    }

    public function save(Request $request, ArticleContent $content, ?Article $article = null): Response
    {
        $article ? Gate::authorize('update', $article) : Gate::authorize('create', Article::class);
        $input = $this->validated($request, $article);
        $article = DB::transaction(function () use ($article, $input, $request): Article {
            if (! Category::whereKey($input['primary_category_id'])->lockForUpdate()->first() || ! AuthorProfile::whereKey($input['author_id'])->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['primary_category_id' => 'Kategori atau profil penulis telah dihapus. Muat ulang pilihan sebelum menyimpan.']);
            }
            preg_match_all('~/api/v1/media/([0-9]+)~', $input['body_html'], $embedded);
            $mediaIds = array_values(array_unique(array_filter(array_merge($embedded[1], [$input['cover_media_id'] ?? null]))));
            sort($mediaIds, SORT_NUMERIC);
            foreach ($mediaIds as $mediaId) {
                $media = MediaAsset::whereKey($mediaId)->lockForUpdate()->first();
                if (! $media) {
                    throw ValidationException::withMessages(['cover_media_id' => 'Gambar telah dihapus. Pilih atau unggah gambar yang tersedia.']);
                }
                abort_unless($media->owner_id === $request->user()->id || $request->user()->can('articles.review'), 403);
            }
            $expected = $input['revision'] ?? null;
            $note = $input['change_note'] ?? 'Simpan artikel';
            unset($input['revision'],$input['change_note']);
            if ($article) {
                $article = Article::whereKey($article->id)->lockForUpdate()->firstOrFail();
                Gate::authorize('update', $article);
                if ($expected !== null && $expected !== $article->revision) {
                    abort(409, 'Artikel berubah di sesi lain. Muat ulang sebelum menyimpan.');
                }
                if ($article->slug !== $input['slug']) {
                    DB::table('article_slug_redirects')->where('old_slug', $input['slug'])->where('article_id', $article->id)->delete();
                    DB::table('article_slug_redirects')->updateOrInsert(['old_slug' => $article->slug], ['article_id' => $article->id]);
                }
                $article->fill($input)->forceFill(['revision' => $article->revision + 1])->save();
            } else {
                $article = Article::create($input + ['status' => 'draft']);
            }
            ArticleRevision::create(['article_id' => $article->id, 'editor_id' => $request->user()->id, 'version' => $article->revision, 'snapshot' => $article->getAttributes(), 'change_note' => $note]);
            AuditService::record('article.saved', $request->user(), ['article_id' => $article->id, 'revision' => $article->revision]);

            return $article;
        });

        return response()->json(['data' => $content->present($article->fresh(), true, true)]);
    }

    public function transition(Request $request, Article $article): Response
    {
        $input = $request->validate(['status' => 'required|in:draft,in_review,scheduled,published,archived', 'scheduled_at' => 'nullable|date|after:now']);
        if ($input['status'] === 'scheduled' && empty($input['scheduled_at'])) {
            throw ValidationException::withMessages(['scheduled_at' => 'Tanggal publikasi wajib diisi.']);
        }
        $article = DB::transaction(function () use ($article, $input, $request): Article {
            $article = Article::whereKey($article->id)->lockForUpdate()->firstOrFail();
            if (in_array($input['status'], ['scheduled', 'published', 'archived'], true) || in_array($article->status, ['published', 'scheduled'], true)) {
                Gate::authorize('publish', $article);
            } else {
                Gate::authorize('update', $article);
            }
            $article->update(['status' => $input['status'], 'scheduled_at' => $input['status'] === 'scheduled' ? $input['scheduled_at'] : null, 'published_at' => $input['status'] === 'published' ? ($article->published_at ?? now()) : null]);
            AuditService::record('article.status_changed', $request->user(), ['article_id' => $article->id, 'status' => $article->status]);

            return $article;
        });

        return response()->json(['data' => app(ArticleContent::class)->present($article, true, true)]);
    }

    public function destroy(Request $request, Article $article): Response
    {
        DB::transaction(function () use ($request, $article): void {
            $article = Article::whereKey($article->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('delete', $article);
            $article->delete();
            AuditService::record('article.deleted', $request->user(), ['article_id' => $article->id]);
        });

        return response()->noContent();
    }

    public function revisions(Article $article): Response
    {
        Gate::authorize('view', $article);

        return response()->json(['data' => ArticleRevision::where('article_id', $article->id)->orderByDesc('version')->limit(100)->get(['id', 'version', 'change_note', 'created_at'])]);
    }

    public function revision(Article $article, ArticleRevision $revision): Response
    {
        Gate::authorize('view', $article);
        abort_unless($revision->article_id === $article->id, 404);

        return response()->json(['data' => $revision->snapshot]);
    }

    public function restore(Request $request, Article $article, ArticleRevision $revision): Response
    {
        Gate::authorize('update', $article);
        abort_unless($revision->article_id === $article->id, 404);
        $snapshot = $revision->snapshot;
        foreach (['tags', 'sources', 'body_doc'] as $field) {
            if (is_string($snapshot[$field] ?? null)) {
                $snapshot[$field] = json_decode($snapshot[$field], true);
            }
        }
        $snapshot['slug'] = $article->slug;
        $snapshot['revision'] = $article->revision;
        $snapshot['change_note'] = 'Pulihkan revisi '.$revision->version;
        $request->replace($snapshot);

        return $this->save($request, app(ArticleContent::class), $article);
    }

    public function categories(): Response
    {
        return response()->json(['data' => Category::orderBy('name')->get()]);
    }

    public function category(string $slug): Response
    {
        return response()->json(['data' => Category::where('slug', $slug)->firstOrFail()]);
    }

    public function categorySave(Request $request, ?Category $category = null): Response
    {
        $input = $request->validate(['name' => 'required|string|max:150', 'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:180', Rule::unique('categories')->ignore($category?->id)], 'description' => 'nullable|string|max:3000', 'parent_id' => 'nullable|integer|exists:categories,id']);
        if (! empty($input['parent_id'])) {
            $parent = Category::findOrFail($input['parent_id']);
            abort_if($parent->parent_id || $parent->id === $category?->id || ($category && Category::where('parent_id', $category->id)->exists()), 422, 'Kategori dibatasi satu tingkat anak dan tidak boleh melingkar.');
        }
        $category ? $category->update($input) : $category = Category::create($input);
        AuditService::record('category.saved', $request->user(), ['reason' => $category->slug]);

        return response()->json(['data' => $category]);
    }

    public function categoryDestroy(Request $request, Category $category): Response
    {
        DB::transaction(function () use ($request, $category): void {
            $category = Category::whereKey($category->id)->lockForUpdate()->firstOrFail();
            if (Category::where('parent_id', $category->id)->exists() || Article::withTrashed()->where('primary_category_id', $category->id)->exists()) {
                throw ValidationException::withMessages(['category' => 'Kategori masih memiliki subkategori atau artikel, termasuk arsip yang dihapus. Pindahkan referensinya sebelum menghapus.']);
            }
            $category->delete();
            AuditService::record('category.deleted', $request->user(), ['reason' => $category->slug]);
        });

        return response()->noContent();
    }

    public function authors(): Response
    {
        return response()->json(['data' => AuthorProfile::orderBy('public_name')->get(['id', 'slug', 'public_name', 'bio', 'public_links'])]);
    }

    public function adminAuthors(): Response
    {
        return response()->json(['data' => AuthorProfile::orderBy('public_name')->get(['id', 'user_id', 'slug', 'public_name', 'bio', 'public_links'])]);
    }

    public function author(string $slug): Response
    {
        return response()->json(['data' => AuthorProfile::where('slug', $slug)->firstOrFail(['id', 'slug', 'public_name', 'bio', 'public_links'])]);
    }

    public function authorSave(Request $request, ?AuthorProfile $author = null): Response
    {
        $input = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id', Rule::unique('author_profiles')->ignore($author?->id)], 'public_name' => 'required|string|max:150', 'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:180', Rule::unique('author_profiles')->ignore($author?->id)], 'bio' => 'nullable|string|max:3000', 'public_links' => 'nullable|array|max:5', 'public_links.*' => 'url:https|max:2000']);
        $user = User::findOrFail($input['user_id']);
        if ($user->status !== 'active' || ! $user->hasPermission('articles.create')) {
            throw ValidationException::withMessages(['user_id' => 'Profil penulis memerlukan akun aktif dengan izin menulis artikel.']);
        }
        $author ? $author->update($input) : $author = AuthorProfile::create($input);
        AuditService::record('author.saved', $request->user(), ['target_user_id' => $author->user_id]);

        return response()->json(['data' => $author->only(['id', 'user_id', 'public_name', 'slug', 'bio', 'public_links'])]);
    }

    public function authorDestroy(Request $request, AuthorProfile $author): Response
    {
        DB::transaction(function () use ($request, $author): void {
            $author = AuthorProfile::whereKey($author->id)->lockForUpdate()->firstOrFail();
            if (Article::withTrashed()->where('author_id', $author->id)->exists()) {
                throw ValidationException::withMessages(['author' => 'Profil masih digunakan oleh artikel, termasuk arsip yang dihapus. Atribusi publik harus tetap tersimpan.']);
            }
            $author->delete();
            AuditService::record('author.deleted', $request->user(), ['target_user_id' => $author->user_id]);
        });

        return response()->noContent();
    }
}
