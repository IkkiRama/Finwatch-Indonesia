<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\AuthorProfile;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PublishStarterEditorial
{
    public function publish(User $user): array
    {
        if ($user->status !== 'active' || ! $user->hasVerifiedEmail() || ! $user->hasPermission('articles.publish') || ! $user->socialIdentities()->where('provider', 'google')->whereNotNull('verified_email_at')->exists()) {
            throw new RuntimeException('Publikasi memerlukan akun Google aktif terverifikasi dengan izin articles.publish.');
        }

        return DB::transaction(function () use ($user): array {
            $author = AuthorProfile::firstOrCreate(['user_id' => $user->id], [
                'slug' => 'rifki-romadhan', 'public_name' => 'Rifki Romadhan',
                'bio' => 'Pengembang FINWATCH Indonesia. Menulis catatan edukasi dan analisis deskriptif independen dari sumber publik; tidak mewakili OJK atau lembaga penyedia data.',
                'public_links' => ['https://www.instagram.com/george_ikki/'],
            ]);
            $created = [];
            $skipped = [];
            foreach (app(StarterEditorialContent::class)->articles() as $index => $piece) {
                if (Article::withTrashed()->where('slug', $piece['slug'])->exists() || DB::table('article_slug_redirects')->where('old_slug', $piece['slug'])->exists()) {
                    $skipped[] = $piece['slug'];

                    continue;
                }
                $category = Category::firstOrCreate(['slug' => $piece['category_slug']], ['name' => $piece['category'], 'description' => $piece['type'] === 'research' ? 'Analisis deskriptif independen dengan data, cara hitung, dan batas interpretasi yang jelas.' : 'Panduan singkat untuk memahami istilah dan mengambil langkah pemeriksaan yang lebih tepat.']);
                $cover = $this->cover($user, $piece, $index);
                $article = Article::create([
                    'author_id' => $author->id, 'primary_category_id' => $category->id,
                    'cover_media_id' => $cover->id, 'title' => $piece['title'], 'slug' => $piece['slug'],
                    'excerpt' => $piece['excerpt'], 'body_html' => app(ArticleContent::class)->sanitize($piece['body']),
                    'body_doc' => null, 'sources' => $piece['sources'], 'tags' => $piece['tags'],
                    'content_type' => $piece['type'], 'status' => 'published', 'published_at' => now()->subMinutes($index),
                    'seo_title' => $piece['title'], 'seo_description' => $piece['excerpt'],
                    'allow_comments' => true, 'is_featured' => in_array($index, [0, 4], true),
                ]);
                ArticleRevision::create(['article_id' => $article->id, 'editor_id' => $user->id, 'version' => $article->revision, 'snapshot' => $article->getAttributes(), 'change_note' => 'Publikasi awal: naskah edukasi/riset independen dengan referensi resmi.']);
                AuditService::record('article.published', $user, ['channel' => 'cli', 'article_id' => $article->id, 'revision' => $article->revision]);
                $created[] = $article->slug;
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    private function cover(User $user, array $piece, int $index): MediaAsset
    {
        $path = 'media/editorial-'.$piece['slug'].'.webp';
        $existing = MediaAsset::where('path', $path)->first();
        if ($existing) {
            return $existing;
        }
        $palette = [[232, 227, 255], [255, 223, 204], [217, 238, 229], [245, 237, 200], [225, 227, 248], [244, 221, 231]];
        $image = imagecreatetruecolor(1200, 720);
        [$red, $green, $blue] = $palette[$index % count($palette)];
        $background = imagecolorallocate($image, $red, $green, $blue);
        $ink = imagecolorallocate($image, 48, 44, 66);
        $paper = imagecolorallocate($image, 255, 253, 248);
        $accent = imagecolorallocate($image, 117, 98, 170);
        imagefill($image, 0, 0, $background);
        imagefilledellipse($image, 965, 130, 420, 420, $paper);
        imagefilledellipse($image, 60, 750, 550, 400, $paper);
        imagesetthickness($image, 4);
        imageline($image, 84, 100, 1080, 100, $ink);
        $this->text($image, 'FINWATCH / '.$piece['category'], 82, 75, 22, $ink);
        $this->text($image, $piece['type'] === 'research' ? 'CATATAN RISET' : 'RUANG BELAJAR', 82, 640, 24, $ink);
        $this->text($image, '0'.($index + 1), 1030, 655, 48, $ink);

        if (in_array($piece['theme'], ['literacy', 'gap'], true)) {
            for ($i = 0; $i < 10; $i++) {
                $x = 240 + ($i % 5) * 150;
                $y = 235 + intdiv($i, 5) * 180;
                imagefilledellipse($image, $x, $y, 44, 44, $i < 7 ? $accent : $paper);
                imagefilledrectangle($image, $x - 28, $y + 35, $x + 28, $y + 105, $i < 7 ? $accent : $paper);
                imageline($image, $x - 20, $y + 105, $x - 20, $y + 135, $ink);
                imageline($image, $x + 20, $y + 105, $x + 20, $y + 135, $ink);
            }
            $this->text($image, 'AKSES  /  PAHAM', 390, 595, 22, $ink);
        } elseif ($piece['theme'] === 'safety') {
            imagefilledpolygon($image, [600, 180, 790, 260, 760, 430, 600, 535, 440, 430, 410, 260], $paper);
            imagepolygon($image, [600, 180, 790, 260, 760, 430, 600, 535, 440, 430, 410, 260], $ink);
            imagesetthickness($image, 14);
            imageline($image, 510, 350, 575, 415, $accent);
            imageline($image, 575, 415, 695, 285, $accent);
            $this->text($image, 'BERHENTI. CEK. LINDUNGI.', 345, 590, 24, $ink);
        } elseif ($piece['theme'] === 'dates') {
            imagefilledrectangle($image, 360, 200, 840, 520, $paper);
            imagerectangle($image, 360, 200, 840, 520, $ink);
            imagefilledrectangle($image, 360, 200, 840, 285, $accent);
            $this->text($image, 'PERIODE  /  RILIS  /  DIAMBIL', 375, 260, 18, $paper);
            foreach ([440, 600, 760] as $x) {
                imagefilledellipse($image, $x, 400, 70, 70, $background);
            }
            $this->text($image, 'SATU ANGKA, TIGA TANGGAL', 315, 590, 24, $ink);
        } else {
            imagefilledpolygon($image, [350, 295, 600, 165, 850, 295], $paper);
            imagepolygon($image, [350, 295, 600, 165, 850, 295], $ink);
            foreach ([420, 550, 680] as $x) {
                imagefilledrectangle($image, $x, 310, $x + 100, 485, $paper);
                imagerectangle($image, $x, 310, $x + 100, 485, $ink);
            }
            imagefilledrectangle($image, 345, 500, 855, 535, $accent);
            $this->text($image, $piece['theme'] === 'research' ? 'BACA ANGKA, JAGA KONTEKS' : 'KREDIT  /  RISIKO  /  MODAL', 330, 590, 24, $ink);
        }
        ob_start();
        imagewebp($image, null, 88);
        $bytes = ob_get_clean();
        Storage::disk('local')->put($path, $bytes);
        $thumb = imagecreatetruecolor(400, 240);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, 400, 240, 1200, 720);
        ob_start();
        imagewebp($thumb, null, 85);
        Storage::disk('local')->put(str_replace('.webp', '-thumb.webp', $path), ob_get_clean());
        imagedestroy($thumb);
        imagedestroy($image);

        return MediaAsset::create(['owner_id' => $user->id, 'path' => $path, 'mime_type' => 'image/webp', 'bytes' => strlen($bytes), 'width' => 1200, 'height' => 720, 'alt_text' => 'Ilustrasi editorial '.$piece['category'].'. Bentuk dekoratif, bukan grafik data.']);
    }

    private function text(\GdImage $image, string $text, int $x, int $y, int $size, int $color): void
    {
        foreach (['C:/Windows/Fonts/seguisb.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'] as $font) {
            if (is_file($font)) {
                imagettftext($image, $size, 0, $x, $y, $color, $font, $text);

                return;
            }
        }
        imagestring($image, 5, $x, $y - 20, $text, $color);
    }
}
