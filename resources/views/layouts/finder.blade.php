<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        @include('partials.head', ['title' => __('Повод — подрядчики для вашего события')])
        <meta name="description" content="{{ __('Подберите до трёх подрядчиков для мероприятия по городу, дате, бюджету и формату. С понятными причинами выбора.') }}">
    </head>
    <body class="finder-page font-sans antialiased">
        {{ $slot }}
        @fluxScripts
    </body>
</html>
