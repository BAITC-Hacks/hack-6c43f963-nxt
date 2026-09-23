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
    <header class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-7 sm:px-10">
        <a href="{{ route('home') }}" class="text-3xl font-semibold tracking-tight" aria-label="{{ __('Повод — главная') }}">повод<span class="text-violet-600 dark:text-violet-400">.</span></a>
        <div class="flex flex-wrap items-center justify-end gap-4">
            <a href="#how-it-works" class="text-sm underline decoration-zinc-300 underline-offset-4 hover:decoration-zinc-800 dark:decoration-zinc-600">{{ __('Как это работает') }}</a>
            <nav class="flex gap-1" aria-label="{{ __('Язык интерфейса') }}">
                <flux:button size="sm" wire:click="switchLocale('kk')" :variant="$locale === 'kk' ? 'primary' : 'ghost'" :aria-pressed="$locale === 'kk' ? 'true' : 'false'" lang="kk">Қазақша</flux:button>
                <flux:button size="sm" wire:click="switchLocale('ru')" :variant="$locale === 'ru' ? 'primary' : 'ghost'" :aria-pressed="$locale === 'ru' ? 'true' : 'false'" lang="ru">Русский</flux:button>
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-7xl px-5 pb-12 sm:px-10">
        <section class="grid gap-8 rounded-[2rem] bg-[#eae6f3] p-6 dark:bg-[#262132] lg:grid-cols-[0.9fr_1.1fr] lg:gap-12 lg:p-12" aria-labelledby="finder-title">
            <div class="flex flex-col justify-between gap-12 py-3">
                <div class="flex flex-col gap-7">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-900 dark:text-violet-300">{{ __('Хорошее событие начинается с людей') }}</p>
                    <h1 id="finder-title" class="font-serif text-5xl leading-[1.06] tracking-tight sm:text-6xl lg:text-7xl">{{ __('Ваш повод.') }}<br>{{ __('Ваши люди.') }}</h1>
                    <p class="max-w-sm text-lg leading-relaxed text-zinc-700 dark:text-zinc-300">{{ __('Найдём подрядчиков, которые подходят вашему событию. По дате, бюджету и делу.') }}</p>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full border border-violet-900/20 px-3 py-2 dark:border-violet-200/20">{{ __('66 профилей') }}</span>
                        <span class="rounded-full border border-violet-900/20 px-3 py-2 dark:border-violet-200/20">{{ __('До 3 рекомендаций') }}</span>
                        <span class="rounded-full border border-violet-900/20 px-3 py-2 dark:border-violet-200/20">{{ __('С причинами выбора') }}</span>
                    </div>
                </div>
                <p class="max-w-sm border-t border-violet-900/15 pt-6 text-sm leading-relaxed text-zinc-600 dark:border-violet-200/20 dark:text-zinc-400">{{ __('Для свадьбы, большого корпоратива или праздника в кругу самых близких.') }}</p>
            </div>
            <form wire:submit="search" class="flex flex-col gap-6 rounded-3xl bg-white p-6 shadow-sm sm:p-8 dark:bg-zinc-900" aria-label="{{ __('Параметры мероприятия') }}">
                <div>
                    <h2 class="text-2xl font-medium tracking-tight">{{ __('Что планируете?') }}</h2>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Расскажите о событии — мы сузим круг поиска.') }}</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
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
                    <div class="sm:col-span-2">
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
                <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ __('Календарь каталога: 23 сентября — 31 декабря 2026 года.') }}</p>
                <div class="flex flex-col gap-3">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="search" class="w-full">
                        <span wire:loading.remove wire:target="search">{{ __('Подобрать подрядчиков') }} <span aria-hidden="true">↗</span></span>
                        <span wire:loading wire:target="search">{{ __('Подбираем варианты…') }}</span>
                    </flux:button>
                    <p class="text-center text-xs text-zinc-500 dark:text-zinc-400">{{ __('Покажем до трёх вариантов и причины выбора') }}</p>
                </div>
            </form>
        </section>

        <section class="py-12" aria-live="polite" aria-atomic="true" aria-label="{{ __('Результаты подбора') }}">
            <p wire:loading wire:target="search" class="py-5 text-lg">{{ __('Проверяем доступность и подбираем варианты…') }}</p>
            @if ($result)
                <div wire:loading.remove wire:target="search" class="flex flex-col gap-7">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div class="flex flex-col gap-2">
                            <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Ваша подборка') }}</p>
                            <h2 class="font-serif text-4xl sm:text-5xl">{{ match ($result['status']) { 'matched' => __('Есть совпадение.'), 'no_category' => __('Пока нет в каталоге.'), default => __('Нужны другие условия.') } }}</h2>
                        </div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __($submitted['city']) }} · {{ __($submitted['category']) }} · {{ \Carbon\CarbonImmutable::parse($submitted['date'])->format('d.m.Y') }}</p>
                    </div>
                    <p wire:dirty class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">{{ __('Параметры изменены. Нажмите «Подобрать подрядчиков», чтобы обновить результаты.') }}</p>
                    @if ($result['status'] === 'no_category')
                        <div class="rounded-2xl border border-zinc-200 bg-white p-7 dark:border-zinc-700 dark:bg-zinc-900">
                            <p>{{ __('В городе «:city» нет подрядчиков категории «:category» в имеющемся каталоге.', ['city' => __($submitted['city']), 'category' => __($submitted['category'])]) }}</p>
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Попробуйте выбрать другой город или категорию.') }}</p>
                        </div>
                    @else
                        <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                            {{ __('В городе и категории: :total. Подходят всем условиям: :eligible. Показываем: :shown.', ['total' => $result['total'], 'eligible' => $result['eligible'], 'shown' => count($result['contractors'])]) }}
                            @if ($result['eligible'] > 0 && $result['eligible'] < 3)
                                {{ __('В каталоге нашлось меньше трёх подходящих вариантов.') }}
                            @elseif ($result['eligible'] === 0)
                                {{ __('Кандидаты есть, но ни один не прошёл все условия. Попробуйте изменить дату, бюджет или дополнительные параметры.') }}
                            @endif
                        </p>
                        @if (array_sum($result['reasons']) > 0)
                            <div class="flex flex-col gap-3 rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
                                <h3 class="text-sm font-medium">{{ __('Почему подошли не все') }}</h3>
                                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    @foreach (['busy' => __('Заняты на дату'), 'budget' => __('Цена выше бюджета'), 'format' => __('Не указан нужный формат'), 'language' => __('Не указан нужный язык'), 'hours' => __('Превышен лимит часов')] as $reason => $label)
                                        @if ($result['reasons'][$reason] > 0)
                                            <li wire:key="reason-{{ $reason }}">{{ $label }}: <strong class="font-medium text-zinc-900 dark:text-zinc-200">{{ $result['reasons'][$reason] }}</strong></li>
                                        @endif
                                    @endforeach
                                </ul>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Причины могут пересекаться: один профиль может не подходить по нескольким условиям.') }}</p>
                            </div>
                        @endif
                        <div class="grid gap-5 lg:grid-cols-3">
                            @foreach ($result['contractors'] as $card)
                                <article wire:key="contractor-{{ $card['profile']['id'] }}" class="flex flex-col gap-5 rounded-3xl border border-zinc-200 bg-white p-7 dark:border-zinc-700 dark:bg-zinc-900">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-serif text-4xl text-violet-700 dark:text-violet-300">0{{ $loop->iteration }}</span>
                                        @if ($card['profile']['synthetic'])
                                            <flux:badge size="sm" color="amber">{{ __('Синтетический профиль') }}</flux:badge>
                                        @endif
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ implode(' · ', array_map(fn ($category) => __($category), $card['profile']['categories'])) }} · {{ __($card['profile']['city']) }}</p>
                                        <h3 class="text-2xl font-medium tracking-tight">{{ $card['profile']['anon_name'] }}</h3>
                                        <p class="text-xl">{{ __('от :price ₸', ['price' => number_format($card['profile']['price_from_kzt'], 0, '.', ' ')]) }}</p>
                                    </div>
                                    <div class="flex flex-col gap-2 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                                        <h4 class="text-xs font-semibold uppercase tracking-wider text-violet-800 dark:text-violet-300">{{ __('Почему подходит') }}</h4>
                                        <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $card['explanation'] }}</p>
                                    </div>
                                    <p class="mt-auto text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ __('По календарю каталога дата свободна. Итоговую цену и доступность нужно подтвердить у подрядчика.') }}</p>
                                    @if ($card['profile']['city_imputed'] || $card['profile']['price_imputed'])
                                        <p class="text-xs text-amber-800 dark:text-amber-300">{{ $card['profile']['city_imputed'] ? __('Город восстановлен в датасете. ') : '' }}{{ $card['profile']['price_imputed'] ? __('Цена оценочная из датасета.') : '' }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                        @if ($result['eligible'] > 0)
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $result['ranking'] === 'ai' ? __('Порядок и цитаты подобраны ИИ. Дата, цена и условия проверены по каталогу.') : __('Подбор по правилам: сначала меньшая цена, при равной цене — ID профиля. Объяснения основаны на каталоге.') }}</p>
                        @endif
                    @endif
                </div>
            @endif
        </section>
        <section id="how-it-works" class="grid gap-8 border-t border-zinc-300/70 pt-8 dark:border-zinc-700 md:grid-cols-3" aria-label="{{ __('Как это работает') }}">
            @foreach ([['01', __('Ваши условия'), __('Укажите город, дату и бюджет. Язык и длительность — по желанию.')], ['02', __('Честный отбор'), __('Проверяем занятость и условия по каталогу. Если вариантов нет, объясним почему.')], ['03', __('Понятный выбор'), __('До трёх профилей с фактами и описанием, чтобы проще было сравнить.')]] as [$number, $heading, $text])
                <div wire:key="step-{{ $number }}" class="flex flex-col gap-3">
                    <p class="text-xs text-violet-800 dark:text-violet-300">{{ $number }} /</p>
                    <h2 class="text-lg font-medium">{{ $heading }}</h2>
                    <p class="max-w-sm text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $text }}</p>
                </div>
            @endforeach
        </section>
        <footer class="mt-12 flex flex-wrap justify-between gap-3 border-t border-zinc-200 pt-6 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
            <span>{{ __('повод. / Каталог для вашего события') }}</span>
            <span>{{ __('Демо · Осень — зима 2026 · Без бронирования') }}</span>
        </footer>
    </main>
</div>
