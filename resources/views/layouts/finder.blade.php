<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        @include('partials.head', ['title' => __('Повод — подрядчики для вашего события')])
        <meta name="description" content="{{ __('Подберите до трёх подрядчиков для мероприятия по городу, дате, бюджету и формату. С понятными причинами выбора.') }}">
    </head>
    <body class="bg-[#f7f6f2] font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        {{ $slot }}
        @fluxScripts
    </body>
</html>
