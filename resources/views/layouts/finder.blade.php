<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ __('seelect — подрядчики для вашего события') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/css/finder.css', 'resources/js/app.js'])
        <link rel="icon" href="{{ asset('images/firebird-glyph.svg') }}" type="image/svg+xml">
        <meta name="description" content="{{ __('Подберите до трёх подрядчиков для мероприятия по городу, дате, бюджету и формату. С понятными причинами выбора.') }}">
    </head>
    <body>
        {{ $slot }}
        @fluxScripts
    </body>
</html>
