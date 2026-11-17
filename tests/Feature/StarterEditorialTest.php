<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Role;
use App\Models\User;
use App\Services\ArticleContent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StarterEditorialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    public function test_command_requires_existing_verified_publisher_and_never_creates_an_account(): void
    {
        $this->artisan('finwatch:publish-starter-editorial', ['email' => 'missing@example.test'])->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $reader = User::factory()->create(['email' => 'reader@example.test']);
        $reader->roles()->attach(Role::where('name', 'reader')->firstOrFail());
        $this->artisan('finwatch:publish-starter-editorial', ['email' => $reader->email])->assertFailed();
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_six_sourced_publications_are_idempotent_have_covers_and_preserve_edits(): void
    {
        $user = User::factory()->create(['email' => 'editor@example.test']);
        $user->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $user->socialIdentities()->create(['provider' => 'google', 'provider_subject' => 'editorial-test-subject', 'verified_email_at' => now()]);
        $this->artisan('finwatch:publish-starter-editorial', ['email' => $user->email])->assertSuccessful();
        $this->assertDatabaseCount('articles', 6);
        $this->assertDatabaseCount('article_revisions', 6);
        $this->assertDatabaseCount('media_assets', 6);
        $this->assertSame(4, Article::published()->where('content_type', 'education')->count());
        $this->assertSame(2, Article::published()->where('content_type', 'research')->count());
        foreach (Article::all() as $article) {
            $this->assertGreaterThan(2, count(app(ArticleContent::class)->toc($article->body_html)));
            $this->assertNotEmpty($article->sources);
            $this->assertStringNotContainsString('<script', $article->body_html);
            Storage::disk('local')->assertExists($article->cover->path);
            Storage::disk('local')->assertExists(str_replace('.webp', '-thumb.webp', $article->cover->path));
        }
        $first = Article::firstOrFail();
        $first->update(['title' => 'Judul yang telah diedit pengelola']);
        $this->artisan('finwatch:publish-starter-editorial', ['email' => $user->email])->assertSuccessful();
        $this->assertSame('Judul yang telah diedit pengelola', $first->fresh()->title);
        $this->assertDatabaseCount('articles', 6);
        $this->getJson('/api/v1/articles?type=education')->assertOk()->assertJsonPath('total', 4);
        $this->getJson('/api/v1/articles?type=research')->assertOk()->assertJsonPath('total', 2);
        $this->get('/artikel/'.$first->slug)->assertOk()->assertSee('Judul yang telah diedit pengelola');
    }

    public function test_renamed_starter_slug_reserved_by_redirect_is_never_recreated(): void
    {
        $user = User::factory()->create(['email' => 'renamed-editor@example.test']);
        $user->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $user->socialIdentities()->create(['provider' => 'google', 'provider_subject' => 'renamed-editorial-test', 'verified_email_at' => now()]);
        $this->artisan('finwatch:publish-starter-editorial', ['email' => $user->email])->assertSuccessful();
        $first = Article::orderBy('id')->firstOrFail();
        $originalSlug = $first->slug;
        $first->update(['slug' => 'alamat-baru-dari-pengelola']);
        DB::table('article_slug_redirects')->insert(['article_id' => $first->id, 'old_slug' => $originalSlug]);

        $this->artisan('finwatch:publish-starter-editorial', ['email' => $user->email, '--refresh-unchanged' => true])->assertSuccessful();
        $this->assertDatabaseCount('articles', 6);
        $this->assertDatabaseMissing('articles', ['slug' => $originalSlug]);
        $this->assertSame('alamat-baru-dari-pengelola', $first->fresh()->slug);
        $this->get('/artikel/'.$originalSlug)->assertStatus(301)->assertRedirect('/artikel/alamat-baru-dari-pengelola');
    }

    public function test_curated_research_profile_disappears_when_body_is_edited_under_the_same_slug(): void
    {
        $user = User::factory()->create(['email' => 'research-profile@example.test']);
        $user->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $user->socialIdentities()->create(['provider' => 'google', 'provider_subject' => 'research-profile-test', 'verified_email_at' => now()]);
        $this->artisan('finwatch:publish-starter-editorial', ['email' => $user->email])->assertSuccessful();
        foreach (Article::where('content_type', 'research')->get() as $article) {
            $this->assertSame($article->slug, app(ArticleContent::class)->present($article)['research_profile_slug']);
        }
        $research = Article::where('content_type', 'research')->firstOrFail();
        $originalSlug = $research->slug;
        $originalBody = $research->body_html;
        $this->getJson('/api/v1/articles/'.$originalSlug)->assertOk()->assertJsonPath('data.research_profile_slug', $originalSlug);
        $research->update(['body_html' => '<h2>Rancangan penelitian baru</h2><p>Naskah ini sudah disunting penulis dengan pertanyaan dan data berbeda.</p>']);
        $this->getJson('/api/v1/articles/'.$originalSlug)->assertOk()->assertJsonPath('data.research_profile_slug', null);
        $this->assertNull(app(ArticleContent::class)->present($research->fresh())['research_profile_slug']);
        $research->update(['body_html' => $originalBody, 'content_type' => 'education']);
        $this->assertNull(app(ArticleContent::class)->present($research->fresh())['research_profile_slug']);
    }
}
