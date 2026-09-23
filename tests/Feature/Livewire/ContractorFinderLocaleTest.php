<?php

use Laravel\Ai\StructuredAnonymousAgent;
use Livewire\Livewire;

beforeEach(function () {
    config(['contractors.ai_enabled' => false]);
});

it('uses Kazakh by default including metadata and translated option labels', function () {
    expect(config('app.locale'))->toBe('kk');

    $this->get(route('home'))->assertOk()
        ->assertSee('<html lang="kk">', false)
        ->assertSee('Сіздің мерекеңіз.')
        ->assertSee('Іс-шара күні')
        ->assertSee('Жүргізуші')
        ->assertSee('Қазақ тілі')
        ->assertSee('Мамандарды іріктеу')
        ->assertSee('value="Ведущий"', false)
        ->assertDontSee('Что планируете?');
});

it('persists the chosen language across reloads and later Livewire requests', function () {
    Livewire::test('pages::contractor-finder')
        ->call('switchLocale', 'ru')
        ->assertSet('locale', 'ru')
        ->assertSee('Что планируете?')
        ->assertDispatched('locale-changed', locale: 'ru')
        ->set('budget', '0')->call('search')
        ->assertSee('Поле «Бюджет»: минимум 1.');

    $this->get(route('home'))->assertSee('<html lang="ru">', false)->assertSee('Ваш повод.');

    Livewire::test('pages::contractor-finder')->call('switchLocale', 'kk')->assertSee('Не жоспарлап отырсыз?');
    $this->get(route('home'))->assertSee('<html lang="kk">', false)->assertSee('Сіздің мерекеңіз.');
});

it('translates existing results without changing inputs profiles or requesting AI again', function () {
    config(['contractors.ai_enabled' => true, 'contractors.cache_store' => 'array', 'ai.providers.openai.key' => 'test-key']);
    StructuredAnonymousAgent::fake([['selections' => [
        ['id' => 'HK-90001', 'explanation' => 'White Sakura Studio — авторская флористика для свадеб и юбилеев.'],
        ['id' => 'HK-39372', 'explanation' => 'Мы специализируемся на авторском цветочном оформлении и флористике для мероприятий в Алматы.'],
    ]]]);

    $component = Livewire::test('pages::contractor-finder')
        ->set('category', 'Флорист')->set('budget', '500000')->set('language', 'русский')->set('hours', '4')
        ->call('search')
        ->assertHasNoErrors()
        ->assertSee('Синтетикалық профиль')
        ->assertSee('жұмыс тілі — орыс тілі')
        ->assertSee('сағат шегі көрсетілмеген')
        ->assertSee('үштен аз лайық нұсқа')
        ->assertSet('result.ranking', 'ai');
    $before = $component->get('result');

    $component->call('switchLocale', 'ru')
        ->assertSee('Синтетический профиль')
        ->assertSee('язык работы — русский')
        ->assertSee('лимит часов не указан')
        ->assertSet('category', 'Флорист')
        ->assertSet('budget', '500000')
        ->assertSet('language', 'русский')
        ->assertSet('hours', '4');

    expect(array_column($component->get('result')['contractors'], 'profile'))->toBe(array_column($before['contractors'], 'profile'));
    expect(array_column($component->get('result')['contractors'], 'excerpt'))->toBe(array_column($before['contractors'], 'excerpt'));
    StructuredAnonymousAgent::assertPromptedTimes(1);
});

it('shows Kazakh validation and both empty states', function () {
    Livewire::test('pages::contractor-finder')
        ->set('date', '2026-09-22')->call('search')
        ->assertHasErrors(['date'])
        ->assertSee('2026 жылғы 23 қыркүйек пен 31 желтоқсан аралығындағы күнді таңдаңыз.')
        ->set('date', '2026-09-23')->set('city', 'Зарубежье')->set('category', 'Флорист')
        ->call('search')->assertSet('result.status', 'no_category')
        ->assertSee('Каталогта «Шетел» қаласында «Флорист» санатындағы мамандар жоқ.')
        ->set('city', 'Алматы')->set('budget', '1')
        ->call('search')->assertSet('result.status', 'no_matches')
        ->assertSee('Бағасы бюджеттен жоғары')
        ->assertSee('Мамандар бар, бірақ ешқайсысы барлық шартқа сай келмеді.');
});

it('rejects unsupported language choices', function () {
    Livewire::test('pages::contractor-finder')->call('switchLocale', '../en')->assertStatus(400);
    expect(session('locale'))->toBeNull();
});

it('falls back to Kazakh for an unsupported saved language', function () {
    $this->withSession(['locale' => 'en'])->get(route('home'))
        ->assertOk()->assertSee('<html lang="kk">', false)->assertSee('Сіздің мерекеңіз.');
});
