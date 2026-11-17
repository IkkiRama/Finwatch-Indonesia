<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishScheduled extends Command
{
    protected $signature = 'finwatch:publish-scheduled';

    protected $description = 'Publikasikan artikel terjadwal yang sudah disetujui dan jatuh tempo.';

    public function handle(): int
    {
        $count = 0;
        Article::where('status', 'scheduled')->where('scheduled_at', '<=', now())->select('id')->chunkById(50, function ($articles) use (&$count): void {
            foreach ($articles as $item) {
                DB::transaction(function () use ($item, &$count): void {
                    $article = Article::whereKey($item->id)->lockForUpdate()->first();
                    if ($article && $article->status === 'scheduled' && $article->scheduled_at?->lte(now())) {
                        $article->update(['status' => 'published', 'published_at' => now(), 'scheduled_at' => null]);
                        AuditService::record('article.scheduled_published', null, ['article_id' => $article->id, 'channel' => 'scheduler']);
                        $count++;
                    }
                });
            }
        });
        $this->info($count.' artikel diterbitkan.');

        return self::SUCCESS;
    }
}
