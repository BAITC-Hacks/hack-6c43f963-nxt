<?php

use App\Services\ContractorChatGuide;
use Livewire\Livewire;

beforeEach(function () {
    config(['contractors.ai_enabled' => false]);
    session()->put('locale', 'ru');
    app()->setLocale('ru');
});

it('walks the chat through chips and finishes with up to three matched cards', function () {
    Livewire::test('pages::contractor-finder')
        ->call('openChat')
        ->assertSet('chatOpen', true)
        ->assertSee('В каком городе пройдёт событие?')
        ->call('selectChatChip', 'Алматы')
        ->call('selectChatChip', 'свадьба')
        ->call('selectChatChip', 'Ведущий')
        ->call('selectChatChip', '2026-09-23')
        ->call('selectChatChip', '1500000')
        ->call('skipChatStep')
        ->call('skipChatStep')
        ->assertSet('chatStep', 'done')
        ->assertSet('result.status', 'matched')
        ->assertCount('result.contractors', 3)
        ->assertSee('Мицури Канроджи')
        ->assertSee('Готово. Ниже — до трёх вариантов с причинами');
});

it('rejects an impossible chat date without advancing', function () {
    Livewire::test('pages::contractor-finder')
        ->set('chatStep', 'date')
        ->call('selectChatChip', '2026-09-22')
        ->assertSet('chatStep', 'date')
        ->assertSee('Выберите дату с 23 сентября по 31 декабря 2026 года.');
});

it('prefills the form from a showcase card and opens the chat', function () {
    Livewire::test('pages::contractor-finder')
        ->call('useShowcase', 1)
        ->assertSet('chatOpen', true)
        ->assertSet('category', 'Флорист')
        ->assertSet('event_format', 'свадьба')
        ->assertSet('city', 'Алматы')
        ->assertSee('Флористика');
});

it('normalizes free-text budget and optional language answers', function () {
    $guide = app(ContractorChatGuide::class);

    expect($guide->normalize('budget', '1 500 000 тенге'))->toMatchArray(['ok' => true, 'value' => 1500000]);
    expect($guide->normalize('language', 'Любой'))->toMatchArray(['ok' => true, 'value' => null]);
    expect($guide->normalize('hours', ''))->toMatchArray(['ok' => true, 'value' => null]);
});
