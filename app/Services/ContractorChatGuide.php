<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Guided chat that collects matcher criteria one field at a time.
 *
 * @phpstan-import-type Criteria from ContractorMatcher
 *
 * @phpstan-type Step 'city'|'event_format'|'category'|'date'|'budget'|'language'|'hours'|'done'
 * @phpstan-type Draft array{city?: string, event_format?: string, category?: string, date?: string, budget?: int, language?: ?string, hours?: ?int}
 * @phpstan-type Chip list<array{value: string, label: string}>
 */
class ContractorChatGuide
{
    public function __construct(private ContractorCatalog $catalog) {}

    /**
     * @return list<Step>
     */
    public function steps(): array
    {
        return ['city', 'event_format', 'category', 'date', 'budget', 'language', 'hours'];
    }

    /**
     * @param  Step  $step
     */
    public function question(string $step): string
    {
        return match ($step) {
            'city' => __('В каком городе пройдёт событие?'),
            'event_format' => __('Какой формат мероприятия?'),
            'category' => __('Кого ищем в каталоге?'),
            'date' => __('На какую дату? Календарь: 23.09–31.12.2026.'),
            'budget' => __('Какой бюджет на подрядчика, в тенге?'),
            'language' => __('Нужен ли конкретный язык работы? Можно пропустить.'),
            'hours' => __('Сколько часов нужно? Можно пропустить.'),
            default => __('Готово. Подбираю варианты…'),
        };
    }

    /**
     * @param  Step  $step
     * @return Chip
     */
    public function chips(string $step): array
    {
        $options = $this->catalog->options();

        return match ($step) {
            'city' => array_map(fn (string $value): array => ['value' => $value, 'label' => __($value)], $options['cities']),
            'event_format' => array_map(fn (string $value): array => ['value' => $value, 'label' => mb_ucfirst(__($value))], $options['event_formats']),
            'category' => array_map(fn (string $value): array => ['value' => $value, 'label' => __($value)], $options['categories']),
            'date' => [
                ['value' => '2026-09-23', 'label' => '23.09.2026'],
                ['value' => '2026-09-24', 'label' => '24.09.2026'],
                ['value' => '2026-10-15', 'label' => '15.10.2026'],
                ['value' => '2026-12-31', 'label' => '31.12.2026'],
            ],
            'budget' => [
                ['value' => '300000', 'label' => '300 000 ₸'],
                ['value' => '500000', 'label' => '500 000 ₸'],
                ['value' => '1000000', 'label' => '1 000 000 ₸'],
                ['value' => '1500000', 'label' => '1 500 000 ₸'],
            ],
            'language' => [
                ['value' => '', 'label' => __('Любой')],
                ...array_map(fn (string $value): array => ['value' => $value, 'label' => mb_ucfirst(__($value))], $options['languages']),
            ],
            'hours' => [
                ['value' => '', 'label' => __('Пропустить')],
                ['value' => '4', 'label' => '4'],
                ['value' => '6', 'label' => '6'],
                ['value' => '8', 'label' => '8'],
            ],
            default => [],
        };
    }

    public function isOptional(string $step): bool
    {
        return in_array($step, ['language', 'hours'], true);
    }

    /**
     * @param  Step  $step
     * @return array{ok: bool, value: mixed, error: ?string}
     */
    public function normalize(string $step, string $raw): array
    {
        $raw = trim($raw);
        $options = $this->catalog->options();

        if ($raw === '' && $this->isOptional($step)) {
            return ['ok' => true, 'value' => null, 'error' => null];
        }

        return match ($step) {
            'city' => $this->pick($raw, $options['cities'], __('Выберите город из списка.')),
            'event_format' => $this->pick($raw, $options['event_formats'], __('Выберите формат из списка.')),
            'category' => $this->pick($raw, $options['categories'], __('Выберите категорию из списка.')),
            'date' => $this->normalizeDate($raw),
            'budget' => $this->normalizeBudget($raw),
            'language' => $raw === '' || mb_strtolower($raw) === mb_strtolower(__('Любой'))
                ? ['ok' => true, 'value' => null, 'error' => null]
                : $this->pick($raw, $options['languages'], __('Выберите язык из списка.')),
            'hours' => $this->normalizeHours($raw),
            default => ['ok' => false, 'value' => null, 'error' => __('Неизвестный шаг.')],
        };
    }

    /**
     * @param  Draft  $draft
     * @param  Step  $step
     * @return Draft
     */
    public function apply(array $draft, string $step, mixed $value): array
    {
        $draft[$step] = $value;

        return $draft;
    }

    /**
     * @param  Step  $step
     * @return Step
     */
    public function next(string $step): string
    {
        $steps = $this->steps();
        $index = array_search($step, $steps, true);

        if ($index === false || $index === count($steps) - 1) {
            return 'done';
        }

        return $steps[$index + 1];
    }

    /**
     * @param  Draft  $draft
     */
    public function isReady(array $draft): bool
    {
        return isset($draft['city'], $draft['event_format'], $draft['category'], $draft['date'], $draft['budget']);
    }

    /**
     * @param  Draft  $draft
     * @return Step
     */
    public function nextMissing(array $draft): string
    {
        foreach (['city', 'event_format', 'category', 'date', 'budget'] as $step) {
            if (! array_key_exists($step, $draft) || $draft[$step] === null || $draft[$step] === '') {
                return $step;
            }
        }

        foreach (['language', 'hours'] as $step) {
            if (! array_key_exists($step, $draft)) {
                return $step;
            }
        }

        return 'done';
    }

    /**
     * @param  Draft  $draft
     * @return Criteria
     */
    public function toCriteria(array $draft): array
    {
        return [
            'city' => $draft['city'],
            'date' => $draft['date'],
            'category' => $draft['category'],
            'event_format' => $draft['event_format'],
            'budget' => (int) $draft['budget'],
            'hours' => $draft['hours'] ?? null,
            'language' => $draft['language'] ?? null,
        ];
    }

    public function displayValue(string $step, mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('Пропущено');
        }

        return match ($step) {
            'budget' => number_format((int) $value, 0, '.', ' ').' ₸',
            'date' => CarbonImmutable::parse((string) $value)->format('d.m.Y'),
            'hours' => (string) $value.' '.__('ч'),
            default => is_string($value) ? (string) __($value) : (string) $value,
        };
    }

    /**
     * @param  list<string>  $allowed
     * @return array{ok: bool, value: mixed, error: ?string}
     */
    private function pick(string $raw, array $allowed, string $error): array
    {
        foreach ($allowed as $option) {
            if (mb_strtolower($raw) === mb_strtolower($option) || mb_strtolower($raw) === mb_strtolower(__($option))) {
                return ['ok' => true, 'value' => $option, 'error' => null];
            }
        }

        return ['ok' => false, 'value' => null, 'error' => $error];
    }

    /**
     * @return array{ok: bool, value: mixed, error: ?string}
     */
    private function normalizeDate(string $raw): array
    {
        $normalized = preg_replace('/[.]/', '-', $raw) ?? $raw;

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $normalized, $matches) === 1) {
            $normalized = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) !== 1) {
            return ['ok' => false, 'value' => null, 'error' => __('Введите дату в формате ГГГГ-ММ-ДД.')];
        }

        if ($normalized < '2026-09-23' || $normalized > '2026-12-31') {
            return ['ok' => false, 'value' => null, 'error' => __('Выберите дату с 23 сентября по 31 декабря 2026 года.')];
        }

        return ['ok' => true, 'value' => $normalized, 'error' => null];
    }

    /**
     * @return array{ok: bool, value: mixed, error: ?string}
     */
    private function normalizeBudget(string $raw): array
    {
        $digits = preg_replace('/[^\d]/', '', $raw) ?? '';

        if ($digits === '' || (int) $digits < 1) {
            return ['ok' => false, 'value' => null, 'error' => __('Укажите бюджет целым числом.')];
        }

        $budget = (int) $digits;

        if ($budget > 1000000000) {
            return ['ok' => false, 'value' => null, 'error' => __('Поле «Бюджет»: максимум 1000000000.')];
        }

        return ['ok' => true, 'value' => $budget, 'error' => null];
    }

    /**
     * @return array{ok: bool, value: mixed, error: ?string}
     */
    private function normalizeHours(string $raw): array
    {
        if ($raw === '' || mb_strtolower($raw) === mb_strtolower(__('Пропустить'))) {
            return ['ok' => true, 'value' => null, 'error' => null];
        }

        if (preg_match('/^\d+$/', $raw) !== 1 || (int) $raw < 1 || (int) $raw > 24) {
            return ['ok' => false, 'value' => null, 'error' => __('Укажите длительность от 1 до 24 часов.')];
        }

        return ['ok' => true, 'value' => (int) $raw, 'error' => null];
    }
}
