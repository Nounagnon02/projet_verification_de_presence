@php
    // Atkinson Hyperlegible, telle que Google Fonts la sert, ne déclare que les
    // sous-ensembles « latin » et « latin-ext ». Leurs unicode-range excluent
    // ẹ (U+1EB9), ọ (U+1ECD) et les marques de ton U+0300 / U+0301 : en yoruba
    // et en fon, ces caractères tomberaient sur une police de repli au milieu
    // des mots. Noto Sans fournit en plus le sous-ensemble « vietnamese », qui
    // les couvre. Spectral (titres) le fournit déjà, elle ne change pas.
    $extended = config('locales.supported.'.app()->getLocale().'.glyphs') === 'extended';
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
@if ($extended)
    <link href="https://fonts.googleapis.com/css2?family=Spectral:wght@500;600;700&family=Noto+Sans:wght@400;700&display=swap" rel="stylesheet" />
    <style>:root { --font-sans: 'Noto Sans'; }</style>
@else
    <link href="https://fonts.googleapis.com/css2?family=Spectral:wght@500;600;700&family=Atkinson+Hyperlegible:wght@400;700&display=swap" rel="stylesheet" />
@endif
