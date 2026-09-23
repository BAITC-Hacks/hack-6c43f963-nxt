<?php

use App\Services\AiContractorChatParser;
use App\Services\ContractorCatalog;
use App\Services\ContractorChatGuide;
use App\Services\ContractorMatcher;
use App\Support\ShowcaseCatalog;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts.finder')] class extends Component {
    #[Locked]
    public string $locale = 'kk';

    public string $city = 'Алматы';
    public string $date = '2026-09-23';
    public string $category = 'Ведущий';
    public string $event_format = 'свадьба';
    public string $budget = '1500000';
    public string $hours = '';
    public string $language = '';

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $result = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $submitted = [];

    public bool $chatOpen = false;
    public string $chatInput = '';
    public string $chatStep = 'city';
    public bool $chatBusy = false;

    /** @var list<array{role: string, text: string}> */
    public array $chatMessages = [];

    /** @var array<string, mixed> */
    public array $chatDraft = [];

    public function mount(ContractorChatGuide $guide): void
    {
        $this->locale = app()->getLocale();
        $this->chatMessages = [
            ['role' => 'assistant', 'text' => __('Здравствуйте. Я помогу подобрать до трёх подрядчиков из каталога. Отвечайте кнопками или текстом.')],
            ['role' => 'assistant', 'text' => $guide->question('city')],
        ];
    }

    public function switchLocale(string $locale, ContractorMatcher $matcher, ContractorChatGuide $guide): void
    {
        abort_unless(in_array($locale, ['kk', 'ru'], true), 400);
        session()->put('locale', $locale);
        app()->setLocale($locale);
        $this->locale = $locale;
        $this->resetValidation();

        if ($this->result !== null) {
            foreach ($this->result['contractors'] as &$card) {
                $card['explanation'] = $matcher->explain($this->submitted, $card['profile'], $card['excerpt']);
            }
            unset($card);
        }

        if ($this->chatStep !== 'done' && $this->chatMessages !== []) {
            $this->chatMessages[count($this->chatMessages) - 1] = [
                'role' => 'assistant',
                'text' => $guide->question($this->chatStep),
            ];
        }

        $this->dispatch('locale-changed',
            locale: $locale,
            title: __('Повод — подрядчики для вашего события').' - '.config('app.name'),
            description: __('Подберите до трёх подрядчиков для мероприятия по городу, дате, бюджету и формату. С понятными причинами выбора.'),
        );
    }

    /** @return array<string, list<string>> */
    #[Computed]
    public function options(): array
    {
        return app(ContractorCatalog::class)->options();
    }

    /** @return list<array{image: string, title: string, caption: string, category: string, event_format: string, city: string}> */
    #[Computed]
    public function showcase(): array
    {
        return ShowcaseCatalog::items();
    }

    /** @return list<array{value: string, label: string}> */
    #[Computed]
    public function chatChips(): array
    {
        if ($this->chatStep === 'done') {
            return [];
        }

        return app(ContractorChatGuide::class)->chips($this->chatStep);
    }

    public function openChat(): void
    {
        $this->chatOpen = true;
    }

    public function closeChat(): void
    {
        $this->chatOpen = false;
    }

    public function restartChat(ContractorChatGuide $guide): void
    {
        $this->chatStep = 'city';
        $this->chatDraft = [];
        $this->chatInput = '';
        $this->chatBusy = false;
        $this->chatMessages = [
            ['role' => 'assistant', 'text' => __('Начнём заново. В каком городе пройдёт событие?')],
        ];
        $this->chatOpen = true;
    }

    public function useShowcase(int $index): void
    {
        $item = ShowcaseCatalog::items()[$index] ?? null;
        abort_unless($item !== null, 404);

        $this->city = $item['city'];
        $this->category = $item['category'];
        $this->event_format = $item['event_format'];
        $this->openChat();
        $this->chatMessages[] = [
            'role' => 'assistant',
            'text' => __('Открыл подбор по витрине «:title». Можно продолжить в чате или заполнить форму ниже.', ['title' => __($item['title'])]),
        ];
    }

    public function selectChatChip(string $value, ContractorChatGuide $guide, AiContractorChatParser $parser, ContractorMatcher $matcher): void
    {
        $this->acceptChatAnswer($value, $guide, $parser, $matcher, fromChip: true);
    }

    public function sendChatMessage(ContractorChatGuide $guide, AiContractorChatParser $parser, ContractorMatcher $matcher): void
    {
        $this->acceptChatAnswer($this->chatInput, $guide, $parser, $matcher, fromChip: false);
    }

    public function skipChatStep(ContractorChatGuide $guide, AiContractorChatParser $parser, ContractorMatcher $matcher): void
    {
        abort_unless($guide->isOptional($this->chatStep), 400);
        $this->acceptChatAnswer('', $guide, $parser, $matcher, fromChip: true);
    }

    public function search(ContractorMatcher $matcher): void
    {
        $options = $this->options;
        $validated = $this->validate([
            'city' => ['required', Rule::in($options['cities'])],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2026-09-23', 'before_or_equal:2026-12-31'],
            'category' => ['required', Rule::in($options['categories'])],
            'event_format' => ['required', Rule::in($options['event_formats'])],
            'budget' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:24'],
            'language' => ['nullable', Rule::in($options['languages'])],
        ], [
            'required' => __('Заполните поле «:attribute».'),
            'in' => __('Выберите значение из списка «:attribute».'),
            'integer' => __('Поле «:attribute» должно быть целым числом.'),
            'min' => __('Поле «:attribute»: минимум :min.'),
            'max' => __('Поле «:attribute»: максимум :max.'),
            'date.date_format' => __('Введите дату в формате ГГГГ-ММ-ДД.'),
            'date.after_or_equal' => __('Выберите дату с 23 сентября по 31 декабря 2026 года.'),
            'date.before_or_equal' => __('Выберите дату с 23 сентября по 31 декабря 2026 года.'),
        ], [
            'city' => __('Город'), 'date' => __('Дата мероприятия'), 'category' => __('Категория подрядчика'),
            'event_format' => __('Тип мероприятия'), 'budget' => __('Бюджет'), 'hours' => __('Длительность'), 'language' => __('Язык работы'),
        ]);

        $this->submitted = [
            'city' => $validated['city'], 'date' => $validated['date'],
            'category' => $validated['category'], 'event_format' => $validated['event_format'],
            'budget' => (int) $validated['budget'],
            'hours' => filled($validated['hours']) ? (int) $validated['hours'] : null,
            'language' => filled($validated['language']) ? $validated['language'] : null,
        ];
        $this->result = $matcher->match($this->submitted);
    }

    private function acceptChatAnswer(string $raw, ContractorChatGuide $guide, AiContractorChatParser $parser, ContractorMatcher $matcher, bool $fromChip): void
    {
        if ($this->chatStep === 'done' || $this->chatBusy) {
            return;
        }

        $this->chatBusy = true;
        $display = trim($raw) === '' ? __('Пропущено') : trim($raw);
        $this->chatMessages[] = ['role' => 'user', 'text' => $display];
        $this->chatInput = '';

        $parsed = $guide->normalize($this->chatStep, $raw);

        if (! $parsed['ok'] && ! $fromChip) {
            $aiValue = $parser->parse($this->chatStep, $raw, $guide->chips($this->chatStep));
            if ($aiValue !== null) {
                $parsed = $guide->normalize($this->chatStep, $aiValue);
            }
        }

        if (! $parsed['ok']) {
            $this->chatMessages[] = ['role' => 'assistant', 'text' => $parsed['error'] ?? __('Не понял ответ. Выберите вариант ниже.')];
            $this->chatBusy = false;

            return;
        }

        $this->chatDraft = $guide->apply($this->chatDraft, $this->chatStep, $parsed['value']);
        $this->chatMessages[] = [
            'role' => 'assistant',
            'text' => __('Принял: :value', ['value' => $guide->displayValue($this->chatStep, $parsed['value'])]),
        ];

        $next = $guide->next($this->chatStep);
        $this->chatStep = $next;

        if ($next === 'done') {
            $criteria = $guide->toCriteria($this->chatDraft);
            $this->city = $criteria['city'];
            $this->date = $criteria['date'];
            $this->category = $criteria['category'];
            $this->event_format = $criteria['event_format'];
            $this->budget = (string) $criteria['budget'];
            $this->hours = $criteria['hours'] === null ? '' : (string) $criteria['hours'];
            $this->language = $criteria['language'] ?? '';
            $this->submitted = $criteria;
            $this->result = $matcher->match($criteria);
            $count = count($this->result['contractors']);
            $this->chatMessages[] = [
                'role' => 'assistant',
                'text' => $count > 0
                    ? __('Готово. Ниже — до трёх вариантов с причинами. Карточки также в разделе результатов.')
                    : __('По этим условиям карточек нет. Смотрите пояснение в результатах — можно изменить ответ и начать заново.'),
            ];
            $this->chatBusy = false;
            $this->dispatch('chat-finished');

            return;
        }

        $this->chatMessages[] = ['role' => 'assistant', 'text' => $guide->question($next)];
        $this->chatBusy = false;
    }
}; ?>

<div class="finder" x-data x-on:locale-changed.window="document.documentElement.lang = $event.detail.locale; document.title = $event.detail.title; document.querySelector('meta[name=description]').content = $event.detail.description">
    <a class="skip-link" href="#finder-form">{{ __('Перейти к подбору') }}</a>
    <header class="site-header shell">
        <a class="wordmark" href="{{ route('home') }}" aria-label="{{ __('Повод — главная') }}">повод<span>✳</span></a>
        <div class="header-tools">
            <nav class="language-switch" aria-label="{{ __('Язык интерфейса') }}">
                <button type="button" wire:click="switchLocale('kk')" aria-pressed="{{ $locale === 'kk' ? 'true' : 'false' }}" lang="kk">Қазақша</button>
                <button type="button" wire:click="switchLocale('ru')" aria-pressed="{{ $locale === 'ru' ? 'true' : 'false' }}" lang="ru">Русский</button>
            </nav>
            <button type="button" class="chat-launch" wire:click="openChat" aria-controls="assistant-panel" aria-expanded="{{ $chatOpen ? 'true' : 'false' }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v7A2.5 2.5 0 0 1 17.5 16H9l-4 4v-4.5A2.5 2.5 0 0 1 4 13.5v-7Z" stroke="currentColor" stroke-width="1.6"/></svg>
                {{ __('ИИ-чат') }}
            </button>
            <a class="edition" href="#how-it-works">{{ __('Как это работает') }}</a>
        </div>
    </header>

    <main class="shell">
        <section class="hero" aria-labelledby="finder-title">
            <div class="eyebrow"><span class="dot"></span> {{ __('Люди, которые создают события') }}</div>
            <h1 id="finder-title">{{ __('Ваш повод.') }}<br><em>{{ __('Ваши люди.') }}</em></h1>
            <div class="hero-bottom">
                <p>{{ __('Найдём подрядчиков, которые подходят вашему событию. По дате, бюджету и делу.') }}<br> {{ __('Для свадьбы, большого корпоратива или праздника в кругу самых близких.') }}</p>
                <div class="hero-actions">
                    <button type="button" class="text-link as-button" wire:click="openChat">{{ __('Спросить ИИ') }} <span aria-hidden="true">↗</span></button>
                    <a class="text-link" href="#finder-form">{{ __('Начать подбор') }} <span aria-hidden="true">↘</span></a>
                </div>
            </div>
        </section>

        <section class="showcase" id="showcase" aria-labelledby="showcase-title" x-data="{ filter: 'all' }">
            <div class="showcase-heading">
                <div>
                    <span class="eyebrow">00 / {{ __('Витрина') }}</span>
                    <h2 id="showcase-title">{{ __('Атмосфера событий.') }}</h2>
                    <p>{{ __('Живые кадры форматов из каталога. Нажмите карточку — откроем ИИ-чат с этими параметрами.') }}</p>
                </div>
                <div class="showcase-filters" role="group" aria-label="{{ __('Фильтр витрины') }}">
                    <button type="button" class="filter-chip" :aria-pressed="filter === 'all'" @click="filter = 'all'">{{ __('Все') }}</button>
                    <button type="button" class="filter-chip" :aria-pressed="filter === 'свадьба'" @click="filter = 'свадьба'">{{ __('свадьба') }}</button>
                    <button type="button" class="filter-chip" :aria-pressed="filter === 'корпоратив'" @click="filter = 'корпоратив'">{{ __('корпоратив') }}</button>
                    <button type="button" class="filter-chip" :aria-pressed="filter === 'той'" @click="filter = 'той'">{{ __('той') }}</button>
                </div>
            </div>
            <div class="showcase-grid">
                @foreach ($this->showcase as $index => $item)
                    <button
                        type="button"
                        wire:key="showcase-{{ $index }}"
                        class="showcase-card"
                        wire:click="useShowcase({{ $index }})"
                        x-show="filter === 'all' || filter === '{{ $item['event_format'] }}'"
                        x-transition.opacity.duration.200ms
                    >
                        <img src="{{ asset($item['image']) }}" alt="{{ __($item['title']) }}" width="640" height="480" loading="{{ $index < 2 ? 'eager' : 'lazy' }}" decoding="async">
                        <span class="showcase-copy">
                            <span class="showcase-meta">{{ __($item['category']) }} · {{ __($item['city']) }}</span>
                            <span class="showcase-name">{{ __($item['title']) }}</span>
                            <span class="showcase-caption">{{ __($item['caption']) }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </section>

        <section class="brief-section" id="finder-form" aria-labelledby="brief-title">
            <aside class="section-intro">
                <span class="eyebrow">01 / {{ __('Ваши условия') }}</span>
                <h2 id="brief-title">{{ __('Что планируете?') }}</h2>
                <p>{{ __('Расскажите о событии — мы сузим круг поиска.') }}</p>
                <div class="calendar-note"><span aria-hidden="true">↗</span><div>{{ __('С причинами выбора') }}<strong>{{ __('Календарь каталога: 23 сентября — 31 декабря 2026 года.') }}</strong></div></div>
            </aside>

            <form wire:submit="search" class="brief-form" aria-label="{{ __('Параметры мероприятия') }}" novalidate>
                <p class="form-note">{{ __('Покажем до трёх вариантов и причины выбора') }}</p>
                <fieldset wire:loading.attr="disabled" wire:target="search">
                    <legend class="sr-only">{{ __('Параметры мероприятия') }}</legend>
                    <div class="field-grid">
                        <div class="field">
                            <label for="city">{{ __('Город') }} <span aria-hidden="true">*</span></label>
                            <select id="city" wire:model="city" required aria-invalid="{{ $errors->has('city') ? 'true' : 'false' }}" @error('city') aria-describedby="city-error" @enderror>
                                <option value="">{{ __('Выберите город') }}</option>
                                @foreach ($this->options['cities'] as $option)
                                    <option wire:key="city-{{ $loop->index }}" value="{{ $option }}">{{ __($option) }}</option>
                                @endforeach
                            </select>
                            @error('city')<p class="field-error" id="city-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="event_format">{{ __('Тип мероприятия') }} <span aria-hidden="true">*</span></label>
                            <select id="event_format" wire:model="event_format" required aria-invalid="{{ $errors->has('event_format') ? 'true' : 'false' }}" @error('event_format') aria-describedby="event-format-error" @enderror>
                                <option value="">{{ __('Выберите формат') }}</option>
                                @foreach ($this->options['event_formats'] as $option)
                                    <option wire:key="format-{{ $loop->index }}" value="{{ $option }}">{{ mb_ucfirst(__($option)) }}</option>
                                @endforeach
                            </select>
                            @error('event_format')<p class="field-error" id="event-format-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="category">{{ __('Категория подрядчика') }} <span aria-hidden="true">*</span></label>
                            <select id="category" wire:model="category" required aria-invalid="{{ $errors->has('category') ? 'true' : 'false' }}" @error('category') aria-describedby="category-error" @enderror>
                                <option value="">{{ __('Выберите категорию') }}</option>
                                @foreach ($this->options['categories'] as $option)
                                    <option wire:key="category-{{ $loop->index }}" value="{{ $option }}">{{ __($option) }}</option>
                                @endforeach
                            </select>
                            @error('category')<p class="field-error" id="category-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="date">{{ __('Дата мероприятия') }} <span aria-hidden="true">*</span></label>
                            <input id="date" type="date" wire:model="date" min="2026-09-23" max="2026-12-31" required aria-invalid="{{ $errors->has('date') ? 'true' : 'false' }}" @error('date') aria-describedby="date-error" @enderror>
                            @error('date')<p class="field-error" id="date-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field field-wide">
                            <label for="budget">{{ __('Бюджет, ₸') }} <span aria-hidden="true">*</span></label>
                            <input id="budget" type="number" wire:model="budget" min="1" max="1000000000" step="1" placeholder="500000" required aria-invalid="{{ $errors->has('budget') ? 'true' : 'false' }}" @error('budget') aria-describedby="budget-error" @enderror>
                            @error('budget')<p class="field-error" id="budget-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="hours">{{ __('Длительность, часы') }} <span class="optional">{{ __('Необязательно') }}</span></label>
                            <input id="hours" type="number" wire:model="hours" min="1" max="24" step="1" placeholder="{{ __('Необязательно') }}" aria-invalid="{{ $errors->has('hours') ? 'true' : 'false' }}" @error('hours') aria-describedby="hours-error" @enderror>
                            @error('hours')<p class="field-error" id="hours-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="language">{{ __('Язык работы') }} <span class="optional">{{ __('Необязательно') }}</span></label>
                            <select id="language" wire:model="language" aria-invalid="{{ $errors->has('language') ? 'true' : 'false' }}" @error('language') aria-describedby="language-error" @enderror>
                                <option value="">{{ __('Любой') }}</option>
                                @foreach ($this->options['languages'] as $option)
                                    <option wire:key="language-{{ $loop->index }}" value="{{ $option }}">{{ mb_ucfirst(__($option)) }}</option>
                                @endforeach
                            </select>
                            @error('language')<p class="field-error" id="language-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <button class="submit-button" type="submit" wire:loading.attr="disabled" wire:target="search">
                        <span wire:loading.remove wire:target="search">{{ __('Подобрать подрядчиков') }}</span>
                        <span wire:loading wire:target="search">{{ __('Подбираем варианты…') }}</span>
                        <span aria-hidden="true">↗</span>
                    </button>
                </fieldset>
            </form>
        </section>

        <section class="results-section" id="results-anchor" aria-live="polite" aria-atomic="true" aria-label="{{ __('Результаты подбора') }}">
            <span class="eyebrow">02 / {{ __('Ваша подборка') }}</span>
            <p wire:loading wire:target="search" role="status">{{ __('Проверяем доступность и подбираем варианты…') }}</p>
            <div wire:loading.remove wire:target="search">
                @if ($result)
                    <h2>{{ match ($result['status']) { 'matched' => __('Есть совпадение.'), 'no_category' => __('Пока нет в каталоге.'), default => __('Нужны другие условия.') } }}</h2>
                    <p class="result-context">{{ __($submitted['city']) }} · {{ __($submitted['category']) }} · {{ \Carbon\CarbonImmutable::parse($submitted['date'])->format('d.m.Y') }}</p>
                    <p wire:dirty class="dirty-notice">{{ __('Параметры изменены. Нажмите «Подобрать подрядчиков», чтобы обновить результаты.') }}</p>
                    @if ($result['status'] === 'no_category')
                        <div class="empty-state">
                            <span class="empty-symbol" aria-hidden="true">↗</span>
                            <div>
                                <h3>{{ __('Пока нет в каталоге.') }}</h3>
                                <p>{{ __('В городе «:city» нет подрядчиков категории «:category» в имеющемся каталоге.', ['city' => __($submitted['city']), 'category' => __($submitted['category'])]) }}</p>
                                <p>{{ __('Попробуйте выбрать другой город или категорию.') }}</p>
                            </div>
                        </div>
                    @else
                        <p class="result-message">{{ __('В городе и категории: :total. Подходят всем условиям: :eligible. Показываем: :shown.', ['total' => $result['total'], 'eligible' => $result['eligible'], 'shown' => count($result['contractors'])]) }}</p>
                        @if ($result['eligible'] > 0 && $result['eligible'] < 3)
                            <p class="result-message">{{ __('В каталоге нашлось меньше трёх подходящих вариантов.') }}</p>
                        @elseif ($result['eligible'] === 0)
                            <div class="empty-state">
                                <span class="empty-symbol" aria-hidden="true">↗</span>
                                <div>
                                    <h3>{{ __('Нужны другие условия.') }}</h3>
                                    <p>{{ __('Кандидаты есть, но ни один не прошёл все условия. Попробуйте изменить дату, бюджет или дополнительные параметры.') }}</p>
                                </div>
                            </div>
                        @endif
                        <div class="cards">
                            @foreach ($result['contractors'] as $card)
                                <article wire:key="contractor-{{ $card['profile']['id'] }}" class="contractor-card">
                                    <div class="card-top"><span class="eyebrow">0{{ $loop->iteration }}</span><span aria-hidden="true">↗</span></div>
                                    @if ($card['profile']['synthetic'])
                                        <p class="synthetic-badge">{{ __('Синтетический профиль') }}</p>
                                    @endif
                                    <h3>{{ $card['profile']['anon_name'] }}</h3>
                                    <p class="card-meta">{{ implode(' · ', array_map(fn ($category) => __($category), $card['profile']['categories'])) }} · {{ __($card['profile']['city']) }}</p>
                                    <p class="price">{{ __('от :price ₸', ['price' => number_format($card['profile']['price_from_kzt'], 0, '.', ' ')]) }}</p>
                                    <div class="explanation">
                                        <h4>{{ __('Почему подходит') }}</h4>
                                        <p>{{ $card['explanation'] }}</p>
                                    </div>
                                    <p class="card-footnote">{{ __('По календарю каталога дата свободна. Итоговую цену и доступность нужно подтвердить у подрядчика.') }}</p>
                                    @if ($card['profile']['city_imputed'] || $card['profile']['price_imputed'])
                                        <p class="imputed">{{ $card['profile']['city_imputed'] ? __('Город восстановлен в датасете. ') : '' }}{{ $card['profile']['price_imputed'] ? __('Цена оценочная из датасета.') : '' }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                        @if (array_sum($result['reasons']) > 0)
                            <div class="reasons">
                                <h3>{{ __('Почему подошли не все') }}</h3>
                                <ul>
                                    @foreach (['busy' => __('Заняты на дату'), 'budget' => __('Цена выше бюджета'), 'format' => __('Не указан нужный формат'), 'language' => __('Не указан нужный язык'), 'hours' => __('Превышен лимит часов')] as $reason => $label)
                                        @if ($result['reasons'][$reason] > 0)
                                            <li wire:key="reason-{{ $reason }}">{{ $label }}: <strong>{{ $result['reasons'][$reason] }}</strong></li>
                                        @endif
                                    @endforeach
                                </ul>
                                <p>{{ __('Причины могут пересекаться: один профиль может не подходить по нескольким условиям.') }}</p>
                            </div>
                        @endif
                        @if ($result['eligible'] > 0)
                            <p class="ranking-note">{{ $result['ranking'] === 'ai' ? __('Порядок и цитаты подобраны ИИ. Дата, цена и условия проверены по каталогу.') : __('Подбор по правилам: сначала меньшая цена, при равной цене — ID профиля. Объяснения основаны на каталоге.') }}</p>
                        @endif
                    @endif
                @else
                    <h2>{{ __('Подбор с объяснением.') }}</h2>
                    <div class="empty-state">
                        <span class="empty-symbol" aria-hidden="true">✳</span>
                        <div>
                            <h3>{{ __('Здесь появятся ваши варианты') }}</h3>
                            <p>{{ __('Заполните форму выше. Для каждого результата расскажем, почему он подходит вашему событию.') }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <section id="how-it-works" class="process" aria-labelledby="process-title">
            <span class="eyebrow">{{ __('Как это работает') }}</span>
            <h2 id="process-title">{{ __('Меньше поиска.') }}<br><em>{{ __('Больше повода.') }}</em></h2>
            <div class="process-steps">
                @foreach ([['01', __('Ваши условия'), __('Укажите город, дату и бюджет. Язык и длительность — по желанию.')], ['02', __('Честный отбор'), __('Проверяем занятость и условия по каталогу. Если вариантов нет, объясним почему.')], ['03', __('Понятный выбор'), __('До трёх профилей с фактами и описанием, чтобы проще было сравнить.')]] as [$number, $heading, $text])
                    <div wire:key="step-{{ $number }}" class="process-step">
                        <span>{{ $number }}</span>
                        <h3>{{ $heading }}</h3>
                        <p>{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </main>
    <footer class="site-footer shell">
        <a class="wordmark" href="{{ route('home') }}" aria-label="{{ __('Повод — главная') }}">повод<span>✳</span></a>
        <p>{{ __('повод. / Каталог для вашего события') }}</p>
        <span class="edition">{{ __('Демо · Осень — зима 2026 · Без бронирования') }}</span>
    </footer>

    <div
        id="assistant-panel"
        class="chat-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="chat-title"
        @if (! $chatOpen) hidden @endif
        x-data
        x-on:chat-finished.window="$el.querySelector('.chat-log')?.scrollTo({ top: 99999, behavior: 'smooth' })"
    >
        <div class="chat-scrim" wire:click="closeChat" aria-hidden="true"></div>
        <div class="chat-sheet">
            <header class="chat-header">
                <div>
                    <p class="eyebrow" id="chat-title">{{ __('ИИ-помощник') }}</p>
                    <p class="chat-subtitle">{{ __('Спросит условия и предложит до трёх карточек') }}</p>
                </div>
                <div class="chat-header-actions">
                    <button type="button" class="chat-icon-btn" wire:click="restartChat" aria-label="{{ __('Начать заново') }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.7-5.7M20 12a8 8 0 0 1-13.7 5.7" stroke="currentColor" stroke-width="1.6"/><path d="M18 3v5h-5M6 21v-5h5" stroke="currentColor" stroke-width="1.6"/></svg>
                    </button>
                    <button type="button" class="chat-icon-btn" wire:click="closeChat" aria-label="{{ __('Закрыть чат') }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.6"/></svg>
                    </button>
                </div>
            </header>

            <div class="chat-log" role="log" aria-live="polite" aria-relevant="additions">
                @foreach ($chatMessages as $index => $message)
                    <div wire:key="chat-msg-{{ $index }}" class="chat-bubble chat-{{ $message['role'] }}">{{ $message['text'] }}</div>
                @endforeach
                @if ($chatBusy)
                    <div class="chat-bubble chat-assistant chat-typing" role="status">{{ __('Думаю…') }}</div>
                @endif
            </div>

            @if ($chatStep !== 'done')
                <div class="chat-chips" role="group" aria-label="{{ __('Быстрые ответы') }}">
                    @foreach ($this->chatChips as $chip)
                        <button type="button" wire:key="chip-{{ $chatStep }}-{{ $chip['value'] !== '' ? $chip['value'] : 'empty' }}" class="chip" wire:click="selectChatChip({{ \Illuminate\Support\Js::from($chip['value']) }})" wire:loading.attr="disabled" wire:target="selectChatChip, sendChatMessage, skipChatStep">{{ $chip['label'] }}</button>
                    @endforeach
                    @if (in_array($chatStep, ['language', 'hours'], true))
                        <button type="button" class="chip chip-skip" wire:click="skipChatStep" wire:loading.attr="disabled">{{ __('Пропустить') }}</button>
                    @endif
                </div>
                <form wire:submit="sendChatMessage" class="chat-compose">
                    <label class="sr-only" for="chat-input">{{ __('Ваш ответ') }}</label>
                    <input id="chat-input" type="text" wire:model="chatInput" autocomplete="off" placeholder="{{ __('Или напишите ответ…') }}" @disabled($chatBusy)>
                    <button type="submit" class="chat-send" wire:loading.attr="disabled" aria-label="{{ __('Отправить') }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 11.5 19 4l-4.5 16L11 13z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    </button>
                </form>
            @else
                <div class="chat-done-actions">
                    <a class="submit-button chat-done-link" href="#results-anchor" wire:click="closeChat">{{ __('Смотреть результаты') }} <span aria-hidden="true">↘</span></a>
                    <button type="button" class="chip" wire:click="restartChat">{{ __('Начать заново') }}</button>
                </div>
            @endif
        </div>
    </div>
</div>
