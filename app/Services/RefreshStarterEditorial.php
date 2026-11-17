<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RefreshStarterEditorial
{
    private const LEGACY_BODY_HASHES = [
        'npl-dan-car-dua-angka-yang-berbeda-cerita' => '11bd148fb2b387daf81509420546e9ff6290913c30275c5b35f37432e621d9ca',
        'sebelum-transfer-berhenti-cek-dan-lindungi-diri' => '3eee374d65304b607ad7d2f8113369f9e06f8f8846982779447a258bc8060103',
        'punya-rekening-belum-tentu-paham-produknya' => '5f09d652fcc31136ded28c69e5da05f662b04c0bfa1c4c9897a6be5a8cb34028',
        'tiga-tanggal-di-balik-satu-angka' => 'b320d3fd0df01bee59e3d787f7958647c9a0fd22f50a2a8ead43acee3353ab81',
        'membaca-jarak-akses-dan-pemahaman-snlik-2025' => '0f61d27257be82fe514eb3410b0a3fbdcc99c45665d4f88499ba609d299e48ec',
        'potret-perbankan-juni-2025-angka-dan-batas-ceritanya' => 'a7aa2d7e75ece109aca1c509a418578aff67768edeab6d38d5ecd33fc6d50be6',
    ];

    private const EXTENDED_BODY_HASHES = [
        'npl-dan-car-dua-angka-yang-berbeda-cerita' => 'aadea3367f9de6a3738c3b569aad622649bdc7eb3ac128e3727507709a0f33e0',
        'sebelum-transfer-berhenti-cek-dan-lindungi-diri' => 'ac3bd03180b25245de10af31c225909775c5e8ff41b8cefdf65ab117326f564f',
        'punya-rekening-belum-tentu-paham-produknya' => '7b0dd6d122da602ad300d9365c77c2d7d9c13ec3cb217a784e143c5775c80fd6',
        'tiga-tanggal-di-balik-satu-angka' => 'a177f56e98f00fe2529473c524f7bcc944c89511f4672509a59c306842ce13e9',
        'membaca-jarak-akses-dan-pemahaman-snlik-2025' => 'e47f4703b157862c4b0ffaa15e01e7c7298c49d1f5960514ab158c46c302d8e0',
        'potret-perbankan-juni-2025-angka-dan-batas-ceritanya' => 'd6d721436737d952aeef843fe1564c8159f2e8930cc0cb98674c2d7a495afa5f',
    ];

    private const RECOGNIZED_NOTES = [
        'Publikasi awal: naskah edukasi/riset independen dengan referensi resmi.',
        'Perluasan naskah bawaan: storytelling, contoh hitung, metode dan batas interpretasi. Naskah lama tetap ada dalam revisi.',
    ];

    private const PROTECTED_FIELDS = ['title', 'slug', 'excerpt', 'body_html', 'body_doc', 'sources', 'tags', 'content_type', 'seo_title', 'seo_description'];

    /** @return array{updated: list<string>, skipped: array<string, string>} */
    public function refresh(User $user): array
    {
        if ($user->status !== 'active' || ! $user->hasVerifiedEmail() || ! $user->hasPermission('articles.publish') || ! $user->socialIdentities()->where('provider', 'google')->whereNotNull('verified_email_at')->exists()) {
            throw new RuntimeException('Pembaruan memerlukan akun Google aktif terverifikasi dengan izin articles.publish.');
        }

        return DB::transaction(function () use ($user): array {
            $updated = [];
            $skipped = [];
            $content = app(ArticleContent::class);
            foreach (app(StarterEditorialContent::class)->articles() as $piece) {
                $article = Article::where('slug', $piece['slug'])->lockForUpdate()->first();
                if (! $article || ! $article->isPublic()) {
                    $skipped[$piece['slug']] = 'Artikel tidak sedang terbit; status dipertahankan.';

                    continue;
                }
                $body = $content->sanitize($piece['body']);
                if (hash_equals(hash('sha256', $body), hash('sha256', $article->body_html))) {
                    $skipped[$piece['slug']] = 'Edisi editorial terbaru sudah terpasang.';

                    continue;
                }
                $previous = ArticleRevision::where('article_id', $article->id)->where('version', $article->revision)->first();
                $knownOriginal = $previous && in_array($previous->change_note, self::RECOGNIZED_NOTES, true)
                    && in_array(hash('sha256', $article->body_html), [self::LEGACY_BODY_HASHES[$piece['slug']], self::EXTENDED_BODY_HASHES[$piece['slug']]], true);
                if (! $knownOriginal || ! $this->matchesSnapshot($article, $previous->snapshot)) {
                    $skipped[$piece['slug']] = 'Ada suntingan atau asal naskah belum cocok; tidak ditimpa.';

                    continue;
                }
                $article->fill([
                    'title' => $piece['title'], 'excerpt' => $piece['excerpt'], 'body_html' => $body,
                    'body_doc' => null, 'sources' => $piece['sources'], 'tags' => $piece['tags'],
                    'seo_title' => $piece['title'], 'seo_description' => $piece['excerpt'],
                ])->forceFill(['revision' => $article->revision + 1])->save();
                ArticleRevision::create([
                    'article_id' => $article->id, 'editor_id' => $user->id, 'version' => $article->revision,
                    'snapshot' => $article->getAttributes(),
                    'change_note' => 'Penyuntingan editorial: urutan cerita dirapikan, pengulangan dikurangi, dan istilah dijelaskan. Edisi sebelumnya tetap tersimpan.',
                ]);
                AuditService::record('article.editorial_refreshed', $user, ['channel' => 'cli', 'article_id' => $article->id, 'revision' => $article->revision]);
                $updated[] = $article->slug;
            }

            return ['updated' => $updated, 'skipped' => $skipped];
        });
    }

    private function matchesSnapshot(Article $article, array $snapshot): bool
    {
        foreach (self::PROTECTED_FIELDS as $field) {
            $original = $snapshot[$field] ?? null;
            if (in_array($field, ['sources', 'tags', 'body_doc'], true) && is_string($original)) {
                $original = json_decode($original, true);
            }
            if ($this->canonicalValue($article->getAttribute($field)) !== $this->canonicalValue($original)) {
                return false;
            }
        }

        return true;
    }

    private function canonicalValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $child) {
            $value[$key] = $this->canonicalValue($child);
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
