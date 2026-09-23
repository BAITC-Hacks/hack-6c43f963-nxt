<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ __('Повод — подрядчики для вашего события') }}</title>
        @vite(['resources/css/app.css', 'resources/css/finder.css', 'resources/js/app.js'])
        <link rel="icon" href="{{ asset('images/firebird-glyph.svg') }}" type="image/svg+xml">
        <meta name="description" content="{{ __('Подберите до трёх подрядчиков для мероприятия по городу, дате, бюджету и формату. С понятными причинами выбора.') }}">
    </head>
    <body>
        {{ $slot }}
        @fluxScripts
    </body>
</html>
