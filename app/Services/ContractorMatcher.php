<?php

namespace App\Services;

class ContractorMatcher
{
    public function __construct(private readonly ContractorCatalog $catalog)
    {
    }

    /** @return array{status: string, message: string, total_in_city_category: int, eligible_count: int, excluded: array{busy: int, over_budget: int, wrong_format: int, wrong_language: int, insufficient_hours: int}, results: array<int, array<string, mixed>>} */
    public function recommend(array $filters): array
    {
        $city = (string) ($filters['city'] ?? '');
        $category = (string) ($filters['category'] ?? '');
        $date = (string) ($filters['date'] ?? '');
        $format = (string) ($filters['event_format'] ?? '');
        $language = $filters['language'] ?? null;
        $duration = $filters['duration_hours'] ?? null;
        $budget = (int) ($filters['budget_kzt'] ?? 0);
        $excluded = ['busy' => 0, 'over_budget' => 0, 'wrong_format' => 0, 'wrong_language' => 0, 'insufficient_hours' => 0];

        $candidates = array_values(array_filter($this->catalog->all(), static fn (array $profile): bool =>
            ($profile['city'] ?? null) === $city && in_array($category, $profile['categories'] ?? [], true)
        ));

        if ($candidates === []) {
            return $this->response('no_category_in_city', "В городе «{$city}» нет подрядчиков категории «{$category}».", 0, 0, $excluded, []);
        }

        $eligible = [];
        foreach ($candidates as $profile) {
            $reasons = [];
            if (in_array($date, $profile['busy_dates'] ?? [], true)) $reasons[] = 'busy';
            if ((int) ($profile['price_from_kzt'] ?? 0) > $budget) $reasons[] = 'over_budget';
            if (! in_array($format, $profile['event_formats'] ?? [], true)) $reasons[] = 'wrong_format';
            if ($language !== null && $language !== '' && ! in_array($language, $profile['languages'] ?? [], true)) $reasons[] = 'wrong_language';
            if ($duration !== null && $duration !== '' && ($profile['max_hours'] ?? null) !== null && (float) $duration > (float) $profile['max_hours']) $reasons[] = 'insufficient_hours';

            if ($reasons !== []) {
                foreach ($reasons as $reason) $excluded[$reason]++;
                continue;
            }

            $profile['synthetic'] = (bool) ($profile['synthetic'] ?? false);
            $profile['score'] = $this->score($profile, $budget, $category, $format, $language);
            $profile['explanation'] = $this->explanation($profile, $budget, $duration);
            $eligible[] = $profile;
        }

        if ($eligible === []) {
            $causes = [];
            foreach ($excluded as $reason => $count) {
                if ($count > 0) $causes[] = $this->reasonLabel($reason).": {$count}";
            }
            return $this->response('no_eligible', 'В городе найдено '.count($candidates)." подрядчиков этой категории, но все исключены. Причины (с возможным пересечением): ".implode(', ', $causes).'.', count($candidates), 0, $excluded, []);
        }

        usort($eligible, static fn (array $a, array $b): int => ($b['score'] <=> $a['score']) ?: strcmp((string) $a['id'], (string) $b['id']));
        $shown = array_slice($eligible, 0, 3);
        $message = count($eligible) < 3
            ? 'Подходят только '.count($eligible).' из '.count($candidates)." подрядчиков категории в этом городе; причины исключения: ".$this->exclusionSummary($excluded).'.'
            : 'Подходят '.count($eligible)." подрядчиков; показаны первые ".count($shown).' по прозрачному рейтингу.';

        return $this->response('ok', $message, count($candidates), count($eligible), $excluded, $shown);
    }

    private function score(array $profile, int $budget, string $category, string $format, ?string $language): int
    {
        $price = (int) ($profile['price_from_kzt'] ?? 0);
        $score = $budget > 0 ? (int) round(100 * ($budget - $price) / $budget) : 0;
        $description = mb_strtolower((string) ($profile['description'] ?? ''));
        foreach ([$category, $format, $language] as $fact) {
            if (is_string($fact) && $fact !== '' && mb_stripos($description, mb_strtolower($fact)) !== false) $score += 5;
        }
        return $score;
    }

    private function explanation(array $profile, int $budget, mixed $duration): string
    {
        $price = (int) ($profile['price_from_kzt'] ?? 0);
        $text = 'Цена от '.number_format($price, 0, ',', ' ').' ₸'.($price === $budget ? ' — ровно в пределах бюджета' : ' при бюджете '.number_format($budget, 0, ',', ' ').' ₸').'.';
        $facts = ['Форматы: '.implode(', ', $profile['event_formats'] ?? [])];
        if (! empty($profile['languages'])) $facts[] = 'языки: '.implode(', ', $profile['languages']);
        if (($profile['max_hours'] ?? null) !== null) $facts[] = 'максимум '.$profile['max_hours'].' ч.';
        $description = trim((string) ($profile['description'] ?? ''));
        if ($description !== '') {
            $sentence = preg_split('/(?<=[.!?])\s+/u', $description)[0] ?? $description;
            $sentence = mb_substr($sentence, 0, 180);
            $facts[] = $sentence;
        }
        return rtrim($text, '.').' '.implode('; ', array_slice($facts, 0, 3)).'.'.($description !== '' ? ' В описании: '.$sentence : '');
    }

    private function exclusionSummary(array $excluded): string
    {
        $causes = [];
        foreach ($excluded as $reason => $count) {
            if ($count > 0) $causes[] = $this->reasonLabel($reason).": {$count}";
        }
        return $causes === [] ? 'подходящих профилей меньше трёх' : implode(', ', $causes);
    }

    private function reasonLabel(string $reason): string
    {
        return ['busy' => 'занят в выбранную дату', 'over_budget' => 'цена выше бюджета', 'wrong_format' => 'неподходящий формат', 'wrong_language' => 'нет нужного языка', 'insufficient_hours' => 'недостаточно часов'][$reason];
    }

    private function response(string $status, string $message, int $total, int $eligible, array $excluded, array $results): array
    {
        return ['status' => $status, 'message' => $message, 'total_in_city_category' => $total, 'eligible_count' => $eligible, 'excluded' => $excluded, 'results' => $results];
    }
}
