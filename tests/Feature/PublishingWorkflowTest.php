<?php

namespace Tests\Feature;

use App\Jobs\SynchronizeSource;
use App\Models\Article;
use App\Models\AuthorProfile;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Dataset;
use App\Models\DataSource;
use App\Models\Role;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\DatasetImporter;
use App\Services\OfficialDownload;
use Database\Seeders\DataSourceSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublishingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['finwatch.admin_mfa_required' => false]);
        $this->seed([RolesAndPermissionsSeeder::class, DataSourceSeeder::class]);
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

    private function articleInput(User $author): array
    {
        $profile = AuthorProfile::firstOrCreate(['user_id' => $author->id], ['slug' => 'penulis-'.$author->id, 'public_name' => 'Penulis Fixture']);
        $category = Category::firstOrCreate(['slug' => 'literasi'], ['name' => 'Literasi']);

        return ['title' => 'Memahami indikator keuangan', 'slug' => 'memahami-indikator', 'author_id' => $profile->id, 'primary_category_id' => $category->id, 'cover_media_id' => null, 'excerpt' => 'Membaca data dengan metodologi dan sumber yang jelas.', 'body_html' => '<h2>Konteks data</h2><p>Periode observasi berbeda dari waktu rilis publikasi.</p>', 'body_doc' => null, 'sources' => [['title' => 'Sumber OJK', 'url' => 'https://www.ojk.go.id']], 'tags' => ['literasi'], 'content_type' => 'education', 'seo_title' => 'Judul SEO khusus', 'seo_description' => 'Deskripsi SEO khusus', 'allow_comments' => true, 'is_featured' => false];
    }

    private function published(User $admin): Article
    {
        $this->act($admin);
        $id = $this->postJson('/api/v1/admin/articles', $this->articleInput($admin))->assertOk()->json('data.id');
        $this->postJson('/api/v1/admin/articles/'.$id.'/status', ['status' => 'published'])->assertOk();

        return Article::findOrFail($id);
    }

    private function row(string $value = '2.5', string $vintage = 'release-1'): array
    {
        return ['indicator_code' => 'test_npl', 'label' => 'NPL Gross', 'period_date' => '2025-01-31', 'value' => $value, 'unit' => '%', 'frequency' => 'monthly', 'scope' => 'Indonesia', 'source_url' => 'https://www.ojk.go.id', 'release_date' => '2025-03-01', 'retrieved_at' => '2025-03-02T00:00:00Z', 'dataset_version' => $vintage, 'methodology_note' => 'Industry aggregate', 'sector' => 'banking', 'segment' => 'aggregate'];
    }

    public function test_import_review_publish_revision_and_rollback_are_atomic_and_private(): void
    {
        $admin = $this->account('admin');
        $this->act($admin);
        $dataset = Dataset::create(['code' => 'test-data', 'name' => 'Test', 'data_source_id' => DataSource::first()->id, 'status' => 'draft']);
        $importer = app(DatasetImporter::class);
        $version = $importer->stage($dataset, [$this->row()], 'snapshots/test.csv', $admin);
        $this->getJson('/api/v1/overview')->assertJsonPath('observations', 0);
        $this->postJson('/api/v1/admin/dataset-versions/'.$version->id.'/publish')->assertOk();
        $this->getJson('/api/v1/observations')->assertJsonPath('total', 1)->assertJsonPath('data.0.value', 2.5);
        $second = $importer->stage($dataset, [$this->row('3.1', 'release-2')], 'snapshots/second.csv', $admin);
        $importer->publish($second, $admin);
        $this->getJson('/api/v1/overview')->assertJsonPath('observations', 1);
        $this->postJson('/api/v1/admin/dataset-versions/'.$version->id.'/rollback')->assertOk();
        $this->getJson('/api/v1/observations')->assertJsonPath('total', 1)->assertJsonPath('data.0.dataset_version', 'release-1');
        $bad = $importer->stage($dataset, [$this->row('=1+1', 'release-3')], 'snapshots/bad.csv', $admin);
        $this->postJson('/api/v1/admin/dataset-versions/'.$bad->id.'/publish')->assertUnprocessable();
        $this->getJson('/api/v1/observations')->assertJsonPath('total', 1)->assertJsonPath('data.0.dataset_version', 'release-1');
        $this->assertSame($version->id, $importer->stage($dataset, [$this->row()], 'snapshot/duplicate.csv', $admin)->id);
    }

    public function test_import_rejects_cross_unit_dupes_private_urls_and_future_periods(): void
    {
        $dataset = Dataset::create(['code' => 'invalid-data', 'name' => 'Test', 'data_source_id' => DataSource::first()->id, 'status' => 'draft']);
        $row = $this->row();
        $row['source_url'] = 'https://127.0.0.1/secrets';
        $row['period_date'] = '2099-01-01';
        $version = app(DatasetImporter::class)->stage($dataset, [$row, $this->row(), $this->row()], 'test.csv', null);
        $this->assertSame('invalid', $version->status);
        $this->assertCount(2, $version->issues);
        $this->assertDatabaseCount('indicator_observations', 0);
    }

    public function test_csv_upload_validates_template_and_retains_snapshot(): void
    {
        $this->act($this->account('admin'));
        $dataset = Dataset::create(['code' => 'upload-data', 'name' => 'Upload', 'data_source_id' => DataSource::first()->id, 'status' => 'draft']);
        $row = $this->row();
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, array_keys($row), ',', '"', '');
        fputcsv($handle, array_values($row), ',', '"', '');
        rewind($handle);
        $csv = stream_get_contents($handle);
        $this->post('/api/v1/admin/datasets/'.$dataset->id.'/import', ['file' => UploadedFile::fake()->createWithContent('data.csv', $csv)], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.status', 'needs_review');
        $this->assertCount(1, Storage::disk('local')->files('snapshots'));
        $this->post('/api/v1/admin/datasets/'.$dataset->id.'/import', ['file' => UploadedFile::fake()->createWithContent('fake.xlsx', 'not-a-zip')], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_sync_enqueues_once_and_truthfully_requires_manual_import(): void
    {
        Queue::fake();
        $this->act($this->account('admin'));
        $source = DataSource::first();
        $first = $this->postJson('/api/v1/admin/sync/runs', ['source_id' => $source->id])->assertAccepted()->json('data.0.id');
        $this->postJson('/api/v1/admin/sync/runs', ['source_id' => $source->id])->assertAccepted()->assertJsonPath('data.0.id', $first);
        Queue::assertPushed(SynchronizeSource::class, 1);
        (new SynchronizeSource($first))->handle(app(OfficialDownload::class), app(DatasetImporter::class));
        $this->assertSame('manual_required', SyncRun::find($first)->status);
    }

    public function test_authors_cannot_publish_or_take_over_other_articles(): void
    {
        $author = $this->account('author');
        $other = $this->account('author');
        $this->act($author);
        $input = $this->articleInput($author);
        $id = $this->postJson('/api/v1/admin/articles', $input)->assertOk()->json('data.id');
        $this->postJson('/api/v1/admin/articles/'.$id.'/status', ['status' => 'published'])->assertForbidden();
        $this->act($other);
        $this->putJson('/api/v1/admin/articles/'.$id, $input)->assertForbidden();
        $this->get('/artikel/'.$input['slug'])->assertNotFound();
    }

    public function test_sanitized_articles_have_real_initial_metadata_sitemap_and_permalink_redirects(): void
    {
        $admin = $this->account('admin');
        $this->act($admin);
        $input = $this->articleInput($admin);
        $input['body_html'] .= '<script>alert(1)</script><img src="https://evil.test/x" onerror="alert(1)"><a href="javascript:alert(1)">unsafe</a>';
        $id = $this->postJson('/api/v1/admin/articles', $input)->assertOk()->json('data.id');
        $this->postJson('/api/v1/admin/articles/'.$id.'/status', ['status' => 'published'])->assertOk();
        $this->get('/artikel/'.$input['slug'])->assertOk()->assertSee('Judul SEO khusus')->assertSee('property="og:title"', false)->assertSee('application/ld+json', false)->assertSee('<h2 id="bagian-1-konteks-data">Konteks data</h2>', false)->assertDontSee('<script>alert', false)->assertDontSee('https://evil.test/x', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee('/artikel/'.$input['slug']);
        $input['slug'] = 'slug-baru';
        $input['revision'] = 1;
        $this->putJson('/api/v1/admin/articles/'.$id, $input)->assertOk();
        $this->get('/artikel/memahami-indikator')->assertRedirect('/artikel/slug-baru')->assertStatus(301);
        $this->get('/artikel/slug-baru/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_missing_slug_is_generated_from_title_and_avoids_used_addresses(): void
    {
        $admin = $this->account('admin');
        $this->act($admin);
        $input = $this->articleInput($admin);
        unset($input['slug']);
        $input['title'] = 'Kenali Risiko: Mulai dari Data!';
        $first = $this->postJson('/api/v1/admin/articles', $input)->assertOk()->assertJsonPath('data.slug', 'kenali-risiko-mulai-dari-data')->json('data.id');
        $this->postJson('/api/v1/admin/articles', $input)->assertOk()->assertJsonPath('data.slug', 'kenali-risiko-mulai-dari-data-2');
        $input['title'] = 'Judul baru tanpa mengganti tautan pembaca';
        $input['revision'] = 1;
        $this->putJson('/api/v1/admin/articles/'.$first, $input)->assertOk()->assertJsonPath('data.slug', 'kenali-risiko-mulai-dari-data');
        $this->postJson('/api/v1/admin/articles/'.$first.'/status', ['status' => 'published'])->assertOk();
        $this->get('/artikel/kenali-risiko-mulai-dari-data')->assertOk();
        $this->deleteJson('/api/v1/admin/articles/'.$first)->assertNoContent();
        unset($input['revision']);
        $input['title'] = 'Kenali Risiko: Mulai dari Data!';
        $this->postJson('/api/v1/admin/articles', $input)->assertOk()->assertJsonPath('data.slug', 'kenali-risiko-mulai-dari-data-3');
    }

    public function test_nested_comments_pending_approval_likes_tombstones_and_report_privacy(): void
    {
        $admin = $this->account('admin');
        $reader = $this->account('reader');
        $article = $this->published($admin);
        $this->act($reader);
        $root = $this->postJson('/api/v1/articles/'.$article->id.'/comments', ['body' => 'Komentar pertama untuk diskusi'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/articles/'.$article->id.'/comments')->assertJsonCount(0, 'data');
        $this->act($admin);
        $this->postJson('/api/v1/admin/comments/'.$root.'/moderate', ['action' => 'approve', 'reason' => 'Sesuai pedoman'])->assertOk();
        $this->act($reader);
        $reply = $this->postJson('/api/v1/articles/'.$article->id.'/comments', ['body' => 'Balasan dalam thread', 'parent_id' => $root])->assertCreated()->json('data.id');
        $this->act($admin);
        $this->postJson('/api/v1/admin/comments/'.$reply.'/moderate', ['action' => 'approve', 'reason' => 'Sesuai pedoman'])->assertOk();
        $this->act($reader);
        $this->postJson('/api/v1/comments/'.$root.'/likes')->assertOk()->assertJsonPath('likes', 1);
        $this->postJson('/api/v1/comments/'.$root.'/likes')->assertOk()->assertJsonPath('likes', 1);
        $this->getJson('/api/v1/articles/'.$article->id.'/comments')->assertJsonPath('data.0.replies_count', 1)->assertDontSee($reader->email);
        $this->postJson('/api/v1/comments/'.$root.'/reports', ['reason' => 'Perlu diperiksa moderator'])->assertOk();
        $this->deleteJson('/api/v1/comments/'.$root)->assertNoContent();
        $this->getJson('/api/v1/articles/'.$article->id.'/comments')->assertJsonPath('data.0.body', 'Komentar telah dihapus');
        $this->getJson('/api/v1/comments/'.$root.'/replies')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cross_article_parent_lock_and_reader_moderation_are_rejected(): void
    {
        $admin = $this->account('admin');
        $reader = $this->account('reader');
        $article = $this->published($admin);
        $parent = Comment::create(['article_id' => $article->id, 'user_id' => $reader->id, 'body_plain' => 'Parent', 'status' => 'approved', 'locked' => true]);
        $this->act($reader);
        $this->postJson('/api/v1/articles/'.$article->id.'/comments', ['body' => 'Balasan dikunci', 'parent_id' => $parent->id])->assertStatus(423);
        $this->postJson('/api/v1/admin/comments/'.$parent->id.'/moderate', ['action' => 'approve', 'reason' => 'Tidak berizin'])->assertForbidden();
        $other = $article->replicate();
        $other->slug = 'other-article';
        $other->save();
        $parent->update(['locked' => false, 'article_id' => $other->id]);
        $this->postJson('/api/v1/articles/'.$article->id.'/comments', ['body' => 'Induk artikel lain', 'parent_id' => $parent->id])->assertUnprocessable();
    }

    public function test_safe_media_rejects_svg_and_keeps_unpublished_image_private(): void
    {
        $this->act($this->account('admin'));
        $this->post('/api/v1/admin/media', ['file' => UploadedFile::fake()->createWithContent('attack.svg', '<svg onload="alert(1)"/>'), 'alt_text' => 'Unsafe'], ['Accept' => 'application/json'])->assertUnprocessable();
        $response = $this->post('/api/v1/admin/media', ['file' => UploadedFile::fake()->image('cover.png', 100, 100), 'alt_text' => 'Cover sumber'], ['Accept' => 'application/json'])->assertCreated();
        $id = $response->json('data.id');
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->get('/api/v1/media/'.$id)->assertNotFound();
    }
}
