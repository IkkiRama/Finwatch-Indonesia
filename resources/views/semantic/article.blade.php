<main class="server-content"><article>
    @if($preview)<p>Pratinjau privat; belum tentu sudah dipublikasikan.</p>@endif
    <nav aria-label="Breadcrumb"><a href="/artikel">Edukasi Keuangan</a> / <a href="/kategori/{{ $article->category->slug }}">{{ $article->category->name }}</a></nav>
    <h1>{{ $article->title }}</h1><p>{{ $article->excerpt }}</p>
    <p>Oleh <a href="/penulis/{{ $article->author->slug }}">{{ $article->author->public_name }}</a> · {{ $article->published_at?->translatedFormat('d F Y') }} · {{ $data['reading_minutes'] }} menit baca</p>
    @if($data['cover'])<img src="{{ $data['cover']['url'] }}" alt="{{ $data['cover']['alt'] }}" width="800">@endif
    <div>{!! $article->body_html !!}</div>
    <h2>Referensi</h2><ul>@foreach($article->sources as $source)<li><a href="{{ $source['url'] }}" rel="noopener noreferrer">{{ $source['title'] }}</a></li>@endforeach</ul>
    <p>Materi edukasi dan penelitian independen, bukan rekomendasi investasi atau penilaian regulator.</p>
</article></main>
