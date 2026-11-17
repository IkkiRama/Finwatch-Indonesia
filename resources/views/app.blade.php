<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'FINWATCH Indonesia' }}</title>
    <meta name="description" content="{{ $meta['description'] ?? 'Platform independen untuk analisis sektor jasa keuangan dan edukasi masyarakat; tidak berafiliasi dengan OJK.' }}">
    <meta name="robots" content="{{ ($noindex ?? false) ? 'noindex, nofollow' : 'index, follow' }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="canonical" href="{{ $meta['canonical'] ?? url()->current() }}">
    <meta property="og:title" content="{{ $meta['title'] ?? $title ?? 'FINWATCH Indonesia' }}">
    <meta property="og:description" content="{{ $meta['description'] ?? 'Analisis dan edukasi keuangan independen.' }}">
    <meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
    <meta property="og:url" content="{{ $meta['canonical'] ?? url()->current() }}">
    <meta property="og:image" content="{{ $meta['image'] ?? url('/og-default.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $meta['title'] ?? $title ?? 'FINWATCH Indonesia' }}">
    <meta name="twitter:description" content="{{ $meta['description'] ?? 'Analisis dan edukasi keuangan independen.' }}">
    <meta name="twitter:image" content="{{ $meta['image'] ?? url('/og-default.png') }}">
    @isset($jsonLd)<script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode($jsonLd, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>@endisset
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
</head>
<body>
    <div id="app">{!! $semantic ?? '' !!}</div>
    @if(!empty($articleBootstrap))<script type="application/json" id="finwatch-article-bootstrap" nonce="{{ Vite::cspNonce() }}">{!! json_encode($articleBootstrap, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@endif
    <noscript>@empty($semantic)<main><h1>FINWATCH Indonesia</h1><p>Aktifkan JavaScript untuk membuka grafik, penjelajah data, dan workspace.</p></main>@endempty<footer>© {{ now()->year }} FINWATCH Indonesia. Proyek independen; tidak berafiliasi dengan OJK. Made with <span class="beating-heart" role="img" aria-label="cinta">♥</span> by <a href="https://www.instagram.com/george_ikki/" target="_blank" rel="noopener noreferrer" aria-label="Profil Instagram Rifki Romadhan">Rifki Romadhan</a></footer></noscript>
</body>
</html>
