<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CommunityController extends Controller
{
    private function readable(Comment $comment): void
    {
        abort_unless($comment->article?->isPublic() && $comment->status === 'approved', 404);
        $parent = $comment->parent_id;
        $depth = 0;
        while ($parent) {
            $ancestor = Comment::withTrashed()->find($parent);
            abort_unless($ancestor && $ancestor->status === 'approved' && ++$depth <= 12, 404);
            $parent = $ancestor->parent_id;
        }
    }

    private function writable(Article $article, ?Comment $parent = null): void
    {
        abort_if(json_decode(DB::table('system_settings')->where('key', 'comments_enabled')->value('value') ?? 'true', true) === false, 423, 'Komentar sedang dinonaktifkan pengelola.');
        abort_unless($article->isPublic(), 404);
        abort_unless($article->allow_comments, 423, 'Diskusi artikel dikunci.');
        $depth = 0;
        while ($parent) {
            abort_unless($parent->article_id === $article->id && ! $parent->trashed() && $parent->status === 'approved', 422, 'Induk komentar tidak valid.');
            abort_if($parent->locked, 423, 'Thread dikunci moderator.');
            abort_if(++$depth > 12, 422, 'Batas kedalaman diskusi tercapai.');
            $parent = $parent->parent_id ? Comment::withTrashed()->findOrFail($parent->parent_id) : null;
        }
    }

    private function record(Comment $comment, Request $request): array
    {
        $deleted = $comment->trashed();

        return ['id' => $comment->id, 'article_id' => $comment->article_id, 'parent_id' => $comment->parent_id, 'user' => ['id' => $comment->user_id, 'name' => $comment->user?->name ?? 'Pengguna', 'avatar_url' => $comment->user?->avatar_url],
            'body' => $deleted ? 'Komentar telah dihapus' : $comment->body_plain, 'deleted' => $deleted, 'status' => $comment->status, 'depth' => $comment->depth, 'locked' => $comment->locked, 'likes' => $comment->like_count, 'liked' => (bool) ($comment->liked ?? false),
            'replies_count' => $comment->replies_count ?? 0, 'created_at' => $comment->created_at->toIso8601String(), 'edited_at' => $comment->edited_at?->toIso8601String(),
            'can_edit' => ! $deleted && $request->user()?->id === $comment->user_id, 'can_moderate' => $request->user()?->can('comments.manage') ?? false];
    }

    public function index(Request $request, Article $article, ?Comment $parent = null): Response
    {
        abort_unless($article->isPublic(), 404);
        if ($parent) {
            $this->readable($parent);
            abort_unless($parent->article_id === $article->id, 404);
        }
        $input = $request->validate(['sort' => 'nullable|in:newest,oldest,top', 'cursor' => 'nullable|string|max:4000']);
        $query = Comment::withTrashed()->where('article_id', $article->id)->where('parent_id', $parent?->id)->where('status', 'approved')->with('user:id,name,avatar_url')->select('comments.*')
            ->withCount(['replies' => fn ($q) => $q->where('status', 'approved')])
            ->selectSub(DB::table('comment_likes')->selectRaw('COUNT(*)')->whereColumn('comment_id', 'comments.id')->where('user_id', $request->user()?->id ?? 0), 'liked');
        if (($input['sort'] ?? 'newest') === 'top') {
            $query->orderByDesc('like_count');
        }
        $query->orderBy('id', ($input['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc');
        $page = $query->cursorPaginate(15);

        return response()->json(['data' => $page->getCollection()->map(fn ($comment) => $this->record($comment, $request)), 'next_cursor' => $page->nextCursor()?->encode(), 'has_more' => $page->hasMorePages()]);
    }

    public function replies(Request $request, Comment $comment): Response
    {
        return $this->index($request, $comment->article, $comment);
    }

    public function context(Request $request, Comment $comment): Response
    {
        $this->readable($comment);
        $chain = [];
        $current = $comment;
        while ($current) {
            $chain[] = $this->record($current->load('user'), $request);
            $current = $current->parent_id ? Comment::withTrashed()->find($current->parent_id) : null;
        }

        return response()->json(['data' => array_reverse($chain)]);
    }

    public function create(Request $request, Article $article): Response
    {
        $input = $request->validate(['body' => 'required|string|min:2|max:3000', 'parent_id' => 'nullable|integer|exists:comments,id']);
        $body = trim($input['body']);
        if (preg_match_all('~https?://~i', $body) > 2 || str_contains($body, "\0")) {
            throw ValidationException::withMessages(['body' => 'Maksimal dua tautan dan tanpa karakter kontrol.']);
        }
        if (Comment::where('user_id', $request->user()->id)->where('created_at', '>', now()->subMinutes(10))->where('body_plain', $body)->exists()) {
            throw ValidationException::withMessages(['body' => 'Komentar identik sudah dikirim.']);
        }
        $comment = DB::transaction(function () use ($request, $article, $input, $body): Comment {
            $lockedArticle = Article::whereKey($article->id)->lockForUpdate()->firstOrFail();
            $parent = isset($input['parent_id']) ? Comment::whereKey($input['parent_id'])->lockForUpdate()->firstOrFail() : null;
            $this->writable($lockedArticle, $parent);

            return Comment::create(['article_id' => $article->id, 'user_id' => $request->user()->id, 'parent_id' => $parent?->id, 'body_plain' => $body, 'depth' => $parent ? $parent->depth + 1 : 0, 'status' => 'pending']);
        });
        AuditService::record('comment.created', $request->user(), ['comment_id' => $comment->id, 'article_id' => $article->id, 'status' => 'pending']);

        return response()->json(['data' => $this->record($comment->load('user'), $request), 'message' => 'Komentar menunggu moderasi.'], 201);
    }

    public function update(Request $request, Comment $comment): Response
    {
        abort_unless($comment->user_id === $request->user()->id, 403);
        $input = $request->validate(['body' => 'required|string|min:2|max:3000']);
        $this->writable($comment->article, $comment->parent_id ? Comment::findOrFail($comment->parent_id) : null);
        abort_if($comment->locked, 423, 'Thread dikunci.');
        if (preg_match_all('~https?://~i', $input['body']) > 2) {
            throw ValidationException::withMessages(['body' => 'Maksimal dua tautan.']);
        }
        DB::transaction(function () use ($comment, $input): void {
            DB::table('comment_revisions')->insert(['comment_id' => $comment->id, 'body_plain' => $comment->body_plain, 'created_at' => now()]);
            $comment->update(['body_plain' => trim($input['body']), 'status' => 'pending', 'edited_at' => now()]);
        });
        AuditService::record('comment.edited', $request->user(), ['comment_id' => $comment->id]);

        return response()->json(['data' => $this->record($comment->load('user'), $request), 'message' => 'Perubahan menunggu moderasi.']);
    }

    public function destroy(Request $request, Comment $comment): Response
    {
        abort_unless($comment->user_id === $request->user()->id, 403);
        $comment->delete();
        AuditService::record('comment.deleted', $request->user(), ['comment_id' => $comment->id]);

        return response()->noContent();
    }

    public function like(Request $request, Comment $comment): Response
    {
        $this->readable($comment);
        abort_if($comment->trashed(), 404);
        $this->writable($comment->article, $comment);
        DB::transaction(function () use ($request, $comment): void {
            $comment = Comment::whereKey($comment->id)->lockForUpdate()->firstOrFail();
            $query = DB::table('comment_likes')->where('comment_id', $comment->id)->where('user_id', $request->user()->id);
            if ($request->isMethod('delete')) {
                $query->delete();
            } elseif (! $query->exists()) {
                DB::table('comment_likes')->insert(['comment_id' => $comment->id, 'user_id' => $request->user()->id]);
            }
            $comment->update(['like_count' => DB::table('comment_likes')->where('comment_id', $comment->id)->count()]);
        });

        return response()->json(['likes' => $comment->fresh()->like_count]);
    }

    public function report(Request $request, Comment $comment): Response
    {
        $this->readable($comment);
        abort_if($comment->trashed(), 404);
        $input = $request->validate(['reason' => 'required|string|min:5|max:500']);
        CommentReport::firstOrCreate(['comment_id' => $comment->id, 'user_id' => $request->user()->id], ['reason' => $input['reason'], 'status' => 'open']);

        return response()->json(['message' => 'Laporan diterima untuk ditinjau.']);
    }

    public function moderation(Request $request): Response
    {
        $input = $request->validate(['status' => 'nullable|in:pending,approved,hidden,rejected,deleted,flagged', 'page' => 'nullable|integer|min:1|max:10000']);
        $comments = Comment::withTrashed()->with(['user:id,name,avatar_url', 'article:id,title,slug'])->withCount('replies');
        if (($input['status'] ?? 'pending') === 'deleted') {
            $comments->onlyTrashed();
        } elseif (($input['status'] ?? 'pending') === 'flagged') {
            $comments->whereIn('id', CommentReport::where('status', 'open')->select('comment_id'));
        } else {
            $comments->where('status', $input['status'] ?? 'pending');
        }

        return response()->json($comments->orderByDesc('id')->paginate(20)->through(fn ($comment) => $this->record($comment, $request) + ['article' => $comment->article->only(['id', 'title', 'slug']), 'reports' => CommentReport::where('comment_id', $comment->id)->where('status', 'open')->get(['id', 'reason', 'created_at'])]));
    }

    public function moderate(Request $request, int $comment): Response
    {
        $input = $request->validate(['action' => 'required|in:approve,hide,reject,delete,restore,lock,unlock', 'reason' => 'required|string|min:3|max:200']);
        $comment = Comment::withTrashed()->findOrFail($comment);
        DB::transaction(function () use ($comment, $input, $request): void {
            match ($input['action']) {
                'approve' => $comment->update(['status' => 'approved']), 'hide' => $comment->update(['status' => 'hidden']), 'reject' => $comment->update(['status' => 'rejected']), 'delete' => $comment->delete(), 'restore' => $comment->restore(), 'lock' => $comment->update(['locked' => true]), 'unlock' => $comment->update(['locked' => false])
            };
            CommentReport::where('comment_id', $comment->id)->update(['status' => 'reviewed']);
            AuditService::record('comment.moderated', $request->user(), ['comment_id' => $comment->id, 'status' => $input['action'], 'reason' => $input['reason']]);
        });

        return response()->json(['message' => 'Keputusan moderasi disimpan.']);
    }
}
