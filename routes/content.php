<?php

use App\Http\Controllers\ArticleController as A;
use App\Http\Controllers\CommunityController as C;
use App\Http\Controllers\MediaController as M;
use App\Http\Controllers\PublicationController as P;
use Illuminate\Support\Facades\Route;

Route::get('/artikel/{slug}', [P::class, 'article']);
Route::get('/kategori/{slug}', [P::class, 'category']);
Route::get('/penulis/{slug}', [P::class, 'author']);
Route::get('/preview/articles/{article}', [P::class, 'preview'])->middleware(['signed', 'throttle:30,1,preview'])->name('article.preview');
Route::get('/sitemap.xml', [P::class, 'sitemap']);
Route::get('/robots.txt', [P::class, 'robots']);
Route::get('/artikel/{slug}/pdf', [P::class, 'pdf'])->middleware('throttle:10,1,pdf');
Route::prefix('api/v1')->middleware('throttle:public-read')->group(function (): void {
    Route::get('articles', [A::class, 'index']);
    Route::get('articles/{slug}', [A::class, 'show']);
    Route::get('categories', [A::class, 'categories']);
    Route::get('categories/{slug}', [A::class, 'category']);
    Route::get('authors', [A::class, 'authors']);
    Route::get('authors/{slug}', [A::class, 'author']);
    Route::get('articles/{article}/comments', [C::class, 'index']);
    Route::get('comments/{comment}/replies', [C::class, 'replies'])->withTrashed();
    Route::get('comments/{comment}/context', [C::class, 'context'])->withTrashed();
    Route::get('media/{media}', [M::class, 'show']);
});
Route::prefix('api/v1')->middleware(['auth:sanctum', 'active', 'can:comments.create', 'throttle:30,1,community-write'])->group(function (): void {
    Route::post('articles/{article}/comments', [C::class, 'create'])->middleware('throttle:5,1,comment-post');
    Route::patch('comments/{comment}', [C::class, 'update']);
    Route::delete('comments/{comment}', [C::class, 'destroy']);
    Route::post('comments/{comment}/likes', [C::class, 'like']);
    Route::delete('comments/{comment}/likes', [C::class, 'like']);
    Route::post('comments/{comment}/reports', [C::class, 'report'])->middleware('throttle:5,1,comment-report');
});
Route::prefix('api/v1/admin')->middleware(['auth:sanctum', 'active', 'can:admin.access', 'admin.mfa', 'throttle:90,1,content-admin'])->group(function (): void {
    Route::middleware('can:articles.create')->group(function (): void {
        Route::get('articles', [A::class, 'index']);
        Route::post('articles', [A::class, 'save']);
        Route::get('articles/{article}', [A::class, 'edit']);
        Route::put('articles/{article}', [A::class, 'save']);
        Route::delete('articles/{article}', [A::class, 'destroy']);
        Route::post('articles/{article}/status', [A::class, 'transition']);
        Route::get('articles/{article}/revisions', [A::class, 'revisions']);
        Route::get('articles/{article}/revisions/{revision}', [A::class, 'revision']);
        Route::post('articles/{article}/revisions/{revision}/restore', [A::class, 'restore']);
        Route::get('media', [M::class, 'index']);
        Route::post('media', [M::class, 'upload'])->middleware('throttle:10,1,media-upload');
        Route::delete('media/{media}', [M::class, 'destroy']);
    });
    Route::middleware('can:categories.manage')->group(function (): void {
        Route::get('authors', [A::class, 'adminAuthors']);
        Route::post('categories', [A::class, 'categorySave']);
        Route::put('categories/{category}', [A::class, 'categorySave']);
        Route::delete('categories/{category}', [A::class, 'categoryDestroy']);
        Route::post('authors', [A::class, 'authorSave']);
        Route::put('authors/{author}', [A::class, 'authorSave']);
        Route::delete('authors/{author}', [A::class, 'authorDestroy']);
    });
    Route::middleware('can:comments.manage')->group(function (): void {
        Route::get('comments', [C::class, 'moderation']);
        Route::post('comments/{comment}/moderate', [C::class, 'moderate']);
    });
});
