<?php

use App\Services\ContractorCatalog;
use App\Services\ContractorMatcher;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts.finder')] class extends Component {
    #[Locked]
    public string $locale = 'kk';

    public function mount(): void
    {
        $this->locale = app()->getLocale();
    }

    public function switchLocale(string $locale, ContractorMatcher $matcher): void
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

        $this->dispatch('locale-changed',
            locale: $locale,
            title: __('Повод — подрядчики для вашего события').' - '.config('app.name'),
            description: __('Подберите до трёх подрядчиков для мероприятия по городу, дате, бюджету и формату. С понятными причинами выбора.'),
        );
    }

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

    /** @return array<string, list<string>> */
    #[Computed]
    public function options(): array
    {
        return app(ContractorCatalog::class)->options();
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
}; ?>

<div x-data x-on:locale-changed.window="document.documentElement.lang = $event.detail.locale; document.title = $event.detail.title; document.querySelector('meta[name=description]').content = $event.detail.description">
    <a href="#finder-form" class="finder-skip">{{ __('Перейти к подбору') }}</a>
    <header class="finder-nav finder-container">
        <a href="{{ route('home') }}" class="finder-brand" aria-label="{{ __('Повод — главная') }}">
            <span class="finder-brand-mark" aria-hidden="true">п.</span>
            <span>повод<span class="finder-brand-dot">.</span></span>
        </a>
        <a href="#how-it-works" class="finder-nav-link">{{ __('Как это работает') }} <span aria-hidden="true">↗</span></a>
        <nav class="finder-language" aria-label="{{ __('Язык интерфейса') }}">
            <flux:button size="sm" wire:click="switchLocale('kk')" :variant="$locale === 'kk' ? 'primary' : 'ghost'" :aria-pressed="$locale === 'kk' ? 'true' : 'false'" lang="kk">Қазақша</flux:button>
            <flux:button size="sm" wire:click="switchLocale('ru')" :variant="$locale === 'ru' ? 'primary' : 'ghost'" :aria-pressed="$locale === 'ru' ? 'true' : 'false'" lang="ru">Русский</flux:button>
        </nav>
    </header>

    <main class="finder-container">
        <section class="finder-hero" aria-labelledby="finder-title">
            <div class="finder-hero-heading">
                <p class="finder-eyebrow"><span class="finder-small-line" aria-hidden="true"></span>{{ __('Люди, которые создают события') }}</p>
                <h1 id="finder-title">{{ __('Ваш повод.') }}<br><em>{{ __('Ваши люди.') }}</em></h1>
            </div>
            <div class="finder-hero-aside">
                <p class="finder-hero-intro">{{ __('Найдём подрядчиков, которые подходят вашему событию. По дате, бюджету и делу.') }}</p>
                <a href="#finder-form" class="finder-text-link">{{ __('Начать подбор') }} <span aria-hidden="true">↓</span></a>
                <div class="finder-hero-facts">
                    <div><strong>66</strong><span>{{ __('профилей в каталоге') }}</span></div>
                    <div><strong>03</strong><span>{{ __('варианта для вас') }}</span></div>
                </div>
            </div>
        </section>

        <div class="finder-season">
            <p>{{ __('Для свадьбы, большого корпоратива или праздника в кругу самых близких.') }}</p>
            <span>2026 <span aria-hidden="true">/</span> {{ __('Осень — зима') }}</span>
        </div>

        <section id="finder-form" class="finder-brief" aria-labelledby="brief-title">
            <aside class="finder-brief-intro">
                <div>
                    <p class="finder-eyebrow">01 / {{ __('Ваши условия') }}</p>
                    <h2 id="brief-title">{{ __('Что планируете?') }}</h2>
                    <p class="finder-brief-description">{{ __('Расскажите о событии — мы сузим круг поиска.') }}</p>
                </div>
                <div class="finder-invitation" aria-hidden="true">
                    <span>повод.</span>
                    <div class="finder-invitation-rule"></div>
                    <span class="finder-invitation-number">01 — 03</span>
                    <span>{{ __('С причинами выбора') }}</span>
                </div>
                <p class="finder-brief-note">{{ __('Календарь каталога: 23 сентября — 31 декабря 2026 года.') }}</p>
            </aside>

            <form wire:submit="search" class="finder-form" aria-label="{{ __('Параметры мероприятия') }}">
                <fieldset>
                    <legend><span>A</span>{{ __('О событии') }}</legend>
                    <div class="finder-field-grid">
                        <flux:select wire:model="city" label="{{ __('Город') }}" required>
                            <option value="">{{ __('Выберите город') }}</option>
                            @foreach ($this->options['cities'] as $option)
                                <option wire:key="city-{{ $loop->index }}" value="{{ $option }}">{{ __($option) }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="date" type="date" label="{{ __('Дата мероприятия') }}" min="2026-09-23" max="2026-12-31" required />
                        <flux:select wire:model="event_format" label="{{ __('Тип мероприятия') }}" required>
                            <option value="">{{ __('Выберите формат') }}</option>
                            @foreach ($this->options['event_formats'] as $option)
                                <option wire:key="format-{{ $loop->index }}" value="{{ $option }}">{{ mb_ucfirst(__($option)) }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="category" label="{{ __('Категория подрядчика') }}" required>
                            <option value="">{{ __('Выберите категорию') }}</option>
                            @foreach ($this->options['categories'] as $option)
                                <option wire:key="category-{{ $loop->index }}" value="{{ $option }}">{{ __($option) }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                </fieldset>

                <fieldset>
                    <legend><span>B</span>{{ __('Бюджет и детали') }}</legend>
                    <div class="finder-field-grid">
                        <div class="finder-budget-field">
                            <flux:input wire:model="budget" type="number" label="{{ __('Бюджет, ₸') }}" min="1" max="1000000000" step="1" placeholder="500000" required />
                        </div>
                        <flux:input wire:model="hours" type="number" label="{{ __('Длительность, часы') }}" min="1" max="24" step="1" placeholder="{{ __('Необязательно') }}" />
                        <flux:select wire:model="language" label="{{ __('Язык работы') }}">
                            <option value="">{{ __('Любой') }}</option>
                            @foreach ($this->options['languages'] as $option)
                                <option wire:key="language-{{ $loop->index }}" value="{{ $option }}">{{ mb_ucfirst(__($option)) }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                </fieldset>

                <div class="finder-submit-row">
                    <p>{{ __('Покажем до трёх вариантов и причины выбора') }}</p>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="search" class="finder-submit">
                        <span wire:loading.remove wire:target="search">{{ __('Подобрать подрядчиков') }} <span class="finder-button-arrow" aria-hidden="true">↗</span></span>
                        <span wire:loading wire:target="search">{{ __('Подбираем варианты…') }}</span>
                    </flux:button>
                </div>
            </form>
        </section>

        <section class="finder-results" aria-live="polite" aria-label="{{ __('Результаты подбора') }}">
            <div wire:loading wire:target="search" class="finder-loading" role="status">
                <span class="finder-loading-dot" aria-hidden="true"></span>{{ __('Проверяем доступность и подбираем варианты…') }}
            </div>
            @if ($result)
                <div wire:loading.remove wire:target="search">
                    <div class="finder-section-heading">
                        <div>
                            <p class="finder-eyebrow">02 / {{ __('Ваша подборка') }}</p>
                            <h2>{{ match ($result['status']) { 'matched' => __('Есть совпадение.'), 'no_category' => __('Пока нет в каталоге.'), default => __('Нужны другие условия.') } }}</h2>
                        </div>
                        <p class="finder-result-context">{{ __($submitted['city']) }}<br>{{ __($submitted['category']) }} · {{ \Carbon\CarbonImmutable::parse($submitted['date'])->format('d.m.Y') }}</p>
                    </div>
                    <p wire:dirty class="finder-notice">{{ __('Параметры изменены. Нажмите «Подобрать подрядчиков», чтобы обновить результаты.') }}</p>

                    @if ($result['status'] === 'no_category')
                        <div class="finder-empty">
                            <span class="finder-empty-symbol" aria-hidden="true">∅</span>
                            <div>
                                <p>{{ __('В городе «:city» нет подрядчиков категории «:category» в имеющемся каталоге.', ['city' => __($submitted['city']), 'category' => __($submitted['category'])]) }}</p>
                                <p class="finder-muted">{{ __('Попробуйте выбрать другой город или категорию.') }}</p>
                                <a href="#finder-form" class="finder-text-link">{{ __('Изменить условия') }} <span aria-hidden="true">↑</span></a>
                            </div>
                        </div>
                    @else
                        <div class="finder-results-summary">
                            <p>{{ __('В городе и категории: :total. Подходят всем условиям: :eligible. Показываем: :shown.', ['total' => $result['total'], 'eligible' => $result['eligible'], 'shown' => count($result['contractors'])]) }}</p>
                            @if ($result['eligible'] > 0 && $result['eligible'] < 3)
                                <p>{{ __('В каталоге нашлось меньше трёх подходящих вариантов.') }}</p>
                            @elseif ($result['eligible'] === 0)
                                <p>{{ __('Кандидаты есть, но ни один не прошёл все условия. Попробуйте изменить дату, бюджет или дополнительные параметры.') }}</p>
                                <a href="#finder-form" class="finder-text-link">{{ __('Изменить условия') }} <span aria-hidden="true">↑</span></a>
                            @endif
                        </div>

                        <div class="finder-recommendations">
                            @foreach ($result['contractors'] as $card)
                                <article wire:key="contractor-{{ $card['profile']['id'] }}" class="finder-contractor">
                                    <div class="finder-contractor-identity">
                                        <span class="finder-contractor-number">0{{ $loop->iteration }}</span>
                                        <div>
                                            <p class="finder-contractor-category">{{ implode(' · ', array_map(fn ($category) => __($category), $card['profile']['categories'])) }}</p>
                                            <h3>{{ $card['profile']['anon_name'] }}</h3>
                                            <p class="finder-contractor-city">{{ __($card['profile']['city']) }} <span aria-hidden="true">/</span> {{ $card['profile']['id'] }}</p>
                                        </div>
                                        @if ($card['profile']['synthetic'])
                                            <span class="finder-synthetic">{{ __('Синтетический профиль') }}</span>
                                        @endif
                                    </div>
                                    <div class="finder-contractor-reason">
                                        <h4><span aria-hidden="true">↳</span> {{ __('Почему подходит') }}</h4>
                                        <p>{{ $card['explanation'] }}</p>
                                    </div>
                                    <div class="finder-contractor-price">
                                        <p class="finder-price">{{ __('от :price ₸', ['price' => number_format($card['profile']['price_from_kzt'], 0, '.', ' ')]) }}</p>
                                        <p>{{ __('По календарю каталога дата свободна. Итоговую цену и доступность нужно подтвердить у подрядчика.') }}</p>
                                        @if ($card['profile']['city_imputed'] || $card['profile']['price_imputed'])
                                            <p class="finder-imputed">{{ $card['profile']['city_imputed'] ? __('Город восстановлен в датасете. ') : '' }}{{ $card['profile']['price_imputed'] ? __('Цена оценочная из датасета.') : '' }}</p>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        @if (array_sum($result['reasons']) > 0)
                            <details class="finder-exclusions" @if ($result['eligible'] === 0) open @endif>
                                <summary>{{ __('Почему подошли не все') }} <span aria-hidden="true">+</span></summary>
                                <div class="finder-exclusion-body">
                                    <ul>
                                        @foreach (['busy' => __('Заняты на дату'), 'budget' => __('Цена выше бюджета'), 'format' => __('Не указан нужный формат'), 'language' => __('Не указан нужный язык'), 'hours' => __('Превышен лимит часов')] as $reason => $label)
                                            @if ($result['reasons'][$reason] > 0)
                                                <li wire:key="reason-{{ $reason }}"><span>{{ $label }}</span><strong>{{ $result['reasons'][$reason] }}</strong></li>
                                            @endif
                                        @endforeach
                                    </ul>
                                    <p>{{ __('Причины могут пересекаться: один профиль может не подходить по нескольким условиям.') }}</p>
                                </div>
                            </details>
                        @endif
                        @if ($result['eligible'] > 0)
                            <p class="finder-ranking-note">{{ $result['ranking'] === 'ai' ? __('Порядок и цитаты подобраны ИИ. Дата, цена и условия проверены по каталогу.') : __('Подбор по правилам: сначала меньшая цена, при равной цене — ID профиля. Объяснения основаны на каталоге.') }}</p>
                        @endif
                    @endif
                </div>
            @endif
        </section>

        <section id="how-it-works" class="finder-process" aria-labelledby="process-title">
            <div class="finder-process-intro">
                <p class="finder-eyebrow">{{ __('Как это работает') }}</p>
                <h2 id="process-title">{{ __('Меньше поиска.') }}<br><em>{{ __('Больше повода.') }}</em></h2>
            </div>
            <div class="finder-process-steps">
                @foreach ([['01', __('Ваши условия'), __('Укажите город, дату и бюджет. Язык и длительность — по желанию.')], ['02', __('Честный отбор'), __('Проверяем занятость и условия по каталогу. Если вариантов нет, объясним почему.')], ['03', __('Понятный выбор'), __('До трёх профилей с фактами и описанием, чтобы проще было сравнить.')]] as [$number, $heading, $text])
                    <div wire:key="step-{{ $number }}" class="finder-process-step">
                        <span>{{ $number }}</span>
                        <div><h3>{{ $heading }}</h3><p>{{ $text }}</p></div>
                    </div>
                @endforeach
            </div>
        </section>
    </main>
    <footer class="finder-footer">
        <div class="finder-container">
            <a href="{{ route('home') }}" class="finder-footer-brand" aria-label="{{ __('Повод — главная') }}">повод.</a>
            <div><p>{{ __('повод. / Каталог для вашего события') }}</p><p>{{ __('Демо · Осень — зима 2026 · Без бронирования') }}</p></div>
            <a href="#finder-form" class="finder-footer-link">{{ __('Начать подбор') }} <span aria-hidden="true">↗</span></a>
        </div>
    </footer>
</div>
