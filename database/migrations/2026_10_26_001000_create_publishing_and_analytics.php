<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table): void {
            $table->json('allowed_hosts')->nullable();
            $table->text('download_url')->nullable();
            $table->string('adapter', 32)->default('manual');
            $table->string('frequency', 32)->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('last_checksum', 64)->nullable();
        });
        Schema::table('datasets', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_version_id')->nullable()->index();
            $table->text('description')->nullable();
        });
        Schema::create('dataset_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('status', 32)->default('needs_review')->index();
            $table->string('checksum', 64);
            $table->string('raw_path');
            $table->json('rows');
            $table->json('issues');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['dataset_id', 'number']);
            $table->unique(['dataset_id', 'checksum']);
        });
        Schema::table('indicator_observations', function (Blueprint $table): void {
            $table->index('dataset_id', 'observation_dataset_lookup');
        });
        Schema::table('indicator_observations', function (Blueprint $table): void {
            $table->dropUnique('observation_series_vintage_unique');
            $table->foreignId('version_id')->nullable()->constrained('dataset_versions')->cascadeOnDelete();
            $table->string('sector', 64)->default('banking')->index();
            $table->string('segment', 100)->default('aggregate');
            $table->unique(['dataset_id', 'version_id', 'indicator_code', 'scope', 'period_date', 'segment'], 'observation_version_series_unique');
        });
        Schema::create('indicators', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('label');
            $table->string('unit', 64);
            $table->string('frequency', 16);
            $table->string('sector', 64)->default('banking');
            $table->string('change_type', 16)->default('level');
            $table->text('definition')->nullable();
            $table->text('methodology_note')->nullable();
            $table->timestamps();
        });
        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('queued')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('attempt')->default(1);
            $table->unsignedBigInteger('version_id')->nullable();
            $table->json('logs');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('risk_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('indicator_code', 64)->index();
            $table->string('operator', 16);
            $table->decimal('threshold', 24, 8);
            $table->unsignedInteger('minimum_samples')->default(3);
            $table->string('severity', 16)->default('watch');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->text('methodology');
            $table->timestamps();
        });
        Schema::create('stress_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('parameters');
            $table->text('methodology');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('published')->default(false);
            $table->timestamps();
        });
        Schema::create('author_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('public_name');
            $table->text('bio')->nullable();
            $table->json('public_links')->nullable();
            $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('path')->unique();
            $table->string('mime_type', 64);
            $table->unsignedInteger('bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('alt_text');
            $table->timestamps();
        });
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->constrained('author_profiles')->restrictOnDelete();
            $table->foreignId('primary_category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt');
            $table->longText('body_html');
            $table->json('body_doc')->nullable();
            $table->json('sources');
            $table->json('tags');
            $table->string('content_type', 20)->default('education')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('allow_comments')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at', 'id']);
        });
        Schema::create('article_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('change_note')->nullable();
            $table->timestamps();
            $table->unique(['article_id', 'version']);
        });
        Schema::create('article_slug_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('old_slug')->unique();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
        });
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->restrictOnDelete();
            $table->text('body_plain');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('depth')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->boolean('locked')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['article_id', 'parent_id', 'status', 'id']);
        });
        Schema::create('comment_likes', function (Blueprint $table): void {
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['comment_id', 'user_id']);
        });
        Schema::create('comment_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 500);
            $table->string('status', 16)->default('open');
            $table->timestamps();
            $table->unique(['comment_id', 'user_id']);
        });
        Schema::create('comment_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->text('body_plain');
            $table->timestamp('created_at');
        });
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });
        Schema::create('system_heartbeats', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->timestamp('seen_at');
        });
    }

    public function down(): void
    {
        foreach (['system_heartbeats', 'system_settings', 'comment_revisions', 'comment_reports', 'comment_likes', 'comments', 'article_slug_redirects', 'article_revisions', 'articles', 'media_assets', 'categories', 'author_profiles', 'stress_scenarios', 'risk_rules', 'sync_runs', 'indicators'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('indicator_observations', function (Blueprint $table): void {
            $table->dropUnique('observation_version_series_unique');
            $table->dropIndex(['sector']);
            $table->dropConstrainedForeignId('version_id');
            $table->dropColumn(['sector', 'segment']);
            $table->unique(['dataset_id', 'indicator_code', 'scope', 'period_date', 'dataset_version'], 'observation_series_vintage_unique');
        });
        Schema::table('indicator_observations', function (Blueprint $table): void {
            $table->dropIndex('observation_dataset_lookup');
        });
        Schema::dropIfExists('dataset_versions');
        Schema::table('datasets', function (Blueprint $table): void {
            $table->dropIndex(['current_version_id']);
            $table->dropColumn(['current_version_id', 'description']);
        });
        Schema::table('data_sources', fn (Blueprint $table) => $table->dropColumn(['allowed_hosts', 'download_url', 'adapter', 'frequency', 'enabled', 'last_checksum']));
    }
};
