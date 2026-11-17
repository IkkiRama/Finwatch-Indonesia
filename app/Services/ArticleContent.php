<?php

namespace App\Services;

use App\Models\Article;
use DOMDocument;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArticleContent
{
    private const RESEARCH_PROFILE_HASHES = [
        '27c6c4137bfba61e86ac310f31cb0e38d45bb628c554083a99c9b3f35e290564' => 'membaca-jarak-akses-dan-pemahaman-snlik-2025',
        '9a36f4323604b4c27f184c07ec190b96e4405776b5a19f3fad9768b2411f3bc8' => 'potret-perbankan-juni-2025-angka-dan-batas-ceritanya',
        'e47f4703b157862c4b0ffaa15e01e7c7298c49d1f5960514ab158c46c302d8e0' => 'membaca-jarak-akses-dan-pemahaman-snlik-2025',
        'd6d721436737d952aeef843fe1564c8159f2e8930cc0cb98674c2d7a495afa5f' => 'potret-perbankan-juni-2025-angka-dan-batas-ceritanya',
    ];

    private ?bool $siteCommentsEnabled = null;

    private function commentsEnabled(): bool
    {
        return $this->siteCommentsEnabled ??= json_decode(DB::table('system_settings')->where('key', 'comments_enabled')->value('value') ?? 'true', true) !== false;
    }

    public function sanitize(string $html): string
    {
        $configuration = HTMLPurifier_Config::createDefault();
        $configuration->set('Cache.SerializerPath', storage_path('framework/cache'));
        $configuration->set('HTML.Allowed', 'p,br,strong,em,u,s,h2,h3,ul,ol,li,blockquote,pre,code,a[href|title|target],img[src|alt|width|height],table,thead,tbody,tr,th,td,hr');
        $configuration->set('URI.AllowedSchemes', ['https' => true, 'http' => true]);
        $configuration->set('HTML.TargetBlank', true);
        $purifier = new HTMLPurifier($configuration);
        $safe = $purifier->purify($html);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><html><body>'.$safe.'</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        foreach (iterator_to_array($dom->getElementsByTagName('img')) as $image) {
            if (! preg_match('~^/api/v1/media/[1-9][0-9]*(\?size=thumb)?$~', $image->getAttribute('src'))) {
                $image->parentNode->removeChild($image);
            }
        }
        foreach ($dom->getElementsByTagName('a') as $link) {
            $link->setAttribute('rel', 'noopener noreferrer');
        }
        $index = 0;
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (in_array($element->tagName, ['h2', 'h3'], true)) {
                $element->setAttribute('id', 'bagian-'.(++$index).'-'.Str::slug(mb_substr($element->textContent, 0, 70)));
            }
        }
        $body = $dom->getElementsByTagName('body')->item(0);
        $result = '';
        foreach ($body->childNodes as $node) {
            $result .= $dom->saveHTML($node);
        }

        return $result;
    }

    public function present(Article $article, bool $detail = false, bool $editable = false): array
    {
        $article->loadMissing(['author:id,user_id,slug,public_name,bio', 'category:id,name,slug', 'cover']);
        $data = ['id' => $article->id, 'title' => $article->title, 'slug' => $article->slug, 'excerpt' => $article->excerpt, 'content_type' => $article->content_type, 'status' => $article->status,
            'author' => ['id' => $article->author->id, 'slug' => $article->author->slug, 'public_name' => $article->author->public_name, 'bio' => $article->author->bio],
            'category' => $article->category->only(['id', 'name', 'slug']), 'cover' => $article->cover ? ['id' => $article->cover->id, 'url' => url('/api/v1/media/'.$article->cover->id), 'alt' => $article->cover->alt_text] : null,
            'tags' => $article->tags, 'published_at' => $article->published_at?->toIso8601String(), 'updated_at' => $article->updated_at?->toIso8601String(), 'scheduled_at' => $article->scheduled_at?->toIso8601String(),
            'reading_minutes' => max(1, (int) ceil(str_word_count(strip_tags($article->body_html)) / 200)), 'allow_comments' => $article->allow_comments && ($editable || $this->commentsEnabled()), 'is_featured' => $article->is_featured, 'revision' => $article->revision,
            'seo_title' => $article->seo_title, 'seo_description' => $article->seo_description, 'url' => url('/artikel/'.$article->slug),
            'research_profile_slug' => $article->content_type === 'research' ? (self::RESEARCH_PROFILE_HASHES[hash('sha256', $article->body_html)] ?? null) : null];
        if ($detail) {
            $data += ['body_html' => $article->body_html, 'sources' => $article->sources, 'author_id' => $article->author_id, 'primary_category_id' => $article->primary_category_id, 'cover_media_id' => $article->cover_media_id, 'toc' => $this->toc($article->body_html)];
            if ($editable) {
                $data['body_doc'] = $article->body_doc;
            }
        }

        return $data;
    }

    public function toc(string $html): array
    {
        preg_match_all('~<(h[23]) id="([^"]+)"[^>]*>(.*?)</h[23]>~s', $html, $matches, PREG_SET_ORDER);

        return array_map(fn ($heading) => ['id' => $heading[2], 'level' => (int) substr($heading[1], 1), 'text' => html_entity_decode(strip_tags($heading[3]), ENT_QUOTES, 'UTF-8')], $matches);
    }
}
