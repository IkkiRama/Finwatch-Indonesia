<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\AuthorProfile;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['finwatch.admin_mfa_required' => false]);
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    private function account(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user;
    }

    private function act(User $user): void
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->actingAs($user, 'web')->withSession(['auth_version' => $user->auth_version]);
    }

    private function guest(): void
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
    }

    private function input(User $author): array
    {
        $profile = AuthorProfile::firstOrCreate(['user_id' => $author->id], ['slug' => 'penulis-fixture-'.$author->id, 'public_name' => 'Penulis Pengujian']);
        $category = Category::firstOrCreate(['slug' => 'pengujian'], ['name' => 'Pengujian']);

        return ['title' => 'Membaca periode dan sumber data', 'slug' => 'membaca-periode-data', 'author_id' => $profile->id, 'primary_category_id' => $category->id, 'cover_media_id' => null, 'excerpt' => 'Membedakan tanggal observasi dan tanggal publikasi dalam data.', 'body_html' => '<h2>Tanggal observasi</h2><p>Tanggal observasi berbeda dari tanggal publikasi sumber resmi.</p>', 'body_doc' => ['type' => 'doc', 'editor_internal_marker' => 'private-editor-value', 'content' => []], 'sources' => [['title' => 'Referensi resmi', 'url' => 'https://www.ojk.go.id']], 'tags' => ['data'], 'content_type' => 'education', 'seo_title' => 'Membaca tanggal data', 'seo_description' => 'Panduan memahami tanggal dalam publikasi.', 'allow_comments' => true, 'is_featured' => false];
    }

    private function draft(User $admin): Article
    {
        $this->act($admin);
        $id = $this->postJson('/api/v1/admin/articles', $this->input($admin))->assertOk()->json('data.id');

        return Article::findOrFail($id);
    }

    public function test_editor_cannot_alter_scheduled_content_and_scheduler_publishes_only_due_articles(): void
    {
        $admin = $this->account('admin');
        $editor = $this->account('editor');
        $article = $this->draft($admin);
        $scheduled = now()->addHour()->startOfSecond();
        $this->postJson('/api/v1/admin/articles/'.$article->id.'/status', ['status' => 'scheduled', 'scheduled_at' => $scheduled->toIso8601String()])->assertOk();
        $this->get('/artikel/'.$article->slug)->assertNotFound();
        $this->artisan('finwatch:publish-scheduled')->expectsOutput('0 artikel diterbitkan.')->assertSuccessful();

        $this->act($editor);
        $input = $this->input($admin);
        $input['title'] = 'Perubahan tanpa izin publikasi';
        $input['revision'] = 1;
        $this->putJson('/api/v1/admin/articles/'.$article->id, $input)->assertForbidden();
        $this->postJson('/api/v1/admin/articles/'.$article->id.'/status', ['status' => 'draft'])->assertForbidden();
        $this->postJson('/api/v1/admin/articles/'.$article->id.'/status', ['status' => 'published'])->assertForbidden();
        $this->assertSame('Membaca periode dan sumber data', $article->fresh()->title);

        $this->travelTo($scheduled->copy()->addSecond());
        $this->artisan('finwatch:publish-scheduled')->expectsOutput('1 artikel diterbitkan.')->assertSuccessful();
        $this->artisan('finwatch:publish-scheduled')->expectsOutput('0 artikel diterbitkan.')->assertSuccessful();
        $this->assertSame('published', $article->fresh()->status);
        $this->get('/artikel/'.$article->slug)->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'article.scheduled_published']);
    }

    public function test_revision_conflict_and_restore_preserve_current_permalink_and_publication_state(): void
    {
        $admin = $this->account('admin');
        $article = $this->draft($admin);
        $first = ArticleRevision::where('article_id', $article->id)->firstOrFail();
        $input = $this->input($admin);
        $input['revision'] = 1;
        $input['title'] = 'Judul terbaru yang telah disunting';
        $input['slug'] = 'alamat-baru-data';
        $input['body_html'] = '<p>Isi revisi kedua dengan uraian metodologi yang lebih panjang.</p>';
        $this->putJson('/api/v1/admin/articles/'.$article->id, $input)->assertOk()->assertJsonPath('data.revision', 2);
        $this->putJson('/api/v1/admin/articles/'.$article->id, $input)->assertStatus(409);
        $this->postJson('/api/v1/admin/articles/'.$article->id.'/status', ['status' => 'published'])->assertOk();
        $this->postJson('/api/v1/admin/articles/'.$article->id.'/revisions/'.$first->id.'/restore')->assertOk()->assertJsonPath('data.title', 'Membaca periode dan sumber data')->assertJsonPath('data.revision', 3)->assertJsonPath('data.slug', 'alamat-baru-data')->assertJsonPath('data.status', 'published');
        $this->assertDatabaseCount('article_revisions', 3);
        $this->get('/artikel/membaca-periode-data')->assertStatus(301)->assertRedirect('/artikel/alamat-baru-data');
        $this->get('/artikel/alamat-baru-data')->assertOk()->assertSee('Tanggal observasi');
    }

    public function test_signed_preview_is_private_expires_and_uses_stable_article_id_after_slug_changes(): void
    {
        $admin = $this->account('admin');
        $article = $this->draft($admin);
        $preview = $this->getJson('/api/v1/admin/articles/'.$article->id)->assertOk()->json('preview_url');
        $input = $this->input($admin);
        $input['slug'] = 'slug-setelah-pratinjau';
        $input['revision'] = 1;
        $this->putJson('/api/v1/admin/articles/'.$article->id, $input)->assertOk();
        $this->guest();
        $response = $this->get($preview)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('slug-setelah-pratinjau')->assertSee('finwatch-article-bootstrap')->assertDontSee('private-editor-value');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get('/artikel/slug-setelah-pratinjau')->assertNotFound();
        $this->getJson('/api/v1/articles/slug-setelah-pratinjau')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('slug-setelah-pratinjau');
        $this->get($preview.'tampered')->assertForbidden();
        $this->travel(16)->minutes();
        $this->get($preview)->assertForbidden();
    }

    public function test_public_article_data_omits_editor_document_while_authorized_edit_keeps_it(): void
    {
        $admin = $this->account('admin');
        $article = $this->draft($admin);
        $this->getJson('/api/v1/admin/articles/'.$article->id)->assertOk()->assertJsonPath('data.body_doc.editor_internal_marker', 'private-editor-value');
        $this->postJson('/api/v1/admin/articles/'.$article->id.'/status', ['status' => 'published'])->assertOk();
        $this->guest();
        $this->getJson('/api/v1/articles/'.$article->slug)->assertOk()->assertJsonMissingPath('data.body_doc')->assertDontSee('private-editor-value')->assertDontSee($admin->email);
        $this->get('/artikel/'.$article->slug)->assertOk()->assertDontSee('private-editor-value');
    }

    public function test_global_comment_switch_is_publicly_effective_without_overwriting_editor_article_choice(): void
    {
        $admin = $this->account('admin');
        $article = $this->draft($admin);
        $this->postJson('/api/v1/admin/articles/'.$article->id.'/status', ['status' => 'published'])->assertOk();
        DB::table('system_settings')->insert(['key' => 'comments_enabled', 'value' => json_encode(false), 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/api/v1/articles/'.$article->slug)->assertOk()->assertJsonPath('data.allow_comments', false);
        $this->getJson('/api/v1/admin/articles/'.$article->id)->assertOk()->assertJsonPath('data.allow_comments', true);
        $this->assertTrue($article->fresh()->allow_comments);
    }

    public function test_author_management_requires_active_writer_and_only_admin_list_exposes_account_id(): void
    {
        $editor = $this->account('editor');
        $reader = $this->account('reader');
        $writer = $this->account('author');
        $blocked = $this->account('author');
        $blocked->forceFill(['status' => 'blocked'])->save();
        $this->act($editor);
        $profile = ['user_id' => $reader->id, 'public_name' => 'Profil Penulis', 'slug' => 'profil-penulis', 'bio' => 'Biografi publik', 'public_links' => []];
        $this->postJson('/api/v1/admin/authors', $profile)->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $profile['user_id'] = $blocked->id;
        $this->postJson('/api/v1/admin/authors', $profile)->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $profile['user_id'] = $writer->id;
        $id = $this->postJson('/api/v1/admin/authors', $profile)->assertOk()->json('data.id');
        $this->getJson('/api/v1/admin/authors')->assertOk()->assertJsonPath('data.0.user_id', $writer->id)->assertDontSee($writer->email);
        $this->getJson('/api/v1/authors')->assertOk()->assertJsonMissingPath('data.0.user_id')->assertDontSee($writer->email);
        $writer->roles()->detach();
        $this->putJson('/api/v1/admin/authors/'.$id, $profile)->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->act($reader);
        $this->getJson('/api/v1/admin/authors')->assertForbidden();
    }

    public function test_article_cannot_be_assigned_to_inactive_or_unprivileged_author_profile(): void
    {
        $admin = $this->account('admin');
        $writer = $this->account('author');
        $input = $this->input($writer);
        $this->act($admin);
        $writer->forceFill(['status' => 'blocked'])->save();
        $this->postJson('/api/v1/admin/articles', $input)->assertUnprocessable()->assertJsonValidationErrors('author_id');
        $writer->forceFill(['status' => 'active'])->save();
        $writer->roles()->detach();
        $this->postJson('/api/v1/admin/articles', $input)->assertUnprocessable()->assertJsonValidationErrors('author_id');
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_taxonomy_deletion_preserves_children_and_soft_deleted_article_attribution(): void
    {
        $admin = $this->account('admin');
        $article = $this->draft($admin);
        $parent = Category::create(['name' => 'Induk', 'slug' => 'induk']);
        $child = Category::create(['name' => 'Anak', 'slug' => 'anak', 'parent_id' => $parent->id]);
        $this->deleteJson('/api/v1/admin/categories/'.$parent->id)->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/categories/'.$child->id)->assertNoContent();
        $this->deleteJson('/api/v1/admin/categories/'.$parent->id)->assertNoContent();
        $this->deleteJson('/api/v1/admin/categories/'.$article->primary_category_id)->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/authors/'.$article->author_id)->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/articles/'.$article->id)->assertNoContent();
        $this->deleteJson('/api/v1/admin/categories/'.$article->primary_category_id)->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/authors/'.$article->author_id)->assertUnprocessable();
        $this->assertSoftDeleted($article);
        $this->assertDatabaseHas('audit_logs', ['event' => 'category.deleted']);
        $unused = AuthorProfile::create(['user_id' => $this->account('author')->id, 'public_name' => 'Belum menerbitkan', 'slug' => 'belum-menerbitkan']);
        $this->deleteJson('/api/v1/admin/authors/'.$unused->id)->assertNoContent();
        $this->assertDatabaseMissing('author_profiles', ['id' => $unused->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'author.deleted']);
        $this->act($this->account('author'));
        $this->deleteJson('/api/v1/admin/categories/'.$article->primary_category_id)->assertForbidden();
        $this->deleteJson('/api/v1/admin/authors/'.$article->author_id)->assertForbidden();
    }

    public function test_article_deletion_is_limited_to_own_draft_or_publisher_and_retains_revisions(): void
    {
        $writer = $this->account('author');
        $other = $this->account('author');
        $editor = $this->account('editor');
        $article = $this->draft($writer);
        $this->getJson('/api/v1/admin/articles/'.$article->id)->assertOk()->assertJsonPath('can_delete', true);
        $this->act($other);
        $this->deleteJson('/api/v1/admin/articles/'.$article->id)->assertForbidden();
        $this->act($editor);
        $this->getJson('/api/v1/admin/articles/'.$article->id)->assertOk()->assertJsonPath('can_delete', false);
        $this->deleteJson('/api/v1/admin/articles/'.$article->id)->assertForbidden();
        $this->act($writer);
        $this->postJson('/api/v1/admin/articles/'.$article->id.'/status', ['status' => 'in_review'])->assertOk();
        $this->deleteJson('/api/v1/admin/articles/'.$article->id)->assertForbidden();
        $this->postJson('/api/v1/admin/articles/'.$article->id.'/status', ['status' => 'draft'])->assertOk();
        $this->deleteJson('/api/v1/admin/articles/'.$article->id)->assertNoContent();
        $this->assertSoftDeleted($article);
        $this->assertDatabaseCount('article_revisions', 1);
        $this->get('/artikel/'.$article->slug)->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['event' => 'article.deleted']);
    }

    private function uploadMedia(User $owner): MediaAsset
    {
        $this->act($owner);
        $id = $this->post('/api/v1/admin/media', ['file' => UploadedFile::fake()->image('media.png', 32, 24), 'alt_text' => 'Gambar pengujian'], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        return MediaAsset::findOrFail($id);
    }

    public function test_unused_media_deletion_checks_ownership_and_removes_original_and_thumbnail(): void
    {
        $owner = $this->account('author');
        $other = $this->account('author');
        $media = $this->uploadMedia($owner);
        $thumb = str_replace('.webp', '-thumb.webp', $media->path);
        Storage::disk('local')->assertExists([$media->path, $thumb]);
        $this->act($other);
        $this->deleteJson('/api/v1/admin/media/'.$media->id)->assertForbidden();
        Storage::disk('local')->assertExists([$media->path, $thumb]);
        $this->act($owner);
        $this->deleteJson('/api/v1/admin/media/'.$media->id)->assertNoContent();
        Storage::disk('local')->assertMissing([$media->path, $thumb]);
        $this->assertDatabaseMissing('media_assets', ['id' => $media->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'media.deleted']);
        $editor = $this->account('editor');
        $media = $this->uploadMedia($owner);
        $this->act($editor);
        $this->deleteJson('/api/v1/admin/media/'.$media->id)->assertNoContent();
    }

    public function test_media_referenced_by_cover_body_or_retained_revision_cannot_be_deleted(): void
    {
        $admin = $this->account('admin');
        $cover = $this->uploadMedia($admin);
        $embedded = $this->uploadMedia($admin);
        $article = $this->draft($admin);
        $input = $this->input($admin);
        $input['cover_media_id'] = $cover->id;
        $input['body_html'] .= '<img src="/api/v1/media/'.$embedded->id.'?size=thumb" alt="Bukti sumber">';
        $input['revision'] = 1;
        $this->putJson('/api/v1/admin/articles/'.$article->id, $input)->assertOk();
        $this->postJson('/api/v1/admin/articles/'.$article->id.'/status', ['status' => 'published'])->assertOk();
        $this->guest();
        $this->get('/api/v1/media/'.$embedded->id.'?size=thumb')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->act($admin);
        foreach ([$cover, $embedded] as $media) {
            $this->deleteJson('/api/v1/admin/media/'.$media->id)->assertUnprocessable()->assertJsonValidationErrors('media');
            Storage::disk('local')->assertExists($media->path);
        }
        $input['cover_media_id'] = null;
        $input['body_html'] = '<p>Isi artikel kini tidak memakai gambar, riwayat lama tetap dapat dipulihkan.</p>';
        $input['revision'] = 2;
        $this->putJson('/api/v1/admin/articles/'.$article->id, $input)->assertOk();
        $this->deleteJson('/api/v1/admin/articles/'.$article->id)->assertNoContent();
        foreach ([$cover, $embedded] as $media) {
            $this->deleteJson('/api/v1/admin/media/'.$media->id)->assertUnprocessable();
            Storage::disk('local')->assertExists($media->path);
        }
        $this->assertDatabaseCount('article_revisions', 3);
    }
}
