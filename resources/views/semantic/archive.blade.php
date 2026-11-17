<main class="server-content"><h1>{{ $title }}</h1><p>{{ $description }}</p>
@forelse($articles as $article)<article><h2><a href="/artikel/{{ $article->slug }}">{{ $article->title }}</a></h2><p>{{ $article->excerpt }}</p></article>@empty<p>Belum ada artikel yang dipublikasikan.</p>@endforelse
</main>
