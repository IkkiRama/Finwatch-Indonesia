<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Role;
use App\Models\User;
use App\Services\ArticleContent;
use App\Services\PublishStarterEditorial;
use App\Services\RefreshStarterEditorial;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RefreshStarterEditorialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    private function publisher(): User
    {
        $user = User::factory()->create(['email' => 'refresh-editor@example.test']);
        $user->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $user->socialIdentities()->create(['provider' => 'google', 'provider_subject' => 'editorial-refresh-test', 'verified_email_at' => now()]);

        return $user;
    }

    private function legacyArticles(User $user): void
    {
        app(PublishStarterEditorial::class)->publish($user);
        $legacy = json_decode(file_get_contents(base_path('tests/Fixtures/legacy-starter-editorial.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($legacy as $piece) {
            $article = Article::where('slug', $piece['slug'])->firstOrFail();
            $article->update([
                'title' => $piece['title'], 'excerpt' => $piece['excerpt'],
                'body_html' => app(ArticleContent::class)->sanitize($piece['body']),
                'sources' => $piece['sources'], 'tags' => $piece['tags'],
                'seo_title' => $piece['title'], 'seo_description' => $piece['excerpt'],
            ]);
            ArticleRevision::where('article_id', $article->id)->where('version', 1)->update(['snapshot' => json_encode($article->getAttributes(), JSON_THROW_ON_ERROR)]);
        }
    }

    private function extendedArticles(User $user): void
    {
        app(PublishStarterEditorial::class)->publish($user);
        $previous = json_decode(file_get_contents(base_path('tests/Fixtures/starter-editorial-v2.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($previous as $piece) {
            $article = Article::where('slug', $piece['slug'])->firstOrFail();
            $article->fill([
                'title' => $piece['title'], 'excerpt' => $piece['excerpt'],
                'body_html' => app(ArticleContent::class)->sanitize($piece['body']),
                'body_doc' => null, 'sources' => $piece['sources'], 'tags' => $piece['tags'],
                'seo_title' => $piece['title'], 'seo_description' => $piece['excerpt'],
            ])->forceFill(['revision' => 2])->save();
            ArticleRevision::create([
                'article_id' => $article->id, 'editor_id' => $user->id, 'version' => 2,
                'snapshot' => $article->getAttributes(),
                'change_note' => 'Perluasan naskah bawaan: storytelling, contoh hitung, metode dan batas interpretasi. Naskah lama tetap ada dalam revisi.',
            ]);
        }
    }

    public function test_review_updates_the_known_second_edition_and_keeps_its_publication_history(): void
    {
        $user = $this->publisher();
        $this->extendedArticles($user);
        $first = Article::orderBy('id')->firstOrFail();
        $before = $first->only(['id', 'slug', 'cover_media_id', 'status']);
        $publication = $first->published_at->toIso8601String();
        $previousBody = $first->body_html;
        $comment = Comment::create(['article_id' => $first->id, 'user_id' => $user->id, 'body_plain' => 'Percakapan lama tetap tersedia.', 'status' => 'approved']);

        $this->artisan('finwatch:publish-starter-editorial', ['email' => $user->email, '--refresh-unchanged' => true])->assertSuccessful();
        $this->assertSame($before, $first->fresh()->only(array_keys($before)));
        $this->assertSame($publication, $first->fresh()->published_at->toIso8601String());
        $this->assertSame(3, $first->fresh()->revision);
        $this->assertSame($previousBody, ArticleRevision::where('article_id', $first->id)->where('version', 2)->firstOrFail()->snapshot['body_html']);
        $this->assertSame('Percakapan lama tetap tersedia.', $comment->fresh()->body_plain);
        $this->assertDatabaseCount('article_revisions', 18);
        $this->assertSame(6, AuditLog::where('event', 'article.editorial_refreshed')->count());
        $this->assertSame([], app(RefreshStarterEditorial::class)->refresh($user)['updated']);
        $this->assertDatabaseCount('article_revisions', 18);
    }

    public function test_review_rejects_changes_to_the_second_edition_and_unrecognized_editor_revisions(): void
    {
        $user = $this->publisher();
        $this->extendedArticles($user);
        $articles = Article::orderBy('id')->get();
        $articles[0]->update(['title' => 'Judul yang dipilih sendiri oleh pengelola']);
        $articles[1]->update(['body_html' => '<p>Isi yang telah disusun sendiri oleh pengelola.</p>']);
        ArticleRevision::where('article_id', $articles[2]->id)->where('version', 2)->update(['change_note' => 'Suntingan manual pengelola']);
        $articles[3]->update(['status' => 'draft']);

        $result = app(RefreshStarterEditorial::class)->refresh($user);
        $this->assertCount(2, $result['updated']);
        $this->assertCount(4, $result['skipped']);
        $this->assertSame('Judul yang dipilih sendiri oleh pengelola', $articles[0]->fresh()->title);
        $this->assertSame('<p>Isi yang telah disusun sendiri oleh pengelola.</p>', $articles[1]->fresh()->body_html);
        $this->assertSame(2, $articles[2]->fresh()->revision);
        $this->assertSame('draft', $articles[3]->fresh()->status);
        $this->assertDatabaseCount('article_revisions', 14);
    }

    public function test_refresh_adds_revisions_and_audit_without_replacing_ids_covers_publication_or_comments(): void
    {
        $user = $this->publisher();
        $this->legacyArticles($user);
        $first = Article::orderBy('id')->firstOrFail();
        $before = $first->only(['id', 'slug', 'cover_media_id', 'status']);
        $publishedAt = $first->published_at->toIso8601String();
        $comment = Comment::create(['article_id' => $first->id, 'user_id' => $user->id, 'body_plain' => 'Komentar pembaca tetap tersedia.', 'status' => 'approved']);
        $first->update(['sources' => array_map(fn (array $source): array => ['url' => $source['url'], 'title' => $source['title']], $first->sources)]);

        $this->artisan('finwatch:publish-starter-editorial', ['email' => $user->email, '--refresh-unchanged' => true])->assertSuccessful();
        $this->assertSame($before, $first->fresh()->only(array_keys($before)));
        $this->assertSame($publishedAt, $first->fresh()->published_at->toIso8601String());
        $this->assertSame(2, $first->fresh()->revision);
        $this->assertDatabaseCount('articles', 6);
        $this->assertDatabaseCount('media_assets', 6);
        $this->assertDatabaseCount('article_revisions', 12);
        $this->assertSame(6, AuditLog::where('event', 'article.editorial_refreshed')->count());
        $this->assertSame('Komentar pembaca tetap tersedia.', $comment->fresh()->body_plain);
        foreach (Article::all() as $article) {
            $this->assertGreaterThanOrEqual($article->content_type === 'research' ? 850 : 650, str_word_count(strip_tags($article->body_html)));
            $this->assertStringNotContainsString('—', $article->body_html);
            $this->assertStringNotContainsString('—', json_encode($article->sources, JSON_UNESCAPED_UNICODE));
        }
        $result = app(RefreshStarterEditorial::class)->refresh($user);
        $this->assertSame([], $result['updated']);
        $this->assertDatabaseCount('article_revisions', 12);
    }

    public function test_refresh_skips_user_edits_and_nonpublic_articles(): void
    {
        $user = $this->publisher();
        $this->legacyArticles($user);
        $articles = Article::orderBy('id')->get();
        $articles[0]->update(['title' => 'Suntingan judul dari pengelola']);
        $articles[1]->update(['body_html' => '<p>Isi yang telah ditulis ulang pengelola.</p>']);
        $articles[2]->update(['status' => 'draft']);
        $result = app(RefreshStarterEditorial::class)->refresh($user);
        $this->assertCount(3, $result['updated']);
        $this->assertCount(3, $result['skipped']);
        $this->assertSame('Suntingan judul dari pengelola', $articles[0]->fresh()->title);
        $this->assertSame('<p>Isi yang telah ditulis ulang pengelola.</p>', $articles[1]->fresh()->body_html);
        $this->assertSame('draft', $articles[2]->fresh()->status);
        $this->assertDatabaseCount('article_revisions', 9);
    }

    public function test_unverified_account_cannot_refresh_existing_editorial(): void
    {
        $publisher = $this->publisher();
        $this->legacyArticles($publisher);
        $reader = User::factory()->create();
        $this->expectException(\RuntimeException::class);
        app(RefreshStarterEditorial::class)->refresh($reader);
    }
}
