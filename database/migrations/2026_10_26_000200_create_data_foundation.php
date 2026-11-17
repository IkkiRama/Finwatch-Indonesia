<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->text('url');
            $table->string('method', 32)->default('manual');
            $table->string('status', 40)->default('awaiting_verification')->index();
            $table->text('description');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('indicator_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->string('indicator_code', 64);
            $table->date('period_date')->index();
            $table->decimal('value', 24, 8);
            $table->string('unit', 64);
            $table->string('scope', 100)->default('Indonesia');
            $table->string('frequency', 16);
            $table->text('source_url');
            $table->date('release_date');
            $table->timestamp('retrieved_at');
            $table->string('dataset_version', 64);
            $table->text('methodology_note')->nullable();
            $table->timestamps();
            $table->unique(['dataset_id', 'indicator_code', 'scope', 'period_date', 'dataset_version'], 'observation_series_vintage_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_observations');
        Schema::dropIfExists('datasets');
        Schema::dropIfExists('data_sources');
    }
};
