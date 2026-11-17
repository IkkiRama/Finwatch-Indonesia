<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\AuthorProfile;
use App\Models\Category;
use App\Services\ArticleContent;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PublicationController extends Controller
{
    public function article(string $slug, ArticleContent $content): Response
    {
        $article = Article::published()->where('slug', $slug)->first();
        if (! $article) {
            $redirect = DB::table('article_slug_redirects')->where('old_slug', $slug)->first();
            if ($redirect) {
                $target = Article::published()->find($redirect->article_id);
                if ($target) {
                    return redirect('/artikel/'.$target->slug, 301);
                }
            }
            abort(404);
        }

        return $this->render($article, $content, false);
    }

    public function preview(Article $article, ArticleContent $content): Response
    {
        return $this->render($article, $content, true)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function render(Article $article, ArticleContent $content, bool $preview): Response
    {
        $data = $content->present($article, true);
        $canonical = url('/artikel/'.$article->slug);
        $meta = ['title' => ($article->seo_title ?: $article->title).' | FINWATCH Indonesia', 'description' => $article->seo_description ?: $article->excerpt, 'canonical' => $canonical, 'image' => $data['cover']['url'] ?? url('/og-default.png'), 'type' => 'article'];
        $jsonLd = [['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $article->title, 'description' => $article->excerpt, 'datePublished' => $article->published_at?->toIso8601String(), 'dateModified' => $article->updated_at->toIso8601String(), 'author' => ['@type' => 'Person', 'name' => $article->author->public_name, 'url' => url('/penulis/'.$article->author->slug)], 'mainEntityOfPage' => $canonical, 'image' => [$meta['image']]],
            ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Edukasi Keuangan', 'item' => url('/artikel')], ['@type' => 'ListItem', 'position' => 2, 'name' => $article->category->name, 'item' => url('/kategori/'.$article->category->slug)], ['@type' => 'ListItem', 'position' => 3, 'name' => $article->title, 'item' => $canonical]]]];

        return response()->view('app', ['title' => $meta['title'], 'noindex' => $preview, 'meta' => $meta, 'jsonLd' => $jsonLd, 'articleBootstrap' => $preview ? $data : null, 'semantic' => view('semantic.article', ['article' => $article, 'data' => $data, 'preview' => $preview])->render()]);
    }

    public function category(string $slug, ArticleContent $content): Response
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        return $this->archive($category->name, $category->description ?? 'Arsip edukasi keuangan berdasarkan kategori.', Article::published()->where('primary_category_id', $category->id)->latest('published_at')->limit(12)->get());
    }

    public function author(string $slug, ArticleContent $content): Response
    {
        $author = AuthorProfile::where('slug', $slug)->firstOrFail();

        return $this->archive($author->public_name, $author->bio ?? 'Arsip publikasi penulis FINWATCH.', Article::published()->where('author_id', $author->id)->latest('published_at')->limit(12)->get());
    }

    private function archive(string $title, string $description, Collection $articles): Response
    {
        $meta = ['title' => $title.' | FINWATCH Indonesia', 'description' => $description, 'canonical' => url()->current(), 'image' => url('/og-default.png'), 'type' => 'website'];

        return response()->view('app', ['title' => $meta['title'], 'noindex' => false, 'meta' => $meta, 'semantic' => view('semantic.archive', compact('title', 'description', 'articles'))->render()]);
    }

    public function sitemap(): Response
    {
        $urls = collect([url('/'), url('/artikel'), url('/sumber-data'), url('/tentang')])->map(fn ($url) => ['url' => $url, 'modified' => null]);
        foreach (Article::published()->select('slug', 'updated_at')->cursor() as $article) {
            $urls->push(['url' => url('/artikel/'.$article->slug), 'modified' => $article->updated_at->toAtomString()]);
        }
        foreach (Category::select('slug', 'updated_at')->cursor() as $category) {
            $urls->push(['url' => url('/kategori/'.$category->slug), 'modified' => $category->updated_at->toAtomString()]);
        }
        foreach (AuthorProfile::select('slug', 'updated_at')->cursor() as $author) {
            $urls->push(['url' => url('/penulis/'.$author->slug), 'modified' => $author->updated_at->toAtomString()]);
        }

        return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        return response("User-agent: *\nDisallow: /admin\nDisallow: /api/\nDisallow: /preview/\nDisallow: /auth/\nSitemap: ".url('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain']);
    }

    public function pdf(string $slug, ArticleContent $content): Response
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('chroot', storage_path('app/private'));
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('print.article', ['article' => $article, 'data' => $content->present($article, true)])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="finwatch-'.$article->slug.'.pdf"', 'X-Content-Type-Options' => 'nosniff']);
    }
}
