<div class="finder">
    <a class="skip-link" href="#brief">Перейти к форме</a>
    <header class="site-header shell">
        <a class="wordmark" href="{{ route('home') }}" aria-label="Событие — на главную">событие<span>✳</span></a>
        <span class="edition">Подбор подрядчиков / MVP</span>
    </header>

    <main class="shell">
        <section class="hero" aria-labelledby="page-title">
            <div class="eyebrow"><span class="dot"></span> Люди, которые делают события</div>
            <h1 id="page-title">Ваше событие.<br><em>Подходящие люди.</em></h1>
            <div class="hero-bottom">
                <p>Расскажите о планах — получите до трёх вариантов.<br>И понятное объяснение, почему каждый вам подходит.</p>
                <a class="text-link" href="#brief">Начнём с деталей <span aria-hidden="true">↘</span></a>
            </div>
        </section>

        <section class="brief-section" id="brief" aria-labelledby="brief-title">
            <aside class="section-intro">
                <span class="eyebrow">01 / Ваш запрос</span>
                <h2 id="brief-title">Всё начинается<br>с деталей.</h2>
                <p>Город, формат и бюджет помогут сузить поиск. Остальное — по желанию.</p>
                <div class="calendar-note"><span aria-hidden="true">↗</span><div>Календарь MVP<strong>23 сентября — 31 декабря 2026</strong></div></div>
            </aside>

            <form wire:submit="search" class="brief-form" novalidate>
                <p class="form-note">Обязательные поля отмечены <span aria-hidden="true">*</span></p>
                <fieldset wire:loading.attr="disabled" wire:target="search">
                    <legend class="sr-only">Параметры события</legend>
                    <div class="field-grid">
                        @foreach (['city' => ['Город', 'cities'], 'event_format' => ['Формат события', 'event_formats'], 'category' => ['Категория подрядчика', 'categories']] as $field => [$label, $key])
                            <div class="field">
                                <label for="{{ $field }}">{{ $label }} <span aria-hidden="true">*</span></label>
                                <select id="{{ $field }}" wire:model="{{ $field }}" required aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" @error($field) aria-describedby="{{ $field }}-error" @enderror>
                                    <option value="">Выберите из списка</option>
                                    @foreach ($options[$key] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error($field)<p class="field-error" id="{{ $field }}-error">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                        <div class="field">
                            <label for="date">Дата события <span aria-hidden="true">*</span></label>
                            <input id="date" type="date" wire:model="date" min="2026-09-23" max="2026-12-31" required aria-invalid="{{ $errors->has('date') ? 'true' : 'false' }}" aria-describedby="date-hint @error('date') date-error @enderror">
                            <small id="date-hint">23.09.2026–31.12.2026</small>
                            @error('date')<p class="field-error" id="date-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field field-wide">
                            <label for="budget_kzt">Бюджет на подрядчика, ₸ <span aria-hidden="true">*</span></label>
                            <input id="budget_kzt" type="number" wire:model="budget_kzt" min="0.01" step="0.01" inputmode="decimal" placeholder="Например, 150000" required aria-invalid="{{ $errors->has('budget_kzt') ? 'true' : 'false' }}" @error('budget_kzt') aria-describedby="budget-error" @enderror>
                            @error('budget_kzt')<p class="field-error" id="budget-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="duration_hours">Длительность, часов <span class="optional">необязательно</span></label>
                            <input id="duration_hours" type="number" wire:model="duration_hours" min="0.01" step="any" inputmode="decimal" placeholder="Например, 4" aria-invalid="{{ $errors->has('duration_hours') ? 'true' : 'false' }}" @error('duration_hours') aria-describedby="duration-error" @enderror>
                            @error('duration_hours')<p class="field-error" id="duration-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="language">Язык <span class="optional">необязательно</span></label>
                            <select id="language" wire:model="language" aria-invalid="{{ $errors->has('language') ? 'true' : 'false' }}" @error('language') aria-describedby="language-error" @enderror>
                                <option value="">Любой язык</option>
                                @foreach ($options['languages'] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('language')<p class="field-error" id="language-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <button class="submit-button" type="submit" wire:loading.attr="disabled" wire:target="search">
                        <span wire:loading.remove wire:target="search">Подобрать подрядчиков</span>
                        <span wire:loading wire:target="search">Подбираем варианты…</span>
                        <span aria-hidden="true">↗</span>
                    </button>
                </fieldset>
                <p class="form-note">До 3 вариантов. С объяснением каждого выбора.</p>
            </form>
        </section>

        <section class="results-section" aria-labelledby="results-title" aria-live="polite" aria-atomic="true">
            <span class="eyebrow">02 / Ваши варианты</span>
            <h2 id="results-title">Подбор с объяснением.</h2>
            <p wire:loading wire:target="search" role="status">Проверяем условия и готовим объяснения…</p>
            <div wire:loading.remove wire:target="search">
                @if ($result === null)
                    <div class="empty-state"><span class="empty-symbol" aria-hidden="true">✳</span><div><h3>Здесь появятся ваши варианты</h3><p>Заполните форму выше. Для каждого результата расскажем, почему он подходит вашему событию.</p></div></div>
                @elseif (($result['status'] ?? null) === 'matched')
                    <p class="result-message">{{ $result['message'] ?? '' }}</p>
                    <div class="cards">
                        @forelse (array_slice($result['contractors'] ?? [], 0, 3) as $contractor)
                            <article class="contractor-card">
                                <div class="card-top"><span class="eyebrow">Вариант {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><span aria-hidden="true">↗</span></div>
                                @if ($contractor['synthetic'] ?? false)<p class="synthetic-badge">Синтетический профиль</p>@endif
                                <h3>{{ $contractor['name'] }}</h3>
                                <p class="card-meta">{{ $contractor['category'] }} · {{ $contractor['city'] }}</p>
                                <p class="price">от {{ number_format((float) $contractor['price_from_kzt'], 0, ',', ' ') }} <span>₸</span></p>
                                <div class="explanation"><h4>Почему подходит</h4><p>{{ $contractor['explanation'] }}</p></div>
                            </article>
                        @empty
                            <div class="empty-state"><p>Вариантов пока нет. Попробуйте изменить условия поиска.</p></div>
                        @endforelse
                    </div>
                @elseif (($result['status'] ?? null) === 'no_category_in_city')
                    <div class="empty-state"><span class="empty-symbol" aria-hidden="true">↗</span><div><h3>В этом городе пока нет такой категории</h3><p>{{ $result['message'] ?? 'В каталоге нет подрядчиков этой категории в выбранном городе.' }}</p><p>Попробуйте другой город или категорию.</p></div></div>
                @elseif (($result['status'] ?? null) === 'no_eligible')
                    <div class="empty-state"><span class="empty-symbol" aria-hidden="true">↗</span><div><h3>Кандидаты есть, но условия не совпали</h3><p class="full-text">{{ $result['message'] ?? 'Никто из кандидатов не прошёл все условия запроса.' }}</p><p>Скорректируйте условия в форме и повторите подбор.</p></div></div>
                @else
                    <div class="empty-state"><p>Вариантов пока нет. Измените условия и попробуйте ещё раз.</p></div>
                @endif
            </div>
        </section>
    </main>
    <footer class="site-footer shell"><span class="wordmark">событие<span>✳</span></span><p>Выбирайте людей. Понимайте почему.</p><span class="edition">HackAlem / 2026</span></footer>
</div>
